<?php

/*
 *   TODO: organize files better (lol, irony)
 */

use PHPCodeMapper\FunctionCall;
use PHPCodeMapper\FunctionCallType;

require_once(__DIR__ . '/util/array-util.php'); // arrayAddValueForKey
require_once(__DIR__ . '/util/string.php'); // stringContains
require_once(__DIR__ . '/util/shell.php'); // getFilenameFromGrepLine, execWithNoPrinting, printWarning, printMessageWithColor
require_once(__DIR__ . '/util/scope.php'); // isFunctionInScope, isClassInScope

class CodeMapper {

  const CODE_MAPPER_LOG = __DIR__ . '/../log/code-mapper.log';

  protected $RELATIVE_PATH_TO_ROOT;
  protected $ROOT_DIR;

  public function __construct($relativePathToRoot) {
    $this->RELATIVE_PATH_TO_ROOT = $relativePathToRoot;
    $this->ROOT_DIR = realpath(__DIR__ . "/$this->RELATIVE_PATH_TO_ROOT");
  }

  public function getRequiresFromFile($filename) {
    // get the list of requires for the function (technically to be the most robust
    // we should use tokens, but this works so far so)
    $fileContents = file_get_contents($filename);
    preg_match_all('/require_once\((.*)\);(.*)\n/', $fileContents, $requireMatches);
    return $requireMatches[1];
  }

  public function extractRelativePathFromRequireStatement($requireStatement) {
    // matches
    //    __DIR__ . '/path/to/file'
    //    "path/to/file"
    $pattern = "(__DIR__\s*.)?\s*(['\"])(.*)\\2";
    preg_match("/$pattern/", $requireStatement, $match);
    // 1 = __DIR__, 2 = ['\"], and 3 = path
    return $match[3] ?? null;
  }

  public function getFullRequirePath($requirerFile, $requiredFile) {
    $requirerDirectory = dirname($requirerFile);
    $pathFromCallerDir = $this->extractRelativePathFromRequireStatement($requiredFile);
    return $requirerDirectory . $pathFromCallerDir;
  }

  /**
   * @param string
   * @param $functionCalls array of FunctionCalls
   * @return array array of functionName => sourceFile TODO: can be somehow tied to FunctionCall class?
   *
   * Current limitations:
   *
   * 1. This misses
   * - callbacks/functions accessed by string (e.g. array_map('functionName', $array))
   * - global variables that need to exist (tho I think we should move away from global variables)
   */
  public function getFunctionCallSourceFiles(string $callerFile, array &$functionCalls, bool $verbose = false) {
    // for finding function defined in the same file
    $callerFileFunctionDefs = $this->getFunctionsDefinedInFile($callerFile);

    $functionsToFiles = [];
    $filesToFunctions = [];
    foreach ($functionCalls as $functionCall) {

      $functionName = $functionCall->getQualifiedFunctionName();
      $functionType = $functionCall->getFunctionCallType();
      $callerFile = $functionCall->getFilename();

      if (isset($functionsToFiles[$functionName])) {
        if ($verbose) {
          echo "Found duplicate function call $functionName. Already handled. skipping" . PHP_EOL;
        }
        continue;
      }

      // instance methods dont need to be required, as they will just exist on
      // the object or not. inclusion of the object will be covered by
      // object instantiation
      if ($functionType == FunctionCallType::INSTANCE_METHOD) {
        if ($verbose) {
          echo "Found instance method: $functionName. skipping" . PHP_EOL;
        }
        continue;
      }

      // don't need to require standard/autoloaded functions
      if ($this->isAlreadyAccessible($functionCall)) {
        if ($verbose) {
          echo "Found standard function: $functionName. skipping" . PHP_EOL;
        }
        continue;
      } else {
        if ($verbose) {
          echo "Function $functionName not globally defined." . PHP_EOL;
        }
      }

      // search for the function name if it's not in the same file
      if (in_array($functionName, $callerFileFunctionDefs)) {
        $sourceFilename = $callerFile;
      } else {
        $sourceFilename = $this->findFunctionDefinitionIsolatedScope($functionCall);
      }

      if (empty($sourceFilename)) {
        printWarning("Failed to find source file for $functionName: $sourceFilename" . PHP_EOL);
        continue;
      }

      if ($verbose) {
        printWithShellColor("Found source file for $functionName: $sourceFilename" . PHP_EOL, 'green');
      }

      // these are for requires
      $functionsToFiles[$functionName] = $sourceFilename;
      arrayAddValueForKey($filesToFunctions, $sourceFilename, $functionName);
    }

    return $filesToFunctions;
  }

  public function findFunctionDefinitionIsolatedScope(FunctionCall $functionCall) {
    $qualifiedFunctionName = $functionCall->getQualifiedFunctionName();
    $callerFile = $functionCall->getFilename();
    $findFunctionScript = __DIR__ . '/find-function-definition.php';
    $output = execWithNoPrinting("php $findFunctionScript $qualifiedFunctionName $callerFile");
    return !empty($output) ? trim($output[0]) : null;
  }

  public function simulateScopeOfFile(string $filename) {
    $requires = $this->getRequiresFromFile($filename);

    foreach ($requires as $fileToRequire) {
      $fullRequirePath = $this->getFullRequirePath($filename, $fileToRequire);
      $realpath = realpath($fullRequirePath);
      if ($realpath === false) {
        // TODO: how to warn? can't print here as of now because messes up return of find-function-definition.php
        continue; // failed to find the file... skip
      }
      $this->safeRequireFile($realpath);
    }
  }

  /**
   * Use Reflection to get file a function is defined in
   *
   * @param $functionName string functionName or className::functionName // TODO better interface?
   * @return string|null filename it's found in or null if the reflection failed
   */
  public function getFunctionDefinitionFile(string $functionName) {
    try {
      // Functions within classes (methods) can't be reflected the same way :eye_roll:
      if (strpos($functionName, '::') !== false) {
        $className = explode('::', $functionName)[0];
        $methodName = explode('::', $functionName)[1];
        $reflector = new ReflectionMethod($className, $methodName);
      } else {
        $reflector = new ReflectionFunction($functionName);
      }

      return $reflector->getFileName();
    } catch (ReflectionException $e) {
      return null; // not in scope
    }
  }

  public function safeRequireFile($filepath) {
    ob_start();
    require_once($filepath);
    ob_end_clean();
  }

  /**
   * @param $filesToFunctions array [filename => [reasonsForRequire]]
   * @param $codeFile string filepath of the file which these require statements
   * are intended for. This is used to format to use __DIR__ correctly
   * @return array  list of require_once statements with comments containing
   * the functions/classes they were required for
   */
  public function generateRequireOnceStatements(array $filesToFunctions, string $codeFile) {
    global $ROOT_DIR;
    // format: require_once(sourceFile); // function1, function2, ...
    $requireLines = [];
    foreach ($filesToFunctions as $sourceFile => $functionNames) {
      $relativePathToSourceFile = $this->getRelativePath($codeFile, $sourceFile, $ROOT_DIR);
      $functionList = implode(', ', $functionNames);

      // same file => no need to require it
      if (realpath($sourceFile) === realpath($codeFile)) {
        printWarning("Function(s) $functionList defined in same file as usage." . PHP_EOL);
        continue;
      }

      $requireLine = "require_once(__DIR__ . '/$relativePathToSourceFile'); // $functionList";
      $requireLines [] = $requireLine;
    }
    return $requireLines;
  }

  /* * * * * * * FILE MANAGEMENT * * * * * */

  /**
   * Searches the codebase for definitions of FunctionCall
   *
   * @param FunctionCall $functionCall
   * @return array output lines from grep call
   */
  public function grepForFunctionDefinitionFiles(FunctionCall $functionCall) {
    global $ROOT_DIR;
    $functionName = $functionCall->getFunctionName();
    $grepPattern = $this->getFunctionCallDeclarationPattern($functionCall);
    // TODO: better exclude directories
    $command = "ag '$grepPattern' $ROOT_DIR";
    $output = execWithNoPrinting($command);
    if (empty($output)) {
      echo "Wow we couldn't even find $functionName by grep'ing. you're in trouble buddy." . PHP_EOL;
    }
    return array_map('getFilenameFromGrepLine', $output);
  }

  public function getRelativePath($file1, $file2, $rootDir) {
    $codeDirectory = dirname($file1);
    $pathFromRoot = str_replace($rootDir, '', $codeDirectory);
    $directoriesFromRoot = explode('/', $pathFromRoot);
    // avoid empty strings
    $directoriesFromRoot = array_filter($directoriesFromRoot, function ($x) {
      return !empty($x);
    });
    // traverse back to root directory w ../../../
    $directoriesFromRoot = array_map(function ($x) {
      return '..';
    }, $directoriesFromRoot);
    $fromFile1ToRoot = implode('/', $directoriesFromRoot);

    // note: we take off a slash here to make the return statement clearer
    $fromRootToFile2 = str_replace("$rootDir/", '', $file2);

    return "$fromFile1ToRoot/$fromRootToFile2";
  }

  /* * * * * * * * UTIL / SCOPE * * * * * * * */

  public function isAlreadyAccessible(FunctionCall $functionCall) {
    $functionCallType = $functionCall->getFunctionCallType();
    $functionName = $functionCall->getFunctionName();
    return $functionCallType === FunctionCallType::UNQUALIFIED
      ? isFunctionInScope($functionName)
      : isClassInScope($functionCall->getClassName());
  }

}
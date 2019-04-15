<?php

/*
 *   TODO: organize files better (lol, irony)
 */

require_once(__DIR__ . '/FunctionCall.php'); // FunctionCall
require_once(__DIR__ . '/util/array-util.php'); // arrayAddValueForKey
require_once(__DIR__ . '/util/string.php'); // stringContains
require_once(__DIR__ . '/util/shell.php'); // getFilenameFromGrepLine, execWithNoPrinting, printWarning, printMessageWithColor
require_once(__DIR__ . '/util/scope.php'); // isFunctionInScope, isClassInScope

const CODE_MAPPER_LOG = realpath(__DIR__ . '/log/code-mapper.log');

$RELATIVE_PATH_TO_ROOT = '../..';
$ROOT_DIR = realpath(__DIR__ . "/$RELATIVE_PATH_TO_ROOT"); // todo should this be env variable?

function getRequiresFromFile($filename) {
  // get the list of requires for the function (technically to be the most robust
  // we should use tokens, but this works so far so)
  $fileContents = file_get_contents($filename);
  preg_match_all('/require_once\((.*)\);(.*)\n/', $fileContents, $requireMatches);
  return $requireMatches[1];
}

function extractRelativePathFromRequireStatement($requireStatement) {
  // matches
  //    __DIR__ . '/path/to/file'
  //    "path/to/file"
  $pattern = "(__DIR__\s*.)?\s*(['\"])(.*)\\2";
  preg_match("/$pattern/", $requireStatement, $match);
  // 1 = __DIR__, 2 = ['\"], and 3 = path
  return $match[3] ?? null;
}

function getFullRequirePath($requirerFile, $requiredFile) {
  $requirerDirectory = dirname($requirerFile);
  $pathFromCallerDir = extractRelativePathFromRequireStatement($requiredFile);
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
function getFunctionCallSourceFiles(string $callerFile, array &$functionCalls, bool $verbose = false) {
  // for finding function defined in the same file
  $callerFileFunctionDefs = getFunctionsDefinedInFile($callerFile);

  $functionsToFiles = [];
  $filesToFunctions = [];
  foreach ($functionCalls as $functionCall) {

    $functionName = $functionCall->getQualifiedFunctionName();
    $functionType = $functionCall->getFunctionCallType();
    $callerFile = $functionCall->getFilename();

    if (isset($functionsToFiles[$functionName])) {
      if ($verbose) { echo "Found duplicate function call $functionName. Already handled. skipping" . PHP_EOL; }
      continue;
    }

    // instance methods dont need to be required, as they will just exist on
    // the object or not. inclusion of the object will be covered by
    // object instantiation
    if ($functionType == FunctionCallType::INSTANCE_METHOD) {
      if ($verbose) { echo "Found instance method: $functionName. skipping" . PHP_EOL; }
      continue;
    }

    // don't need to require standard/autoloaded functions
    if (isAlreadyAccessible($functionCall)) {
      if ($verbose) { echo "Found standard function: $functionName. skipping" . PHP_EOL; }
      continue;
    } else {
      if ($verbose) { echo "Function $functionName not globally defined." . PHP_EOL; }
    }

    // search for the function name if it's not in the same file
    if (in_array($functionName, $callerFileFunctionDefs)) {
      $sourceFilename = $callerFile;
    } else {
      $sourceFilename = findFunctionDefinitionIsolatedScope($functionCall);
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

function findFunctionDefinitionIsolatedScope(FunctionCall $functionCall) {
  global $ROOT_DIR;
  $qualifiedFunctionName = $functionCall->getQualifiedFunctionName();
  $callerFile = $functionCall->getFilename();
  $output = execWithNoPrinting("php $ROOT_DIR/scripts/find-function-definition.php $qualifiedFunctionName $callerFile");
  return !empty($output) ? trim($output[0]) : null;
}

function simulateScopeOfFile(string $filename) {
  global $ROOT_DIR;
  $requires = getRequiresFromFile($filename);
  $pathToCallerFile = getRelativePath(__FILE__, $filename, $ROOT_DIR);

  foreach ($requires as $fileToRequire) {
    $fullRequirePath = getFullRequirePath($filename, $fileToRequire);
    $realpath = realpath($fullRequirePath);
    if ($realpath === false) {
      // TODO: how to warn? can't print here as of now because messes up return of find-function-definition.php
      continue; // failed to find the file... skip
    }
    safeRequireFile($realpath);
  }
}

/**
 * Use Reflection to get file a function is defined in
 *
 * @param $functionName string functionName or className::functionName // TODO better interface?
 * @return string|null filename it's found in or null if the reflection failed
 */
function getFunctionDefinitionFile(string $functionName) {
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

function safeRequireFile($filepath) {
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
function generateRequireOnceStatements(array $filesToFunctions, string $codeFile) {
  global $ROOT_DIR;
  // format: require_once(sourceFile); // function1, function2, ...
  $requireLines = [];
  foreach ($filesToFunctions as $sourceFile => $functionNames) {
    $relativePathToSourceFile = getRelativePath($codeFile, $sourceFile, $ROOT_DIR);
    $functionList = implode(', ', $functionNames);

    // same file => no need to require it
    if (realpath($sourceFile) === realpath($codeFile)) {
      printWarning("Function(s) $functionList defined in same file as usage." . PHP_EOL);
      continue;
    }

    $requireLine = "require_once(__DIR__ . '/$relativePathToSourceFile'); // $functionList";
    $requireLines []= $requireLine;
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
function grepForFunctionDefinitionFiles(FunctionCall $functionCall) {
  global $ROOT_DIR;
  $functionName = $functionCall->getFunctionName();
  $grepPattern = getFunctionCallDeclarationPattern($functionCall);
  // TODO: better exclude directories
  $command = "ag '$grepPattern' $ROOT_DIR";
  $output = execWithNoPrinting($command);
  if (empty($output)) {
    echo "Wow we couldn't even find $functionName by grep'ing. you're in trouble buddy." . PHP_EOL;
  }
  return array_map('getFilenameFromGrepLine', $output);
}

function isSourceFile($sourceFilename) {
  // TODO: better -- this includes libs etc
  global $ROOT_DIR;
  return stringContains($ROOT_DIR, $sourceFilename);
}

function getRelativePath($file1, $file2, $rootDir) {
  $codeDirectory = dirname($file1);
  $pathFromRoot = str_replace($rootDir, '', $codeDirectory);
  $directoriesFromRoot = explode('/', $pathFromRoot);
  // avoid empty strings
  $directoriesFromRoot = array_filter($directoriesFromRoot, function($x){return !empty($x);});
  // traverse back to root directory w ../../../
  $directoriesFromRoot = array_map(function($x){return '..';}, $directoriesFromRoot);
  $fromFile1ToRoot = implode('/', $directoriesFromRoot);

  // note: we take off a slash here to make the return statement clearer
  $fromRootToFile2 = str_replace("$rootDir/", '', $file2);

  return "$fromFile1ToRoot/$fromRootToFile2";
}

/* * * * * * * * UTIL / SCOPE * * * * * * * */

function isAlreadyAccessible(FunctionCall $functionCall) {
  // note: this ternary format is experimental (personally, not for the language or anything)
  //       pro: makes it clear that those lines are continuations of the previous
  //       con: makes first line less clear that it continues
  //       I saw Guzzle do it so I'm tryin it out to see how I feel about it
  $functionCallType = $functionCall->getFunctionCallType();
  $functionName = $functionCall->getFunctionName();
  return $functionCallType === FunctionCallType::UNQUALIFIED
    ? isFunctionInScope($functionName)
    : isClassInScope($functionCall->getClassName());
}

/* * * * * * UTIL / PHP / TOKENS * * * * * * * */

/**
 * Class FunctionCallFactory
 *
 * not really sure if this is a great way to do this but it seems ok. I was
 * struggling where to put the logic for this / the functions that take
 * $tokens and $index as arguments... Ultimately I opted for DI+Factory instead
 * of putting all the token<=>FunctionCall logic in the FunctionCall class
 */
class FunctionCallFactory {
  public static function fromTokens($filename, &$tokens, $index) {
    $tokenText = $tokens[$index][1];
    $functionCallType = determineFunctionCallType($tokens, $index);
    $functionName = ($functionCallType == FunctionCallType::OBJECT_INSTANTIATION)
      ? '__construct'
      : $tokenText;
    $className = determineFunctionCallClassName($tokens, $index, $functionCallType);
    return new FunctionCall($filename, $functionName, $functionCallType, $className);
  }
}

/*
 * Note: "OBJECT_INSTANTIATION" is actually equivalent to "constructor".
 * I went for object instantiation cause it seemed clearer to me.
 */
class FunctionCallType {
  const OBJECT_INSTANTIATION = 0;
  const STATIC_METHOD = 1;
  const INSTANCE_METHOD = 2;
  const UNQUALIFIED = 3; // not sure what to call this, but "ELSE" basically
}

function getFunctionCallDeclarationPattern(FunctionCall $functionCall) {
  $functionName = $functionCall->getFunctionName();
  $functionCallType = $functionCall->getFunctionCallType();
  if ($functionCallType == FunctionCallType::OBJECT_INSTANTIATION) {
    return "class $functionName";
  } else if ($functionCallType == FunctionCallType::STATIC_METHOD) {
    return "static function $functionName\(";
  } else {
    return "function $functionName\(";
  }
}

function determineFunctionCallClassName(&$tokens, $index, $functionCallType) {
  $thisTokenText = $tokens[$index][1] ?? null;
  $tokenTwoBeforeText = $tokens[$index-2][1] ?? null;
  switch ($functionCallType) {
    case FunctionCallType::STATIC_METHOD:
      return $tokenTwoBeforeText;
    case FunctionCallType::OBJECT_INSTANTIATION:
      return $thisTokenText;
    case FunctionCallType::UNQUALIFIED:
      return null;
    case FunctionCallType::INSTANCE_METHOD:
      return null; // TODO theoretically we have to find the type of the var...
    default:
      return null;
  }
}

function determineFunctionCallType(&$tokens, $index) {
  $previousTokenType = $tokens[$index - 1][0] ?? null;
  if ($previousTokenType == T_OBJECT_OPERATOR) {
    return FunctionCallType::INSTANCE_METHOD;
  } else if ($previousTokenType == T_DOUBLE_COLON){
    return FunctionCallType::STATIC_METHOD;
  }

  $tokenTwoBeforeType = $tokens[$index - 2][0] ?? null;
  if ($tokenTwoBeforeType == T_NEW) {
    return FunctionCallType::OBJECT_INSTANTIATION;
  }

  // default
  return FunctionCallType::UNQUALIFIED;
}

function isFunctionCall(&$tokens, $index) {
  return isIdentifier($tokens[$index]) && isNextTokenOpenParen($tokens, $index);
}

/*
 * Note: It's important to tokenize rather than regex in order to avoid
 *       comments etc.
 */
function getFunctionCalls($filename) {
  $fileContents = file_get_contents($filename);
  $tokens = token_get_all($fileContents);
  $functionCalls = [];
  foreach ($tokens as $index => $token) {
    if (isFunctionCall($tokens, $index)) {
      $functionCalls []= FunctionCallFactory::fromTokens($filename, $tokens, $index);
    }
  }
  return $functionCalls;
}

/**
 * @param $filename string filename to parse for function definitions
 * @return array
 */
function getFunctionsDefinedInFile($filename) {
  $definedFunctions = [];
  $fileContents = file_get_contents($filename);
  $tokens = token_get_all($fileContents);
  $functionTokens = array_filter($tokens, 'isFunctionToken');
  foreach ($functionTokens as $index => $token) {
    $functionName = getNextStringIdentifier($tokens, $index);
    $definedFunctions []= $functionName;
  }
  return $definedFunctions;
}

// Note: this is for the keyword 'function', not detecting if the token
//       is a function call
function isFunctionToken($token) {
  return $token[0] == T_FUNCTION;
}

function getNextStringIdentifier(&$tokens, $index) {
  while ($token = $tokens[$index]) {
    $tokenType = $token[0] ?? null;
    if ($tokenType === T_STRING) {
      return $token[1];
    }
    $index++;
  }
  return null;
}

function isIdentifier($token) {
  return ($token[0] ?? null) == T_STRING; // TODO are there more?
}

/**
 * @param $tokens array of tokens passed by reference to save memory
 * @param $index int index of current token
 * @return bool whether next token is open paren
 */
function isNextTokenOpenParen(&$tokens, $index) {
  $nextToken = $tokens[$index + 1] ?? null;
  return $nextToken == '(';
}

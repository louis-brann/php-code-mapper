<?php

// TODO this may be roundabout way of doing this, as i think we may be
//      able to use function_exists() instead.... awks
function isFunctionInScope($functionName) {
  /*
   * ReflectionFunctions can only examine functions in the current scope. (TODO Verify)
   * If the function isn't defined, it'll throw an exception. Since this file
   * doesn't require any other files, it should only have access to
   *   a) standard PHP functions
   *   b) autloaded functions
   * both of which we don't need to manually require.
   *
   * Note: Even if the functions are in scope, $reflectionFunction->getFileName
   *       may not return anything. (I think this is true for standard PHP
   *       functions)
   */
  try {
    $reflFunc = new ReflectionFunction($functionName);
    $sourceFile = $reflFunc->getFileName();
    if (strlen($sourceFile) > 0) {
      // i haven't seen this happen yet, so it'll be cool to know if it ever does
      echo "WOW! Found a standard source file: $sourceFile" . PHP_EOL;
    }
    return true;
  } catch (Exception $e) {
    return false;
  }
}

function isClassInScope($className) {
  return class_exists($className); // TODO is it really that simple?
}

<?php

// TODO: can we use namespaces here?
require_once(__DIR__ . '/CodeMapper.php');

$rootDir = $argv[1] ?? null;
$qualifiedFunction = $argv[2] ?? null;
$callerFile = $argv[3] ?? null;
if (empty($rootDir) || empty($qualifiedFunction) || empty($callerFile)) {
  print "Not enough arguments. Received: php find-function-definition.php $rootDir $qualifiedFunction $callerFile" . PHP_EOL;
  print "Doesn't match actual usage." . PHP_EOL;
  print "Usage: php find-function-definition.php <rootDir> <function> <callerFile>" . PHP_EOL;
  exit(1);
}

$mapper = new PHPCodeMapper\CodeMapper($rootDir);
$mapper->simulateScopeOfFile($callerFile);
$definitionFile = $mapper->getFunctionDefinitionFile($qualifiedFunction);

print $definitionFile;

<?php

// TODO change to namespaces ASAP
require_once(__DIR__ . '/CodeMapper.php');

$rootDir = $argv[1] ?? null;
$qualifiedFunction = $argv[2] ?? null;
$callerFile = $argv[3] ?? null;
if (is_null($rootDir) || is_null($qualifiedFunction) || is_null($callerFile)) {
  print "Not enough arguments." . PHP_EOL;
  print "Usage: php find-function-definition.php <rootDir> <function> <callerFile>" . PHP_EOL;
}

$mapper = new CodeMapper($rootDir);
$mapper->simulateScopeOfFile($callerFile);
$definitionFile = $mapper->getFunctionDefinitionFile($qualifiedFunction);

print $definitionFile;

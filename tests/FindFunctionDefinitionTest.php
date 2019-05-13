<?php

namespace PHPCodeMapper\Tests;

use PHPCodeMapper\CodeMapper;
use PHPCodeMapper\FunctionCall;
use PHPCodeMapper\FunctionCallType;
use PHPUnit\Framework\TestCase;

class FindFunctionDefinitionTest extends TestCase {

  protected $targetDir = __DIR__ . '/targets';

  // todo name me better
  function testCase() {
    // params + expected output
    $callerFile = $this->targetDir . '/example-script.php';
    $functionCall = new  FunctionCall($callerFile, 'reflectionTestFunction', FunctionCallType::UNQUALIFIED, null);
    $expectedDefinitionFile = $this->targetDir . '/reflection-test-function.php';

    // test
    $codeMapper = new CodeMapper('/../tests/targets');
    $file = $codeMapper->findFunctionDefinitionIsolatedScope($functionCall);
    $this->assertEquals($expectedDefinitionFile, $file);
  }
}

<?php

namespace PHPCodeMapper\Tests;

use PHPCodeMapper\FunctionCallType;
use PHPUnit\Framework\TestCase;

// TODO remove this ASAP
require_once(__DIR__ . '/../src/code-mapper-util.php');

class GetFunctionCallsTest extends TestCase {

  protected $targetFile = __DIR__ . '/targets/example-script.php';

  public function testAllCases() {
    $functionCalls = getFunctionCalls($this->targetFile);
    $this->assertEquals(4, count($functionCalls));
    $foundTypes = array_map(function($fc){return $fc->getFunctionCallType();}, $functionCalls);
    $expectedTypes = [
      FunctionCallType::OBJECT_INSTANTIATION,
      FunctionCallType::STATIC_METHOD,
      FunctionCallType::INSTANCE_METHOD,
      FunctionCallType::UNQUALIFIED
    ];
    $this->assertEquals($expectedTypes, $foundTypes);
  }

}

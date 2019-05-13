<?php

namespace PHPCodeMapper\Tests;

use PHPCodeMapper\TokenHandler;
use PHPCodeMapper\FunctionCallType;
use PHPUnit\Framework\TestCase;

class TokenHandlerTest extends TestCase {

  protected $targetFile = __DIR__ . '/targets/example-script.php';

  public function testGetFunctionCalls() {
    $functionCalls = TokenHandler::getFunctionCalls($this->targetFile);
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

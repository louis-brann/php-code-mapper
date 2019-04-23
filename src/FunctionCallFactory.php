<?php

namespace PHPCodeMapper;

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

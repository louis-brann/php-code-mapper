<?php

namespace PHPCodeMapper;

class TokenHandler {
  /* * * * * * UTIL / PHP / TOKENS * * * * * * * */

  public static function getFunctionCallDeclarationPattern(FunctionCall $functionCall) {
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

  public static function determineFunctionCallClassName(&$tokens, $index, $functionCallType) {
    $thisTokenText = $tokens[$index][1] ?? null;
    $tokenTwoBeforeText = $tokens[$index - 2][1] ?? null;
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

  public static function determineFunctionCallType(&$tokens, $index) {
    $previousTokenType = $tokens[$index - 1][0] ?? null;
    if ($previousTokenType == T_OBJECT_OPERATOR) {
      return FunctionCallType::INSTANCE_METHOD;
    } else if ($previousTokenType == T_DOUBLE_COLON) {
      return FunctionCallType::STATIC_METHOD;
    }

    $tokenTwoBeforeType = $tokens[$index - 2][0] ?? null;
    if ($tokenTwoBeforeType == T_NEW) {
      return FunctionCallType::OBJECT_INSTANTIATION;
    }

    // default
    return FunctionCallType::UNQUALIFIED;
  }

  public static function isFunctionCall(&$tokens, $index) {
    return self::isIdentifier($tokens[$index]) && self::isNextTokenOpenParen($tokens, $index);
  }

  /*
   * Note: It's important to tokenize rather than regex in order to avoid
   *       comments etc.
   */
  public static function getFunctionCalls($filename) {
    $fileContents = file_get_contents($filename);
    $tokens = token_get_all($fileContents);
    $functionCalls = [];
    foreach ($tokens as $index => $token) {
      if (self::isFunctionCall($tokens, $index)) {
        $functionCalls [] = FunctionCallFactory::fromTokens($filename, $tokens, $index);
      }
    }
    return $functionCalls;
  }

  /**
   * @param $filename string filename to parse for function definitions
   * @return array
   */
  public static function getFunctionsDefinedInFile($filename) {
    $definedFunctions = [];
    $fileContents = file_get_contents($filename);
    $tokens = token_get_all($fileContents);
    $functionTokens = array_filter($tokens, 'isFunctionToken');
    foreach ($functionTokens as $index => $token) {
      $functionName = self::getNextStringIdentifier($tokens, $index);
      $definedFunctions [] = $functionName;
    }
    return $definedFunctions;
  }

// Note: this is for the keyword 'function', not detecting if the token
//       is a function call
  public static function isFunctionToken($token) {
    return $token[0] == T_FUNCTION;
  }

  public static function getNextStringIdentifier(&$tokens, $index) {
    while ($token = $tokens[$index]) {
      $tokenType = $token[0] ?? null;
      if ($tokenType === T_STRING) {
        return $token[1];
      }
      $index++;
    }
    return null;
  }

  public static function isIdentifier($token) {
    return ($token[0] ?? null) == T_STRING; // TODO are there more?
  }

  /**
   * @param $tokens array of tokens passed by reference to save memory
   * @param $index int index of current token
   * @return bool whether next token is open paren
   */
  public static function isNextTokenOpenParen(&$tokens, $index) {
    $nextToken = $tokens[$index + 1] ?? null;
    return $nextToken == '(';
  }
}

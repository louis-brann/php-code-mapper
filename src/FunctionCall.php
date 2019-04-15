<?php

/**
 * Class FunctionCall
 *
 * really just a bundle of properties. the class definition is mainly to serve
 * as an interface
 *
 * TODO: inherit from ReflectionFunction or something of the sort?
 *
 * TODO: the functionName functionCallType functionDefinition can all be
 *       grouped into a FunctionData class (is Function already taken? TODO look into namespaces)
 *       and then a FunctionCall would consist of a callerFile + FunctionData
 */
class FunctionCall {
  protected $filename;
  protected $functionName;
  protected $functionCallType;
  protected $className;
  protected $functionDefinition;

  public function __construct($filename, $functionName, $functionCallType, $className) {
    $this->filename = $filename;
    $this->functionName = $functionName;
    $this->functionCallType = $functionCallType;
    $this->className = $className;
  }

  public function getFilename() {
    return $this->filename;
  }

  public function getFunctionName() {
    return $this->functionName;
  }

  public function getFunctionCallType() {
    return $this->functionCallType;
  }

  public function getClassName() {
    return $this->className;
  }

  public function getQualifiedFunctionName() {
    $functionName = $this->getFunctionName();
    $className = $this->getClassName();
    return !empty($className) ? "$className::$functionName" : $functionName;
  }
}

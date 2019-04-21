<?php

require_once(__DIR__ . '/reflection-test-variable.php');
require_once(__DIR__ . '/reflection-test-function.php');
require_once(__DIR__ . '/ReflectionTestClass.php');

/*
 * Possible dependencies between files
 *
 * 1. Class
 *     - constructor call
 *     - method
 *       - static
 *       - instance
 *     - variable
 *       - static
 *       - instance
 * 2. Function
 * 3. Variable
 */

$x = new ReflectionTestClass();                     // constructor
ReflectionTestClass::staticTest();                  // method static
$x->instanceTest();                                 // method instance
$assignment = ReflectionTestClass::$staticVariable; // variable static
$assignment = $x->instanceVariable;                 // variable instance
reflectionTestFunction();                           // function
$assignment = $global;                              // variable

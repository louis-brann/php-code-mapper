<?php

require_once(__DIR__ . '/reflection-test-variable.php');
require_once(__DIR__ . '/reflection-test-function.php');
require_once(__DIR__ . '/ReflectionTestClass.php');

/*
 * Possible dependencies between files
 * 
 * TODO: namespaced things...
 *
 * 1. Class
 *     - constructor call
 *     - method
 *       - static
 *       - instance
 *     - variable
 *       - constant
 *       - static
 *       - instance
 * 2. Global Function
 * 3. Global Variable
 * 4. Global Constant
 */

$x = new ReflectionTestClass();                     // constructor
ReflectionTestClass::staticTest();                  // method static
$x->instanceTest();                                 // method instance
$assignment = ReflectionTestClass::CLASS_CONST;     // variable constant
$assignment = ReflectionTestClass::$staticVariable; // variable static
$assignment = $x->instanceVariable;                 // variable instance
reflectionTestFunction();                           // function
$assignment = $global;                              // variable
$assignment = GLOBAL_CONST_DEFINE;                  // constant
$assignment = GLOBAL_CONST_KEYWORD;                 // constant

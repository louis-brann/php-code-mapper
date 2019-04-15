<?php

/**
 * $array[$key] []= $value with safety net
 *
 * could use a better name if you can think of one
 *
 * @param $array
 * @param $key
 * @param $value
 */
function arrayAddValueForKey(&$array, $key, $value) {
  if (!isset($array[$key])) {
    $array[$key] = [];
  }
  $array[$key] []= $value;
}

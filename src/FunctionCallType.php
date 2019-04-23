<?php

namespace PHPCodeMapper;

/*
 * Note: "OBJECT_INSTANTIATION" is actually equivalent to "constructor".
 * I went for object instantiation cause it seemed clearer to me.
 */
class FunctionCallType {
  const OBJECT_INSTANTIATION = 0;
  const STATIC_METHOD = 1;
  const INSTANCE_METHOD = 2;
  const UNQUALIFIED = 3; // not sure what to call this, but "ELSE" basically
}

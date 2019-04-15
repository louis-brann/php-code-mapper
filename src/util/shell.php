<?php

function getFilenameFromGrepLine($grepLine) {
  // TODO this doesn't handle filenames with colons in it but if you're really
  //      putting colons in your filenames you kinda deserve it so
  return explode(':', $grepLine)[0] ?? null;
}

/**
 * Note I thought this would stop output from being printed. It does not
 *      stop _all_ printing, as warnings/errors still get printed
 * TODO: fix/rename?
 *
 * @param $command string command to execute
 * @param $verbose bool whether to print
 * @return array lines of output from exec command
 */
function execWithNoPrinting($command, $verbose = false) {
  if ($verbose) { echo "$command" . PHP_EOL; }
  $startTime = microtime(true);
  ob_start();
  exec($command, $output);
  ob_end_clean();
  $endTime = microtime(true);
  if ($verbose) { echo "finished exec in " . ($endTime - $startTime) . " seconds" . PHP_EOL; }
  return $output;
}


function printWarning($message) {
  printWithShellColor("[WARNING] " . $message, 'red');
}

function printWithShellColor($msg, $color) {
  $supportedColorCodes = [
    'black'       => '0;30',
    'dark_gray'   => '1;30',
    'blue'        => '0;34',
    'light_blue'  => '1;34',
    'green'       => '0;32',
    'light_green' => '1;32',
    'cyan'        => '0;36',
    'light_cyan'  => '1;36',
    'red'         => '0;31',
    'light_red'   => '1;31',
    'purple'      => '0;35',
    'light_purple'=> '1;35',
    'brown'       => '0;33',
    'yellow'      => '1;33',
    'light_gray'  => '0;37',
    'white'       => '1;37'
  ];
  if (!isset($supportedColorCodes[$color])) {
    echo $msg;
    return;
  }

  $colorCode = $supportedColorCodes[$color];
  $colorStart = "\033[${colorCode}m";
  $colorEnd = "\033[0m";
  $coloredMessage = $colorStart . $msg . $colorEnd;
  echo $coloredMessage;
}

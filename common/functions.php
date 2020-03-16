<?php

require_once 'globals.php';

function prepare_options($options, $default_options) {
  return array_merge($default_options, $options);
}

function info($msg, $options = []) {
  global $info_counter;

  $options = prepare_options($options, [
    'suffix' => "\n",
  ]);

  echo "[$info_counter] → $msg" . $options['suffix'];
  return ++$info_counter;
}

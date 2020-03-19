<?php

require_once 'globals.php';

function get_operating_system() {
  return ((getenv("windir") !== false) ? "Windows" : "Linux");
}

function prepare_options($options, $default_options) {
  return array_merge($default_options, $options);
}

function info($msg, $options = []) {
  global $info_counter;
  global $info_indent_level;

  $options = prepare_options($options, [
    'prefix' => '',
    'suffix' => "\n",
    'use_array_keys' => false,
    'autoindent' => true,
    'indent' => '',
  ]);

  if (is_array($msg) || is_object($msg)) {
    ++$info_indent_level;

    if ($options['use_array_keys']) {
      foreach ($msg as $key => $value) {
        if (is_array($value) || is_object($value)) {
          info("$key is an " . (is_array($value) ? 'array' : 'object') . " consisting of", $options);
          info($value, $options);
        } else {
          info("$key = $value", $options);
        }
      }
    } else {
      array_map('info', $msg, $options);
    }

    --$info_indent_level;
    return $info_counter;
  }

  echo $options['prefix']
    . "[$info_counter] → "
    . ($options['autoindent'] ? str_repeat("\t", $info_indent_level) : $options['indent'])
    . $msg
    . $options['suffix'];

  return ++$info_counter;
}

function failure($msg = null) {
  if (is_integer($msg)) return $msg;
  if ($msg !== null) {
    info("FAILURE: " . $msg);
  }
  exit(EXIT_FAILURE);
}

function execute($array_of_commands, $options = []) {
  $default_options = [
    'break_on_exit_nonzero' => true,
    'line_separator' => "\n",
    'output_passthrough' => true,
    'verbose' => true,
  ];

  $options = array_merge($default_options, $options);

  foreach ($array_of_commands as $command) {
    $output = [];
    $exit_code = null;

    if ($options['verbose']) {
      info('Executing ' . $command);
    }

    if ($options['output_passthrough']) {
      passthru($command, $exit_code);
    } else {
      exec($command, $output, $exit_code);
      foreach ($output as $line) {
        echo $line . $options['line_separator'];
      }
    }

    if ($options['break_on_exit_nonzero'] && $exit_code != 0) {
      info("Exit code equals $exit_code, execution chain stopped on command '$command'.");
      break;
    }

    if ($options['verbose']) {
      echo "\n";
    }
  }
}

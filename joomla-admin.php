<?php

require 'common/functions.php';

extract(prepare_options(getopt('', [
  'help',
  'dir:',
  'config:',
  'report',
  'users',
]), [
  'dir' => '.',
  'config' => 'configuration.php',
]));

if ($argc == 1 || isset($help)) {
  $me = basename(__FILE__);
  echo "\n\tphp $me [OPTIONS]

  Possible options:

    --help      This screen.
    --dir       Path to Joomla directory
    --config    Name of config file ('configuration.php' by default)
    --report    Dump of all constants and selected variables extracted from config file
                plus some selected options extracted from database
    --users
  ";
  exit;
}

$dir = rtrim($dir, '/');

define('_JEXEC', true);
define('JPATH_BASE', $dir);
$defines_php = JPATH_BASE . '/includes/defines.php';
$framework_php = JPATH_BASE . '/includes/framework.php';

if (file_exists($defines_php)) {
  require_once $defines_php;

  if (file_exists($framework_php)) {
    require_once $framework_php;

  } else {
    info("File $framework_php does not exist");
  }
} else {
  info("File $defines_php does not exist");
}


if (isset($report)) {
  if (false === class_exists('JConfig')) {
    $config_file = realpath("$dir/$config");

    if ($config_file === false && file_exists($config_file) === false) {
      failure("File $config_file does not exist");
    }

    include "$config_file";
  }

  $settings = get_class_vars('JConfig');

  foreach ($settings as $setting => $value) {
    if ($value === false) $value = 'false';
    if ($value === null) $value = 'NULL';
    if ($value === '') {
      info("$setting is an empty string");
    } else {
      info("$setting = $value");
    }
  }

  $constants = get_defined_constants(true);

  foreach ($constants['user'] as $constant => $value) {
    if (in_array($constant, ['EXIT_SUCCESS', 'EXIT_FAILURE'])) continue;
    if ($value === false) $value = 'false';
    if ($value === null) $value = 'NULL';
    if ($value === '') {
      info("$constant is an empty string");
    } else {
      info("$constant = $value");
    }
  }
}

if (isset($users)) {

}

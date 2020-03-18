<?php

require 'common/functions.php';

extract(prepare_options(getopt('', [
  'help',
  'dir:',
  'config:',
  'report',
]), [
  'dir' => '.',
  'config' => 'configuration.php',
]));

if (isset($help)) {
  $me = basename(__FILE__);
  echo "\n\tphp $me [OPTIONS]

  Possible options:

    --help      This screen.
    --dir       Path to wordpress directory
    --config    Name of config file ('wp-config.php' by default)
    --report    Dump of all constatns and selected variables extracted from config file
                plus some selected options extracted from database
  ";
  exit;
}

$dir = rtrim($dir, '/');
$config_file = realpath("$dir/$config");

if ($config_file === false && file_exists($config_file) === false) {
  info("Config file $config_file does not exist");
  exit;
}

include "$config_file";

$settings = get_class_vars('JConfig');

if (isset($report)) {
  foreach ($settings as $setting => $value) {
    if ($value === false) $value = 'false';
    if ($value === null) $value = 'NULL';
    if ($value === '') {
      info("$setting is an empty string");
    } else {
      info("$setting = $value");
    }
  }
}


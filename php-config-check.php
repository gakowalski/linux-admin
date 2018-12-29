<?php

// inspiration:
// https://www.cyberciti.biz/faq/linux-unix-apache-lighttpd-phpini-disable-functions/
// https://www.cyberciti.biz/tips/php-security-best-practices-tutorial.html
// https://www.owasp.org/index.php/PHP_Configuration_Cheat_Sheet

$ini_keys = array(
  'allow_url_fopen',
  'allow_url_include',
  'expose_php',
  'display_errors',
  'log_errors',
  'ignore_repeated_errors',
  'disable_functions',
  'open_basedir',
);

// TO DO: how to disable eval? it's not a function!

$functions_to_be_disabled = array(
  'exec',
  'passthru',
  'shell_exec',
  'system',
  'proc_open',
  'popen',
  'curl_exec',
  'curl_multi_exec',
  'parse_ini_file',
  'show_source',
);

function warning($key, $value, $message) {
  if ($value === '') $value = 'an empty string';
  echo "$key is $value - WARNING, $message\n";
}
function advice($key, $value, $new_value) {
  warning($key, $value, "should be '$new_value'!");
}

function check_ini_key($key) {
  if (get_cfg_var($key) != ini_get($key)) {
    echo "$key has different values in config file and at runtime!\n";
  }
  return check_ini_value($key, ini_get($key));
}

function check_ini_value($key, $value) {
  if ($value === '') {
    warning($key, $value, 'appears empty, unknown default value is applied or no value is applied');
    return;
  }

  // checking file/directory existence
  switch ($key) {
    case 'open_basedir':
    case 'error_log':
      if (file_exists($value) === false) {
        warning($key, $value, 'file/directory appears to be non-existent');
      }
    default:
  }

  // specialized checks
  switch ($key) {
    case 'allow_url_fopen':
    case 'allow_url_include':
    case 'expose_php':
    case 'display_errors':
      if ($value) advice($key, $value, 'Off');
      break;

    case 'ignore_repeated_errors':
      if ($value == false) advice($key, $value, 'On');
      break;

    case 'log_errors':
      if ($value == 1) {
        echo "$key is On, checking dependent keys\n\t";
        check_ini_key('error_log');
      } else {
        advice($key, $value, 'On');
      }
      break;

    case 'open_basedir':
      if ($value == '/var/www/') {
        warning($key, $value, 'consider setting individual values for each PHP app (eg. /var/www/website1)');
      }
    break;

    case 'disable_functions':
      $functions = explode(',', $value);
      $diff = array_intersect(array_diff($functions_to_be_disabled, $functions), $functions_to_be_disabled);
      warning($key, $value, "consider adding '$diff'");
      break;

    default:
      echo "Unknown INI key $key - INTERNAL ERROR!\n";
  }
}

echo "cfg_file_path:".get_cfg_var('cfg_file_path')."\n";
echo "Loaded INI file:".php_ini_loaded_file()."\n";
echo "Scanned INI files:".php_ini_scanned_files()."\n";


foreach ($ini_keys as $key) {
  check_ini_key($key);
}

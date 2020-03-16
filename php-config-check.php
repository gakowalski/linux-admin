<?php

// do not modify
$line_check = __LINE__;

require 'common/functions.php';

// inspiration:
// https://www.cyberciti.biz/faq/linux-unix-apache-lighttpd-phpini-disable-functions/
// https://www.cyberciti.biz/tips/php-security-best-practices-tutorial.html
// https://www.owasp.org/index.php/PHP_Configuration_Cheat_Sheet

// TODO: check if mysqlnd is used instead of mysql, recommend mysqlnd

$ini_keys = array(
  'allow_url_fopen',
  'allow_url_include',
  'expose_php',
  'display_errors',
  'log_errors',
  'ignore_repeated_errors',
  'disable_functions',
  'open_basedir',
  'post_max_size',
  'default_charset',
  'file_uploads',
  'upload_tmp_dir',
  'extension_dir',
  'user_dir',
  'include_path',
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

$ini_local_copy = [];

function warning($key, $value, $message) {
  if ($value === '') $value = 'an empty string';
  info("$key is $value - WARNING, $message");
}
function advice($key, $value, $new_value) {
  warning($key, $value, "should be '$new_value'!");
}

// taken from on: https://stackoverflow.com/a/22500394/925196
function size_to_bytes($size)
{
    $suffix = strtoupper(substr($size, -1));
    if (!in_array($suffix, array('P','T','G','M','K'))){
        return (int) $size;
    }
    $value = substr($size, 0, -1);
    switch ($suffix) {
        case 'P': $value *= 1024; // Fallthrough intended
        case 'T': $value *= 1024; // Fallthrough intended
        case 'G': $value *= 1024; // Fallthrough intended
        case 'M': $value *= 1024; // Fallthrough intended
        case 'K': $value *= 1024; break;
    }
    return $value;
}

function check_ini_key($key) {
  global $ini_local_copy;

  if (isset($ini_local_copy[$key])) {
    return $ini_local_copy[$key];
  }

  $config_value = get_cfg_var($key);
  $runtime_value = ini_get($key);

  if ($config_value != $runtime_value) {
    info("$key has different values in config file and at runtime");
    info("\t\tconfig:  $config_value");
    info("\t\truntime: $runtime_value");
  }


  $ini_local_copy[$key] = $runtime_value;

  check_ini_value($key, $runtime_value);

  return $runtime_value;
}

function check_ini_value($key, $value) {
  if ($value === '') {
    switch ($key) {
      case 'user_dir':
        // if empty, setting is unused
        return;

      case 'upload_tmp_dir':
        warning($key, $value, 'appears empty, PHP will try to use the system\'s default tmp dir ' . sys_get_temp_dir());
        return;

      default:
        warning($key, $value, 'appears empty, unknown default value is applied or no value is applied');
        return;
    }
  }

  // checking file/directory existence
  switch ($key) {
    case 'open_basedir':
    case 'error_log':
    case 'upload_tmp_dir':
      if (file_exists($value) === false) {
        warning($key, $value, 'file/directory appears to be non-existent');
      } else if (is_writable($value)) {
        warning($key, $value, 'is not writable');
      }
    break;

    case 'extension_dir':
      if (file_exists($value) === false) {
        warning($key, $value, 'file/directory appears to be non-existent');
      }
      return;

    case 'include_path':
      if (check_path_collection($value, ['writable' => false]) === false) return;
    break;

    case 'open_basedir':
      if (check_path_collection($value) === false) return;
    break;

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
        info("$key is On, checking dependent keys...");
        check_ini_key('error_log');
      } else {
        advice($key, $value, 'On');
      }
      break;

    case 'error_log':
      // see previous switch
      break;

    case 'open_basedir':
      if (in_path_collection('/var/www/', $value)) {
        warning($key, $value, 'consider setting individual values for each PHP app (eg. /var/www/website1)');
      }
    break;

    case 'disable_functions':
      $functions = explode(',', $value);
      $diff = array_intersect(array_diff($functions_to_be_disabled, $functions), $functions_to_be_disabled);
      warning($key, $value, "consider adding '$diff'");
    break;

    case 'post_max_size':
      $upload_size = check_ini_key('upload_max_filesize');
      if (size_to_bytes($value) < size_to_bytes($upload_size)) {
        warning($key, $value, "should be bigger than upload_max_filesize ($upload_size)");
      }
    break;

    case 'upload_max_filesize':
      // checked together with post_max_size
    break;

    case 'default_charset':
      if ($value != 'UTF-8') advice($key, $value, 'UTF-8');
    break;

    case 'file_uploads':
      if (!$value) advice($key, $value, 'On');
    break;

    default:
      info("Unknown INI key $key - INTERNAL ERROR!");
  }
}

function check_path($path, $options = []) {
  $options = prepare_options($options, [
    'writable'  =>  true,
    'readable'  =>  true,
    'directory' =>  true,
  ]);
  extract($options);

  $tmp = realpath($path); //< realpath returns false if path is non-existing
  if ($tmp) $path = $tmp;

  if ($tmp === false || file_exists($path) === false) {
    info("path $path appears to be non-existent");
    return false;
  } else if ($readable && is_readable($path) === false) {
    info("path $path appears to be non-readable");
    return false;
  } else if ($writable && is_writable($path) === false) {
    info("path $path appears to be non-writable");
    return false;
  } else if ($directory && is_file($path)) {
    info("path $path appears to be a file, not a directory");
    return false;
  }
  return true;
}

function check_path_collection($path_collection, $options = []) {
  $array = explode(PATH_SEPARATOR, $path_collection);
  foreach ($array as $path_root) {
    if (check_path($path_root, $options) === false) return false;;
  }
  return true;
}

function in_path_collection($single_path, $path_collection) {
  $array = explode(PATH_SEPARATOR, $path_collection);
  $single_path = realpath($single_path);

  foreach ($array as $path_root) {
    if (strpos($single_path, realpath($path_root))) return $path_root;
  }

  return false;
}

$options = getopt('', [
  'httpd::',
  'protocol:',
  'host:',
  'port:',
]);

if (isset($options['protocol'])) {
  $protocol = $options['protocol'];
} else {
  $protocol = 'http';
}

if (isset($options['host'])) {
  $host = $options['host'];
} else {
  $host = '127.0.0.1';
}
$host = rtrim($host, '/') . '/';

if (isset($options['httpd'])) {
  $document_root = $options['httpd'];
  if ($document_root == false) {
    info("No document root specified, trying to read it from httpd -S");
    $document_root = exec('httpd -S | grep "Main DocumentRoot"');
    if (preg_match('/(\/[\/a-zA-Z]+)/', $document_root, $matches)) {
      $document_root = $matches[0];
      info("Extracted document root: $document_root");
    } else {
      info("Couldn't extract document root");
      exit(-1);
    };
  }
  if (file_exists($document_root)) {
    if (is_writable($document_root)) {
      $file = basename(__FILE__);
      $target = "$document_root/$file";
      if (copy(__FILE__, $target)) {
        $output = file_get_contents("$protocol://$host/$file");
        if ($output === false) {
          info("Can't read from webserver");
        } else {
          echo $output;
        }
        if (unlink($target) === false) {
          echo "\nCOULD'T REMOVE SCRIPT FROM THE WEBSERVER, DO IT MANUALLY\n";
        };
        exit(($output === false) ? -1 : 0);
      } else {
        info("Couldn't put ".__FILE__." at $target");
        exit(-1);
      };
    } else {
      info("Document root is not writable");
      exit(-1);
    }
  } else {
    info("Document root not exits!");
    exit(-1);
  }
}

if (http_response_code() !== false) {
  info('Running online as ' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']. $_SERVER['REQUEST_URI']);
} else {
  info('Running offline (probably in "cli" environment)');
  if (ini_get('open_basedir')) {
    info("Enabled open_basedir directive can cause some checks to be skipped or to throw errors");
    info("Consider running this script with parameter -d open_basedir=Off");
  }
}

info('PHP Version: ' . phpversion());
info("cfg_file_path: " . get_cfg_var('cfg_file_path'));
info("Loaded INI file: " . php_ini_loaded_file());
info("Scanned INI files: " . php_ini_scanned_files());

if (!defined("PATH_SEPARATOR")) {
  info("constant PATH_SEPARATOR is not defined - strange things can happen");
}
if ($line_check !== 4) {
  info("magic constant __LINE__ seems to return wrong numbers for this script!");
}

foreach ($ini_keys as $key) {
  check_ini_key($key);
}

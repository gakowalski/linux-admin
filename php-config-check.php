<?php

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

$info_counter = 0;
$ini_local_copy = [];

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

  if (get_cfg_var($key) != ini_get($key)) {
    info("$key has different values in config file and at runtime!");
  }

  $runtime_value = ini_get($key);
  $ini_local_copy[$key] = $runtime_value;

  check_ini_value($key, $runtime_value);

  return $runtime_value;
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
        info("$key is On, checking dependent keys...");
        check_ini_key('error_log');
      } else {
        advice($key, $value, 'On');
      }
      break;

    case 'open_basedir':
    case 'error_log':
      // see previous switch
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
}

info('PHP Version: ' . phpversion());
info("cfg_file_path: " . get_cfg_var('cfg_file_path'));
info("Loaded INI file: " . php_ini_loaded_file());
info("Scanned INI files: " . php_ini_scanned_files());

foreach ($ini_keys as $key) {
  check_ini_key($key);
}

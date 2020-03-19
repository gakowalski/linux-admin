<?php

require 'common/functions.php';

extract(prepare_options(getopt('', [
  'help',
  'php',
  'connect',
  'user:',
  'password:',
  'host:',
  'port',
  'client:',
  'dir:',
  'mysql:',
  'mysqladmin:',
  'mysqldump:',
]), [
  'config' => 'my.cnf',
  'user' => 'root',
  'password' => null,
  'host' => '127.0.0.1',
  'port' => 3306,
  'client' => 'mysqli',
  'mysql' => 'mysql',
  'mysqladmin'  => 'mysqladmin',
  'mysqldump'   => 'mysqldump',
]));

if (isset($help)) {
  $me = basename(__FILE__);
  echo "\n\tphp $me [OPTIONS]

  Possible options:

    --help        This screen.
    --php         Report on PHP mysql connectivity capabilities
    --client      Choose one: mysqli [default], PDO, cli
      --mysql         Path to mysql command
      --mysqladmin    Path to mysqladmin command
      --mysqldump     Path to mysqldump command
    --connect
      --user
      --password
      --host
      --port
  ";
  exit;
}

if (isset($php)) {
  if (extension_loaded('mysql')) info("MySQL extension loaded, version " . phpversion('mysql'));
  if (extension_loaded('mysqlnd')) info("MySQLnd extension loaded, version " . phpversion('mysqlnd'));
  if (extension_loaded('pdo')) info("PDO extension loaded, version " . phpversion('pdo'));
  if (extension_loaded('pdo_mysql')) info("PDO MySQL extension loaded, version " . phpversion('pdo_mysql'));

  $mysqlnd_functions = [
    'mysqli_fetch_all',
    'mysqli_get_client_stats',
    'mysqli_get_connection_stats',
  ];

  if (version_compare(phpversion(), '5.4.0', '<')) {
    $mysqlnd_functions[] = 'mysqli_get_cache_stats';
  }

  $mysqlnd_functions_count = 0;

  foreach ($mysqlnd_functions as $mysqlnd_function) {
    if (function_exists($mysqlnd_function)) {
      $mysqlnd_functions_count++;
    } else {
      info("$mysqlnd_function not found");
    }
  }

  if ($mysqlnd_functions_count) {
    info($mysqlnd_functions_count . ' of ' . count($mysqlnd_functions) . " MySQLnd specific functions present");
  } else {
    info("No MySQLnd specific functions present");
  }
}

if (isset($connect)) {
  info("Connecting to $user@$host:$port");

  if ($client == 'mysqli') {
    if ($password === null) info("No password being used");
    else info("Password is being used");

    $mysqli = mysqli_connect($host, $user, $password, '', $port);
    if ($mysqli === false || mysqli_connect_error()) {
      info("Something went wrong");
      info('Reported error #' . mysqli_connect_errno() . ': ' .  mysqli_connect_error());
      failure();
    } else {
      info("Connection successful");

      info([
        'Client version' => mysqli_get_client_version($mysqli),
        'Client info' => mysqli_get_client_info(),
        'Client library compiled as thread safe' => (mysqli_thread_safe() ? 'Yes' : 'No'),
        'Host' => mysqli_get_host_info($mysqli),
        'Server version' => mysqli_get_server_version($mysqli),
        'Server info' => mysqli_get_server_info($mysqli),
        'Server status' => mysqli_stat($mysqli),
        'Protocol version' => mysqli_get_proto_info($mysqli),
        'Current charset' => mysqli_character_set_name($mysqli),
        'Charset' => mysqli_get_charset($mysqli),
      ], [ 'use_array_keys' => true ]);

      mysqli_close($mysqli);
    }
  } else if ($client == 'PDO') {
    try {
      $pdo = new PDO("mysql:host=$host:$port", $user, $password);

      info("Connection successful");

      $pdo_constants = array(
        'PDO::ATTR_AUTOCOMMIT',
        'PDO::ATTR_CASE',
        'PDO::ATTR_CLIENT_VERSION',
        'PDO::ATTR_CONNECTION_STATUS',
        'PDO::ATTR_CURSOR',
        'PDO::ATTR_CURSOR_NAME',
        'PDO::ATTR_DRIVER_NAME',
        'PDO::ATTR_ERRMODE',
        'PDO::ATTR_FETCH_CATALOG_NAMES',
        'PDO::ATTR_FETCH_TABLE_NAMES',
        'PDO::ATTR_ORACLE_NULLS',
        'PDO::ATTR_MAX_COLUMN_LEN',
        'PDO::ATTR_PERSISTENT',
        'PDO::ATTR_PREFETCH',
        'PDO::ATTR_SERVER_INFO',
        'PDO::ATTR_SERVER_VERSION',
        'PDO::ATTR_STATEMENT_CLASS',
        'PDO::ATTR_STRINGIFY_FETCHES',
        'PDO::ATTR_TIMEOUT',
      );

      if (version_compare(phpversion(), '5.1.3', '>=')) {
        $pdo_constants[] = 'PDO::ATTR_EMULATE_PREPARES';
      }

      if (version_compare(phpversion(), '5.2.0', '>=')) {
        $pdo_constants[] = 'PDO::ATTR_DEFAULT_FETCH_MODE';
      }

      if (version_compare(phpversion(), '7.2.0', '>=')) {
        $pdo_constants[] = 'PDO::ATTR_DEFAULT_STR_PARAM';
      }

      $attributes = [];

      foreach ($pdo_constants as $pdo_const) {
        $value = @$pdo->getAttribute(constant($pdo_const));
        if ($value === false) {
          info("$pdo_const not supported", [ 'autoindent' => false, 'indent' => "\t" ]);
        } else {
          $attributes[$pdo_const] = $value;
        }
      }

      info($attributes, [ 'use_array_keys' => true ]);

    } catch (PDOException $e) {
      info("Something went wrong");
      info('Reported error: ' . $e->getMessage());
      failure();
    }
  } else if ($client == 'cli') {
    $common_options = "--host=$host --port=$port --user=$user";
    if ($password !== null) {
      $common_options = "$common_options --password=$password";
    }

    $mysql_cmd = "$mysql $common_options";
    $mysqladmin_cmd = "$mysqladmin $common_options";
    $mysqldump_cmd = "$mysqldump $common_options";

    execute([
      "$mysql --version",
      "$mysqladmin --version",
      "$mysqldump --version",
      "$mysqladmin_cmd ping",
      "$mysqladmin_cmd version",
      "$mysqladmin_cmd status",
      "$mysqladmin_cmd extended-status",
      "$mysqladmin_cmd processlist",
    ]);
  } else {
    info("Unknown client $client");
  }

}

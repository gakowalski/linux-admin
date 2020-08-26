<?php

require 'common/functions.php';

extract(prepare_options(getopt('', [
  'help',
  'dir:',
  'report',
  'backup::',
  'mysqldump:',
  'find'
]), [
  'dir' => '.',
  'mysqldump' => 'mysqldump',
]));

if ($argc == 1 || isset($help)) {
  $me = basename(__FILE__);
  echo "\n\tphp $me [OPTIONS]

  Possible options:

    --help      This screen.
    --dir=      Path to app directory
    --backup=        Backup 'db', 'files' or 'both' (default)
      --mysqldump=  Path to mysqldump (if this utility is not in PATH)
    --report    Dump of all constants and selected variables extracted from config file
                plus some selected options extracted from database
    --find      Try to locate Laravel instances
    --check     Check for configuration issues and check source files for common errors
  ";
  exit;
}

$operating_system = get_operating_system();

if (false === isset($find)) {
  $dir = rtrim($dir, '/');
  $config_file = realpath("$dir/.env");

  if ($config_file === false && file_exists($config_file) === false) {
    info("Config file $config_file does not exist");
    exit;
  }

  $config_data = file_get_contents($config_file);
  $config_array = string_to_array($config_data);
}

if (isset($report)) {
  foreach ($config_array as $key => $value) {
    if ($value === false) $value = 'false';
    if ($value === null) $value = 'NULL';
    if ($value === '') {
      info("$key is an empty string");
    } else {
      info("$key = $value");
    }
  }
}

if (isset($backup)) {
  info('Starting backup of ' . ($backup == 'both' ? 'files and db' : $backup));

  $host = $config_array['DB_HOST'];
  $user = $config_array['DB_USERNAME'];
  $password = $config_array['DB_PASSWORD'];
  $database = $config_array['DB_DATABASE'];
  $dump = "laravel-$database-$host-" . date('Y-m-d-') . time();
  $db_dump = "$dump.sql";
  $db_log = "$dump.log";

  // DATABASE BACKUP
  if ($backup != 'files') {
    $cmds = [];
    $cmds[] = "$mysqldump --host=$host --user=$user --password=$password --log-error=$db_log --single-transaction --extended-insert $database > $db_dump";

    info("Operating system: $operating_system");

    if ($operating_system == "Windows") {
      $cmds[] = "powershell Compress-Archive $db_dump $db_dump.zip";
      $cmds[] = "del $db_dump";

      $db_dump = "$db_dump.zip";
    } else if ($operating_system == "Linux") {
      if (posix_getuid() == 0){
        $cmds[] = 'dnf install pv -y || yum install pv -y';
      }
      $cmds[] = "pv $db_dump | gzip -c -v > $db_dump.gz || gzip -v $db_dump";
      $db_dump = "$db_dump.gz";
    }
    execute($cmds);

    if (realpath($db_dump)) {
      info("Database backup complete: " . realpath($db_dump));
      info("Log file created: " . realpath($db_log));
    } else {
      failure("Database backup failure");
    }
  }

  // FILES BACKUP
  if ($backup != 'db') {
    if ($operating_system == 'Windows') {
      $file_dump = "$dump.zip";
      execute("powershell Compress-Archive $dir $file_dump");
    } else if ($operating_system == "Linux") {
      $file_dump = "$dump.tar.gz";
      execute("tar -zcvf $file_dump $dir");
    }

    if (realpath($file_dump)) {
      info("File backup complete: " . realpath($file_dump));
    } else {
      failure("File backup failure");
    }
  }

  info('Site backup complete');
}

if (isset($find)) {
  if (get_operating_system() == 'Windows') {
    failure("Locating WP sites is unsupported on Windows");
  }

  $cmds = [];

  if (posix_getuid() == 0){
    info("Running as root user");
    $cmds[] = 'dnf install mlocate -y || yum install mlocate -y';
    $cmds[] = 'updatedb';
  } else {
    info("Running as non-root user - results may be incomplete!");
  }

  $cmds[] = "locate artisan | sed --expression='s/artisan//g'";

  execute($cmds);
}

/*** --check ***/
if (isset($check)):

  /** check configuration **/
  if ($config_array['APP_NAME'] == 'Laravel') {
    echo "Default APP_NAME used. Is this OK for this project to be called 'Laravel'? This name might be injected into the <title> tag.\n";
  }
  if (empty($config_array['APP_KEY'])) {
    echo "Empty APP_KEY. Run 'php artisan key:generate' to generate new one.\n";
  }

  /** check source files **/
  $app_files = `find $dir/app/ -type f -name "*.php"`;
  $migration_files = `find $dir/database/migration/ -type f -name "*.php"`;

  foreach (string_to_array($app_files) as $source_file_path) {
    echo "Detected: $source_file_path\n";
  }
  foreach (string_to_array($migration_files) as $source_file_path) {
    echo "Detected: $source_file_path\n";
  }

endif;

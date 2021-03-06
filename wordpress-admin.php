<?php

require 'common/functions.php';

extract(prepare_options(getopt('', [
  'help',
  'dir:',
  'config:',
  'adduser',
  'username:',
  'password:',
  'email:',
  'role:',
  'report',
  'advice',
  'backup::',
  'mysqldump:',
  'find'
]), [
  'dir' => '.',
  'config' => 'wp-config.php',
  'password' => time(),
  'email' => '',
  'role' => 'subscriber',
  'mysqldump' => 'mysqldump',
]));

if ($argc == 1 || isset($help)) {
  $me = basename(__FILE__);
  echo "\n\tphp $me [OPTIONS]

  Possible options:

    --help      This screen.
    --dir=      Path to Wordpress directory
    --config=   Name of config file ('wp-config.php' by default)
    --adduser   Add user, use sub-options
      --username=   Required unique username
      --password=   Optional password (if not supplied, will be randomly generated)
      --email=      Optional email address
      --role=       Optional role name, eg. e.g. subscriber (default), administrator
    --backup=        Backup 'db', 'files' or 'both' (default)
      --mysqldump=  Path to mysqldump (if this utility is not in PATH)
    --report    Dump of all constants and selected variables extracted from config file
                plus some selected options extracted from database
    --advice    Check settings and give advice
    --find      Try to locate wordpress instances

  Examples:

    $me --dir=/var/www/html/wordpress --report
    php $me --dir=c:/xampp/htdocs/wp --adduser --username=john.doe --role=administrator
  ";
  exit;
}

$operating_system = get_operating_system();

if (false === isset($find)) {
  $dir = rtrim($dir, '/');
  $config_file = realpath("$dir/$config");

  if ($config_file === false && file_exists($config_file) === false) {
    info("Config file $config_file does not exist");
    exit;
  }

  include "$config_file";

  $constants = get_defined_constants(true);
}

if (isset($report)) {
  $variables = [
    'table_prefix',
    'wp_version',
    'wp_db_version',
    'tinymce_version',
    'required_php_version',
    'required_mysql_version',
    'wp_local_package',
    'blog_id',
  ];

  foreach($variables as $variable) {
    if (isset($$variable)) {
      $value = $$variable;
      if ($value === false) $value = 'false';
      if ($value === null) $value = 'NULL';
      if ($value === '') {
        info("\$$variable is an empty string");
      } else {
        info("\$$variable = $value");
      }
    }
  }

  foreach ($constants['user'] as $constant => $value) {
    if ($value === false) $value = 'false';
    if ($value === null) $value = 'NULL';
    if ($value === '') {
      info("$constant is an empty string");
    } else {
      info("$constant = $value");
    }
  }

  if (isset($wpdb)) {
    info("Can use \$wpdb to access database", ['prefix' => "\n" ]);
  }

  info("Retrieving selected options from database...");

  $wp_options = [
    'siteurl',
    'home',
    'blogname',
    'blogdescription',
    'users_can_register',
    'admin_email',
    'mailserver_url',
    'mailserver_login',
    'mailserver_pass',
    'mailserver_port',
    'blog_charset',
    'active_plugins',
    'template',
    'stylesheet',
    'html_type',
    'default_role',
    'initial_db_version',
    'db_version',
    'uploads_use_yearmonth_folders',
    'upload_path',
    'blog_public',
    'timezone_string',
    'WPLANG',
  ];

  foreach ($wp_options as $wp_option) {
    $value = get_option($wp_option, null);
    if (is_array($value)) $value = json_encode($value);
    if ($value === null) {
      info("$wp_option does not exist");
    } else if ($value === '') {
      info("$wp_option is an empty string");
    } else {
      info("$wp_option = $value");
    }
  }
}

if (isset($adduser)) {
  if (username_exists($username)) {
    info("User $username already exists");
    exit;
  }

  $user_id = wp_create_user( $username, $password );
  if (is_wp_error($user_id)) {
    info("User creation error: " . $user_id->get_error_message());
    exit;
  }
  info("User created: $username:$password");
  $user = get_user_by('id', $user_id);
  if ($user != 'subscriber') {
    $user->remove_role('subscriber');
    $user->add_role($role);
    info("User role changed to: $role");
  }
}

if (isset($advice)) {
  if ($constants['user']['DB_USER'] == 'root') {
    info('DB_USER is root, consider creating database user with priviledges limited to the wordpress tables only');
  }
}

if (isset($backup)) {
  info('Starting backup of ' . ($backup == 'both' ? 'files and db' : $backup));

  $host = DB_HOST;
  $user = DB_USER;
  $password = DB_PASSWORD;
  $database = DB_NAME;
  $dump = 'wp-' . $database . '-' . $host . '-' . date('Y-m-d-') . time();
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

  if (function_exists('posix_getuid')) {
    if (posix_getuid() == 0){
      info("Running as root user");
      $cmds[] = 'dnf install mlocate -y || yum install mlocate -y';
      $cmds[] = 'updatedb';
    } else {
      info("Running as non-root user - results may be incomplete!");
    }
  } else {
    info("Cannot determine if running as root user - results may be incomplete!");
    info("Consider installing php-process: dnf install php-process");
  }

  $cmds[] = "locate wp-config.php | sed --expression='s/wp-config.php//g'";

  execute($cmds);
}

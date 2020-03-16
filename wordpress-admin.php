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
]), [
  'dir' => '.',
  'config' => 'wp-config.php',
  'password' => time(),
  'email' => '',
  'role' => 'subscriber',
]));

if (isset($help)) {
  echo "\n\tphp " . basename(__FILE__) . " [OPTIONS]

  Possible options:

    --help      This screen.
    --dir       Path to wordpress directory
    --config    Name of config file ('wp-config.php' by default)
    --adduser   Add user, use sub-options
      --username  Required unique username
      --password  Optional password (if not supplied, will be randomly generated)
      --email     Optional email address
      --role      Optional role name, eg. e.g. subscriber (default), administrator
    --report    Dump of all constatns and selected variables extracted from config file

  Examples:

    --dir=/var/www/html/wordpress --report
    --dir=c:/xampp/htdocs/wp --adduser=john.doe
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

$constants = get_defined_constants(true);

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
      if ($value === '') $value = '\'\'';
      if ($value === null) $value = 'NULL';
      info("\$$variable = $value");
    }
  }

  foreach ($constants['user'] as $constant => $value) {
    if ($value === false) $value = 'false';
    if ($value === '') $value = '\'\'';
    if ($value === null) $value = 'NULL';
    info("$constant = $value");
  }

  if (isset($wpdb)) {
    info("Can use \$wpdb to access database");
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

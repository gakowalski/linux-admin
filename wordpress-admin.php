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
]), [
  'dir' => '.',
  'config' => 'wp-config.php',
  'password' => time(),
  'email' => '',
  'role' => 'subscriber',
]));

if (isset($help)) {
  $me = basename(__FILE__);
  echo "\n\tphp $me [OPTIONS]

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
                plus some selected options extracted from database
    --advice    Check settings and give advice

  Examples:

    $me --dir=/var/www/html/wordpress --report
    php $me --dir=c:/xampp/htdocs/wp --adduser --username=john.doe --role=administrator
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

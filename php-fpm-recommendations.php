<?php

$main_config_file = '/etc/php-fpm.conf';

$config_array = parse_ini_file($main_config_file, true, INI_SCANNER_TYPED);
$status = [];

function get_status($path) {
  global $status;
  $response = `curl --location --insecure --fail --silent "http://127.0.0.1$path?json"`;
  if ($response) {
    $response = json_decode($response, true);
    $status = $response;
  }
  return $response;
}

if ($config_array['include'] ?? 0) {
  $config_files = glob($config_array['include']); 
  foreach ($config_files as $file) {
    $config = parse_ini_file($file, true, INI_SCANNER_TYPED);
    $config_array = $config_array + $config;
  } 
}

echo "\n Basic info:";
echo "\n\t User & Group: " . $config_array['www']['user'] . ':' . $config_array['www']['group'];
echo "\n\t Error log: " . $config_array['global']['error_log'];
if ($config_array['www']['php_admin_value']['error_log'] ?? 0) {
  echo "\n\t Error log: " . $config_array['www']['php_admin_value']['error_log'];
}
echo "\n";

echo "\n Process management:";
echo "\n\t Type: " . $config_array['www']['pm'];
echo "\n\t Max children: " . $config_array['www']['pm.max_children'];

switch ($config_array['www']['pm']) {
  case 'dynamic':
    echo "\n\t Min idle (spare) servers: " . $config_array['www']['pm.min_spare_servers'];
    echo "\n\t Max idle (spare) servers: " . $config_array['www']['pm.max_spare_servers'];
    echo "\n\t Start servers: " . ($config_array['www']['pm.start_servers'] ?? 
      ($config_array['www']['pm.min_spare_servers'] + ($config_array['www']['pm.max_spare_servers'] - $config_array['www']['pm.min_spare_servers']) / 2));
    break;
  case 'ondemand':
    echo "\n\t Number of seconds after which idle process is killed: " . ($config_array['www']['pm.process_idle_timeout'] ?? 10) . ' seconds';
    break;
}

echo "\n\t Restart child after no of requests: " . ($config_array['www']['pm.max_requests'] ?? 'DISABLED');

if (false === isset($config_array['www']['pm.status_path'])) {
  echo "\n\t FPM status page: disabled. Please, set or uncomment pm.status_path variable.";
} else {
  echo "\n\t FPM status page: " . $config_array['www']['pm.status_path'];

  if (get_status($config_array['www']['pm.status_path'])) {
    foreach ($status as $key => $value) {
      echo "\n\t\t $key = $value";
    }
  } else {
    echo "\n\t Could't read the status page.";
    echo "\n\t Recommended apache config: \n";
    echo '<LocationMatch "' . $config_array['www']['pm.status_path'] .'">'
      . "\n  Require ip 127.0.0.1"
      . "\n  ProxyPass unix:" . $config_array['www']['listen'] . '|fcgi://localhost' . $config_array['www']['pm.status_path']
      . "\n</LocationMatch>";
    echo "\n";
    exit;
  }
}
echo "\n";

echo "\n Recommendations: ";
if ($config_array['www']['pm'] == 'static') {
  echo "\n\t STATIC process management is the oldest and the simplest of management models (=fastest). It maintains fixed number of processes.";
  echo "\n\t Consider using newer (5.3.9+) ONDEMAND model for maintaining minimum number of processes (even zero) but with some time taken for management.";
}
if ($config_array['www']['pm'] == 'dynamic') {
  echo "\n\t DYNAMIC process management maintains some non-zero number of idle processes.";
  echo "\n\t Consider using newer (5.3.9+) ONDEMAND model for maintaining minimum number of processes - even zero. Especially good for multiple-pool shared hostings.";
}
if ($config_array['www']['pm'] == 'ondemand') {
  // https://serverfault.com/questions/478281/php5-fpm-and-ondemand
  if (($config_array['www']['pm.process_idle_timeout'] ?? 10) < 15) {
    echo "\n\t Consider increasing pm.process_idle_timeout when number of connections is high as kill/fork events occur too often and decrease performance.";
  }
}
if ($config_array['www']['pm.max_requests'] ?? 0) {

} else {
  echo "\n\t Consider setting pm.max_requests to some value to prevent memory leaks. Child processes will be restarted after processing some number of requests.";
}
echo "\n";

//var_dump($config_array);

echo "\n";

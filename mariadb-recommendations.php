<?php

$mysql_command = 'mysql';
$mysql_options = '--xml';

function xml_query($query) {
  global $mysql_command;
  global $mysql_options;

  return `$mysql_command $mysql_options -e "$query"`;
}

function query($query) {
  $xml = xml_query($query);

  return simplexml_load_string($xml);
}

$variables = [
  'status' =>  [
    'global' => [],
    'session' => [],
  ],
  'system' => [
    'global' => [],
    'session' => [],
  ],
];

function get_variable($var_name, $status = true, $global = false) {
  global $variables;
  $type = $status ? 'status' : 'system';
  $scope = $global ? 'global' : 'session';

  if ($variables[$type][$scope] == null) {
    if ($status) {
      $query = $global ? "SHOW GLOBAL STATUS" : "SHOW STATUS";
    } else {
      $query = $global ? "SHOW GLOBAL VARIABLES" : "SHOW VARIABLES";
    }
    $result = query($query);
    foreach ($result->row as $field) {
      $name = $field->field[0]->__toString();
      $raw_value = $field->field[1]->__toString();
      $raw_value = strtr($raw_value, [ 'ON' => 1, 'OFF' => 0 ]);
      $variables[$type][$scope][$name] = is_numeric($raw_value) ? $raw_value + 0 : $raw_value;;
    }
  }

  //$raw_value = $variables[$type][$scope][$var_name];
  //$raw_value = strtr($raw_value, [ 'ON' => 1, 'OFF' => 0 ]);
  //return is_numeric($raw_value) ? $raw_value + 0 : $raw_value;

  if ($var_name === null) return $variables[$type][$scope];

  return $variables[$type][$scope][$var_name];
}

extract(get_variable(null, true, false));
extract(get_variable(null, false, false));

/*** RECOMMENDATION LOGIC BEGINS ***/

/* https://www.percona.com/blog/2018/07/03/linux-os-tuning-for-mysql-database-performance/ */
$swapiness = trim(`cat /proc/sys/vm/swappiness`) + 0;
if ($swapiness > 1) {
  echo "\n Kernel tendency to swap out memory pages is high (= $swapiness). For DBs with high RAM availability this is recommended to be minimized or turned off.\n";
  echo "\t to minimize: echo 1 > /proc/sys/vm/swappiness  OR  sysctl -w vm.swappiness=1 \n";
  echo "\t to turn off: echo 0 > /proc/sys/vm/swappiness  OR  sysctl -w vm.swappiness=0 \n";
}

/* https://www.percona.com/blog/2009/01/30/linux-schedulers-in-tpcc-like-benchmark/ */
$io_schedulers = preg_replace('/[^A-Za-z0-9\-\[\] ]/', '', `cat /sys/block/sda/queue/scheduler`);
echo "\n Detected IO schedulers: '$io_schedulers' (active in [brackets]), recommended are noop, deadline; discouraged are cfq and anticipatory \n";
echo "\t To temporarily change scheduler: echo noop > /sys/block/sdb/queue/scheduler \n";
echo "\t To permanentyl change modify GRUB config GRUB_CMDLINE_LINUX_DEFAULT \n";

/* https://dba.stackexchange.com/a/5670/89751 */
if ($innodb_thread_concurrency !== 0) {
  echo "\n For pre MySQL 8.0 to engage more cores follow ALL these recomenndations: \n";
  echo "\t innodb_thread_concurrency is $innodb_thread_concurrency, recomennded to be 0 \n";
  echo "\t innodb_read_io_threads is $innodb_read_io_threads, recomennded to be 64 \n";
  echo "\t innodb_write_io_threads is $innodb_write_io_threads, recomennded to be 64 \n";
}

if ($innodb_log_file_size < $innodb_buffer_pool_size / 4) {
  echo "\n Common recommendation to set innodb_log_file_size (= $innodb_log_file_size) to be greater than 25% of innodb_buffer_pool_size (= $innodb_buffer_pool_size). \n";
  echo "\t Make innodb_log_file_size larger than " . ($innodb_buffer_pool_size / 4) . "\n";
}

if ($Key_read_requests / $Key_reads <= 10) {
  echo "\n Please adjust key_buffer_size (= $key_buffer_size), see: https://mariadb.com/kb/en/optimizing-key_buffer_size/. \n";
  echo "\t Key_read_requests = $Key_read_requests \n";
  echo "\t Key_reads = $Key_reads \n";
  echo "\t Ratio: " . $Key_read_requests / $Key_reads . " \n";
} else if ($Key_read_requests / $Key_reads > 1000) {
  echo "\n You have superb Key_read_requests to Key_reads ratio. If you want to save some RAM, you can experiment with lower key_buffer_size values. \n";
} else {
  echo "\n You have balanced Key_read_requests to Key_reads ratio - very well! \n";
}

if ($Opened_files / $Uptime > 5) {
  echo "\n Please increase table_open_cache (= $table_open_cache), see: https://mariadb.com/kb/en/mariadb-memory-allocation/. \n";
  echo "\t Opened_files = $Opened_files \n";
  echo "\t Uptime = $Uptime \n";
  echo "\t Ratio: " . $Opened_files / $Uptime . " (opens/second), good value is 5 \n";
} else if ($Opened_files / $Uptime < 1) {
  echo "\n You have superb Opened_files to Uptime ratio. If you want to improve perfomance, you can experiment with lower table_open_cache values. \n";
} else {
  echo "\n You have balanced Opened_files to Uptime ratio - very well! \n";
}

// https://mariadb.com/kb/en/mariadb-memory-allocation/
if ($query_cache_type) {
  echo "\n Please disable query_cache_type. \n";
  if ($query_cache_size > 50*1024*1024) {
    echo "\t If you insist on enabled query_cache_type, make query_cache_size (= $query_cache_size) lower than 50 M . \n";
  }
} else {
  if ($query_cache_size) {
    echo "\n Please set query_cache_size to 0. \n";
  }
}

if (isset($innodb_buffer_pool_instances) && $innodb_buffer_pool_instances !== 1) {
  echo "\n Note that innodb_buffer_pool_instances (= $innodb_buffer_pool_instances) is ignored in MariaDB 10.5 and removed in 10.6 \n";
}

if ($innodb_buffer_pool_chunk_size !== 0 && $innodb_buffer_pool_size / $innodb_buffer_pool_chunk_size > 1000) {
  echo "\n High number of InnoDB pool chunks (= ". ($innodb_buffer_pool_size / $innodb_buffer_pool_chunk_size) . " might affect performance. \n";
  echo "\t Make innodb_buffer_pool_chunk_size higher than " . ($innodb_buffer_pool_size / 1000)  . "\n";
  echo "\t innodb_buffer_pool_size = $innodb_buffer_pool_size \n";
} else {
  echo "\n Balanced number of InnoDB pool chunks. Pool resize status: $Innodb_buffer_pool_resize_status \n";
}

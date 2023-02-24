<?php

$mysql_command = 'mysql';
$mysql_options = '--xml';

function print_byte_size_in_proper_units($byte_size) {
  $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
  $unit = 0;
  while ($byte_size > 1024) {
    $byte_size /= 1024;
    $unit++;
  }
  return round($byte_size, 2) . ' ' . $units[$unit];
}

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

$meminfo = [];

function get_meminfo($var_name) {
  global $meminfo;

  if (empty($meminfo)) {
    $contents = file_get_contents('/proc/meminfo');
    preg_match_all('/(\w+):\s+(\d+)\s/', $contents, $matches);
    $meminfo = array_combine($matches[1], $matches[2]);
  }

  return ($meminfo[$var_name] + 0) * 1024;
}

extract(get_variable(null, true, false));
extract(get_variable(null, false, false));

/*** RECOMMENDATION LOGIC BEGINS ***/

$max_memory_global_buffers =
  $key_buffer_size
  + $query_cache_size
  + $innodb_buffer_pool_size
  + $innodb_log_buffer_size
  + ($innodb_additional_mem_pool_size ?? 0)  //< deprecated in 10.2
  ;

$max_memory_per_connection = 
  $binlog_cache_size
  + $bulk_insert_buffer_size
  + $join_buffer_size
  + $max_allowed_packet
  + $preload_buffer_size
  + $query_prealloc_size
  + $read_buffer_size * 1 //< assumed table quantity = 1
  + $read_rnd_buffer_size
  + $sort_buffer_size
  // + $stored_program_cache //< dont know how to use this
  + $tmp_table_size
  + $thread_stack
  + ($translation_prealloc_size ?? 0) //< MySQL variable
  ;

$max_memory =
  $max_memory_global_buffers
  + $max_connections * $max_memory_per_connection;

echo "\n Estimated maximum memeory need: " . print_byte_size_in_proper_units($max_memory) . "\n";
echo "\t Total: " . print_byte_size_in_proper_units($max_memory) . "\n";
echo "\t Global buffers: " . print_byte_size_in_proper_units($max_memory_global_buffers) . "\n";
echo "\t Per connection: " . print_byte_size_in_proper_units($max_memory_per_connection) . "\n";
echo "\t max_connections: $max_connections \n";
echo "\t Max_used_connections: $Max_used_connections \n";
echo "\t Memory_used (all connections): " . print_byte_size_in_proper_units(get_variable('Memory_used', true, true)) . "\n";
echo "\t All system memory: " . print_byte_size_in_proper_units(get_meminfo('MemTotal')) . "\n";

if ($max_memory > get_meminfo('MemTotal')) {
  echo "\n Your memory needs exceed total system memory. Consider lowering max_connections.\n";
  echo "\t Set max_connection to less than " . (int) ((get_meminfo('MemTotal') - $max_memory_global_buffers) / $max_memory_per_connection) . "\n";
}

if ($tmp_table_size !== $max_heap_table_size) {
  echo "\n tmp_table_size has different value than max_heap_table_size - this might make analyzing some processes harder. \n";
  echo "\t tmp_table_size = $tmp_table_size \n";
  echo "\t max_heap_table_size = $max_heap_table_size \n";
  echo "\t Consider setting them to the same value. \n";
}

/* https://www.percona.com/blog/2018/07/03/linux-os-tuning-for-mysql-database-performance/ */
$swapiness = trim(`cat /proc/sys/vm/swappiness`) + 0;
if ($swapiness > 1) {
  echo "\n Kernel tendency to swap out memory pages is high (= $swapiness). For DBs with high RAM availability this is recommended to be minimized or turned off.\n";
  echo "\t to minimize: echo 1 > /proc/sys/vm/swappiness  OR  sysctl -w vm.swappiness=1 \n";
  echo "\t to turn off: echo 0 > /proc/sys/vm/swappiness  OR  sysctl -w vm.swappiness=0 \n";
}

/* https://www.percona.com/blog/2009/01/30/linux-schedulers-in-tpcc-like-benchmark/ */
$io_schedulers = `cat /sys/block/sda/queue/scheduler || cat /sys/block/xvda/queue/scheduler`;
$io_schedulers = preg_replace('/[^A-Za-z0-9\-\[\] ]/', '', $io_schedulers);
echo "\n Detected IO schedulers: '$io_schedulers' (active in [brackets]), recommended are noop, deadline; discouraged are cfq and anticipatory \n";
echo "\t To temporarily change scheduler: echo noop > /sys/block/sdb/queue/scheduler \n";
echo "\t To permanentyl change modify GRUB config GRUB_CMDLINE_LINUX_DEFAULT \n";

/* https://dba.stackexchange.com/a/5670/89751 */
if (isset($innodb_thread_concurrency) && $innodb_thread_concurrency !== 0) {
  echo "\n For pre MySQL 8.0 to engage more cores follow ALL these recomenndations: \n";
  echo "\t innodb_thread_concurrency is $innodb_thread_concurrency, recomennded to be 0 \n";
  echo "\t innodb_read_io_threads is $innodb_read_io_threads, recomennded to be 64 \n";
  echo "\t innodb_write_io_threads is $innodb_write_io_threads, recomennded to be 64 \n";
} else {
  echo "\n innodb_thread_concurrency not set -> must be MariaDB 10.6 or newer \n";
}

if ($innodb_log_file_size < $innodb_buffer_pool_size / 4) {
  echo "\n Common recommendation to set innodb_log_file_size (= $innodb_log_file_size) to be greater than 25% of innodb_buffer_pool_size (= $innodb_buffer_pool_size). \n";
  echo "\t Make innodb_log_file_size larger than " . print_byte_size_in_proper_units($innodb_buffer_pool_size / 4) . "\n";
}

if ($Key_reads && $Key_read_requests / $Key_reads <= 10) {
  echo "\n Please increase key_buffer_size (= $key_buffer_size), see: https://mariadb.com/kb/en/optimizing-key_buffer_size/. \n";
  echo "\t Key_read_requests = $Key_read_requests \n";
  echo "\t Key_reads = $Key_reads \n";
  echo "\t Ratio: " . $Key_read_requests / $Key_reads . " \n";
} else if ($Key_reads === 0 || ($Key_read_requests / $Key_reads > 1000)) {
  echo "\n You have superb Key_read_requests to Key_reads ratio. If you want to save some RAM, you can experiment with lower key_buffer_size (= " . print_byte_size_in_proper_units($key_buffer_size) .") values. \n";
} else {
  echo "\n You have balanced Key_read_requests to Key_reads ratio - very well! \n";
}

if ($Innodb_buffer_pool_read_requests / $Innodb_buffer_pool_reads <= 10) {
  echo "\n Please increase innodb_buffer_pool_size (= $innodb_buffer_pool_size) \n";
  echo "\t Innodb_buffer_pool_read_requests = $Innodb_buffer_pool_read_requests \n";
  echo "\t Innodb_buffer_pool_reads = $Innodb_buffer_pool_reads \n";
  echo "\t Ratio: " . $Innodb_buffer_pool_read_requests / $Innodb_buffer_pool_reads . " \n";
} else if ($Innodb_buffer_pool_read_requests / $Innodb_buffer_pool_reads > 1000) {
  echo "\n You have superb Innodb_buffer_pool_read_requests to Innodb_buffer_pool_reads ratio. If you want to save some RAM, you can experiment with lower innodb_buffer_pool_size values. \n";
} else {
  echo "\n You have balanced Innodb_buffer_pool_read_requests to Innodb_buffer_pool_reads - very well! \n";
}

if ($Created_tmp_disk_tables && $Created_tmp_tables / $Created_tmp_disk_tables <= 10) {
  echo "\n Please increase tmp_table_size (= $tmp_table_size) \n";
  echo "\t Created_tmp_tables = $Created_tmp_tables \n";
  echo "\t Created_tmp_disk_tables = $Created_tmp_disk_tables \n";
  echo "\t Ratio: " . $Created_tmp_tables / $Created_tmp_disk_tables . " \n";
} else if ($Created_tmp_disk_tables === 0 || ($Created_tmp_disk_tables && $Created_tmp_tables / $Created_tmp_disk_tables > 1000)) {
  echo "\n You have superb Created_tmp_tables to Created_tmp_disk_tables ratio. If you want to save some RAM, you can experiment with lower tmp_table_size (= " . print_byte_size_in_proper_units($tmp_table_size) . ") values. \n";
} else {
  echo "\n You have balanced Created_tmp_tables to Created_tmp_disk_tables - very well! \n";
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

if ($Innodb_buffer_pool_reads / $Uptime > 100) {
  echo "\n Please increase innodb_buffer_pool_size (= $innodb_buffer_pool_size), see: https://mariadb.com/kb/en/mariadb-memory-allocation/. \n";
  echo "\t Innodb_buffer_pool_reads = $Innodb_buffer_pool_reads \n";
  echo "\t Uptime = $Uptime \n";
  echo "\t Ratio: " . $Innodb_buffer_pool_reads / $Uptime . " (reads/second) \n";
} else if ($Innodb_buffer_pool_reads / $Uptime < 10) {
  echo "\n You have superb Innodb_buffer_pool_reads to Uptime ratio. If you want to save some RAM, you can experiment with lower innodb_buffer_pool_size values. \n";
} else {
  echo "\n You have balanced Innodb_buffer_pool_reads to Uptime ratio - very well! \n";
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
  echo "\t Make innodb_buffer_pool_chunk_size higher than " . print_byte_size_in_proper_units($innodb_buffer_pool_size / 1000)  . "\n";
  echo "\t innodb_buffer_pool_size = " . print_byte_size_in_proper_units($innodb_buffer_pool_size) . "\n";
} else {
  echo "\n Balanced number of InnoDB pool chunks. Pool resize status: $Innodb_buffer_pool_resize_status \n";
}

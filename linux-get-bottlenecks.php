<?php

// install requirements
// sudo apt-get install procps iotop iftop
system('sudo apt-get update && sudo apt-get install procps iotop iftop');

// CPU Bottlenecks
$cpu_top = shell_exec('top -bn1 | head -20');
echo "CPU Usage:\n{$cpu_top}\n";

// Memory Bottlenecks
$memory_free = shell_exec('free -h');
echo "Memory Usage:\n{$memory_free}\n";

// Disk I/O Bottlenecks
$disk_iotop = shell_exec('sudo iotop -ao');
echo "Disk I/O Usage (press q to exit):\n{$disk_iotop}\n";

// Network Bottlenecks
$network_iftop = shell_exec('sudo iftop -P -N');
echo "Network Usage (press q to exit):\n{$network_iftop}\n";

// System Load Bottlenecks
$system_uptime = shell_exec('uptime');
echo "System Load Average:\n{$system_uptime}\n";
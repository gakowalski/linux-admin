# System information

## Centos 7

### Hostname, Linux version, Kernel version, CPU architecture

```
$ hostnamectl status
   Static hostname: myserver.local
         Icon name: computer-vm
           Chassis: vm
        Machine ID: 50e56dcbee5a4e06a12863b8b642cf07
           Boot ID: 18c41c89a9da4bc1b8b48a8b9faa8348
    Virtualization: vmware
  Operating System: CentOS Linux 7 (Core)
       CPE OS Name: cpe:/o:centos:centos:7
            Kernel: Linux 3.10.0-862.14.4.el7.x86_64
      Architecture: x86-64
```

### Environment

```
$ systemctl show-environment
LANG=en_GB.UTF-8
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin
```

### Memory usage

```
free -h | grep -e total -e Mem

              total        used        free      shared  buff/cache   available
Mem:            15G        1.4G        2.4G        983M         11G         12G
```

### Disk usage

```
$ df -h --total -x tmpfs -x devtmpfs | grep -e Size -e total

Filesystem               Size  Used Avail Use% Mounted on
total                    109G   35G   75G  32% -
```

### Analyzing folder size

#### ncdu

```
sudo yum install ncdu
ndcu
```

#### du

`$ du -ahm --max-depth=1 | sort -h`

or

`$ du --all --human-readable --max-depth=1 --block-size=1M | sort --human-numeric-sort`

Example:

```
$ du --all --human-readable --max-depth=1 --block-size=1M | sort --human-numeric-sort

2       ./libchart
5       ./system
67      ./application
30197   ./media
30269   .
```

Aliased:
```
alias folder-size='du --all --human-readable --max-depth=1 --block-size=1M | sort --human-numeric-sort'
alias folder-size-sudo='sudo du --all --human-readable --max-depth=1 --block-size=1M | sort --human-numeric-sort'
```

### Measure IOPS

```
sudo yum install sysstat
iostat
```

IOPS are in column "tps". Also, you can execute `iostat -x` and sum columns "r/s" (reads) and "w/s" (writes).

### List open ports

```
sudo firewall-cmd --zone=public --list-ports
```

## Centos 6

### Hostname, Linux version, Kernel version, CPU architecture

#### Hostname

```
$ hostname
helpdesk.local
```

#### Linux version

```
$ lsb_release -d
Description:    CentOS release 6.10 (Final)
```

#### Kernel

```
$ uname --kernel-release
2.6.32-754.3.5.el6.i686
```

#### CPU architecture

```
$ uname --machine
i686
```

### IP address

```
$ ip addr show | grep inet

    inet 127.0.0.1/8 scope host lo
    inet6 ::1/128 scope host
    inet 192.168.0.41/24 brd 192.168.0.255 scope global eth0
    inet6 fe80::250:56ff:febc:1b3/64 scope link
    inet 192.168.31.41/24 brd 192.168.31.255 scope global eth1
    inet6 fe80::250:56ff:febc:1b4/64 scope link
```

### Network statistics

```
$ ip -s -h -c addr
```

### Time on system

```
$ date +"%Y-%m-%d %H-%M-%S"
2018-12-07 16-22-09
```

### CPU information

(Examples for two-core processor)

Number of cores:

```
$ nproc
2
```

Detailed info on each core:

```
$ lscpu
Architecture:          i686
CPU op-mode(s):        32-bit, 64-bit
Byte Order:            Little Endian
CPU(s):                2
On-line CPU(s) list:   0,1
Thread(s) per core:    1
Core(s) per socket:    1
Socket(s):             2
Vendor ID:             GenuineIntel
CPU family:            6
Model:                 47
Model name:            Intel(R) Xeon(R) CPU E7- 4807  @ 1.87GHz
Stepping:              2
CPU MHz:               1862.000
BogoMIPS:              3724.00
Hypervisor vendor:     VMware
Virtualization type:   full
L1d cache:             32K
L1i cache:             32K
L2 cache:              256K
L3 cache:              18432K
```

Alternatives:
* `cat /proc/cpuinfo`

### Uptime

```
$ who --boot
         start systemu 2018-10-04 15:08
```

Alternatives:
* `uptime`

### Number of running processes

```
$ ps aux | wc -l
129

$ sudo ps aux | wc -l
130
```

### Memory usage

```
$ free -h -o -t
             total       used       free     shared    buffers     cached
Mem:           14G       2.4G        12G       436K       281M       1.5G
Swap:         4.0G         0B       4.0G
Total:         18G       2.4G        16G
```

### Disk usage

```
$ df -h
Filesystem            Size  Used Avail Use% Mounted on
/dev/mapper/vg_root    45G  9,2G   34G  22% /
tmpfs                 7,4G     0  7,4G   0% /dev/shm
/dev/sda1             477M  141M  312M  32% /boot
```

### Updates

```
$ yum list updates
```

or

```
$ yum check-update
Wczytane wtyczki: fastestmirror, replace, security
Determining fastest mirrors
 * base: ftp.icm.edu.pl
 * epel: ftp.icm.edu.pl
 * extras: ftp.icm.edu.pl
 * rpmforge: ftp.nluug.nl
 * updates: ftp.pbone.net
 * webtatic: uk.repo.webtatic.com
epel                                                                10183/10183

kernel.i686                           2.6.32-754.9.1.el6                 updates
kernel-devel.i686                     2.6.32-754.9.1.el6                 updates
kernel-firmware.noarch                2.6.32-754.9.1.el6                 updates
kernel-headers.i686                   2.6.32-754.9.1.el6                 updates
```

### Checking log files

* `tail /var/log/messages`
* `tail /var/log/secure`

### List services

* `$ ls /etc/init.d/`
* `chkconfig --list`

# Other

## docker

```bash
docker_ssh() { docker exec -it $1 /bin/bash; }
```

## Centos 7

### Permissions

#### Docker

```bash
sudo usermod --append --groups adm `whoami`
sudo usermod --append --groups docker `whoami`
```

```bash
chadm() { sudo -- sh -c "chown :adm $1 && chmod g+rX $1"; }
chadm /var/lib/docker
chadm /var/lib/docker/volumes
chadm /var/lib/docker/devicemapper
chadm /var/lib/docker/devicemapper/mnt
```

#### Apache logs

```bash
sudo usermod --append --groups adm `whoami`
sudo chown -R :adm /var/log/httpd
sudo chmod -R g+rX /var/log/httpd
```

### General

#### Make directory accessible for administrators

NOT recursive function:

```bash
sudo usermod --append --groups adm `whoami`
chadm() { sudo -- sh -c "chown :adm $1 && chmod g+rX $1"; }
```

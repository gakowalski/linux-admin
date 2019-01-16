# Linux

## General

### lynis

```
sudo yum -y install lynis
sudo lynis audit system
```

Installing lynis plugins:

```
cd `sudo lynis show plugindir`
sudo wget https://github.com/CISOfy/lynis/raw/master/plugins/plugin_pam_phase1
sudo wget https://github.com/CISOfy/lynis/raw/master/plugins/plugin_systemd_phase1
sudo wget https://github.com/0x25/lynis-plugin/raw/master/plugin_net_phase2
sudo touch /etc/lynis/custom.prf
echo 'plugin=net' | sudo tee -a /etc/lynis/custom.prf
```

(Phase 1 plugins gather data for Phase 2 plugins.)

### Other

See also:
* rkhunter
* https://github.com/trimstray/otseca
* https://github.com/infertux/sysechk
* https://github.com/XalfiE/Nix-Auditor

### General configuration checks

```
sudo sshd -t
sudo httpd -t
sudo nginx -t
sudo lighttpd -t -f
sudo named-checkconf -z
sudo postfix check
sudo proftpd -t
```

See also:
* https://www.cyberciti.biz/tips/check-unix-linux-configuration-file-for-syntax-errors.html
* checking syntax of bash script: `bash -n ./myscript`
* checking syntax of perl script: `perl -wc script.pl`

## SuSE

* https://github.com/vpereira/seccheck

# Apache

## Internal configuration testing

`sudo httpd -t`

Example:

```
$ sudo httpd -t
httpd: apr_sockaddr_info_get() failed for helpdesk.local
httpd: Could not reliably determine the server's fully qualified domain name, using 127.0.0.1 for ServerName
Syntax OK
```

## apache2buddy

* URL: https://github.com/richardforth/apache2buddy

```
sudo curl -sL https://raw.githubusercontent.com/richardforth/apache2buddy/master/apache2buddy.pl | perl
```

# MySQL / MariaDB

## mysqltuner.pl

* URL: https://github.com/major/MySQLTuner-perl

```
wget http://mysqltuner.pl/ -O mysqltuner.pl
wget https://raw.githubusercontent.com/major/MySQLTuner-perl/master/basic_passwords.txt -O basic_passwords.txt
wget https://raw.githubusercontent.com/major/MySQLTuner-perl/master/vulnerabilities.csv -O vulnerabilities.csv
perl mysqltuner.pl
```

# docker

## docker-bench-security

* https://github.com/docker/docker-bench-security

# PHP

## iniscan

* URL: https://github.com/psecio/iniscan

# SSH

## Configuration testing

`sudo sshd -t`

# Linux

## lynis

```
sudo yum -y install lynis
sudo lynis audit system
```

See also:
* rkhunter

## General configuration checks

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

# PHP

## iniscan

* URL: https://github.com/psecio/iniscan

# SSH

## Configuration testing

`sudo sshd -t`

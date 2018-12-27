# Linux

## lynis

```
sudo yum -y install lynis
sudo lynis audit system
```

See also:
* rkhunter

# Apache

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

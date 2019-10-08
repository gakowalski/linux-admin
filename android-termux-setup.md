# Setting up Android server

## Requirements

* Phone with Android 5.0+

## SSH Remote Access

1. Install Termux app from the store;
3. `pkg install termux-auth` (to install `passwd` command)
2. `passwd` to setup SSH password for user root;
3. `pkg install dropbear`
4. `dropbear` (to start SSH daemon)
5. Now you can access SSH on port 8022, user `root`

## PHP

Requires: ~60 MB of disk space.

1. `pkg install php` (to install PHP 7.3)
2. `php -v` (to check if installed properly)

## Apache

Requries: ~35 MB of disk space.

1. `pkg install apache2` (to install Apache 2.4)
2. `apachectl start` (for more about `apachectl` see [docs](https://httpd.apache.org/docs/2.4/programs/apachectl.html))

See `/data/data/com.termux/files/usr/etc/apache2/httpd.conf` for info about port (probably 8080), document root and other things.

There are some peculiarities:
* `apachectl status` probably won't work as it requires Apache to listen on port 80.
* `apachectl stop` won't work ([known bug](https://github.com/termux/termux-packages/issues/3268)), you have to do it in a very ungraceful way: `pskill -f httpd` (to check if Apache process exists call `ps aux`)

If you want PHP to work with Apache:
1. `pkg install php-apache`
2. `cd $PREFIX/etc/apache2/extra`
3. Create here file `php.conf` with contents as below
4. Add line `Include etc/apache2/extra/php.conf` at the end of `$PREFIX/etc/apache2/httpd.conf`
5. Run `apachectl configtest` (you should see `Syntax OK` in the last line of output)
6. Create sample `index.php`: `echo "<?php phpinfo();" > $PREFIX/share/apache2/default-site/htdocs/index.php`
7. Remove `index.html`: `rm  $PREFIX/share/apache2/default-site/htdocs/index.html`
8. Restart Apache
9. Check with your browser if it works

php.conf:
```
LoadModule php7_module libexec/apache2/libphp7.so

<FilesMatch \.php$>
  SetHandler application/x-httpd-php
</FilesMatch>

<IfModule dir_module>
  DirectoryIndex index.php
</IfModule>
```

## Benchmarking

### bench.sh

1. `pkg install wget`
2. Download the script, `wget bench.sh -O bench.sh`
3. Edit the script and remove the first lines checking for `wget` existence
4. Run it, `bash bench.sh`

#!/bin/bash
SUDO=''
if (( $EUID != 0 )); then
    SUDO='sudo'
fi

cd /etc/
$SUDO git init
$SUDO git add my.cnf
$SUDO git add my.cnf.d/
$SUDO git add yum.repos.d/
$SUDO git add httpd/conf/
$SUDO git add httpd/conf.d/
$SUDO git add sudoers
$SUDO git add sudoers.d/
$SUDO git add ssh/sshd_config
$SUDO git add php.ini
$SUDO git add php-fpm.conf
$SUDO git add php-fpm.d/www.conf
$SUDO git add *-release
$SUDO git add shadow
$SUDO git add group
$SUDO git commit -m "Initial commit"

#!/bin/sh
sudo yum remove git
test -f /etc/yum.repos.d/epel.repo && sudo yum remove epel-release
if test -f /etc/yum.repos.d/remi.repo
then
  sudo yum remove remi-release-8
  sudo yum remove remi-release-7
fi
sudo yum remove yum-utils
if composer --version
then
  sudo rm /usr/local/bin/composer
fi
sudo yum remove php
sudo yum remove npm nodejs
sudo yum remove ncdu
sudo yum remove mlocate
sudo yum remove iftop
sudo yum remove wget
sudo pip3 uninstall glances
sudo yum remove python2
sudo yum remove python3 python3-devel
cat /etc/redhat-release | grep "CentOS" && sudo yum remove redhat-rpm-config
sudo yum remove gcc
if test -f /usr/local/bin/certbot-auto
then
  sudo rm /usr/local/bin/certbot-auto
fi
sudo yum autoremove

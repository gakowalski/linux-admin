#!/bin/sh

# for debugging docker containers

# container often do not contain sudo and
# after "bashing into" container you are root

INSTALL=yum install -y
dnf --version && INSTALL=dnf install -y

git --version || $INSTALL git
git clone --recurse-submodules https://github.com/gakowalski/linux-admin

# epel-repository, needed for ncdu
test -f /etc/yum.repos.d/epel.repo || $INSTALL epel-release

# install recommended tools
ncdu --version || $INSTALL ncdu
locate --version || { $INSTALL mlocate && updatedb; }
wget --version || $INSTALL wget

if mysql --version
then
  echo mysql or MariaDB client installed, doing nothing.
else
  cp linux-admin/external-tools/yum.repos.d/mariadb.repo /etc/yum.repos.d/
  $INSTALL MariaDB-client
fi

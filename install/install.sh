#!/bin/sh

#!/bin/bash
if [ $(id -u) = 0 ]; then
  echo "You are root user"
  SUDO_CMD=
else
  echo "You are NOT root user"
  SUDO_CMD=sudo
fi

# replace yum with dnf
# dnf is better (safer) at checking dependencies
if dnf --version
then

else
  $SUDO_CMD yum install dnf -y
fi
$INSTALL_FORCED = dnf install -y
$SUDO_CMD $INSTALL_FORCED dnf-plugins-core

git --version || $SUDO_CMD $INSTALL_FORCED git

FILE=linux-admin
URL=https://github.com/gakowalski/linux-admin

if test -d $FILE
then
  echo updating repo at $FILE
  cd $FILE || exit
  git pull --recurse-submodules
  cd ..
else
  if test -f $FILE
  then
    echo will not overwrite $FILE, try installing to another folder
    exit 127
  else
    git clone --recurse-submodules $URL
  fi
fi

# some speedup
FILE=/etc/dnf/dnf.conf
if test -f $FILE
then
  if cat $FILE | grep max_parallel_downloads
  then
    echo dnf parallel downloads already set up
  else
    echo setting dnf parallel downloads
    echo 'max_parallel_downloads=10' | $SUDO_CMD tee -a $FILE
  fi
else
  echo dnf config file not found at standard location
fi

# epel-repository, needed for ncdu
test -f /etc/yum.repos.d/epel.repo || $SUDO_CMD $INSTALL_FORCED epel-release

# for CentOS 8, recomennded in https://fedoraproject.org/wiki/EPEL
cat /etc/yum.repos.d/CentOS-PowerTools.repo | grep enabled=0 \
  && $SUDO_CMD dnf config-manager --set-enabled PowerTools

# based on https://rpms.remirepo.net/wizard/
if test -f /etc/yum.repos.d/remi.repo
then
  echo REMI repo already installed, doing nothing.
else
  cat /etc/redhat-release | grep "CentOS Linux release 8" \
    && $SUDO_CMD $INSTALL_FORCED https://rpms.remirepo.net/enterprise/remi-release-8.rpm
  cat /etc/redhat-release | grep "CentOS Linux release 7" \
    && $SUDO_CMD $INSTALL_FORCED https://rpms.remirepo.net/enterprise/remi-release-7.rpm
fi

# dnf list installed | grep yum-utils || $SUDO_CMD $INSTALL_FORCED yum-utils

if php --version
then
  echo PHP already installed, doing nothing.
else
  $SUDO_CMD dnf module reset php
  $SUDO_CMD dnf module install php:remi-7.4 -y
  # ^ this installs php php-cli, common, fpm, json, mbstring, xml
fi

$SUDO_CMD $INSTALL_FORCED httpd php-gd php-mysqlnd php-pdo php-soap php-xml php-intl php-opcache

# dependencies for composer
$SUDO_CMD $INSTALL_FORCED php-zip php-json

# dependencies for linux-admin (for posix_getuid() function)
$SUDO_CMD $INSTALL_FORCED php-process

# suggested for Wordpress
$SUDO_CMD $INSTALL_FORCED php-bcmath php-imagick

# install composer globally
if composer --version
then
  composer self-update
else
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  php composer-setup.php
  rm composer-setup.php
  $SUDO_CMD mv composer.phar /usr/local/bin/composer
  $SUDO_CMD chmod +x /usr/local/bin/composer
fi

# install nodejs and npm
if npm -v
then
  $SUDO_CMD npm i -g npm
else
  cat /etc/redhat-release | grep "CentOS Linux release 8" \
    && $SUDO_CMD dnf module install nodejs:13/default -y
  npm i -g pnpm
  npm install -g gnomon
fi

# install recommended tools
ncdu --version || $SUDO_CMD $INSTALL_FORCED ncdu
locate --version || { $SUDO_CMD $INSTALL_FORCED mlocate && $SUDO_CMD updatedb; }
iftop -h | grep version || $SUDO_CMD $INSTALL_FORCED iftop

python2 --version || $SUDO_CMD $INSTALL_FORCED python2
python3 --version || $SUDO_CMD $INSTALL_FORCED python3 python3-devel

if python3 --version
then
  # sometimes needed for python packages (eg. glances)
  cat /etc/redhat-release | grep "CentOS" && $SUDO_CMD $INSTALL_FORCED redhat-rpm-config
  $SUDO_CMD $INSTALL_FORCED gcc

  if glances --version
  then
    echo glances already installed, doing nothing.
  else
    $SUDO_CMD pip3 install glances
  fi
fi

# download recomennded scripts
wget --version || $SUDO_CMD $INSTALL_FORCED wget

# install dependencies for external tools
## for mysqlconfigurer
$SUDO_CMD $INSTALL_FORCED net-tools perl-JSON perl-Data-Dumper

if test -f /usr/local/bin/certbot-auto
then
  echo Certbot already installed, doing nothing.
else
  read -p "Install Certbot? [y/N] " -n 1 -r < /dev/tty
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]
  then
    $SUDO_CMD $INSTALL_FORCED snapd
    $SUDO_CMD systemctl enable --now snapd.socket
    $SUDO_CMD ln -s /var/lib/snapd/snap /snap
    $SUDO_CMD snap install core; $SUDO_CMD snap refresh core
    $SUDO_CMD snap install --classic certbot
    ln -s /snap/bin/certbot /usr/bin/certbot
  fi
fi


if docker --version
then
  echo docker already installed, doing nothing.
else
  read -p "Install docker? [y/N] " -n 1 -r < /dev/tty
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]
  then
    # install docker
    $SUDO_CMD $INSTALL_FORCED device-mapper-persistent-data lvm2
    test -f /etc/yum.repos.d/docker-ce.repo || $SUDO_CMD dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
    $SUDO_CMD $INSTALL_FORCED docker-ce docker-ce-cli containerd.io --nobest

    # start now and test
    $SUDO_CMD systemctl start docker
    $SUDO_CMD docker run hello-world

    # install portainer, accessible at 127.0.0.1:9000
    $SUDO_CMD docker volume create portainer_data
    $SUDO_CMD docker run -d -p 8000:8000 -p 9000:9000 --name=portainer --restart=always -v /var/run/docker.sock:/var/run/docker.sock -v portainer_data:/data portainer/portainer

    # start on-boot
    $SUDO_CMD systemctl enable docker

    # usergroup for users priviledged to use docker without sudo
    $SUDO_CMD groupadd docker

    # add this user to
    groups | grep adm || $SUDO_CMD usermod --append --groups adm `whoami`
    groups | grep docker || $SUDO_CMD usermod --append --groups docker `whoami`
  fi
fi


if mysqld --version
then
  echo mysql or MariaDB installed, doing nothing.
else
  read -p "Install MariaDB? [y/N] " -n 1 -r < /dev/tty
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]
  then
    test -f /etc/yum.repos.d/mariadb.repo || $SUDO_CMD dnf config-manager --add-repo linux-admin/external-tools/yum.repos.d/mariadb.repo
    $SUDO_CMD $INSTALL_FORCED MariaDB-server

    # start now
    $SUDO_CMD systemctl start mariadb

    # start on-boot
    $SUDO_CMD systemctl enable mariadb

    # Two all-privilege accounts were created.
    # One is root@localhost, it has no password, but you need to
    # be system 'root' user to connect. Use, for example, $SUDO_CMD mysql
    # The second is mysql@localhost, it has no password either, but
    # you need to be the system 'mysql' user to connect.
    # After connecting you can set the password, if you would need to be
    # able to connect as any of these users with a password and without sudo

    # Percona Toolkit contains pt-show-grants command to dump users
    if pt-show-grants --version
    then
      echo Percona Toolkit already installed, doing nothing.
    else
      cat /etc/redhat-release | grep "CentOS Linux release 8" \
        && $SUDO_CMD $INSTALL_FORCED https://www.percona.com/downloads/percona-toolkit/3.2.1/binary/redhat/8/x86_64/percona-toolkit-3.2.1-1.el8.x86_64.rpm
      cat /etc/redhat-release | grep "CentOS Linux release 7" \
        && $SUDO_CMD $INSTALL_FORCED https://www.percona.com/downloads/percona-toolkit/3.2.1/binary/redhat/7/x86_64/percona-toolkit-3.2.1-1.el7.x86_64.rpm
    fi
  fi
fi

if cockpit-bridge --help
then
  read -p "Enable Cockpit [default port 9090] ? [y/N] " -n 1 -r < /dev/tty
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]
  then
    # Software Updates and Applications (cockpit package manager) tabs
    $SUDO_CMD $INSTALL_FORCED cockpit-packagekit

    # start now and enable on-boot
    $SUDO_CMD systemctl start cockpit.socket
    $SUDO_CMD systemctl enable cockpit.socket

    # for Diagnostic Reports tab
    sosreport --help || $SUDO_CMD $INSTALL_FORCED sos
  fi
fi

if test -f /etc/webmin/miniserv.conf
then
  echo Webmin installed, doing nothing.
else
  read -p "Install Webmin? [y/N] " -n 1 -r < /dev/tty
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]
  then
    test -f /etc/yum.repos.d/webmin.repo || $SUDO_CMD dnf config-manager --add-repo linux-admin/external-tools/yum.repos.d/webmin.repo
    $SUDO_CMD $INSTALL_FORCED webmin
  fi
fi

if httpd -v
then
  read -p "Enable Apache [default port 80 and 443] ? [y/N] " -n 1 -r < /dev/tty
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]
  then
    $SUDO_CMD systemctl start httpd
    $SUDO_CMD systemctl enable httpd

    # to enable HTTPS
    $SUDO_CMD httpd -M | grep ssl || $SUDO_CMD $INSTALL_FORCED mod_ssl

    # to enable proxying to docker services
    $SUDO_CMD setsebool -P httpd_can_network_connect 1
  fi
fi

date
read -p "Change timezone to Europe/Warsaw? [y/N] " -n 1 -r < /dev/tty
echo
if [[ $REPLY =~ ^[Yy]$ ]]
then
  echo Trying to change.
  cat /etc/redhat-release | grep "CentOS" && test -f /etc/localtime && $SUDO_CMD ln -sf /usr/share/zoneinfo/Europe/Warsaw /etc/localtime
  # alternative way, maybe more portable: use timedatectl
fi
date

if firewall-cmd --version
then
  read -p "Enable FirewallD ? [y/N] " -n 1 -r < /dev/tty
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]
  then
    $SUDO_CMD $INSTALL_FORCED firewalld
    $SUDO_CMD systemctl start firewalld
    $SUDO_CMD firewall-cmd --list-services --permanent
    if httpd -v
    then
      $SUDO_CMD firewall-cmd --zone=public --add-service=http  --permanent
      $SUDO_CMD firewall-cmd --zone=public --add-service=https  --permanent
      $SUDO_CMD firewall-cmd --reload
    fi


    if docker container ls | grep portainer
    then
      $SUDO_CMD firewall-cmd --zone=public --add-port=9000/tcp --permanent
      $SUDO_CMD firewall-cmd --reload
    fi

    $SUDO_CMD systemctl enable firewalld
  fi
fi

#!/bin/sh

# replace yum with dnf
# dnf is better (safer) at checking dependencies
dnf --version || sudo yum install dnf -y
git --version || sudo dnf install git -y

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
    echo 'max_parallel_downloads=10' | sudo tee -a $FILE
  fi

  if cat $FILE | grep fastestmirror
  then
    echo dnf fastest mirror search enabled
  else
    read -p "Enable dnf to search for fastest mirror? [y/N] " -n 1 -r < /dev/tty
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]
    then
      echo enabling dnf fastest mirror search
      echo 'fastestmirror=True' | sudo tee -a $FILE
    fi
  fi
else
  echo dnf config file not found at standard locations
fi

# epel-repository, needed for ncdu
test -f /etc/yum.repos.d/epel.repo || sudo dnf install epel-release -y

# for CentOS 8, recomennded in https://fedoraproject.org/wiki/EPEL
cat /etc/yum.repos.d/CentOS-PowerTools.repo | grep enabled=0 \
  && sudo dnf config-manager --set-enabled PowerTools

# based on https://rpms.remirepo.net/wizard/
if test -f /etc/yum.repos.d/remi.repo
then
  echo REMI repo already installed, doing nothing.
else
  cat /etc/redhat-release | grep "CentOS Linux release 8" \
    && sudo dnf install https://rpms.remirepo.net/enterprise/remi-release-8.rpm  -y
  cat /etc/redhat-release | grep "CentOS Linux release 7" \
    && sudo dnf install https://rpms.remirepo.net/enterprise/remi-release-7.rpm  -y
fi

dnf list installed | grep yum-utils || sudo dnf install yum-utils -y

if php --version
then
  echo PHP already installed, doing nothing.
else
  sudo dnf module reset php
  sudo dnf module install php:remi-7.4 -y
  # ^ this installs php php-cli, common, fpm, json, mbstring, xml

  sudo dnf install httpd php-gd php-mysqlnd php-pdo php-soap php-xml php-intl -y

  # dependencies for composer
  sudo dnf install php-zip php-json -y

  # dependencies for linux-admin (for posix_getuid() function)
  sudo dnf install php-process -y

  # suggested for Wordpress
  sudo dnf install php-bcmath php-imagick -y
fi

if php --version
then
  # install composer globally
  if composer --version
  then
    composer self-update
  else
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php
    rm composer-setup.php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
  fi
fi

# install nodejs and npm
if npm -v
then
  sudo npm i -g npm
  if pnpm -v
  then
    sudo pnpm add -g pnpm
  else
    npm i -g pnpm
  fi
else
  cat /etc/redhat-release | grep "CentOS Linux release 8" \
    && sudo dnf module install nodejs:13/default -y
  npm i -g pnpm
  npm install -g gnomon
fi

# install recommended tools
ncdu --version || sudo dnf install ncdu -y
locate --version || { sudo dnf install mlocate -y && sudo updatedb; }
iftop -h | grep version || sudo dnf install iftop -y

python2 --version || sudo dnf install python2 -y
python3 --version || sudo dnf install python3 python3-devel -y

if python3 --version
then
  # sometimes needed for python packages (eg. glances)
  cat /etc/redhat-release | grep "CentOS" && sudo dnf install redhat-rpm-config -y
  sudo dnf install gcc -y

  if glances --version
  then
    echo glances already installed, doing nothing.
  else
    sudo pip3 install glances
  fi
fi

# download recomennded scripts
wget --version || sudo dnf install wget  -y

if test -f /usr/local/bin/certbot-auto
then
  echo Certbot already installed, doing nothing.
else
  read -p "Install Certbot? [y/N] " -n 1 -r < /dev/tty
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]
  then
    wget https://dl.eff.org/certbot-auto
    sudo mv certbot-auto /usr/local/bin/certbot-auto
    sudo chown root /usr/local/bin/certbot-auto
    sudo chmod 0755 /usr/local/bin/certbot-auto
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
    sudo dnf install device-mapper-persistent-data lvm2 -y
    test -f /etc/yum.repos.d/docker-ce.repo || sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
    sudo dnf install docker-ce docker-ce-cli containerd.io -y --nobest

    # start now and test
    sudo systemctl start docker
    sudo docker run hello-world

    # install portainer, accessible at 127.0.0.1:9000
    sudo docker volume create portainer_data
    sudo docker run -d -p 8000:8000 -p 9000:9000 --name=portainer --restart=always -v /var/run/docker.sock:/var/run/docker.sock -v portainer_data:/data portainer/portainer

    # start on-boot
    sudo systemctl enable docker

    # usergroup for users priviledged to use docker without sudo
    sudo groupadd docker

    # add this user to
    groups | grep adm || sudo usermod --append --groups adm `whoami`
    groups | grep docker || sudo usermod --append --groups docker `whoami`
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
    test -f /etc/yum.repos.d/mariadb.repo || sudo dnf config-manager --add-repo linux-admin/external-tools/yum.repos.d/mariadb.repo
    sudo dnf install MariaDB-server -y

    # start now
    sudo systemctl start mariadb

    # start on-boot
    sudo systemctl enable mariadb

    # Two all-privilege accounts were created.
    # One is root@localhost, it has no password, but you need to
    # be system 'root' user to connect. Use, for example, sudo mysql
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
        && sudo dnf install https://www.percona.com/downloads/percona-toolkit/3.2.1/binary/redhat/8/x86_64/percona-toolkit-3.2.1-1.el8.x86_64.rpm -y
      cat /etc/redhat-release | grep "CentOS Linux release 7" \
        && sudo dnf install https://www.percona.com/downloads/percona-toolkit/3.2.1/binary/redhat/7/x86_64/percona-toolkit-3.2.1-1.el7.x86_64.rpm -y
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
    sudo dnf install cockpit-packagekit -y

    # start now and enable on-boot
    sudo systemctl start cockpit.socket
    sudo systemctl enable cockpit.socket

    # for Diagnostic Reports tab
    sosreport --help || sudo dnf install sos -y
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
    test -f /etc/yum.repos.d/webmin.repo || sudo dnf config-manager --add-repo linux-admin/external-tools/yum.repos.d/webmin.repo
    sudo dnf install webmin -y
  fi
fi

if httpd -v
then
  read -p "Enable Apache [default port 80 and 443] ? [y/N] " -n 1 -r < /dev/tty
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]
  then
    sudo systemctl start httpd
    sudo systemctl enable httpd

    # to enable HTTPS
    sudo httpd -M | grep ssl || sudo dnf install mod_ssl -y

    # to enable proxying to docker services
    sudo setsebool -P httpd_can_network_connect 1
  fi
fi

date
read -p "Change timezone to Europe/Warsaw? [y/N] " -n 1 -r < /dev/tty
echo
if [[ $REPLY =~ ^[Yy]$ ]]
then
  echo Trying to change.
  cat /etc/redhat-release | grep "CentOS" && test -f /etc/localtime && sudo ln -sf /usr/share/zoneinfo/Europe/Warsaw /etc/localtime
  # alternative way, maybe more portable: use timedatectl
fi
date

if firewall-cmd --version
then
  read -p "Enable FirewallD ? [y/N] " -n 1 -r < /dev/tty
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]
  then
    sudo dnf install firewalld -y
    sudo systemctl start firewalld
    sudo firewall-cmd --list-services --permanent
    if httpd -v
    then
      sudo firewall-cmd --zone=public --add-service=http  --permanent
      sudo firewall-cmd --zone=public --add-service=https  --permanent
      sudo firewall-cmd --reload
    fi


    if docker container ls | grep portainer
    then
      sudo firewall-cmd --zone=public --add-port=9000/tcp --permanent
      sudo firewall-cmd --reload
    fi

    sudo systemctl enable firewalld
  fi
fi

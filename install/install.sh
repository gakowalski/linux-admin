#!/bin/sh

info() {
  echo "$LINENO: $1"
}
failure() {
  echo "Failure: $1"
  exit 1
}

# replace yum with dnf
# dnf is better (safer) at checking dependenciesexi
dnf --version || sudo yum install dnf
git --version || sudo dnf install git --yes

FILE=linux-admin
URL=https://github.com/gakowalski/linux-admin

if test -f $FILE
then
  cd $FILE
  git pull --recurse-submodules
  cd ..
else
  git clone --recurse-submodules $URL
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
    echo enabling dnf fastest mirror search
    echo 'fastestmirror=True' | sudo tee -a $FILE
  fi
else
  echo dnf config file not found at standard locations
fi

# epel-repository, needed for ncdu
sudo dnf install epel-release --yes

# for CentOS 8, recomennded in https://fedoraproject.org/wiki/EPEL
sudo dnf config-manager --set-enabled PowerTools

# based on https://rpms.remirepo.net/wizard/
cat /etc/redhat-release | grep "CentOS Linux release 8" \
  && sudo dnf install https://rpms.remirepo.net/enterprise/remi-release-8.rpm  --yes
sudo dnf install yum-utils --yes

if php --version
then

else
  sudo dnf module reset php
  sudo dnf module install php:remi-7.4
  # ^ this installs php php-cli, common, fpm, json, mbstring, xml

  sudo dnf install httpd php-gd php-mysqlnd php-pdo php-soap php-xml php-intl

  # dependencies for composer
  sudo dnf install php-zip php-json
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
else
  cat /etc/redhat-release | grep "CentOS Linux release 8" \
    && sudo dnf module install nodejs:13/default
fi

# install recommended tools
ncdu --version || sudo dnf install ncdu
locate --version || { sudo dnf install mlocate && sudo updatedb; }
iftop -h || sudo dnf install iftop

python2 --version || sudo dnf install python2
python3 --version || sudo dnf install python3 python3-devel

if python3 --version
then
  # sometimes needed for python packages (eg. glances)
  cat /etc/redhat-release | grep "CentOS" && sudo dnf install redhat-rpm-config
  sudo dnf install gcc

  if glances --version
  then

  else
    sudo pip3 install glances
  fi
fi

# download recomennded scripts
sudo dnf install wget  --yes
test ! -f certbot-auto && wget https://dl.eff.org/certbot-auto

if docker --version
then
else
  read -p "Install docker? [y/N]" -n 1 -r
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]
  then
    # install docker
    sudo dnf install device-mapper-persistent-data lvm2
    sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
    sudo dnf install docker-ce docker-ce-cli containerd.io

    # start now and test
    sudo systemctl start docker
    sudo docker run hello-world

    # start on-boot
    sudo systemctl enable docker

    # usergroup for users priviledged to use docker without sudo
    sudo groupadd docker
  fi
fi

if mysql --version
then
else
  read -p "Install MariaDB? [y/N]" -n 1 -r
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]
  then
    sudo rpm --import https://yum.mariadb.org/RPM-GPG-KEY-MariaDB
    cp https://raw.githubusercontent.com/gakowalski/linux-admin/master/external-tools/yum.repos.d/mariadb.repo /etc/yum.repos.d/
    sudo yum install MariaDB-server
  fi
fi

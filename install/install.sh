#!/bin/sh

info() {
  echo "$LINENO: $1"
}
failure() {
  echo "Failure: $1"
  exit 1
}

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

# replace yum with dnf
# dnf is better (safer) at checking dependenciesexi
sudo yum install dnf

# some speedup
FILE=/etc/dnf/dnf.conf
if test -f $FILE
then
  cat $FILE | grep max_parallel_downloads && echo 'max_parallel_downloads=10' | sudo tee -a $FILE
  cat $FILE | grep fastestmirror && echo 'fastestmirror=True' | sudo tee -a $FILE
fi

# refresh packages to update
sudo dnf list updates

# propose updating (you'll be asked Y or N before install)
sudo dnf update

# install recommended tools
sudo dnf install ncdu
sudo dnf install mlocate && sudo updatedb

# download recomennded scripts
test ! -f certbot-auto && wget https://dl.eff.org/certbot-auto

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

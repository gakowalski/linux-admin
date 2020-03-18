#!/bin/sh
git clone --recurse-submodules https://github.com/gakowalski/linux-admin

# replace yum with dnf
# dnf is better (safer) at checking dependencies
sudo yum install dnf

# some speedup
echo 'max_parallel_downloads=10' | sudo tee -a /etc/dnf/dnf.conf
echo 'fastestmirror=True' | sudo tee -a /etc/dnf/dnf.conf

# refresh packages to update
sudo dnf list updates

# propose updating (you'll be asked Y or N before install)
sudo dnf update

# install recommended tools
sudo dnf install ncdu
sudo dnf install mlocate && sudo updatedb

read -p "Install docker? " -n 1 -r
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

#!/bin/bash

# This script is being run maually by a new administrator after his first login to unknown machine to get familiar with its system
# It is not being run automatically by any script or cron job

uname -a
lsb_release -a
cat /etc/os-release

# Check the running services
systemctl list-units --type=service

# Check the basic hardware resources
top
free -m
df -h

# Check the network interfaces
ip addr

# Check the network connections
netstat -tulpn

# Check the firewall status
## First check for presence of common firewall packages
rpm -qa | grep firewalld
rpm -qa | grep iptables
rpm -qa | grep nftables
## Then check the status of the firewall
systemctl status firewalld
systemctl status iptables
systemctl status nftables
ufw status

# Check the SELinux status
getenforce

# Check the logs
journalctl -xe
tail -f /var/log/syslog
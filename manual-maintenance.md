# Manual maintenance routines

## General

## Security

### Update packages and upgrade OS

```
sudo yum -y install deltarpm
sudo yum update
sudo yum autoremove
sudo yum -y install rpmorphan
sudo rpmorphan
sudo rpmduplicates
```

```
sudo apt-get update
sudo apt-get upgrade
sudo apt-get autoremove
```

See also:
* https://github.com/epinna/Unusedpkg

### Check for empty user passwords

`awk -F: '($2 == "") {print}' /etc/shadow`

### Check for world-writable files in /dir

`find /dir -xdev -type d \( -perm -0002 -a ! -perm -1000 \) -print`

### Check for rootkits and other security risks

```
sudo yum -y install chkrootkit lynis rkhunter
sudo chkrootkit
sudo lynis audit system
sudo rkhunter --update
sudo rkhunter --check
```

See also:
* https://github.com/lateralblast/lunar

### Review list of active users from last 90 days

`lastlog -b 90`

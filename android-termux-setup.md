# Setting up Android server

## Requirements

* Phone with Android 5.0+

## SSH Remote Access

1. Install Termux app from the store;
3. `pkg install termux-auth` (to install `passwd` command)
2. `passwd` to setup SSH password for user root;
3. `pkg install dropbear`
4. `dropbear` (to start SSH daemon)
5. Now you can access SSH on port 8022, user `root

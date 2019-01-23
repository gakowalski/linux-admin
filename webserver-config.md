# Webserver config

## Block .git* files

### Apache

Add to the main Apache config file:

```
RedirectMatch 404 /.git
```

To find main config file, use:
```
sudo apachectl -V | grep HTTPD_ROOT
sudo apachectl -V | grep SERVER_CONFIG_FILE
```

Concatenate results to get config file path. Edit file, test if no syntax errors `sudo apachectl -t` and then gracefully restart Apache.

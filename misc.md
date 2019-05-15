## Go to first folder containing given substring

Define function:

```bash
goto() { cd `locate $1 | head -n 1`; }
```

Change directory (example):

```
$ pwd
/home/grzegorz.kowalski
$ goto httpd
$ pwd
/etc/httpd
```

Depends on `locate` command. May need refreshing file search database by the `updatedb` command.

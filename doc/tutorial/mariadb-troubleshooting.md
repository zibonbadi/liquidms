![LiquidMS logo](../liquidMS.svg)

Troubleshooting MariaDB
=======================

Troubleshooting Docker
----------------------

To connect to a MariaDB Docker Container, let it execute a Bash shell:
```
$ docker-exec -it <db container ID> /usr/bin/env bash
```

SQL database administration
---------------------------

Within your shell, you should have access to all programs and the file
system of the container. To connect to your database now, run the following
command and enter your database user password when prompted:

```
$ mysql -u <db username> -p
```

If you're running as a regular user, you may already be assigned to the
`liquidms` database. Test this using the following SQL commands:

```SQL
SHOW DATABASES;
USE liquidms;
SHOW TABLES;
```



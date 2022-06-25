![LiquidMS logo](../liquidMS.svg)

Troubleshooting MariaDB
=======================

Troubleshooting Docker
----------------------

To connect to a MariaDB Docker Container, let it execute a Bash shell:
```
$ docker-exec -it <db container ID> /usr/bin/env bash
```

SQL database administration basics
----------------------------------

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

Checking the Event scheduler
----------------------------

For LiquidMS to properly clean up it's server list, it needs to be able to
execute scheduled events. To achieve this, check whether the event
scheduler is turned on:

```SQL
SHOW VARIABLES WHERE VARIABLE_NAME = 'event_scheduler';
```

To enable the event scheduler, you will need to
issue the following command as root:

```SQL
SET GLOBAL 'event_scheduler' = ON;
```


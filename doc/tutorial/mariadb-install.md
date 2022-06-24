![LiquidMS logo](../liquidMS.svg)

MariaDB
=======

As MariaDB currently remains our testing and design database (both due to
lack of database feature standardization as well as it's libre
software-induced ubiquity), here's a few tips on how to configure MariaDB
for a smooth, painless LiquidMS experience:


Downloading and installing
--------------------------

### Gentoo Linux

```
root# emerge -a dev-db/unixODBC dev-db/mariadb dev-db/mariadb-connector-odbc
```

Setting up ODBC for MariaDB
---------------------------

In principle, LiquidMS relies on ODBC to ensure an industry standard
interface between the satellite and an arbitrary, SQL-capable database.
However as LiquidMS currently requires features that have thus far not been
properly standardized, LiquidMS' database logic has been designed against MySQL/MariaDB.

After installing your variant of unixODBC, you will need to define a
*driver* and *data source*. The driver defines the method of connection to
your database whereas the data source defines information about the server
and database you're trying to access. Below an exemplary MariaDB driver
(`/etc/odbcinst.ini` on Linux):

```INI
[MariaDB]
Description=MariaDB ODBC Connector
Driver=/usr/lib64/mariadb/libmaodbc.so
UsageCount=1
```


Enabling the event scheduler
----------------------------

LiquidMS relies on programmable database events to allow for live server
updates in a secure manner. In order to keep the necessary event scheduler
persistently enabled across reboots of the MariaDB daemon, add the
following line to your MariaDB config file (usually `my.cnf` or `my.ini`):

    event_scheduler=ON



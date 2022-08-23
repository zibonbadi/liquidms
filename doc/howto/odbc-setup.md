![LiquidMS logo](../liquidMS.svg)

Setting up ODBC
===============

Setting up ODBC on Unix-like systems (Linux, \*BSD, macOS)
---------------------------------------------------------

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

For data source definition, we recommend a configuration
(`/etc/odbc.ini` or `~/.odbc.ini` on Linux) like this:

```INI
[liquidms]
Description = LiquidMS database
Driver = <your driver>
Database = <your database ("liquidms" by default)>
Server = <your database server>
Port = <your database's server port>
Socket = /var/run/mysqld/mysqld.sock
User = <your SQL user>
Password = <your SQL password>
```

Once you're done setting up the configs, you can test unixODBC using
`iusql -v <your data source (e.g. "liquidms">`.
If you have trouble the [ArchWiki] has a helpful article on unixODBC.

[ArchWiki]: <https://wiki.archlinux.org/title/Open_Database_Connectivity>

Setting up ODBC on Windows
--------------------------

TBA


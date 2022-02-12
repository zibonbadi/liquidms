![liquidMS logo](doc/liquidMS.svg)

liquidMS
========

SYNOPSIS
--------

- **php -S** *hostname* **server.php**
- **php fetch.php** [*jobname*]
- **php liquidanacron.php**

SUMMARY
-------

*liquidMS* is an API-compatible clean room implementation of the Sonic Robo
Blast 2 [HTTP Master Server API][v1spec]. It is capable of mirroring data
from any API Compatible master server, thus being capable to be operated as
a node within a distributed master server network.

The project is licensed under the [GNU AFFERO GENERAL PUBLIC LICENSE Version 3][gnuaffero],
which is accessible via this link, the included `LICENSE.md` file
or via the HTTP endpoint `/liquidms/license`.

Special thanks to GoldenTails whose reverse engineered HTTP master server
served as a reference to this project.  
<https://git.do.srb2.org/Golden/RevEngMS>

[vqspec]: <https://web.archive.org/web/20220205110841/https://mb.srb2.org/MS/tools/api/v1/>
[gnuaffero]: <https://www.gnu.org/licenses/agpl-3.0.en.html>

INSTALLATION
------------


First, download the source code and install all dependencies.  You'll need
[PHP] and [Composer] for this with the following PHP extensions enabled:

- EXT_PDO
- EXT_YAML
- EXT_MBSTRING

[PHP]: <https://www.php.net/>
[Composer]: <https://getcomposer.org/doc/00-intro.md>

```
$ git clone "https://github.com/zibonbadi/liquidms.git"
$ cd liquidms
liquidms$ composer install
```


liquidMS requires a seperate SQL-capable relational database. As the
connection is established through an [ODBC] interface, this can be either
on-disk, on-system or remote.  All details about the preferred database
connection can be configured in the *environment file*; see
__CONFIGURATION__ for more info.

[ODBC]: <https://en.wikipedia.org/w/index.php?title=Open_Database_Connectivity&oldid=1044732966> "ODBC - Wikipedia"

Each running instance of a liquidMS SRB2 master server is called a *node*.
Nodes may be run independently from their corresponding database and thus
may be used as read-only database mirrors in case you attempt to run a
distributed liquidMS node network.

### Setting up ODBC on Unix-like systems (Linux, *BSD, macOS)

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
Description = liquidMS database
Driver = <your driver>
Database = <your database>
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


CONFIGURATION
-------------

All server configuration will be stored in the *config file*
`config.yaml`. For security, this Git repository will **not** include this
file within its commit history. Use the example file `config.yaml.example` for
reference as to what configuration options liquidMS will accept. 

Also keep in mind that YAML is very sensitive about the indentation of
fields. Each indentation level is defined by *two whitespaces, not tabs*.
This shouldn't be too news to a regular GitHub user, but it's still noted
here for Linus-proofing.

```YAML
---
# This is an example config.yaml for a liquidMS node environment
db: # liquidMS DB connection settings
   dsn: "DRIVER=liquidMS ODBC driver;SERVER=localhost" # ODBC DSN string.
   user: "alice"
   password: "password" # KEEP THIS SECRET!
fetch: # Master servers to leech off of
  vanilla:
    host: "http://mb.srb2.org/0/MS"
    minute: 15
  development:
    host: "http://localhost:8080"
    minute: 3
...
```

The attribute `dsn` stands for *Data Source Name*. It provides the ODBC system
with information about database and connection and its details may vary
depending on the database implementation. For a quick reference on how to write
DSN connection strings for most commonly used database implementations, we
recommend <https://www.connectionstrings.com/>. Otherwise we are not able to
take accountability for how you decide to run or connect to your database
using this interface; it is simply impossible for us to help you with that.


### MariaDB

As MariaDB currently remains our testing and design databaase (both due to
lack of database feature standardization as well as it's libre
software-induced ubiquity), here's a few tips on how to configure MariaDB
for a smooth, painless liquidMS experience:

#### Enabling the event scheduler

liquidMS relies on programmable database events to allow for live server
updates in a secure manner. In order to keep the necessary event scheduler
persistently enabled across reboots of the MariaDB daemon, add the
following line to your MariaDB config file (usually `my.cnf` or `my.ini`):

    event_scheduler=ON



### liquidanacron

Should you choose to use `liquidanacron.php` for your fetch queries, the
execution of master server queries will be determined by the field
`minute`. Much like anacron on POSIX systems, this script will keep track
of when a query has been executed last using timestamps documented in the
automatically generated file `timestamps.yaml` and automatically query a
master server once a specified amount of time has passed since last
execution.

Each number above 0 entered into a time field will be interpreted as a
multiplier to be understood as "every x minutes"; other values will equate
to 1. To ignore a specific temporal requirement, simply omit it's time
field from the job.


USAGE
-----

liquidMS is able to mirror server listings of any API-compliant SRB2
HTTP V1 master server within it's own server database. This is called the
"superset mirror" concept and it divides it's servers into two categories:

The room *Universe* is defined as all servers stored within a liquidMS
node's corresponding database, both internal and remote fetched.

The room *World* is defined as all servers uniquely registered to the
database and it's responses will be automatically generated by liquidMS.
In order to host local rooms, one must register these in their database
manually with an ID between 2 and 99. This was a deliberate security
measure to avoid unauthorized remote database fiddling in distributed
setups. Room ID 1 is reserved for World and remains ignored.

All rooms with an id of 100+ will be reserved to be automatically generated
by liquidMS depending on the origin and designated room of all remote
servers within it's database. These will be regularly deleted and rebuilt
so don't even attempt to set up a room in this range, it's not worth it.


### Hosting a liquidMS node

liquidMS is able to mirror server listings of any API-compliant SRB2
HTTP V1 master server within it's own server database. The script
`fetch.php` parses server listings fetched from said master servers defined
in `config.yaml` and upserts them into it's defined ODBC database.
For recurring execution, this script can be used either through the
supplied daemon script `liquidanacron.php` or independently as a
system-managed regular occurrence, such as a scheduled task on Windows or a
(ana)cronjob on POSIX.

To query individual servers, specify their job names as arguments to the
script like below. If unspecified, all servers will be fetched at once:

```sh
$ php fetch.php [jobname]
```

The script `liquidanarcon.php` serves as a simple daemon that . Acting upon
every minute, it logs timestamps of individual fetch queries and runs them
after the timespan defined in it's designated `minute` field has passed.
If no temporal data is specified for a job, it will be skipped.

For more information, see the *liquidanacron* section in __CONFIGURATION__.

```YAML
fetch:
  vanilla:
    host: "http://mb.srb2.org/0/MS"
    minute: 15
```

### Snitching

liquidMS features a custom extension called the *Snitch API*. Through use
of the endpoint `/liquidms/snitch` via HTTP GET and POST requests,
independent hosts can contribute to database mirroring on other liquidMS
nodes without the need for database access authorization on behalf of the
peer.

By supplying a peer files of type `text/csv;header=absent` to the peer's
HTTP API, hosts can actively contribute to that peer's database. The CSV
data that is both supplied as well as expected by liquidMS nodes is
defined by the following structure:

	<host>,<port>,<servername>,<version>,<roomname>,<origin>

To easily contribute data towards a peer ("snitching"), the configuration
file `config.yaml` defines the fields `fetchmode` with possible values of
`fetch` and `snitch` as well as the collection `snitch`. When set to
*fetch*, the fetch scripts `fetch.php` and `liquidanacron.php` will attempt
to provide automatically generated SQL queries to the configured odbc
connection, as if these were to be used for a self hosted node setup. When
set to *snitch*, these scripts will instead attempt to supply their data to
all peers specified in the *snitch* collection of the configuration file.


DEVELOPMENT
-----------

Simply Launch a server with PHP:

	$ php -S 127.0.0.1:8080

NOTE: The game has been reported to have difficulties around the local DNS
      name `localhost`. Also note that the URL must not end in a slash for

Our `.gitignore` file also reserves a dedicated directory `local/` in case
you need to store information locally without committing them or fiddling
with resets or the `.gitignore`; config files and database setup scripts
for example.

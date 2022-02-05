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
Blast 2 [HTTP Master Server API][v1spec]. It is capable of fetching server info from any
API Compatible master server and include the response in it's own output,
thus being capable to be operated as a node within a distributed master
server network.

Special thanks to GoldenTails whose reverse engineered HTTP master server
served as a reference to this project.  
<https://git.do.srb2.org/Golden/RevEngMS>

[vqspec]: <https://web.archive.org/web/20220205110841/https://mb.srb2.org/MS/tools/api/v1/>

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

To run a development server for your node, simply launch it as a PHP server:

```Bash
php -S 127.0.0.1:8000 server.php
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

liquidMS is capable to mirror server listings of any API-compliant SRB2
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

Should you choose to use `liquidanacron.php`, the execution of master
server queries will be determined by the fields `day`, `hour`, and
`minute`. Much like anacron on POSIX systems, this script will keep track
of when a query has been executed last using timestamps documented in the
automatically generated file `liquidanacron.yaml` and automatically query a
master server once a specified amount of time has passed since last execution.

Each number above 0 entered into a time field will be interpreted as a
multiplier to be understood as "every x minutes/hours/days"; other values
will equate to 1. To ignore a specific temporal requirement, simply omit
it's time field from the job. For example, to execute a query every 24
hours and 15 minutes, a job may look like the following:

```YAML
fetch:
  vanilla:
    host: "http://mb.srb2.org/0/MS"
    day: 1
    minute: 15
```

If no temporal data is specified for a job, it will be skipped.

CONFIGURATION
-------------

All server configuration will be stored in the *config file*
`config.yaml`. For security, this Git repository will **not** include this
file within its commit history. Use the example file `config.yaml.example` for
reference as to what configuration options liquidMS will accept. 


```YAML
---
# This is an example config.yaml for a liquidMS node environment
db: # liquidMS DB connection settings
   dsn: "DRIVER=liquidMS ODBC driver;SERVER=localhost" # ODBC DSN string.
   user: "alice"
   password: "password" # KEEP THIS SECRET!
fetch: # Master servers to leech off of
- "http://mb.srb2.org/0/MS"
- "http://goldentails.tk/ms"
- "http://mother.asnet.org/"
...
```

The attribute `dsn` stands for *Data Source Name*. It provides the ODBC system
with information about database and connection and its details may vary
depending on the database implementation. For a quick reference on how to write
DSN connection strings for most commonly used database implementations, we
recommend <https://www.connectionstrings.com/>. Otherwise we are not able to
take accountability for how you decide to run or connect to your database
using this interface; it is simply impossible for us to help you.

USAGE
-----

liquidMS is able to mirror server listings of any API-compliant SRB2
HTTP V1 master server within it's own server database. This is called the
"superset mirror" concept and it divides it's servers into two categories:

The room *Universe* is defined as all servers stored within a liquidMS
node's corresponding database, both internal and remote fetched.

The room *World* is defined as all servers uniquely registered to the
database and it's responses will be automatically generated by liquidMS.

All subsequent rooms will be automatically generated by liquidMS depending
on the origin and designated room of all servers within it's database.

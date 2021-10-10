liquidMS
========

*liquidMS* is an API-compatible clean room implementation of the Sonic Robo
Blast 2 HTTP Master Server. It is capable of fetching server info from any
API Compatible master server and include the response in it's own output,
thus being capable to be operated as a node within a distributed master
server network.

Special thanks to GoldenTails whose reverse engineered HTTP master server
served as a reference to this project.  
<https://git.do.srb2.org/Golden/RevEngMS>

INSTALLATION
------------

liquidMS requires an external SQL-capable relational database and an [ODBC]
driver to connect to it. As the ODBC is a universal database interface that
allows liquidMS to connect to arbitrary databeses, be they on-disk, on-system or
remote.  All details about the preferred database connection can be configured
in the *environment file*; see __CONFIGURATION__ for more info.

[ODBC]: <https://en.wikipedia.org/w/index.php?title=Open_Database_Connectivity&oldid=1044732966> "ODBC - Wikipedia"


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
   dsn: # ODBC DSN string.
   server: "localhost"
   driver: "MYSQL ODBC x.xx DRIVER"
   user: "alice"
   password: "password" # KEEP THIS SECRET!
   database: "liquidms"
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

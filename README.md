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

To run a node, simply launch it as a PHP server:

```Bash
php -S 127.0.0.1:8000 server.php
```

Make sure the following PHP extensions are enabled:

- EXT_PDO
- EXT_YAML

liquidMS requires a seperate SQL-capable relational database. As the
connection is established through an [ODBC] interface, this can be either
on-disk, on-system or remote.  All details about the preferred database
connection can be configured in the *environment file*; see
__CONFIGURATION__ for more info.

[ODBC]: <https://en.wikipedia.org/w/index.php?title=Open_Database_Connectivity&oldid=1044732966> "ODBC - Wikipedia"


CONFIGURATION
-------------

All server configuration will be stored in the *config file*
`config.yaml`. For security, this Git repository will **not** include this
file within its commit history. Use the example file `config.yaml.example` for
reference as to what configuration options liquidMS will accept. 

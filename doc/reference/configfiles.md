![LiquidMS logo](../liquidMS.svg)

Configuration files
===================

`.env`
------

```Shell
TZ=Europe/Moscow
LIQUIDMS_HOST=satellite
LIQUIDMS_PORT=8080
ODBC_HOST=db
ODBC_PORT=3306
MYSQL_DATABASE=liquidms
MYSQL_USER=alice
MYSQL_PASSWORD=changeme
MYSQL_ROOT_PASSWORD=CHANGEMEOHGODPLEASECHANGEME
```

This file defines environment variables to be used
by Docker Compose during the creation of containers.

`TZ`
: UNIX time zone of the containers.

`LIQUIDMS_HOST`
: Satellite container hostname.

`LIQUIDMS_PORT`
: Satellite container port.

`ODBC_HOST`
: Database container hostname for use with ODBC.

`ODBC_PORT`
: Database container port for use with ODBC.

`MYSQL_DATABASE`
: MySQL/MariaDB database to access

`MYSQL_USER`
: MySQL/MariaDB database user to use as the satellite

`MYSQL_PASSWORD`
: MySQL/MariaDB database user password for the satellite

`MYSQL_ROOT_PASSWORD`
: MySQL/MariaDB root password. **FOR ADMINISTRATORS ONLY**


`config.yaml`
-------------

```YAML
db: # liquidMS DB connection settings
   dsn: "liquidms" # ODBC DSN string; by name
   #dsn: "DRIVER=liquidMS ODBC driver;SERVER=localhost" # ODBC DSN string; by syntax
   user: "alice"
   password: "password" #Keep this secret
...
```

This section will only define the parts that aren't covered by the next two
subsets. Please inspect those two as well to get a better idea of what you
can configure.

`db`
: ODBC connection definition. Requires all three of the following.

`db.dsn`
: DSN connection string. May be a proper connection string, but usually an
  external connector specification using `odbc.ini` works better.

`db.user`
: Database user name. Somehow required despite DSN specification.

`db.password`
: Database user password. Somehow required despite DSN specification.


`fetch.config.yaml`
-------------------

```YAML
## This file is documents a minimal example configuration
## for the provided snitch container.
## Simply adjust the values to your liking and save it as
## fetch.config.yaml
---
fetchmode: "snitch" # self-snitch for security
motd: |
  liquidMS is licensed under AGPLv3
fetch: # Master servers to leech off of
  vanilla: # Example v1 master server
    host: "https://mb.srb2.org/MS/0"
    api: v1 # "v1" to fetch from /servers
    minute: 5 # Should be unused due to crontab
  self: # Example LiquidMS (snitch API) server
    host: "http://caddy"
    api: snitch # "snitch" to fetch from /liquidms/snitch
    minute: 10 # Should be unused due to crontab
snitch: # Servers to snitch to
- "http://caddy" # Caddy container
...
```

This is a subset of the `config.yaml` containing only the parts relevant to
running a snitch using the fetchscript:

`fetch`
: Collection of fetch jobs to fulfill. Jobs can be arbitrarily named
  and passed to `fetch.php` as arguments for isolated execution.

`fetch.<jobname>.api`
: API to use for the fetch job. Can be either `"v1"` for
  fetching from V1 or `"snitch"` for fetch-from-snitch.

`fetch.<jobname>.host`
: Hostname to fetch from.

`fetch.<jobname>.minute`
: Interval to fetch. Only used by `liquidanacron.php`

`fetchmode`
: Can either be `"fetch"` or `"snitch"`

`snitch`
: Collection of URLs. Defines other LiquidMS nodes to snitch server data
  to. Can include your local satellite. Requires `fetchmode: "snitch"`.


`satellite.config.yaml`
-----------------------

```YAML
## This file is documents a minimal example configuration
## for the provided satellite container.
## Simply adjust the values to your liking and save it as
## satellite.config.yaml
---
modules: # Features to enable
- v1 # v1 API
- snitch # snitch API
- browser # integrated server browser
- srb2query # SRB2Query endpoint (for fancy browser)
sbpath: "/var/www/liquidms/browser/fancy"
motd: |
  liquidMS is licensed under AGPLv3
...
```

This is a subset of the `config.yaml` containing only the parts relevant to
hosting a satellite

`modules`
: A list of hosted modules. Currently 4 modules are supported

	- `v1`: The V1 API used by SRB2
	- `snitch`: The [Snitch API]
	- `browser`: The server browser endpoints
	- `srb2query`: The SRB2Query API

[Snitch API]: <../teardown/snitch,md>

`motd`
: The MOTD (Message Of The Day). Displayed through the V1 API and server browser.

`sbpath`
: The (absolute) directory path to where to find the used server browser's `index.php`


`fetch.crontab`
---------------

```YAML

## LiquidMS example schedule for Docker containers (crontab)
## simply save your changes into "fetch".crontab
## 
##	min		hour	day	month	weekday	command
	*/5		*		*	*		*		/usr/bin/env php /var/www/liquidms/liquidms/fetch.php
#	*/10	*		*	*  		*		/usr/bin/env php /var/www/liquidms/liquidms/fetch.php "self"
```

This is a standard crontab file only meant for use in Docker containers.
[Cron] is a daemon found on UNIX systems that periodically runs programs
based on the time of day. The above snippet should suffice in teaching you
how they work.


`tables.sql` 
------------

```SQL
-- Use this example file to define banlists and world rooms
-- To be saved as "tables.sql"

-- World rooms
USE `liquidms`;
INSERT INTO `rooms` (`_id`, `roomname`, `description`) VALUES
(3, "Sonic's room", 'Gotta go fast with those speedy netgames!'),
(4, "Tails' workshop", "Let's build better netgames")
ON DUPLICATE KEY UPDATE _id=VALUES(_id), roomname=VALUES(roomname), description=VALUES(description);

-- Banlist
INSERT INTO `bans` (`host`,`subnetmask`,`expire`,`comment`,`bans`) VALUES
('fe80::1', 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 DAY), 'Knuckles needs to calm down'),
('::ffff:0.0.0.0',  'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ff00', NULL, "Lock out Eggman and his robots")
ON DUPLICATE KEY
UPDATE _id=VALUES(_id), _id=VALUES(host), _id=VALUES(subnetmask), _id=VALUES(expire), _id=VALUES(comment);
```

This script needs to be executed by your database upon initial setup.
The above snippet only serves for administrative reference.


On alternative Server browsers
------------------------------

The server browser can be altered or exchanged using
the config option `sbpath`, requiring an absolute path to the directory
containing the frontend. The exact structure required of a frontend to work
as a LiquidMS-compatible server browser will be displayed down below in YAML.

```YAML
sbpath:
- index.php # Entry point to your frontend.
- favicon.svg # Favicon
# More information on hooking your PHP scripts into LiquidMS in
# the "Views" section at <https://github.com/klein/klein.php>
- css/ # CSS data. Nested structure is permitted.
- img/ # Image stock. Nested structure is permitted.
- js/ # JavaScript resources. Nested structure is permitted.
- static/ # Static resources. Nested structure is permitted.
```

Currently LiquidMS ships with two server browsers by default,
only one of which can be hosted per satellite:

`dist/browser/fancy`
: An dynamic, modern server browser with the ability
  to query netgames right from within the UI.

`dist/browser/fast`
: A non-interactive, lightweight static browser faturing server-side
  rendering aimed at low usage of resources and bandwidth.


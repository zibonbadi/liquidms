![LiquidMS logo](doc/liquidMS.svg)

LiquidMS SRB2Kart/Ring Racers API
=================================

SUMMARY
-------

This PHP webserver is part of [LiquidMS] and should not be distributed
separately.  LiquidMS and this API server are licensed under the [GNU
AFFERO GENERAL PUBLIC LICENSE Version 3][gnuaffero].

[LiquidMS]: <https://github.com/zibonbadi/liquidms/>
[gnuaffero]: <https://www.gnu.org/licenses/agpl-3.0.en.html>

The *LiquidMS SRB2Kart API server* is a clean room implementation of the Sonic
Robo Blast 2 Kart/Dr. Robotnik's Ring Racers HTTP Master Server API
[Version 2][kartspec_v2] and [Version 2.2][kartspec_v2.2] . Being part of
LiquidMS, it supports mirroring other master servers through the *LiquidMS
Snitch API* (version 2+).

[kartspec_v2]: <https://web.archive.org/web/20250401161203/https://ms.kartkrew.org/tools/api/2/>
[kartspec_v2.2]: <https://web.archive.org/web/20250401160905/https://ms.kartkrew.org/tools/api/2.2/>

INSTALLATION
------------

1. Copy this folder to where you wanna host your server.
2. Create the necessary database, tables, etc. Using LiquidMS' own `configure.py`
   may help you here.

   **NOTE:** LiquidMS relies on database-side mechanisms such as `TRIGGER`s
   and `EVENT`s. Using MySQL/MariaDB for your database is recommended.
3. Create `config.yaml` to configure your server (see below)
4. (optional: Configure your HTTP server using the local `.htaccess` or similar)
5. Profits :3

### Sample `config.yaml`

```yaml
---
basepath: /api/srb2kart # Base path this API is hosted under. Use this for subdirectory-based hosting 
db: # Database settings
  dsn: <DSN connection string>
  user: <Database user>
  password: <Database user's password>
tables: # Database table names
  bans: srb2kart_bans
  servers: srb2kart_servers
  versions: srb2kart_versions
motd: |
  Some Message of the day.
  This will be displayed
  to connecting users.
...
```

### Database table description

#### Bans table

| Field    | Type          | Null | Key | Default                                | Extra          |
|----------|---------------|------|-----|----------------------------------------|----------------|
| _id      | int(11)       | NO   | PRI | NULL                                   | auto_increment |
| ip_start | varbinary(16) | NO   |     | NULL                                   |                |
| ip_end   | varbinary(16) | NO   |     | NULL                                   |                |
| expire   | datetime      | YES  |     | (current_timestamp() + interval 1 day) |                |
| comment  | varchar(128)  | YES  |     | NULL                                   |                |

#### Servers table

| Field      | Type                 | Null | Key | Default             | Extra                         |
|------------|----------------------|------|-----|---------------------|-------------------------------|
| host       | varbinary(16)        | NO   | PRI | NULL                |                               |
| port       | smallint(6) unsigned | NO   | PRI | NULL                |                               |
| servername | varchar(256)         | NO   |     | NULL                |                               |
| game       | varchar(32)          | YES  |     | NULL                |                               |
| origin     | varchar(64)          | NO   |     | localhost           |                               |
| updated_at | datetime             | YES  |     | current_timestamp() | on update current_timestamp() |

#### Versions table

| Field  | Type        | Null | Key | Default | Extra          |
|--------|-------------|------|-----|---------|----------------|
| modid  | int(11)     | NO   | PRI | NULL    | auto_increment |
| gameid | int(11)     | NO   |     | 1       |                |
| name   | varchar(32) | YES  |     | NULL    |                |



### Caveats

#### Fetch from the Snitch API if possible

The SRB2Kart MS API ([v2][kartspec_v2], [v2.2][kartspec_v2.2])  was not
designed with indirection or mirroring in mind.  As such the origin of
netgames fetched from the SRB2Kart API are merely superimposed by LiquidMS'
snitch script based on which HTTP server was queried.  Fetching universe
netgames from a LiquidMS node's SRB2Kart API unchecked may lead to a
[broadcast storm] across the wider LiquidMS network.

[kartspec_v2]: <https://web.archive.org/web/20250401161203/https://ms.kartkrew.org/tools/api/2/>
[kartspec_v2.2]: <https://web.archive.org/web/20250401160905/https://ms.kartkrew.org/tools/api/2.2/>
[broadcast storm]: <https://en.wikipedia.org/wiki/Broadcast_storm>


Endpoints
---------

All endpoints require a query parameter `?v=<API version>` to be supplied.
The following API versions are recognized by LiquidMS:

- `?v=2` for API version 2
- `?v=2.2` for API version 2.2
- Appending `-liquid` to the API version unlocks LiquidMS-specific
  extensions. For example querying `/games?v=2.2` will respond with an
  error while `/games?v=2.2-liquid` will yield all games known to the server.

### POST requests

All endpoints are assumed to be hosted under `${BASEPATH}`:

`/rules` (GET)
: When using API 2.2 or LiquidMS mode, this will output the Message-of-the-Day.

`/games` (GET)
: In standard mode, this yields the error `Missing application name`.
  In LiquidMS mode, it gives a list of games known to the server.

`/games/<game>/servers` (GET)
: In standard mode, this yields the error `Unknown action`.
  In LiquidMS mode, this yields all netgames for this game, regardless of version.
  The format follows that of `/games/<game>/<version>/servers`.

`/games/<game>/version`
: Lists the latest version of `<game>` known to the server or the error `No
  such game`. The game version takes the form `<Version ID> <Version name>[LF]`.

`/games/<game>/<version>/servers` (GET)
: List all netgames matching this game and version.
  The list is output as a space-separated value table of the following
  structure and without header:

    <host> <port> <URL-encoded contact>[LF]


### POST requests

All endpoints are assumed to be hosted under `${BASEPATH}`:

`/games/<game>/<version>/servers/register` (POST)
: Registers a netgame on the server. Requires the request body format
  `application-x-www-form-urlencoded` with:

  - `port` (integer): New netgame's port.
  - `contact` (URL-encoded string): New netgame title.

  Upon successful registration, the server will yield a *Netgame ID*.

`/servers/<Netgame ID>/update` (POST)
: Updates a netgame on the server. Requires the request body format
  `application-x-www-form-urlencoded` with:

  - `contact` (URL-encoded string): New netgame title.

`/servers/<Netgame ID>/unlist` (POST)
: Unlist a netgame. Requires a POST request, but no body.


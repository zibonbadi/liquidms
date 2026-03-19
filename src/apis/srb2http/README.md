![LiquidMS logo](doc/liquidMS.svg)

LiquidMS SRB2HTTP API
=====================

SUMMARY
-------

This PHP webserver is part of [LiquidMS] and should not be distributed
separately.  LiquidMS and this API server are licensed under the [GNU
AFFERO GENERAL PUBLIC LICENSE Version 3][gnuaffero].

[LiquidMS]: <https://github.com/zibonbadi/liquidms/>
[gnuaffero]: <https://www.gnu.org/licenses/agpl-3.0.en.html>

The *LiquidMS SRB2 API server* is a clean room implementation of the Sonic
Robo Blast 2 [HTTP Master Server API][v1spec]. Being part of LiquidMS, it
supports mirroring other master servers through the *LiquidMS Snitch API*.

Special thanks to GoldenTails whose [RevEngMS][GoldenTails] served as a
reference to this project.  

[GoldenTails]: <https://git.do.srb2.org/Golden/RevEngMS>
[v1spec]: <https://web.archive.org/web/20220205110841/https://mb.srb2.org/MS/tools/api/v1/>

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
basepath: /srb2http # Base path this API is hosted under. Use this for subdirectory-based hosting
in a sub
netgame_query_limit: # Rate limiting.
  n: 5
  seconds: 1
db: # Database settings
  dsn: <DSN connection string>
  user: <Database user>
  password: <Database user's password>
tables:
  bans: srb2http_bans
  rooms: srb2http_rooms
  servers: srb2http_servers
  versions: srb2http_versions
motd: |
  Some Message of the day.
  This will be displayed
  to connecting users.
...
```

### Database table description

#### Bans table

| Field     | Type         | Null | Key | Default                                | Extra          |
|-----------|--------------|------|-----|----------------------------------------|----------------|
| _id       | int(11)      | NO   | PRI | NULL                                   | auto_increment |
| ip_start  | binary(16)   | NO   |     | NULL                                   |                |
| ip_end    | binary(16)   | NO   |     | NULL                                   |                |
| expire    | datetime     | YES  |     | (current_timestamp() + interval 1 day) |                |
| host_only | int(11)      | YES  |     | 1                                      |                |
| comment   | varchar(128) | YES  |     | NULL                                   |                |

#### Rooms table

| Field       | Type        | Null | Key | Default                                               | Extra |
|-------------|-------------|------|-----|-------------------------------------------------------|-------|
| _id         | int(11)     | NO   | UNI | NULL                                                  |       |
| roomname    | varchar(32) | NO   | PRI | NULL                                                  |       |
| origin      | varchar(32) | NO   | PRI | localhost                                             |       |
| description | text        | YES  |     | 'Powered by LiquidMS: DO NOT REGISTER NETGAMES HERE.' |       |

#### Servers table

| Field      | Type                 | Null | Key | Default             | Extra                         |
|------------|----------------------|------|-----|---------------------|-------------------------------|
| host       | binary(16)           | NO   | PRI | NULL                |                               |
| port       | smallint(6) unsigned | NO   | PRI | NULL                |                               |
| servername | varchar(256)         | NO   |     | NULL                |                               |
| version    | varchar(16)          | NO   |     | NULL                |                               |
| roomname   | varchar(32)          | YES  |     | NULL                |                               |
| origin     | varchar(64)          | NO   |     | localhost           |                               |
| updated_at | datetime             | YES  |     | current_timestamp() | on update current_timestamp() |

#### Versions table

| Field  | Type        | Null | Key | Default | Extra          |
|--------|-------------|------|-----|---------|----------------|
| modid  | int(11)     | NO   | PRI | NULL    | auto_increment |
| gameid | int(11)     | NO   |     | 1       |                |
| name   | varchar(32) | YES  |     | NULL    |                |

### Caveats

#### Be careful with room descriptions and MOTDs

[The SRB2 Master Server API specification][v1spec] can be opinionated about
formatting. Make sure the output of your API's `/rooms` endpoint matches
the following schema (e.g. using [curl]). The game may also not
automatically wrap text, so be mindful.
If you're writing on Windows, be sure to use
[UNIX-style `LF` newlines](https://en.wikipedia.org/wiki/Newline).

[v1spec]: <https://web.archive.org/web/20220205110841/https://mb.srb2.org/MS/tools/api/v1/>
[curl]: <https://curl.se/>
```
[START OF ROOM]
<room number>
<room name>

<description with max 1 consecutive blank line>


[END OF ROOM]
```

#### Fetch from the Snitch API if possible

[The SRB2's MS API][v1spec] was never designed with indirection or mirroring
in mind.
As such the origin of netgames fetched from SRB2's API are merely
superimposed by LiquidMS' snitch script based on which HTTP server was queried.
Fetching universe netgames from a LiquidMS node's SRB2 API unchecked may
lead to a [broadcast storm] across the wider LiquidMS network.

[v1spec]: <https://web.archive.org/web/20220205110841/https://mb.srb2.org/MS/tools/api/v1/>
[broadcast storm]: <https://en.wikipedia.org/wiki/Broadcast_storm>


Endpoint
--------

All endpoints are assumed to be hosted under `${BASEPATH}`:

`/`
: API root. Without further subpaths, it will return `Unknown error.`

`/servers`
: List *universe* servers, meaning all netgames regardless of room or origin.

`/servers/update`
: Update a netgame. Requires the following POST request fields:

  - `?title`: New netgame title

`/servers/unlist`
: Unlist a netgame. Requires a POST request

`/rooms`
: Room information for all rooms.

`/rooms/<room ID>`
: Room information for `<room ID>`.

`/rooms/<room ID>/servers`
: List servers inside `<room ID>`.

`/rooms/<room ID>/register`
: Register a server to `<room>`. Requires the following POST request fields:

  - `?title`: Netgame title
  - `?port`: Netgame port
  - `?version`: Literal game version

`/versions/<modid>`
: Game versions. `<modid>` defines 

### LiquidMS-specific quirks

LiquidMS automatically creates a private room `World` under `/rooms/1`.
This is used to output all *world* netgames, meaning netgames from across
all rooms which aren't mirrored from other servers. This room does not need
to be defined in your database.

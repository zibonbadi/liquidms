![LiquidMS logo](doc/liquidMS.svg)

LiquidMS SRB2Legacy API
=======================

[LiquidMS] endpoint for Sonic Robo Blast v2.1 and below.


[LiquidMS]: <https://github.com/zibonbadi/liquidms/>
[gnuaffero]: <https://www.gnu.org/licenses/agpl-3.0.en.html>

USAGE
-----

    python __main__.py [-h] [config]

LiquidMS Legacy can be run with custom configuration files by specifying `config`. By default `config.yaml` is assumed.

### YAML Configuration

```YAML
hostname: localhost # Needs to match your server's outside hostname.
port: 28900         # Publicly accessible server port
buffer_size: 2048   # Buffer size to fetch from client
max_timeout: 10
db:
  url: <RFC1738 Database URL>
  tables:
    bans: srb2http_bans
    rooms: srb2http_rooms
    servers: srb2http_servers
    versions: <srb2http_versions>
motd: |
  Some MOTD
```

### Database table description

The LiquidMS SRB2Legacy server is designed to integrate into databases of the LiquidMS SRB2HTTP API. As such it's database structure and configuration is identical to that of the LiqudMS SRB2HTTP API.

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

#### Fetch from the Snitch API if possible

The original SRB2 Master Server API was never designed with indirection or mirroring in mind.
As such the origin of netgames fetched from SRB2's API are merely
superimposed by LiquidMS' snitch script based on which HTTP server was queried.
Fetching universe netgames from a LiquidMS node's SRB2 API unchecked may
lead to a [broadcast storm] across the wider LiquidMS network.

[broadcast storm]: <https://en.wikipedia.org/wiki/Broadcast_storm>

SRB2Legacy network protocol
---------------------------

The SRB2Legacy API is based on a TCP/UDP socket connection. All messages follow little endian byte order and start follow the data structure below.

```C
typedef struct {
  int32_t id;  // Unused
  int32_t type;
  int32_t room; // API version 21 only
  uint32_t length;
  void* data; // Length defined by length field
} message;
```

The folowing table lists all accepted message types:

| Type name               | ID  | Client | Server | Comment
|-------------------------|-----|--------|--------|---------
| `ADD_SERVER_MSG`        | 101 |   x    |        |
| `REMOVE_SERVER_MSG`     | 103 |   x    |        |
| `ADD_SERVERv2_MSG`      | 104 |   x    |        |
| `GET_SERVER_MSG`        | 200 |   x    |        |
| `GET_SHORT_SERVER_MSG`  | 205 |   x    |        |
| `ASK_SERVER_MSG`        | 206 |   x    |        |
| `ANSWER_ASK_SERVER_MSG` | 207 |        |   x    |
| `GET_MOTD_MSG`          | 208 |   x    |        |
| `SEND_MOTD_MSG`         | 209 |        |   x    |
| `GET_ROOMS_MSG`         | 210 |   x    |        | API v21 only
| `SEND_ROOMS_MSG`        | 211 |        |   x    | API v21 only
| `GET_ROOMS_HOST_MSG`    | 212 |   x    |        | API v21 only
| `GET_VERSION_MSG`       | 213 |   x    |        |
| `SEND_VERSION_MSG`      | 214 |        |   x    |
| `GET_BANNED_MSG`        | 215 |   x    |        | Introduced with SRB2 2.0; deprecated in 2.1
| `PING_SERVER_MSG`       | 216 |   x    |        |
| `GET_EXT_SERVER_MSG`    | 217 |   x    |        | IPv6 support (unofficial)


### Getting the hosted game version

A client sends an empty message of type `GET_VERSION_MSG` to the server. The server then responds with a message of type `SEND_VERSION_MSG` containing the string representation of the supported game version. If `NULL` is supplied, the game client will skip ignore it's internal version check.

### Querying rooms

`GET_ROOMS_MSG`, `GET_ROOMS_HOST_MSG` and `SEND_ROOMS_MSG` are used to query/. `GET_ROOMS_HOST_MSG` is used to differentiate between room lookup for play or netgame hosting purposes.

By default the server will respond with multiple messges of type `SEND_ROOMS_MSG`, alternating between empty messages and messages containing information on exactly one room before finishing the list with a zero-length message of the same type. The data structure for rooms is explained below:

```C
struct room {
  char header[16];        // <- Unused, but necessary. Must be padded to fit
  uint32_t id;            // <- Little endian byte order
  char name[32];          // <- NUL-terminated, Must be padded to fit
  char description[255];  // <- NUL-terminated
}
```

If a user is banned from hosting netgames, the server may alternatively respond with `GET_BANNED_MSG` instead of a room list:

```C
struct ban{
  char header[16];               // <- Unused, but necessary. Must be padded to fit
  char ip_start[16|40];          // 40 in case of IPv6
  char ip_end[16|40];            // 40 in case of IPv6
  char expiration_datetime[32];  // <- NUL-terminated, Must be padded to fit
  char reason[255];              // <- NUL-terminated, Must be padded to fit
  int32_t hosting_only;
}
```

### Querying netgame lists

Empty messages of type `GET_SERVER_MSG`, `GET_SHORT_SERVER_MSG` or `GET_EXT_SERVER_MSG` are used to query netgame lists. Using the header's `room` field, the client may specify a room to be queried. When `room` is set to zero, all listed netgames will be supplied. `GET_EXT_SERVER_MSG` tells the server to supply messages with IPv6 support.

The server will respond using multiple messages of type `ANSWER_ASK_SERVER_MSG`, alternating between empty messages and messages containing a server structure as shown below. Finally the server sends a zero-length message to terminate the transaction:

```C
typedef struct {
  char head[16];
  char ip[16|40];   // 40 in case of GET_EXT_SERVER_MSG
  char port[8];
  char name[32];    // Shown in the server browser
  uint32_t room;    // API v21 only
  char version[8];  // String-representation of game version used by netgame
} netgame;
```

### Registering and removing Netgames

`ADD_SERVER_MSG`, `ADD_SERVERv2_MSG` and `REMOVE_SERVER_MSG` are used to register and remove netgames on the master server respectively. Each message containes a netgame data structure following the IPv4 version of the previous section's `struct`. The `ip` field is left blank - instead the netgame is identified using the IP issuing the request.

Registering and removing netgames yields no response from the master server.

`PING_SERVER_MSG` is issued periodically by the client as a keep-alive signal and to update the netgame info. LiquidMS treats it as idempotent to `ADD_SERVER_MSG`.
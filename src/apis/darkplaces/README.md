![LiquidMS logo](doc/liquidMS.svg)

LiquidMS DarkPlaces API
=======================

[LiquidMS] endpoint for the [DarkPlaces] API - an extended master server API for Quake III Arena.

[DarkPlaces]: https://icculus.org/twilight/darkplaces/
[LiquidMS]: <https://github.com/zibonbadi/liquidms/>
[gnuaffero]: <https://www.gnu.org/licenses/agpl-3.0.en.html>

USAGE
-----

    python __main__.py [-h] [config]

LiquidMS DarkPlaces can be run with custom configuration files by specifying `config`. By default `config.yaml` is assumed.

### Configuration

```YAML
hostname: localhost # Needs to match your server's outside hostname.
loglevel: "critical" | "error" | "debug | "info" | "warning"
port: 27950         # Publicly accessible server port
buffer_size: 2048   # Buffer size to fetch from client
max_timeout: 10
db:
  url: <RFC1738 Database URL>
  tables:
    netgames: chaosnet_netgames
```


Data Model
----------

```json
# The DarkPlaces protocol is an extension of Quake III Arena
"api_name" : "darkplaces",
"api_data" : {
  "ip": "203.0.113.42",
  "port": 27910,
  "protocol": "DarkPlaces", # Game protocol, not MS protocol
  "gametype": 0,
  "maxplayers": 32, 
  # clients, game (DarkPlaces protocol) sv_maxplayers, etc.
}
```

LiquidMS DarkPlaces integrates it's data model into [ChaosNet]. The above code snipped describes the DarkPlaces-specific API data and the API name to be used across Chaosnet. Since the DarkPlaces protocol is an extension of the Quake III Arena protocol, the game API name is defined as `"darkplaces"` to signify supporting it's extensions.

[ChaosNet]: ../chaosnet/README.md

Protocol
--------

Each request uses a TCP/UDP connection to transmit one or more *messages* between client and server, each starting with four `\xFF` bytes (e.g. `\xFF\xFF\xFF\xFFheartbeat DarkPlaces`). LiquidMS DarkPlaces supports the following messages: 

Message | Direction | Description
--|--|--
`heartbeat` | Client-to-server | Heartbeat/keep-alive signal. Server registration.
`getinfo` | Server-to-client | Authentication challenge.
`infoResponse` | Client-to-server | Netgame information. Response to `getinfo`.
`getservers` | Client-to-server |  Retrieve netgame list (IPv4-only)
`getserversResponse` | Server-to-Client | Transmit netgame list (IPv4-only)
`getserversExt` | Client-to-server | Retrieve netgame list (IPv6-compatible)
`getserversExtResponse` | Server-to-Client | Transmit netgame list (IPv6-compatible)

### heartbeat

```Python
"\xFF\xFF\xFF\xFFheartbeat <protocol>\n"
```

`heartbeat` is a registration/keep-alive signal sent by a netgame to the master server. `protocol` defines the *DarkPlaces-native master server protocol* being listed against (e.g. `QuakeArena-1`, `DarkPlaces`).

Game protocol names may use all printable ASCII characters **EXCEPT** `\`,`/`,`;`,`"` and `%`.

Each heartbeat is closed by a newline `\n`.

### getinfo/infoResponse

```Python
"\xFF\xFF\xFF\xFFgetinfo <challenge>"
```

`getinfo` issues a challenge string back to the netgame in response to a `heartbeat` request. If the netgame mirrors the challenge correctly, the netgame is verified and added to/updated on the master server.

```Python
"\xFF\xFF\xFF\xFFinfoResponse\n[KEY...]"
```

`infoResponse` is immediately followed by a newline followed by a series of *keys* following the format `\<key>\<value>`. Each `infoResponse` **MUST** supply the following keys:

- `\challenge\<challenge>`: Challenge string previously received from `getinfo`.
- `\protocol\<protocol>`: Protocol number identical to that issued `getservers`.
- `\clients\<num>`: Currently active players on the netgame.
- `\sv_maxclients\<num>`: Maximum supported players. Must not be 0.

Additionally, most clients also supply `gamemode=<num|string>` to allow filtering by game type. Protocol-wise, the value may be a string, but must be mapped to a numerical value representing the game type.

### getservers/getserversExt

```Python
"\xFF\xFF\xFF\xFFgetservers [gamename] <version> [params...]"
"\xFF\xFF\xFF\xFFgetserversExt [gamename] <version> [params...]"
```

Each request must contain a game/version ID followed by zero or more whitespace-separated parameters.

The game protocol `DarkPlaces` also requires `gamename` to be defined before the ID. This name may be different from the game protocol given in `heartbeat`, but follows the same character restrictions.

The parameters `empty` and `full` define filtering for empty/full netgames, respectively.  
The parameter `gametype=<num>` defines filtering netgames by game type (zero by default).  
The following parameters are supported for Quake 3 Arena compatibility:

- `ffa`: Free-for-all mode. Equivalent to `gametype=0`
- `tourney`: Tourney mode. Equivalent to `gametype=1`
- `team`: Team deathmatch mode. Equivalent to `gametype=3`
- `ctf`: Capture the flag mode. Equivalent to `gametype=4`


### getserverResponse/getserverExtResponse

```Python
"\xFF\xFF\xFF\xFFgetserverResponse\\[...]\x04\0\0\0"
"\xFF\xFF\xFF\xFFgetserverExtResponse/[...]\\[...]\x04\0\0\0"
```

`getserverResponse`/`getserverExtResponse` transmits a list of netgames from master server to client. `getserverResponse` may only transmits IPv4-based netgames while `getserverResponse` includes IPv6-based netgames.

Each IPv4 netgame is encoded with a preceding backslash (`\`).  
The IPv4 address is then byte-encoded into four bytes (big endian) followed by two bytes for the port number (big endian).

Each IPv6 netgame is encoded with a preceding forward slash (`/`).  
The IPv6 address is then byte-encoded into 16 bytes (big endian) followed by two bytes for the port number (big endian).

Netgame lists may be split across multiple chunks across multiple packets. Once **all** servers have been transmitted, the master server terminates the list using an `EOT` byte followed by `\x00\x00\x00`

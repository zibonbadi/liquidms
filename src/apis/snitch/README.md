![LiquidMS logo](doc/liquidMS.svg)

LiquidMS Snitch API
=====================

DEPRECATION NOTICE
------------------

LiquidMS Snitch is **DEPRECATED** and should **not** be hosted. Instead, you should host the [Chaosnet] API.


### Migration

This Snitch endpoint will continue to work. However, it will now return the HTTP headers `Deprecation` and `X-LiquidMS-Deprecated`.

To migrate your LiquidAnacron config to chaosnet, simply use `api: chaosnet` and point your jobs to the peer's INBOX/OUTBOX URL. Read more in the [LiquidAnacron manual]

[LiquidAnacron manual]: ../../liquidanacron/README.md

### *Why is Snitch being deprecated?*

Snitch was originally designed as a simple proof-of-concept to demonstrate LiquidMS'
distributed hosting capabilites and is tied to the data structures of the [SRB2HTTP]
and [SRB2Kart] APIs. Chaosnet is instead designed around [ActivityPub], which is a
stable, well-known web standard that provides much more flexibility to support non-SRB2 APIs.

[ActivityPub]: https://www.w3.org/TR/activitypub/
[Chaosnet]: ../chaosnet/README.md
[SRB2HTTP]: ../srb2http/README.md
[SRB2Kart]: ../srb2kart/README.md


LEGAL NOTICE
------------

This PHP webserver is part of [LiquidMS] and should not be distributed
separately.  LiquidMS and this API server are licensed under the [GNU
AFFERO GENERAL PUBLIC LICENSE Version 3][gnuaffero].

[LiquidMS]: <https://github.com/zibonbadi/liquidms/>
[gnuaffero]: <https://www.gnu.org/licenses/agpl-3.0.en.html>


SUMMARY
-------
The *LiquidMS Snitch API server* provides you with endpoints to serve
LiquidMS' *Snitch API*. This API allows LiquidMS to mirror other master
servers and synchronise with other LiquidMS servers, using a *Snitch script*.


INSTALLATION
------------

1. Copy this folder to where you wanna host your server.
2. Create `config.yaml` to configure your server (see below)

   **NOTE:** LiquidMS relies on database-side mechanisms such as `TRIGGER`s
   and `EVENT`s. Using MySQL/MariaDB for your database is recommended.
3. (optional: Configure your HTTP server using the local `.htaccess` or similar)
4. Profits :3

### Sample `config.yaml`

```yaml
---
basepath: /liquidms/snitch # Base URL path. Use this if you're hosting under a subdirectory
loglevel: verbose # How much text the server logs per-request (Default: "quiet")
db: # Database settings
  dsn: <DSN connection string>
  user: <Database user>
  password: <Database user's password>
apis: # List of APIs to serve
  srb2http:
    tables:
      bans: srb2http_bans
      servers: srb2http_servers
      versions: srb2http_versions
      rooms: srb2http_rooms
  srb2kart:
    tables:
      bans: srb2kart_bans
      servers: srb2kart_servers
      versions: srb2kart_versions
...
```

### Caveat: Fetch from the Snitch API if possible

When running a snitch script, please let it fetch data from the Snitch API
over any other API.

Since most game APIs aren't usually designed with master server mirroring
in mind, The ´origin´s of netgames pulled from other APIs may not fully
represent their true origin when pulled from another LiquidMS server.

By pulling from LiquidMS' Snitch API directly, you can guarantee that
origins of universe netgames remain consitent across snitches, avoiding a
potential [broadcast storm] across the wider LiquidMS network.

[broadcast storm]: <https://en.wikipedia.org/wiki/Broadcast_storm>


Endpoints
---------

All endpoints are assumed to be hosted under `${BASEPATH}`:

`/` (GET request)
: Emits a CSV table of netgames without header.

`/` (POST request)
: Expects a file upload of a CSV table `snitch.csv` without header.

The CSV tables emitted by and expected from the Snitch API follow this
schema, in order:

`host`
: Netgame host address (IPv4 or IPv6)

`port`
: Netgame port

`servername`
: URL-encoded server name

`version`
: Version string, e.g. (v.2.2.15)

`roomname`
: Name of the room the netgame belongs to

`origin`
: Host of the room's origin

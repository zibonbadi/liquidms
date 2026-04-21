![LiquidMS logo](doc/liquidMS.svg)

LiquidMS web frontend
=====================

SUMMARY
-------

This PHP webserver is part of [LiquidMS] and should not be distributed
separately.  LiquidMS and this API server are licensed under the [GNU
AFFERO GENERAL PUBLIC LICENSE Version 3][gnuaffero].

[LiquidMS]: <https://github.com/zibonbadi/liquidms/>
[gnuaffero]: <https://www.gnu.org/licenses/agpl-3.0.en.html>

The *LiquidMS web frontend* serves as a convenient way to browse netgames known to a LiquidMS node. It based on *services*, such that each master server API can be browsed through it's own miniature master server browser.

INSTALLATION
------------

1. Install PHP dependencies: `composer install`
2. Copy this folder to where you wanna host your server.
3. Create `config.yaml` to configure your server (see below)
4. (optional: Configure your HTTP server using the local `.htaccess` or similar)
5. Profits :3

### Sample `config.yaml`

```yaml
---
basepath: /web # Base path the web server is hosted under. Use this for subdirectory-based hosting
db: # Database settings
  dsn: <DSN connection string>
  user: <Database user>
  password: <Database user's password>
settings:
  do_srb2query: false
services: # Create one subsection for each API service you wanna reference
  some-srb2http-api:
    _api: SRB2HTTP
    srb2http_servers: srb2http_servers
  some-srb2kart-api:
    _api: SRB2Kart
    srb2kart_servers: srb2kart_servers
...
```

Endpoints
---------

All endpoints are assumed to be hosted under `${BASEPATH}`:

`/`
: Loads the server browser page.

`/css`
: CSS stylesheets

`/img`
: Image resources.

`/js`
: JavaScript resources.

`/api/srb2query`
: Internal. Requires `settings.do_srb2query = true`. Used to relay SRB2Query-related netgame information. The following query options are required:

  - `?host`: Netgame host address
  - `?port`: Netgame host port


`/api/dbquery/<service name>`
: Query a service's netgames. Service names are derived from `config.yaml`


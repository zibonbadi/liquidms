![LiquidMS logo](../liquidMS.svg)

SRB2Kart HTTP API
=================

Preface
-------

**NOTE:** All information noted here has been derived from clean room
testing of existing SRB2Kart HTTP Master Server implementations. As such,
it has been created using limited information and may not reflect any
official specification.


API Basics
----------

Defining `/` as the HTTP server's base, all endpoints/actions take the
following form:

    /games/[:game]/[:action]?<query>
    
The only exception to this rule is given for `/rules?<query>` which serves
a game-agnostic function of listing the local master server rules.


General actions
----------------

`GET /rules?<query>`
: Return server rules (`Content-Type: text/plain`)


SRB2Kart Actions
----------------

`GET /games/SRB2Kart/versions?<query>`
: List `<modversion> <versionstring>`.

`GET /games/SRB2Kart/[:modversion]/servers?<query>`
: List all servers. The output is formatted exactly as specified for the [V1 API][v1spec].

[v1spec]: <https://web.archive.org/web/20220205110841/https://mb.srb2.org/MS/tools/api/v1/>

`POST /games/SRB2Kart/[:modversion]/register?<query>`
: Add new netgame listing

`POST /servers/[:netgameid]/update?<query>`
: Update netgame listing

`POST /servers/[:netgameid]/unlist?<query>`
: Remove netgame from list


SRB2Kart Queries
----------------

### API version

The query parameter `?v=<VALUE>` defines API version used. The following
table lists some typical API versions corresponding to their game versions:

Game version | API version `?v=`
--|--
v1.3 | 2
v1.6 | 2.2

If no API version is given, all API endpoints return `Missing API version`, if the
server can't recognize/support the given API version, `Unknown API version` shall be returned.


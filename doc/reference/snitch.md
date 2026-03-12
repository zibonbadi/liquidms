![LiquidMS logo](../liquidMS.svg)

Snitch API
==========

LiquidMS features a custom extension called the *Snitch API*. Through use
of the endpoint `/liquidms/snitch` via HTTP GET and POST requests,
independent hosts can contribute universe netgame mirrors to other LiquidMS
nodes without the need for database access authorization on behalf of the
peer, as it will automatically sanitize the supplied data based on it's
internal netgame hosting policy.

Basics
------

By supplying files of type `text/csv;header=absent` to a peer's
HTTP API, hosts can actively contribute to that peer's database. The CSV
data that is both supplied as well as expected by LiquidMS nodes is
defined by the following structure:

	<host>,<port>,<servername>,<version>,<roomname>,<origin>

To easily contribute data towards a peer ("snitching"), the configuration
file `config.yaml` defines the fields `fetchmode` with possible values of
`fetch` and `snitch` as well as the collection `snitch`. When set to
*fetch*, the fetch scripts `fetch.php` and `liquidanacron.php` will attempt
to provide automatically generated SQL queries to the configured ODBC
connection, as if these were to be used for a self hosted node setup. When
set to *snitch*, these scripts will instead attempt to supply their data to
all peers specified in the *snitch* collection of the configuration file.

```YAML
fetchmode: "fetch" | "snitch"
snitch:
  "http://localhost:8080"
  "http://my-fav-liquidms.node"
```

Snitch Version 2
----------------

LiquidMS v1.2+ introduced a new, backwards-compatible version of the Snitch
API, capable of handling the various master server APIs now implemented and
managed in simultaneously by LiquidMS. To access it, multiple HTTP headers
must be defined, otherwise LiquidMS will fall back to using Snitch version 1:

`X-Lq-Snitch-Version`
: Defines the LiquidMS Snitch API version. If 1, invalid or undefined,
LiquidMS will fall back to using Snitch version 1.

`X-Lq-Snitch-Accept`
: Comma separated list of accepted APIs using LiquidMS-internal
  connotations such as "v1", "kart", and likewise. If not defined, yet
  `X-Lq-Snitch-Version` is given, LiquidMS will proceed to provide all
  available netgames indiscriminately.

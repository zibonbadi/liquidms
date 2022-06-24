![LiquidMS logo](../liquidMS.svg)

Snitching API
=============

LiquidMS features a custom extension called the *Snitch API*. Through use
of the endpoint `/liquidms/snitch` via HTTP GET and POST requests,
independent hosts can contribute universe netgame mirrors to other LiquidMS
nodes without the need for database access authorization on behalf of the
peer, as it will automatically sanitize the supplied data based on it's
internal netgame hosting policy.

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



![LiquidMS logo](../liquidMS.svg)

Snitching
=========

Although LiquidMS is easily able to fetch and mirror the netgames of any
V1 compatible master server, using the regular fetch script comes with some
limitations in case you're planning to host a multi-node LiquidMS network:

1. Due to restrictions of the V1 API, fetching from a LiquidMS node
   attributes all fetched netgames as hosted on said node, independent of
   their actual origin.
2. Commiting the fetched data to a node's database requires an authorized
   ODBC connection to the node's database. This may be slow and insecure
   when run over public networks and restricts the contribution of data to
   the node's host and their authorized administrators.
3. Each fetch script instance may only contribute it's data to one database
   at a time, preventing the possibility of independent volunteer fetches.

Because of this, LiquidMS features a custom API called the *Snitch API*.
This API allows by sending CSV data over HTTP between fetchscripts and
LiquidMS nodes on which this API is enabled.

To control for volunteer requests potentially submitting bad or malicious
data, all will be sufficiently sanitized by the node and database upon each
request before being inserted into the dataset.

For more technical information on the Snitch API, see the [reference].

[reference]: ../reference/snitch.md


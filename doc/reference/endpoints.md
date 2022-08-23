![LiquidMS logo](../liquidMS.svg)

HTTP Endpoints
==============

`/liquidms/license`
: A Markdown copy of the License ([GNU AFFERO GENERAL PUBLIC LICENSE Version 3][gnuaffero]).

[gnuaffero]: <https://www.gnu.org/licenses/agpl-3.0.en.html>

`/liquidms/browse`
: Server browser

`/liquidms/browse/(css|img|js|static)/*`
: Server browser asset paths. Assets have to be located in
  `$sbpath/(css|img|js|static)/*` respectively.

`/liquidms/srb2query`
: SRB2Query API. Accepts the following query parameters:

  - `?hostname`: Netgame hostname.
  - `?port`: UDP Port (Default: 5029)

`/v1`
: V1 API. For a full specification, see [the original specification by James R.][v1spec]

[v1spec]: <https://web.archive.org/web/20220205110841/https://mb.srb2.org/MS/tools/api/v1/>

`/v1/servers`
: V1 API.

`/v1/servers/update`
: Update a netgame. Requires the following POST request fields:

  - `?title`: New netgame title

`/v1/servers/unlist`
: Unlist a netgame. Requires a POST request

`/v1/rooms`
: Room information for all rooms.

`/v1/rooms/<room ID>`
: Room information for `<room ID>`.

`/v1/rooms/<room ID>/servers`
: List servers of `<room ID>`.

`/v1/rooms/<room ID>/register`
: Register a server to `<room>`. Requires the following POST request fields:

  - `?title`: Netgame title
  - `?port`: Netgame port
  - `?version`: Literal game version

`/v1/versions/<version ID>`
: V1 API version definitions.


![LiquidMS logo](doc/chaosnet.svg)

ChaosNet (Beta 1)
=================

*ChaosNet* (formerly Snitch V2) is the successor API for [LiquidMS Snitch].
It replaces the CSV-based legacy protocol with an [ActivityPub]-,
[ActivityStreams]- and [JSON-LD]-based server-to-server and client-to-server API.

ChaosNet allows LiquidMS nodes to synchronise netgame data with one another,
support any yet-unknown master server API's netgame data format, and share
netgame data through the ActivityPub protocol.

[ActivityPub]: https://www.w3.org/TR/activitypub/
[ActivityStreams]: https://www.w3.org/TR/activitystreams-core/
[JSON-LD]: https://json-ld.org/
[LiquidMS Snitch]: ../snitch/README.md


Design Goals
------------

3. **Distributed synchronisation**: LiquidMS nodes are designed to
   synchronize among one another without central coordination nor
   broadcast storms. This lets LiquidMS build a resilient, consistent,
   and distributed gaming network hosted by it's community.

2. **ActivityPub-based**: ActivityPub defines stable, well-known web
   mechanisms for client-to-server and server-to-server communication.
   This allows LiquidMS to reliably and efficiently build a distributed
   online multiplayer gaming network while simultaneously integrating
   this network into the wider Fediverse.

3. **API-agnostic data model**: ChaosNet is designed to support data from
   any potential master server API, regardless of which game it came from.
   This also means you may define your own JSON-LD spec on top of ChaosNet
   to easily integrate your future online games into the Fediverse.

4. **Bulk and individual updates**: For efficiency's sake, ChaosNet supports
   both bulk requests through the `Deliver` activity as well as manage
   singular netgame lifecycles using `Create`, `Update` and `Delete`.

5. **Frequent updates**: Netgames update frequently and ChaosNet is designed
   with that in mind. That's why the protocol is stateless and idempotent,
   to allow updating the network as often as the nodes allow.


Scope of Beta 1
---------------

- Service actor with inbox, outbox, following, followers
- `POST /inbox` for Create, Update, Delete, Deliver, Follow, (Undo-)Follow
- `GET /outbox` activity log
- `GET /collection` current netgame state (the primary listing endpoint)
- Node-to-node bulk sync via `Deliver`
- Follow/Accept subscription model for push distribution
- Unified database table with JSON `api_data`
- liquidanacron adapters (`fetchUpdate_chaosnet`, `snitch_chaosnet`)

### Stubbed elements (no-op placeholder)

- HTTP Signatures (authentication)

### Not included (post-Beta 1)

- Social features (Join/Leave, Person actors)
- WebFinger discovery
- Game clients implementing ActivityPub directly
- Shared inbox for multi-actor deployments



Configuration
-------------

ChaosNet is configured via `config.yaml`, which looks like this:

```yaml
---
basepath: /api/chaosnet
loglevel: verbose
node_host: "liquidms.example"
node_actor_uri: "https://liquidms.example/api/chaosnet"
db:
  dsn: mysql:host=db;dbname=liquidms
  user: dbuser
  password: changeme
...
```

`basepath`: The path ChaosNet is hosted under. Useful for shared hosting setups.

`loglevel`: `quiet` suppresses, `verbose` enables additional logging.

`node_host`: hostname of your LiquidMS node (default: `"localhost"`).

`node_actor_uri`: Full URI of the actor document (optional; defaults to `https://{node_host}/api/chaosnet`).

`db.dsn`: DSN connection string for your database.

`db.user`: Database username.

`db.password`: Your database user's password.


ActivityPub and Web API
-----------------------

Under ChaosNet, every LiquidMS node is an ActivityPub `Service` actor.
Netgames are represented as ActivityStreams `Game` objects, which are
passed through various ActivityPub activities.
All of these are further specified below.

### Endpoints

All endpoints return `Content-Type: application/activity+json`.

| Method | Path | Description |
|--------|------|-------------|
| `GET`  | `/api/chaosnet` | Service actor document |
| `POST` | `/api/chaosnet/inbox` | Receive activities |
| `GET`  | `/api/chaosnet/inbox` | Inbox collection (empty for now) |
| `GET`  | `/api/chaosnet/outbox` | Recent activity log (OrderedCollection, paginated) |
| `GET`  | `/api/chaosnet/collection` | Current netgames (OrderedCollection, paginated) |
| `GET`  | `/api/chaosnet/following` | Outbound subscriptions |
| `GET`  | `/api/chaosnet/followers` | Inbound subscriptions |

`GET` endpoints are paginated, requiring the following query parameters:

| Parameter | Type | Default | Maximum |
|-----------|------|---------|---------|
| `page`    | int  | 1       | —       |
| `pagesize` | int | 50 (collection) / 20 (outbox) | 200 / 100 |

Paginated responses includes a sub-object `links` with fields `first`, `last`, `next`, `prev`, `self`.

### Actor Model

Each LiquidMS node is an ActivityPub `Service` actor. The actor document behind `GET /api/chaosnet` (sample basepath) looks like this:

```json
{
    "@context": "https://www.w3.org/ns/activitystreams",
    "type": "Service",
    "id": "https://liquidms.example/api/chaosnet",
    "name": "LiquidMS node at liquidms.example",
    "inbox": "https://liquidms.example/api/chaosnet/inbox",
    "outbox": "https://liquidms.example/api/chaosnet/outbox",
    "following": "https://liquidms.example/api/chaosnet/following",
    "followers": "https://liquidms.example/api/chaosnet/followers",
    "generator": {
        "type": "Application",
        "name": "LiquidMS ChaosNet"
    },
    "url": "https://liquidms.example/api/chaosnet/collection"
}
```

ChaosNet `Service` actors are identified by their URI. This ensures a unique, queryable ID without the need for a user account system.

You can find more info on Actor identification under [Configuration](#configuration).

### Game Object

```json
{
    "type": "Game",
    "id": "https://liquidms.example/<sha256-id>",
    "name": "Alice's Server",
    "game_host": "203.0.113.42",
    "game_port": 5029,
    "game_api_name": "srb2http",
    "game_api_data": {
        "servername": "Alice's Server",
        "version": "2.2.15",
        "roomname": "Standard"
    },
    "external_origin": "mb.srb2.org",
    "origin_node": "https://node-a.example/api/chaosnet",
    "path": [
        "https://node-a.example/api/chaosnet",
        "https://node-b.example/api/chaosnet"
    ],
    "updated": "2026-05-27 12:00:00"
}
```

ChaosNet is designed to carry netgame data from any master server API
without prior knowledge of that API's schema. Aside from the usual fields required by ActivityPub/ActivityStreams, ChaosNet is based on these game-related fields:

- `name`: sanitized Netgame name for use within LiquidMS (printable characters stripped, URL-encoded).
- `game_api_host`: IP address/hostname the game is hosted under
- `game_api_port`: the hosted game's port
- `game_api_name`: a string identifier for the API used (e.g. `"srb2http"`,
  `"srb2kart"`, `"luanti"`)
- `game_api_data`: a JSON object containing all API-specific fields


The following table describes the full set of fields: 

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | `string` | always `"Game"` |
| `id` | `string` (URI) | yes | Globally unique identifier |
| `name` | `string` | yes | Client-facing server name, normalized (non-printable chars stripped, URL-decoded) |
| `game_host` | `string` | yes | IP address or hostname of the game server |
| `game_port` | `int` | yes | Port of the game server |
| `game_api_name` | `string` | yes | API identifier, e.g. `"srb2http"`, `"srb2kart"` |
| `game_api_data` | `object` | yes | API-specific data. Specifics on this data are documented in their respective API's documentation. |
| `external_origin` | `string` | no | Source before entering LiquidMS (e.g. `"mb.srb2.org"`). `NULL` if ChaosNet-native. |
| `origin_node` | `string` (URI) | no | First LiquidMS node that propagated this netgame |
| `path` | `[string]` | no | Actor URIs this netgame has passed through |
| `updated` | `string` | yes | Last-updated timestamp |

### Activities

#### Client-to-Server activities

Activity types accepted by `POST /inbox`:

| Activity | Effect |
|----------|--------|
| `Create{object: Game}` | INSERT a new netgame |
| `Update{object: Game}` | UPDATE an existing netgame |
| `Delete{object: Game}` | DELETE a netgame by id or (host, port, api_name) |

#### Server-to-Server activities

| Activity | Effect |
|----------|--------|
| `Deliver{object: OrderedCollection{items: [Game]}}` | Batch upsert of all games in the collection. Used for initial sync and periodic bulk updates. |
| `Follow{object: Service}` | Subscribe to the target node's outbox. Auto-responded with `Accept`. |
| `Accept{object: Follow}` | Confirm a follow request (sent automatically by the followed node). |
| `Reject{object: Follow}` | Deny a follow request. |
| `Undo{object: Follow}` | Unfollow (wraps the original `Follow` activity). |

#### Path-based loop prevention

To prevent broadcast storms, ChaosNet implements a loop prevention mechanism through use of a `path` array:

- Every inbound activity is checked: if this node's actor URI is already
  in the netgame's `path` array, the activity is **silently skipped**.
- On inbound `Deliver` activities, the receiving node appends its own
  actor URI to each netgame's `path` before storing and before
  forwarding to followers.
- `origin_node` is set once on first insert and never overwritten by
  subsequent updates from other nodes - ensuring the original source
  is always preserved.


Database Schema
---------------

LiquidMS implements ChaosNet's netgame data model through the SQL table `chaosnet_netgames`.
For additional performance, database indices are defined for
`(last_synced_at DESC)` and `(api_name)`. 
Below is a full definition of all data fields:

| Column | Type | Description |
|--------|------|-------------|
| `id` | `VARCHAR(64)` PK | `SHA256(host|port|api_name)` — deterministic dedup |
| `host` | `VARCHAR(45)` | IP address or hostname of the game server |
| `port` | `SMALLINT UNSIGNED` | Game server port |
| `api_name` | `VARCHAR(32)` | API identifier, e.g. `"srb2http"`, `"srb2kart"`, `"chaosnet"` |
| `api_data` | `JSON` | API-specific fields. Always includes a `name` field (normalized). |
| `external_origin` | `VARCHAR(256)` | Source of the netgame data before entering the LiquidMS network. `NULL` if ChaosNet-native. |
| `origin_node` | `VARCHAR(256)` | Actor URI of the first LiquidMS node that propagated this netgame. Set once on insert, never overwritten. |
| `path` | `JSON` | Array of actor URIs this netgame has passed through. Used to prevent re-delivery to nodes that already have it. |
| `updated_at` | `DATETIME` | Auto-set on insert and update. |
| `last_synced_at` | `DATETIME` | Receiver's timestamp of last sync activity. Used for culling. Indexed DESC. |

To implement ChaosNet's ActivityPub API, LiquidMS also defines the tables `chaosnet_follows` and `chaosnet_outbox`.

### `chaosnet_follows`

| Column | Type | Description |
|--------|------|-------------|
| `actor_uri` | `VARCHAR(256)` | URI of the following/followed actor |
| `inbox_uri` | `VARCHAR(256)` | Inbox URI of the follower (for forwarding) |
| `state` | `ENUM('pending','accepted','rejected')` | Follow state |
| `direction` | `ENUM('inbound','outbound')` | Inbound (they follow us) or outbound (we follow them) |
| `created_at` | `DATETIME` | Timestamp |

### `chaosnet_outbox`

| Column | Type | Description |
|--------|------|-------------|
| `id` | `VARCHAR(256)` PK | Unique activity ID |
| `type` | `VARCHAR(32)` | Activity type |
| `actor` | `VARCHAR(256)` | Actor URI |
| `object` | `JSON` | Full JSON-LD activity object |
| `published` | `DATETIME` | Publication timestamp, indexed DESC |

### Culling lifecycle

A scheduled MariaDB event (`chaosnet_netgames_cull`) runs every minute and
deletes rows where `last_synced_at` is older than 20 minutes. This ensures
stale entries do not accumulate.

Culling uses `last_synced_at` (the **receiver's** timestamp) rather than
`updated_at` (the sender's reported timestamp). This avoids clock skew
issues - the culling decision is always based on the local database server's
clock.

Culling is **silent**: no `Delete` activity is emitted to the outbox or
forwarded to followers. If the netgame is still alive, the owning node will
re-push it on the next sync interval.


Migration from Snitch V1
------------------------

Snitch V1 (the CSV-based protocol at `/api/snitch`) is **deprecated**.

- Existing Snitch V1 endpoints continue to work but return `Deprecation`
  and `X-LiquidMS-Deprecated` HTTP headers.
- New deployments should use `api: chaosnet` in their liquidanacron config.
- To migrate, point your dest configuration to the peer's
  `/api/chaosnet/inbox` URL instead of `/api/snitch`.


License
-------

    LiquidMS - federated master server
    Copyright (C) 2021-2026 Zibon Badi et al.
    
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.
    
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.
    
    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.

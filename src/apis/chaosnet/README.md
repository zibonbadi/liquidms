![LiquidMS logo](doc/chaosnet.svg)

ChaosNet (Beta 2)
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

1. **Distributed synchronisation**: LiquidMS nodes are designed to
   synchronize among one another without central coordination nor
   broadcast storms. This lets LiquidMS build a resilient, consistent,
   and distributed gaming network hosted by its community.

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

5. **State machine lifecycle**: Netgame rows transition through defined
   states (`new` → `active` → `stale` → `deleted`). ChaosNet detects
   changes written by any API to the shared database and propagates them
   across the social graph — even if the change came from a non-ChaosNet
   API.

6. **Frequent updates**: Netgames update frequently and ChaosNet is designed
   with that in mind. That's why the protocol is stateless and idempotent,
   to allow updating the network as often as the nodes allow.


Scope of Beta 2
---------------

- Per-API Service actors with independent inbox, outbox, following, followers
- `GET /api/chaosnet` root directory listing of available API actors
- `POST /api/chaosnet/services/<api>/inbox` for Create, Update, Delete, Deliver, Follow
- `GET /api/chaosnet/services/<api>/outbox` per-API activity log
- `GET /api/chaosnet/services/<api>/collection` per-API netgame listing
- `GET /api/chaosnet/services/<api>/following` and `/followers`
- Node-to-node bulk sync via `Deliver`
- Follow/Accept subscription model for push distribution
- Unified database table with JSON `api_data`
- State machine lifecycle (new → active → stale → deleted)
- `processPendingRows()` — detects `new`/`stale` rows left by other APIs,
  creates outbox entries, transitions states, and optionally forwards to
  followers
- Inline forwarding — received activities are forwarded to all accepted
  followers immediately during the inbox request
- Outbox logging for pending rows on `GET /collection`
- liquidanacron adapters (`fetchUpdate_chaosnet`, `snitch_chaosnet`)

### Stubbed elements (no-op placeholder)

- HTTP Signatures (authentication)

### Not included (post-Beta 2)

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
apis:
  - srb2http
  - srb2kart
db:
  dsn: mysql:host=db;dbname=liquidms
  user: dbuser
  password: changeme
...
```

`basepath`: The path ChaosNet is hosted under. Useful for shared hosting setups.

`loglevel`: `quiet` suppresses, `verbose` enables additional logging.

`node_host`: hostname of your LiquidMS node (default: `"localhost"`).

`node_actor_uri`: Full URI of the node's root actor document (optional; defaults to `https://{node_host}/api/chaosnet`).

`apis`: List of game APIs this node supports. Each API gets its own Service actor at `/api/chaosnet/services/<api>`.

`db.dsn`: DSN connection string for your database.

`db.user`: Database username.

`db.password`: Your database user's password.


ActivityPub and Web API
-----------------------

ChaosNet implements a multi-actor model. The root endpoint (`/api/chaosnet`)
returns a directory listing of available per-API Service actors. Each
supported game API has its own dedicated actor, inbox, outbox, collection,
and follower graph, hosted under `/api/chaosnet/services/<api>`.

This design lets nodes independently manage which APIs they host and
propagate, scoping the social graph by API support.

### Root endpoint

| Method | Path | Description |
|--------|------|-------------|
| `GET`  | `/api/chaosnet` | Directory listing of available API actors |

### Per-API endpoints

All endpoints return `Content-Type: application/activity+json`.

| Method | Path | Description |
|--------|------|-------------|
| `GET`  | `/api/chaosnet/services/<api>` | Service actor document |
| `POST` | `/api/chaosnet/services/<api>/inbox` | Receive activities for this API |
| `GET`  | `/api/chaosnet/services/<api>/inbox` | Inbox collection (empty for now) |
| `GET`  | `/api/chaosnet/services/<api>/outbox` | Recent activity log (OrderedCollection, paginated) |
| `GET`  | `/api/chaosnet/services/<api>/collection` | Current netgames for this API (OrderedCollection, paginated) |
| `GET`  | `/api/chaosnet/services/<api>/following` | Outbound subscriptions for this API |
| `GET`  | `/api/chaosnet/services/<api>/followers` | Inbound subscriptions for this API |

`GET` endpoints are paginated, requiring the following query parameters:

| Parameter | Type | Default | Maximum |
|-----------|------|---------|---------|
| `page`    | int  | 1       | —       |
| `pagesize` | int | 50 (collection) / 20 (outbox) | 200 / 100 |

Paginated responses includes a sub-object `links` with fields `first`, `last`, `next`, `prev`, `self`.

### Root Actor Model

The root endpoint `/api/chaosnet` returns a collection of available API actors:

```json
{
    "@context": "https://www.w3.org/ns/activitystreams",
    "id": "https://liquidms.example/api/chaosnet",
    "type": "Collection",
    "name": "LiquidMS node at liquidms.example",
    "items": [
        {"type": "Service", "id": "https://liquidms.example/api/chaosnet/services/srb2http", "name": "srb2http"},
        {"type": "Service", "id": "https://liquidms.example/api/chaosnet/services/srb2kart", "name": "srb2kart"}
    ]
}
```

### Per-API Actor Model

Each configured API gets a `Service` actor. The actor document behind
`GET /api/chaosnet/services/srb2http` looks like this:

```json
{
    "@context": "https://www.w3.org/ns/activitystreams",
    "type": "Service",
    "id": "https://liquidms.example/api/chaosnet/services/srb2http",
    "name": "srb2http netgames at liquidms.example",
    "inbox": "https://liquidms.example/api/chaosnet/services/srb2http/inbox",
    "outbox": "https://liquidms.example/api/chaosnet/services/srb2http/outbox",
    "following": "https://liquidms.example/api/chaosnet/services/srb2http/following",
    "followers": "https://liquidms.example/api/chaosnet/services/srb2http/followers",
    "generator": {
        "type": "Application",
        "name": "LiquidMS ChaosNet"
    },
    "url": "https://liquidms.example/api/chaosnet/services/srb2http/collection"
}
```

ChaosNet `Service` actors are identified by their URI. This ensures a unique, queryable ID without the need for a user account system.

### Game Object

```json
{
    "type": "Game",
    "id": "https://liquidms.example/api/chaosnet/services/srb2http/collection/<sha256-id>",
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
    "origin_node": "https://node-a.example/api/chaosnet/services/srb2http",
    "path": [
        "https://node-a.example/api/chaosnet/services/srb2http",
        "https://node-b.example/api/chaosnet/services/srb2http"
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

Activity types accepted by `POST /api/chaosnet/services/<api>/inbox`:

| Activity | Effect |
|----------|--------|
| `Create{object: Game}` | INSERT a new netgame. `game_api_name` must match the actor's API. Sets row state to `active`. |
| `Update{object: Game}` | UPDATE an existing netgame. `game_api_name` must match the actor's API. Sets row state to `active`. |
| `Delete{object: Game}` | Set row state to `deleted` by id or (host, port, api_name). |

#### Server-to-Server activities

| Activity | Effect |
|----------|--------|
| `Deliver{object: OrderedCollection{items: [Game]}}` | Batch upsert of all games in the collection. Items must have matching `game_api_name`. Used for initial sync and periodic bulk updates. |
| `Follow{object: Service}` | Subscribe to the target API actor's outbox. Auto-responded with `Accept`. |
| `Accept{object: Follow}` | Confirm a follow request (sent automatically by the followed node). |
| `Reject{object: Follow}` | Deny a follow request. |
| `Undo{object: Follow}` | Unfollow (wraps the original `Follow` activity). |

#### Inline forwarding

Every `Create`, `Update`, `Delete`, and `Deliver` activity received via
a per-API inbox is immediately forwarded to all accepted inbound followers
of that same API during the same request. This ensures netgame changes
propagate across the social graph without requiring a separate polling daemon.

Forwarding is best-effort with a 10-second timeout per follower. Failed
forwards are logged but do not affect the response.

#### Path-based loop prevention

To prevent broadcast storms, ChaosNet implements a loop prevention mechanism through use of a `path` array:

- Every inbound activity is checked: if this API actor's URI is already
  in the netgame's `path` array, the activity is **silently skipped**.
- On inbound `Deliver` activities, the receiving node appends its own
  API actor URI to each netgame's `path` before storing and before
  forwarding to followers.
- On forwarding, the forwarding node appends its own API actor URI to each
  game object's `path` — preventing the activity from being re-delivered
  back to nodes that have already seen it.
- `origin_node` is set once on first insert and never overwritten by
  subsequent updates from other nodes - ensuring the original source
  is always preserved.

#### Pending row processing

Before processing any inbound activity, ChaosNet scans `chaosnet_netgames`
for rows left in `new` or `stale` state by other APIs (e.g. srb2http,
srb2kart) that share the same database table. This scan is global across
all APIs. For each pending row, ChaosNet uses the row's own `api_name`
to determine which API actor to record the activity under and which
followers to forward to.

| Current state | Action | Next state |
|---------------|--------|------------|
| `new` | Create or Update outbox entry under the row's API actor (heuristic: `Update` if prior activity exists in outbox, `Create` otherwise). Forward to that API's followers if applicable. | `active` |
| `stale` | Create Delete outbox entry under the row's API actor. Forward to that API's followers if applicable. | `deleted` |

When triggered by `GET /collection`, pending rows are logged to the outbox
but not forwarded (HTTP forwarding only happens during inbox requests).


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
| `state` | `ENUM('new','active','stale','deleted')` | Lifecycle state. Default `'new'`. |
| `updated_at` | `DATETIME` | Auto-set on insert and update. |
| `last_synced_at` | `DATETIME` | Receiver's timestamp of last sync activity. Used for culling. Indexed DESC. |

To implement ChaosNet's ActivityPub API, LiquidMS also defines the tables `chaosnet_follows` and `chaosnet_outbox`.

### `chaosnet_follows`

| Column | Type | Description |
|--------|------|-------------|
| `actor_uri` | `VARCHAR(256)` | URI of the following/followed actor |
| `inbox_uri` | `VARCHAR(256)` | Inbox URI of the follower (for forwarding) |
| `api_name` | `VARCHAR(32)` | The API this follow relationship belongs to |
| `state` | `ENUM('pending','accepted','rejected')` | Follow state |
| `direction` | `ENUM('inbound','outbound')` | Inbound (they follow us) or outbound (we follow them) |
| `created_at` | `DATETIME` | Timestamp |
| PK | `(actor_uri, direction, api_name)` | |

### `chaosnet_outbox`

| Column | Type | Description |
|--------|------|-------------|
| `id` | `VARCHAR(256)` PK | Unique activity ID |
| `type` | `VARCHAR(32)` | Activity type |
| `actor` | `VARCHAR(256)` | Actor URI |
| `api_name` | `VARCHAR(32)` | The API this outbox entry belongs to |
| `object` | `JSON` | Full JSON-LD activity object |
| `published` | `DATETIME` | Publication timestamp, indexed DESC |

### State machine lifecycle

Netgames in `chaosnet_netgames` transition through a state machine inspired
by UNIX process states:

```
                     ┌──────────┐
 Other API INSERT ──→│   new     │
   (or UPDATE        └────┬─────┘
    on unknown id)        │ processPendingRows()  (Create or Update outbox entry)
                          ▼
                     ┌──────────┐
               ┌────→│  active   │ ←──────────────┐
               │     └────┬─────┘                  │
               │          │ cull event (20 min)    │ processPendingRows()
               │          ▼                        │ (Update outbox entry)
               │     ┌──────────┐                  │
               │     │  stale    │─────────────────┘
               │     └────┬─────┘
               │          │ processPendingRows() (Delete outbox entry)
               │          ▼
               │     ┌──────────┐
               │     │ deleted   │
               │     └────┬─────┘
               │          │ purge event (10 min)
               │          ▼
               │     (row removed)
               │
               └──── direct new→stale: other API sets state = 'stale'
                     ChaosNet processes as Delete anyway
```

| State | Description |
|-------|-------------|
| `new` | Inserted by another API, not yet processed by ChaosNet |
| `active` | Processed by ChaosNet (outbox entry created, forwarded if followers exist) |
| `stale` | Hit the 20-minute cull timeout, or explicitly set by another API on unlist |
| `deleted` | ChaosNet has logged a Delete activity for this row; awaiting purge |

| Event | Schedule | Action |
|-------|----------|--------|
| `chaosnet_netgames_cull` | Every 1 minute | `UPDATE state='stale'` where `state IN ('new','active')` and `last_synced_at` > 20 min |
| `chaosnet_netgames_purge` | Every 5 minutes | `DELETE` where `state='stale'` (> 30 min) or `state='deleted'` (> 10 min) |

Culling uses `last_synced_at` (the **receiver's** timestamp) rather than
`updated_at` (the sender's reported timestamp). This avoids clock skew
issues — the culling decision is always based on the local database server's
clock. The two fields are kept separate to detect and correct time skew
across the network.

### Pending row processing

`InboxModel::processPendingRows()` scans for rows in `new` or `stale` state
and processes them without requiring an explicit network activity. It is
called automatically:

| Trigger | Forward to followers? | Limit | Scope |
|---------|----------------------|-------|-------|
| `POST /inbox` (any activity) | Yes | 50 rows per request | Global (all APIs) |
| `GET /collection` | No (outbox only) | Unlimited | Global (all APIs) |

For each `new` row, ChaosNet checks `chaosnet_outbox` for a prior
Create/Update activity for the same game ID within the same API. If found
→ `Update`, otherwise → `Create`. The resulting activity is recorded in the
outbox under the row's API actor and, if forwarding is enabled, POSTed to all
accepted followers of that API.

For each `stale` row, a `Delete` activity is recorded in the outbox and
forwarded under the row's API actor.

### Inline forwarding

When ChaosNet processes a `Create`, `Update`, or `Delete` activity from its
per-API inbox, it immediately forwards the activity to all accepted inbound
followers of that same API during the same request cycle. For `Deliver`
activities, each individual item is forwarded as an `Update`. Forwarding is
best-effort:

- Uses cURL with a 10-second timeout per follower
- Failures are logged and do not block the response
- Each forwarded activity has the forwarding API actor's URI appended to
  the game object's `path` array to prevent broadcast loops
- No retry mechanism — stale data is naturally refreshed by the next
  sync cycle or culled by the scheduled events


Migration from Snitch V1
------------------------

Snitch V1 (the CSV-based protocol at `/api/snitch`) is **deprecated**.

- Existing Snitch V1 endpoints continue to work but return `Deprecation`
  and `X-LiquidMS-Deprecated` HTTP headers.
- New deployments should use `api: chaosnet` in their liquidanacron config.
- To migrate, point your dest configuration to the peer's
  `/api/chaosnet/services/<api>/inbox` URL instead of `/api/snitch`.


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

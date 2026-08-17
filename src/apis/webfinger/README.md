# LiquidMS WebFinger API

A simple [Webfinger](https://webfinger.net/) endpoint for [LiquidMS ChaosNet](../chaosnet/README.md). 

## Deployment and Configuration

There are no package dependencies, but the following PHP extensions must be enabled:

- ext-yaml
- curl

Due to [RFC 7033](https://datatracker.ietf.org/doc/html/rfc7033) [the Webfinger standard], this API **MUST** be reachable under the global path `/.well-known/webfinger` of whatever (sub-)domain your LiquidMS node is hosted on. For convenience, this API includes a simple `.htaccess` configuration.

The Webfinger API is configured via `config.yaml`, which looks (roughly) like this:

```yaml
loglevel: quiet
debug_output: False
hosts:
  liquidms.example:
    - local: darkplaces
      actor_uri: https://liquidms.example/api/chaosnet/services/darkplaces
    - local: gamespy
      actor_uri: https://liquidms.example/api/chaosnet/services/gamespy
  chaos.example:
    - local: srb2kart
      actor_uri: https://chaos.example/services/srb2kart
```

`debug_output`:
Enable debug output (optional; default `False`)

`hosts`:
List of actors grouped by (sub-)domain.
maps each domain the node serves to a list of explicit

`hosts.<HOST>.local`:
Local service actor name. SHOULD map to an API handle as defined by/for [ChaosNet](../chaosnet/README.md)

`hosts.<HOST>.actor_uri`:
Explicit, full URI to the service actor.

`loglevel`: Log level. (`['verbose', 'warning', 'quiet']`). Currently unused.

## Endpoints and Behaviour

In accordance with Webfinger, this API is designed to only serve `GET /.well-known/webfinger?resource=<uri>` routes and thus every request to it must route to `public/index.php`.

The `resource` parameter accepts two forms:

- `acct:<local>@<host>`
- an actor URL, e.g. `https://liquidms.example/api/chaosnet/services/srb2kart`

To support Webfinger-based actor discovery both among LiquidMS nodes and the wider Fediverse, Webfinger requests are resolved as follows:

- **Locally-configured host**: resolved against the alias map. Unknown local part
  or URL → `404` (never forwarded).
- **Foreign host** will be proxied: the API fetches
  `https://<host>/.well-known/webfinger?resource=<uri>` (with http fallback, 10s timeout) and returns the remote JRD verbatim.
- Missing or malformed `resource` parameters → `400 Bad Request`
- non-`GET` requests will return `405 Method Not Allowed`;
- Invalid URLs will return `404 Not Found`.

Local responses are served as `application/jrd+json` with a `Cache-Control`
header. Example:

```json
{
  "subject": "acct:srb2kart@liquidms.example",
  "aliases": ["https://liquidms.example/api/chaosnet/services/darkplaces"],
  "links": [
    {
      "rel": "self",
      "type": "application/activity+json",
      "href": "https://liquidms.example/api/chaosnet/services/darkplaces"
    }
  ]
}
```
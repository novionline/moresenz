# Novi Cache Warmer Loopback

Makes on-server [Cache Warmer](https://wordpress.org/plugins/cache-warmer/) populate and HIT [Comet Cache](https://cometcache.com/) without editing either plugin. Designed to be reusable across hosts (not tied to one server IP).

## Problem

Comet Lite skips cache read/write when `REMOTE_ADDR === SERVER_ADDR` (“self-serve”). Cache Warmer’s `wp_remote_get` from the origin always hits that rule and never writes cache files.

## Approach

For warmer requests only (User-Agent contains `NoviCacheWarmer`):

1. Force IPv4 (`CURLOPT_IPRESOLVE_V4`).
2. Bind the client socket (`CURLOPT_INTERFACE`) to a local IPv4 where probing proves `REMOTE_ADDR !== SERVER_ADDR`.

### Dynamic bind-IP discovery

1. List local IPv4 addresses (`net_get_interfaces`), skip `127.0.0.1`.
2. Prefer private ranges; deprioritize addresses that are also the site’s public A-record.
3. For each candidate, native-cURL the Comet-uncached REST probe:
   `GET /wp-json/novi-cache-warmer-loopback/v1/ip-probe`
4. First candidate where `remote !== server` wins.
5. Store in option `novi_cache_warmer_bind_ip` + transient (1 day). Failures cool down for 1 hour.

Pure `127.0.0.1` is not used: on many hosts `SERVER_ADDR` becomes `127.0.0.1` too.

## Setup

1. Require this file from `mu-plugins/load.php`.
2. In Cache Warmer → User-Agent, include `NoviCacheWarmer`.
3. Optional `wp-config.php`:

```php
define('NOVI_CACHE_WARMER_UA_TOKEN', 'NoviCacheWarmer');
define('NOVI_CACHE_WARMER_BIND_IP', '10.0.0.5'); // skip auto-detect
define('NOVI_CACHE_WARMER_PROBE_TOKEN', 'secret'); // optional; required for non-local probe calls
```

## Security (probe route)

`GET /wp-json/novi-cache-warmer-loopback/v1/ip-probe` is used only for bind-IP discovery.

- Hidden from the REST index (`show_in_index => false`)
- Requires secret header `X-Novi-Cw-Probe` (auto-generated option `novi_cache_warmer_probe_secret`, or `NOVI_CACHE_WARMER_PROBE_TOKEN`)
- Also requires `REMOTE_ADDR` to be one of this host’s own interface IPs (self-connect only)
- Returns only `remote` / `server` / `equal` (no host leak)

Public internet calls without the secret get `401`. The secret never needs to be shared outside the server.


- Needs **at least two usable IPv4 identities** on the host (typically private NIC + public). Single-address VPS may not find a working bind IP — set `NOVI_CACHE_WARMER_BIND_IP` if you know a working path, or use an external warmer.
- First warmer request after cache expiry may spend a few seconds discovering.

## Disable

Remove the `require` from `load.php` (or delete this directory). Delete option `novi_cache_warmer_bind_ip` if desired.

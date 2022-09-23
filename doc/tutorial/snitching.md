![LiquidMS logo](../liquidMS.svg)

Snitching
=========

*Snitching* describes the process of running one of the fetch scripts
(`fetch.php` or `liquidanacron.php`) to pass data towards a LiquidMS node's
[Snitch API]. Due to the way LiquidMS is constructed, a snitch does not
need to run a node of their own - or even forward a port - to contribute.

[Snitch API]: ../reference/snitch.md

Basics
------

In order for the fetch scripts to run correctly, the following basic
configuration structure must be defined within `dist/config.yaml`:

```YAML
fetchmode: "snitch"
fetch:
  vanilla:
    host: https://v1serv/
    api: v1
    minute: 5
  liquid:
    host: http://liquidserv/
    api: snitch
    minute: 1
snitch:
  "http://localhost:8080"
  "http://my-fav-liquidms.node"
```

`fetchmode: "snitch"` runs the scripts in snitch mode. Both scripts can be run in mode `fetch` as
well, but would require a valid ODBC connector with sufficient
authorization to be present on the system running the script and would be
unable to snitch to other servers.

`fetch` defines source servers using so called *fetchjobs*, which can be
run individually or collectively using `fetch.php` at the user's
discretion.  `vanilla` specifies an example fetchjob relying on the v1
(SRB2) API as a fallback. `liquid` defines an example fetchjob running in a
fetch-from-snitch setup with a LiquidMS server featuring a Snitch API endpoint.

`snitch` represents a set of URLs defining servers to pass the aggregated netgames to.


One-time snitch using `fetch.php`
---------------------------------

Just run `fetch.php` using PHP. All desired fetchjobs have to be defined by name as
arguments. Without any argment, all fetchjobs are run in sequence.

```
user$ php dist/liquidms/fetch.php [FETCHJOB]
```


Using `liquidanacron.php`
-------------------------

Just run `dist/liquidms/liquidanacron.php` in PHP. Once launched,
`liquidanacron.php` will run a check once every minute and keep track of
the desired time intervals per fetchjob.

```
user$ php dist/liquidms/liquidanacron.php
```


Regular snitching using Cron jobs
---------------------------------

On UNIX systems, you can use cron jobs to run `fetch.php` regularly, for example:

```crontab
*/5 * * * * /usr/bin/env php /path/to/fetch.php "your-fetch-job"
```


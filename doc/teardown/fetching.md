![LiquidMS logo](../liquidMS.svg)

Fetching
========

`fetch.php`
-----------

The script `fetch.php` parses server listings fetched from said master
servers defined in `config.yaml` and upserts them into it's defined ODBC
database.  For recurring execution, this script can be used either through
the supplied daemon script `liquidanacron.php` or independently as a
system-managed regular occurrence, such as a scheduled task on Windows or a
(ana)cronjob on POSIX. We recommend the latter for added flexibility.

To query individual servers, specify their job names as arguments to the
script like below. If unspecified, all servers will be fetched at once:

```sh
$ php fetch.php [jobname]
```


`liquidanacron.php`
-------------------

The script `liquidanarcon.php` serves as a simple daemon that regularly
engages in fetching/snitching of all specified universe servers. Acting
upon every minute, it logs timestamps of individual fetch queries and runs
them after the timespan defined in it's designated `minute` field has
passed. If no temporal data is specified for a job, it will be skipped.

For more information, see the *liquidanacron* section in __CONFIGURATION__.

```YAML
fetch:
  vanilla:
    host: "http://mb.srb2.org/0/MS"
    api: "v1" | "snitch"
    minute: 15
```

Each number above 0 entered into a time field will be interpreted as a
multiplier to be understood as "every x minutes"; other values will equate
to 1. To ignore a specific temporal requirement, simply omit it's time
field from the job.

In case you need to troubleshoot `liquidanacron.php`'s timestamps, they are
noted down in the file `timestamps.yaml`.

Fetch-from-Snitch
-----------------

The fetchjob's `api` field defines the API to be queried against during
fetch requests. `v1` signifies the default HTTP Master Server API, whereas
`snitch` is able to fetch servers from the [Snitch API][snitchapi].  It is
recommended to use the Snitch API whenever possible to avoid possible
duplicates and network storms within a LiquidMS network; especially one
consisting of independent databases.

[snitchapi]: ../reference/snitch.md


![LiquidMS logo](doc/liquidMS.svg)

LiquidAnacron - LiquidMS Snitch Script
======================================

SUMMARY
-------

This PHP script is part of [LiquidMS] and should not be distributed
separately.  LiquidMS and this script are licensed under the
[GNU AFFERO GENERAL PUBLIC LICENSE Version 3][gnuaffero].

[LiquidMS]: <https://github.com/zibonbadi/liquidms/>
[gnuaffero]: <https://www.gnu.org/licenses/agpl-3.0.en.html>

*LiquidAnacron* is a script to pull netgame information from various
Master Server APIs and pass it to one or more LiquidMS servers
using the *Snitch API*.

USAGE
-----

    $ php /path/to/liquidanacron.php [-1] [job ...]

### OPTIONS

`job` is one or more *fetch jobs*, defined in `config.yaml`. If not given,
LiquidAnacron will simply try to execute all jobs defined in `config.yaml`.

`-1`
: ONESHOT mode. Instead of running periodically every
  minute, LiquidAnacron only executes once.


### Sample `config.yaml`

A base configuration file `config.yaml` is required to run LiquidAnacron.
Below you can find a sample file which explains the available options:

```yaml
---
src: # List of fetch jobs
  some_fetch_job:
    host: <URL/hostname of API server>
    api: srb2http|srb2legacy|srb2kart|snitch # API to pull from
    minute: x
dest: # List of destinations to push to
  some_destination:
    host: <URL to Snitch API>
    api: snitch # API to push to. Only `snitch` and `snitch_v2` (future) are supported
    minute: x # Execute every x minutes. Values <1 will be skipped
...
```

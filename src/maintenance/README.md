![LiquidMS logo](doc/liquidMS.svg)

LiquidMS Maintenance Tools
==========================

These tools are part of [LiquidMS] and should not be distributed
separately.  LiquidMS is icensed under the
[GNU AFFERO GENERAL PUBLIC LICENSE Version 3][gnuaffero].

This directory contains an assortment of maintenance utilities which may
help you configure and administer your LiquidMS node.

[LiquidMS]: <https://github.com/zibonbadi/liquidms/>
[gnuaffero]: <https://www.gnu.org/licenses/agpl-3.0.en.html>

`run_sql.php`
-------------

This script connects to a database and executes an SQL script read from a
file.  You may want to run this in case your hosting environment doesn't
support [scheduled events][sqlevent].

[sqlevent]: <https://dev.mysql.com/doc/refman/8.0/en/events-overview.html>

### Usage

    $ php run_sql.php --user=[USER] --password=[PASSWORD] --dsn=[DSN STRING] [SQL SCRIPT...]

`-f[FILE] --file [FILE]`
: Suppresses all other flags. Instead all info is loaded from *FILE*.
  *FILE* is expected to be a YAML file with the following structure:

```yaml
db:
    dsn: <DSN connection string>
    user: <DB user>
    password: <DB user's password>
query: <QUERY STRING>
```

`user`
: Database user.

`password`
: Database user's password.

`dsn`
: DSN connection string to connect to the database

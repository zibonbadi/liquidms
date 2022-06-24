![LiquidMS logo](../liquidMS.svg)

How to install LiquidMS
=======================

Host LiquidMS using Docker(-Compose)
------------------------------------

You can easily run a working LiquidMS node as a set of Docker
containers using the included sample Docker Compose setup:

1. Navigate your terminal to this repository
2. Create the following files based on your customizations.
   For reference, use the provided `*.example` files:
   - `.env`
   - `fetch.config.yaml`
   - `fetch.crontab`,
   - `satellite.config.yaml`
   - `tables.sql` 
3. Run `docker-compose build && docker-compose up`

Voilà! You now have a running LiquidMS setup!
In case you need to restart it without changing the configs, simply run:

	docker-compose up


Natively installing LiquidMS
----------------------------

First, download the source code and install all dependencies. You'll need
[PHP] 8.0.x, [Composer] and either [MariaDB] or [MySQL] for this with
appropriate ODBC connectors and the following PHP extensions enabled:

- EXT_MBSTRING
- EXT_ODBC
- EXT_PDO
- EXT_SOCKETS (for hosting the SRB2Query module)
- EXT_YAML

If you have trouble installing PHP extensions, here's a few links for help,
depending on your operating system:

- [Windows](https://www.php.net/manual/en/install.pecl.windows.php)
- [Gentoo Linux](https://wiki.gentoo.org/wiki/PHP)
- [Arch Linux](https://wiki.archlinux.org/title/PHP)
- [Generic Linux](https://serverfault.com/questions/436634/installing-php-extensions-on-linux)

If you can't find yours, feel free to contribute whatever solution you
found. Additionally, if you're not sure whether your base PHP installation
already ships with a certain extensions, try running `php --modules`.

[Composer]: <https://getcomposer.org/doc/00-intro.md>
[MariaDB]: <https://mariadb.com/>
[MySQL]: <https://mysql.com/>
[PHP]: <https://www.php.net/>

```
$ git clone "https://github.com/zibonbadi/liquidms.git"
$ cd liquidms
liquidms$ composer install
```

LiquidMS requires a seperate SQL-capable relational database. As the
connection is established through an [ODBC] interface, this can be either
on-disk, on-system or remote.  All details about the preferred database
connection can be configured in the *environment file*; see
__CONFIGURATION__ for more info.

[ODBC]: <https://en.wikipedia.org/w/index.php?title=Open_Database_Connectivity&oldid=1044732966> "ODBC - Wikipedia"


Installing the database
-----------------------

Each running instance of a LiquidMS SRB2 master server is called a *node*.
Nodes may be run independently from their corresponding database and thus
may be used as read-only database mirrors in case you attempt to run a
distributed LiquidMS node network.

In order to set up your database (*world*), run `setup.sql` for a basic
setup and a modified version of `tables.sql.example` for your individual
configuration, like this:

```
user$ cat setup.sql tables.sql | isql liquidms
```

Hosting a development server
----------------------------

We generally advise you to develop LiquidMS using the exemplary Docker
Compose container network. If you wanna develop and test a natively
running setup though, you can simply Launch a PHP development server:

	$ php -S 127.0.0.1:8080 dist public/index.php
 
Keep in mind that the php development server is deliberately
single-threaded and only processes one request at a time. This may severely
impact the performance of parallelized network tests, such as testing a
series of SRB2Query requests.

NOTE: The game has been reported to have difficulties around the local DNS
      name `localhost`. Also note that the URL must not end in a slash as
	  the game is not trailing slash-aware when doing HTTP requests.

Our `.gitignore` file also reserves a dedicated directory `local/` in case
you need to store information locally without committing them or fiddling
with resets or the `.gitignore`; config files and database setup scripts
for example.


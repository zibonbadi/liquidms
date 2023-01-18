![LiquidMS logo](doc/liquidMS.svg)

LiquidMS
========

SUMMARY
-------

*LiquidMS* is a clean room implementation of the Sonic Robo Blast 2 [HTTP
Master Server API][v1spec]. It can mirror netgame data from any API
Compatible master server, and due to the ability to synchronize with other
instances of itself, it is capable of being operated as a node within a
distributed master server network.

The project is licensed under the [GNU AFFERO GENERAL PUBLIC LICENSE Version 3][gnuaffero],
which is also accessible via the included `LICENSE.md` file
or the HTTP endpoint `/liquidms/license`.

Special thanks to GoldenTails whose reverse engineered HTTP master server
served as a reference to this project.  

[v1spec]: <https://web.archive.org/web/20220205110841/https://mb.srb2.org/MS/tools/api/v1/>
[gnuaffero]: <https://www.gnu.org/licenses/agpl-3.0.en.html>

INDEX
-----

This documentation follows the [Diataxis model], being divided into 4 categories:

[Diataxis model]: <https://diataxis.fr/>


### Tutorials

1. [Configuring MariaDB](doc/tutorial/mariadb-install.md)
2. [Troubleshooting MariaDB](doc/tutorial/mariadb-troubleshooting.md)
3. [Troubleshooting Docker](doc/tutorial/docker-troubleshooting.md)
4. [Snitching](doc/tutorial/snitching.md)
4. [Figuring out HTTP Headers](doc/tutorial/http-headers.md)

### How-To guides

1. [Installing & hosting LiquidMS](doc/howto/install.md)
2. [Connecting to LiquidMS](doc/howto/connect.md)
3. [Setting up ODBC](doc/howto/odbc-setup.md)
4. [Banning netgames](doc/howto/banning.md)
5. [Forwarding ports](doc/howto/portforwarding.md)

### Teardown

1. [LiquidMS hosting model](doc/teardown/hosting-model.md)
2. [Fetching](doc/teardown/fetching.md)
3. [Snitching](doc/teardown/snitching.md)
4. [Fast server browser](doc/teardown/fastbrowser.md)
5. [Fancy server browser](doc/teardown/fancybrowser.md)

### Reference

1. [Endpoints](doc/reference/endpoints.md)
2. [Configuration files](doc/reference/configfiles.md)
3. [Snitch API](doc/reference/snitch.md)
3. [Netgame Configuration (external)](doc/reference/netgames.md)



FREQUENTLY ASKED QUESTIONS (FAQ)
--------------------------------

> So... I can host my own Master Server now?

Yes you can and much, much more.

> Do I need Docker (Compose) to run LiquidMS?

No. The Docker integration has been included to provide an easy way to host
an exemplary multi-node setup. All you need to host LiquidMS is a web
server, a MySQL/MariaDB database and a sufficiently configured PHP
environment. See `INSTALLATION` for more details.

> Do I need to forward ports/buy a server? 

Yes and no. If you simply want to snitch, all you need is an HTTP-capable
internet connection and a PHP environment to run `fetch.php` or
`liquidanacron.php`. If you decide to run a dedicated LiquidMS node
however, you will need to provide access to your server. Port
forwarding, registering domains and such will naturally be necessary.

> My node is running but no one can connect to it!

In order for your server to be accessible from the outside, you need to
[forward] some ports though your network (usually your router). LiquidMS
requires the following ports:

- 80: HTTP
- 443: HTTPS (SSL encrypted)

[forward]: <https://en.wikipedia.org/w/index.php?title=Port_forwarding&oldid=1085088256>

> My world rooms aren't acessible in-game! Help!

[The original V1 API used by SRB2][v1spec] interprets the
definition of rooms using the following schema. Take care to make your room
names, descriptions and MOTD match this closely:

```
[START OF ROOM]
<room number>
<room name>

<description with max 1 consecutive blank line>


[END OF ROOM]
```

> Why do you keep insisting to fetch-from-snitch?

[The original API][v1spec] was never designed with indirection or mirroring
in mind. As such LiquidMS can only supply tags to inform users of non-world
data. Should a node fetch from another node's v1 API, all information about
netgames' origins are substituted for the fetched v1 server, accumulating
tagged data in the mirrored database.


External sources
----------------

- [SRB2Query] by James R. (exposed to the integrated server browser).
- [RevEngMS by GoldenTails][GoldenTails] (for reference purposes).

[SRB2Query]: <https://git.do.srb2.org/Golden/SRB2-Query>
[GoldenTails]: <https://git.do.srb2.org/Golden/RevEngMS>


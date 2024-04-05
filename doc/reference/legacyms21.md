![LiquidMS logo](../liquidMS.svg)

SRB2 Legacy API ("MasterServer v2.1")
=====================================

This document attempts to reconstruct the binary master server API that has
been in use by SRB2 v2.0 (referred to as "MasterServer v2.1").  For
reference, the files `mserv.c` and `mserv.h` of have been consulted, which
are free to audit under the GNU General Public License version 2.

The legacy master server API is implemented in the game client as a UDP/IP socket protocol set
up upon the default UDP port of `:28900`.

Unlike it's HTTP-based successor, the legacy master server API does not
appear to logically partition it's netgames into rooms. Instead, all
interaction after initial connection with the legacy master server can be
deduced from the non-static function names defined in `mserv.h`:

- Register netgame
- Unregister netgame
- Tick (send a keep-alive signal to prevent your netgame from being culled)
- Fetch netgame lists
- Request MOTD

Netgame registration
--------------------

<++>


Netgame deregistration
----------------------

<++>


Master Server Tick
------------------

Much like v1's POST endpoint `/<serverid>/update`, the legacy API relies on
a regular keep-alive signal supplied by the client to determine whether a
netgame listing is deprecated and worth culling. On behalf of the game
client (v2.0), the frequency of the keep-alive signal has been hardcoded to
a span of two minutes.

Upon invocation, <++>

constructs a message packet defined by the following fields:


Messages
--------

MasterServer v2.1 messages are made up of the following 8-byte-aligned fields:

         +--------------------+
    0x00 | (long) id          |
         +--------------------+
    0x04 | (long) type        |
         +--------------------+
    0x08 | (long) length      |
         +--------------------+
    0x0C | (char) buffer      |
         |   ... (max. 1024B) |
         +--------------------+

Netgame entries are defined by the following structure:

         +----------------+------------------+
    0x00 | (char) header  | (uint) signature |  // Union
         +----------------+------------------+
    0x20 | (char) ip      |
         +----------------+
    0x30 | (char) port    |
         +----------------+
    0x38 | (char) name    |
         +----------------+
    0x48 | (char) version |
         +----------------+
               [0x50]

### Message Types

Each message

ID | Name
--|--
101 | ADD_SERVER_MSG | 
101 | REMOVE_SERVER_MSG | 
101 | ADD_SERVERv2_MSG | 
200 | GET_SERVER_MSG | 
205 | GET_SHORT_SERVER_MSG | 
206 | ASK_SERVER_MSG | 
207 | ANSWER_ASK_SERVER_MSG | 
208 | GET_MOTD_MSG | 
209 | SEND_MOTD_MSG | 

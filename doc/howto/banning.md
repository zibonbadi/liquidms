![LiquidMS logo](../liquidMS.svg)

Banning netgames from your node
===============================

LiquidMS supports banning of servers through use of the database table
`bans`, consisting of the fields `host`, `subnetmask` and `expire`.

As the names suggest, it allows administrators to ban ranges of IPs based
on *subnet masks* rather than traditional numeric ranges. This allows for
more precise control of IP ranges through carefully crafted subnet masks at
the expense of subnet masks being restricted to sizes equal to a power of two.


```SQL
INSERT INTO `bans` (`host`,`subnetmask`,`expire`,`comment`,`bans`) VALUES
('fe80::1', 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 DAY), 'Knuckles needs to calm down'),
('::ffff:0.0.0.0',  'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ff00', NULL, "Lock out Eggman and his robots")
ON DUPLICATE KEY
UPDATE _id=VALUES(_id), _id=VALUES(host), _id=VALUES(subnetmask), _id=VALUES(expire), _id=VALUES(comment);
```

The database field `expire` contains a timestamp defining the expiration
date and by extension duration of the ban after which the entry will be
automatically removed from the database. The default value is the time of
entry plus 24 hours. A timestamp of `NULL` defines a permanent ban.

**NOTE:** Due to missing functionality on behalf of MariaDB/MySQL, banning
is currently only implemented for singular IPs.



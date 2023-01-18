![LiquidMS logo](../liquidMS.svg)

Figuring out your HTTP headers
==============================


What you need
--------------

- An SRB2 Client to spoof
- A network analysis tool. In this tutorial we're gonna use [Wireshark].
- A PHP environment capable of running a fetch instance.

[Wireshark]: <https://www.wireshark.org/>


How to do it
------------

1. Open ~~Wireshark~~ your network analysis tool.
2. Listen to the network card that's gonna be handling SRB2's connections.
3. Select the port and protocol to listen to. HTTP is usually transmitted through port 80.
4. Make a connection to your desired master server using the SRB2 client.
   It is necessary for this connection to be made successfully otherwise
   the inspected data may be useless to you.
5. Inspect the packets that travel inbetween your client and master server
   and extract the necessary HTTP headers for it (e.g. `User-Agent`)
6. Add these HTTP headers to your fetch instance's `config.yaml`
7. Enjoy!


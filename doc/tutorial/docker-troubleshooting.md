![LiquidMS logo](../liquidMS.svg)

Troubleshooting Docker
======================

> I can't run the example network!

You need Docker and Docker Compose to orchestrate the example network.
These are two different programs, so make sure you have both installed.
For more information, check out our [install guide].

[install guide]: <../howto/install.md>


> My containers can't find each other!

Make sure you run Docker (Compose) with the appropriate permissions.
Best practice on Linux is to assign yourself to the `docker` group, but
*be careful as this implies root privileges for this account.*


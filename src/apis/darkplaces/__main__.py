import enum,json,struct,hashlib,datetime,socket,logging,re,urllib,ipaddress # Built-in libs
from ipaddress import ip_address, IPv4Address, IPv6Address
import yaml,sqlalchemy # External libs

# Convenience imports
from yaml import CLoader as Loader

# SQL database stuff
from sqlalchemy import Table, Column
from sqlalchemy.orm import Session, declarative_base
from sqlalchemy.ext.automap import automap_base
from sqlalchemy.dialects.mysql import insert

log = logging.getLogger(__name__)
db = None
GAME_API_NAME = "darkplaces"
Base = declarative_base()

import asyncio
import enum,struct,datetime,socket # Built-in libs


class NetgameState(enum.Enum):
    new     = 0
    active  = 1
    stale   = 2
    deleted = 3

class Q3AGametype(enum.Enum):
    ffa             = 0
    tourney         = 1
    team            = 2
    ctf             = 3
    elimination     = 8
    ctfelmination   = 9
    lms             = 10
    dd              = 11
    dom             = 12


class UDPMessage():

    def __init__(self, **kwargs):
        self.ip = kwargs["ip"] if "ip" in kwargs.keys() else None
        self.port = kwargs["port"] if "port" in kwargs.keys() else 27950
        self.message = kwargs["message"] if "message" in kwargs.keys() else None

        opt_kwargs = {k:v for k,v in kwargs.items() if k not in ["ip","port","message"]}
        for k,v in opt_kwargs.items():
            self.__dict__[k] = v
    
    def __str__(self):
        keystring = ", ".join([f"{key}={value}" for key,value in self.__dict__.items() ])
        return f"UDPMessage({keystring})"


    def from_packet(self, packet):
        """Static function to create a UDPPacket object from a predefined packet
        """
        log.debug(f"Message header: '{packet[:4]}'")
        if packet[:4] != b'\xFF\xFF\xFF\xFF':
            raise Exception("Message doesn't start with 0xFF 0xFF 0xFF 0xFF !")
        
        msg_string = packet[4:].decode('ascii', errors="ignore")
        self.message = re.search(r"[^\W]+", msg_string).group(0)

        match self.message:
            case "heartbeat":
                self.protocol = re.search(r"heartbeat ([^\s\\\/;\"%]+)", msg_string).group(1)
                pass
            case "getinfo":
                raise NotImplementedError(f"Converting to {self.message} packets not implemented yet!")
                pass
            case "infoResponse":
                options = msg_string[13:]

                log.debug(f"Parsed option string: {options}")

                game_options_split = options.lstrip('\\').split('\\')

                log.debug(f"Split options: {game_options_split}")

                gos_keys = game_options_split[::2]
                gos_values = game_options_split[1::2]

                self.options = {}
                for k,v in zip(gos_keys,gos_values):
                    log.debug(f"\tInfoResponse attr {k}: {v}")
                    self.options[k] = v
                pass
            case "getservers" | "getserversExt":
                # Commented out: Regex to potentially match each option individually
                # Instead we just collect all options and split them later
                #regex_matches = re.search(r"getservers(Ext)?(?:\s+([^\s\\\/;\"%]+))?\s+(\d+)(?:[ \r\t\v]+(\S+))*", msg_string)
                regex_matches = re.search(r"getservers(Ext)?(?:\s+([^\s\\\/;\"%]+))?\s+(\d+)([^\n]*)", msg_string)

                self.protocol = regex_matches.group(3)
                self.game_name = regex_matches.group(2)
                options_pre = regex_matches.group(4).split()
                self.options = {}

                for gop in options_pre:
                    kvmatch = re.match(r"([a-zA-z0-9]+)(?:=([a-zA-z0-9]+))?", gop)
                    key = kvmatch.group(1)
                    value = kvmatch.group(2) if kvmatch.group(2) != None else True
                    self.options[key] = value
                    log.debug(f"UDPMessage: {key} = {value}")
                
                pass

            case "getserversResponse" | "getserversExtResponse":
                raise NotImplementedError(f"Converting to {self.message} packets not implemented yet!")
                pass
            case _:
                pass
        
        return self
        

    def to_packets(self):
        """
        Create a list of packets from the UDPMessage.
        The individual content varies by message type.
        
        If a message is too large (e.g. ``getServers(Ext)Response``),
        it will automatically be split into multiple packets.

        :return: List of packets generated
        :rtype: list
        """
        packet_base = b"\xFF\xFF\xFF\xFF" + f"{self.message}".encode('ascii', errors='ignore')
        gsr_terminator = b'\x04\x00\x00\x00'
        packets = []

        match self.message:
            case "getinfo":
                # Initial whitespace to separate it from message (compare: getserver(Ext)Response)
                packets.append( packet_base + f" {self.challenge}".encode('ascii', errors='ignore') )
                pass
            case "getserversResponse" | "getserversExtResponse":

                server_structs = []
                for n in self.netgames:
                    if type(n["ip"]) == IPv6Address and m == "getServersExtResponse":
                        server_structs.append(
                            struct.pack(f"!c16sH", \
                                            '/'.encode('ascii',errors="ignore"),
                                            n["ip"].packed,
                                            int(n["port"])
                            )
                        )
                    else:
                        server_structs.append(
                            struct.pack(f"!c4sH", \
                                            '\\'.encode('ascii',errors="ignore"),
                                            n["ip"].packed,
                                            int(n["port"])
                            )
                        )

                tmp_packet = packet_base
                for i in server_structs:
                    if len(tmp_packet) + len(gsr_terminator) > config["buffer_size"]:
                        # We're full? Push the chunk and make a new one
                        packets.append(tmp_packet)
                        tmp_packet = packet_base
                    # Consecutively add netgames to tmp_packet until we can't
                    tmp_packet = tmp_packet + i

                # Add EOT marker to last packet
                tmp_packet = tmp_packet + gsr_terminator
                # Push last packet too
                packets.append(tmp_packet)
    
                pass
            case "getservers" | "getserversExt" | "infoResponse" | "heartbeat":
                raise NotImplementedError(f"Converting to {self.message} packets not implemented yet!")
            case _:
                pass

        return packets


class NetgameDB():
    def __init__(self, db, ipv6=True):
        # Global vars bc SQLAlchemy is weird
        self.sqlengine = sqlalchemy.create_engine(db["url"]) # echo=True for debug output
        #self.tbl_servers = db["tables"]["servers"]

        self.tbl_servers = Table(
            db["tables"]["netgames"],
            Base.metadata,
            Column('id',                sqlalchemy.String(64), primary_key=True ),
            Column('host',              sqlalchemy.String(45)   ),
            Column('port',              sqlalchemy.String(64)   ),
            Column('name',              sqlalchemy.String(256)  ),
            Column('api_name',          sqlalchemy.String(32)   ),
            Column('api_data',          sqlalchemy.JSON         ),
            #Column('external_origin',   sqlalchemy.String(256)  ),
            Column('origin_node',       sqlalchemy.String(256)  ),
            #Column('path',              sqlalchemy.String(256)  ),
            Column('state',             sqlalchemy.Enum(NetgameState)  ),
            Column('updated_at',        sqlalchemy.DateTime  ),
            Column('last_synced_at',    sqlalchemy.DateTime  ),
        )

        self.ipv6_support = ipv6

        # Challenge strings
        self.challenges = {}
        pass
    
    def map6to4(self, ip):
        return ip_address(ip)

    def encode_ip(ip):
        if type(ip) == IPv6Address:
            return bytes('/'+ip.packed)
        if type(ip) == IPv4Address:
            return bytes('\\'+ip.packed)
        return None

    def challenge(self, ip, protocol:str=None, challenge:str=None):
        """
        A three-way response function to generate challenges.
        Please use type checks to set/reset the state of the
        challenge data to your liking, depending on the request.
        
        Since the challenge is a stateful transaction across
        multiple requests, this function will automatically
        return a challenge string if no challenge is found
        for the given IP address.

        If a challenge string is found from a previous getinfo
        challenge, the function will return whether the challenge
        is successfully resovled using ``challenge``. And delete
        the last challenge string automatically.

        :param ip: The issuer's IP address 
        :param protocol: Master Server Protocol given by ``heartbeat <PROTOCOL``
        :type protocol: str | None
        :type ip: str | IPv4Address | IPv6Address
        :param challenge: Challenge string issued by the request
        :type challenge: str | None
        :rtype: bool | str
        """
        
        import random

        if ip in self.challenges:
            # Happy path: Challenge completed -> delete challenge
            if self.challenges[ip]["challenge"] == challenge:
                del self.challenges[ip]
                return True
            else: # FAIL!!! Reject and try again!!
                del self.challenges[ip]
                return False
        
        # A NEW CHALLENGER!! For real, just give us a challenge string
        #characters = string.ascii_letters+string.ascii_digits
        characters = "!#$&'()*+,-.0123456789:<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[]^_`abcdefghijklmnopqrstuvwxyz{|}~"
        self.challenges[ip] = {
            # We need to keep track of the MS protocol because we wanna support multiple
            "challenge": ''.join(random.choice(characters) for i in range(16)),
            "protocol": protocol
        }
        return self.challenges[ip]["challenge"]

    def infoResponse(self, ip:str, port:int, **kwargs):
        
        req_challenge = kwargs["challenge"]
        protocol = self.challenges[ip]["protocol"] if ip in self.challenges else None
        local_challenge = self.challenges[ip]["challenge"] if ip in self.challenges else None

        challenge_check = self.challenge(ip, protocol=protocol, challenge=req_challenge)
        
        log.debug(f"CHALLENGE IN {req_challenge} : {local_challenge} LOCAL")
        
        if type(challenge_check) == bool and challenge_check:
            
            excluded_fields = ["ip","port","challenge"]
            metadata = {k:v for k,v in kwargs.items() if k not in excluded_fields}

            netgame = {
                "id": hashlib.sha256(f"{ip}|{port}|{GAME_API_NAME}".encode('ascii', errors='ignore')).hexdigest(),
                "host": ip,
                "port": port,
                "name": metadata["hostname"],
                "api_name": GAME_API_NAME,
                "api_data": metadata,
                "origin_node": config["hostname"],
            }

            for key,value in kwargs.items():
                log.debug(f"Netgame attr: {key} = {value}")
            self.push_netgame(**netgame)
        else:
            log.error(f"Challenge check failed! {ip}")

        return challenge_check

    def getserversExt(self, message, protocol, **kwargs):

        servers = []

        query = sqlalchemy.select(self.tbl_servers.c.host,self.tbl_servers.c.port,self.tbl_servers.c.api_data) \
            .filter( self.tbl_servers.c.api_data["protocol"] == protocol ) \
            .filter( self.tbl_servers.c.state.in_(["new", "active"]) )

        gametype = kwargs["gametype"] if "gametype" in kwargs.keys() else None
        gametype = self.translate_q3a_gametype(**kwargs) if int(protocol) == 71 else gametype

        # Additional conditions if needed
        if "empty" in kwargs:
            query = query.filter(self.tbl_servers.c.api_data["clients"] == 0)
        if "full" in kwargs:
            query = query.filter(self.tbl_servers.c.api_data["clients"] == self.tbl_servers.c.api_data["sv_maxclients"])
        if gametype != None:
            query = query.filter(self.tbl_servers.c.api_data["gametype"] == gametype)

        log.debug(f"[SQL] {query}")

        # Query database and add netgames to response
        with Session(self.sqlengine) as session:
            result = session.execute(query).fetchall()

            if message == "getserversExt":
                filtered_results = result
            else:
                log.info("Restricting query to IPv4 addresses")
                filtered_results = [x for x in result if type(self.map6to4(x.host)) == IPv4Address]
            
            log.info(f"Found {len(filtered_results)} netgames")

            servers = []
            for netgame in filtered_results:
                mapped_ip = self.map6to4(netgame.host)

                sv = {
                    "ip": mapped_ip,
                    "port": int(netgame.port),
                    # More API-specific data idk
                }

                servers.append(sv)

        return servers

    def push_netgame(self, **netgame):
        #raise NotImplementedError("TODO: Gotta work that SQLAlchemy")
        
        query = insert(self.tbl_servers).values(**netgame).on_duplicate_key_update(**netgame)

        with Session(self.sqlengine) as session:
            result = session.execute(query)
            session.commit()

        pass

    def translate_q3a_gametype(self, **kwargs):
        """
        Translates Quake III Arena/OpenArena-style gametype names into numbers.
        """
        
        gt_aliases = [x.name for x in Q3AGametype]

        # Check explicit gametype parameter
        out = kwargs["gametype"] if "gametype" in kwargs.keys() else None

        # Check if the parameter is using a name
        if out in gt_aliases:
            # Convert detected gametype into Q3A/OA number
            out = NetgameState[gametype].value

        # Check if the gametype is given otherwise
        for gt in gt_aliases:
            if gt in kwargs.keys():
                # Convert detected gametype into Q3A/OA number
                out = Q3AGametype[gt].value
                break

        return out


class UDPDarkplacesProtocol:
    def connection_made(self, transport):
        self.transport = transport
        log.info(f'{config["hostname"]}:{config["port"]} UP')

    def datagram_received(self, data, addr):
        log.info(f"{addr} issued {data} ({len(data)} bytes)")

        incoming_ip = addr[0]
        incoming_port = addr[1]
        
        request = UDPMessage(ip=incoming_ip, port=incoming_port).from_packet(data)
        log.debug(f"\tMessage object: {request}")

        try:
            match request.message:
                ### Metadata endpoints ###
                case "heartbeat":
                    # Ensure we get a challenge string
                    challenge = db.challenge(incoming_ip, protocol=request.protocol, challenge=None)
                    if type(challenge) != str:
                        log.info(f"Removing stale challenge for {incoming_ip}")
                        challenge = db.challenge(ip=incoming_ip, protocol=request.protocol, challenge=None)
                    
                    log.debug(f"Issuing getinfo challenge {challenge}")

                    getinfo_msg = UDPMessage(ip=incoming_ip, port=incoming_port, message="getinfo", challenge=challenge).to_packets()

                    for msg in getinfo_msg:
                        log.info(f"Responding {msg}")
                        self.transport.sendto(msg, addr)


                case "infoResponse":
                    # NOW we create/update
                    if not db.infoResponse(request.ip, request.port, **request.options):
                        challenge = db.challenge(incoming_ip,None)
                        if type(challenge) != str:
                            challenge = db.challenge(ip,challenge=request_options["challenge"])
                        
                        log.error(f"{addr} Challenge failed! Issuing new challenge {challenge}")

                        getinfo_msg = UDPMessage(ip=incoming_ip, port=incoming_port, message="getinfo", challenge=challenge).to_packets()

                        for msg in getinfo_msg:
                            log.info(f"Responding {msg}")
                            self.transport.sendto(msg, addr)    
                case "getservers" | "getserversExt" as m:
                    servers = db.getserversExt(m, request.protocol, **request.options)
                    log.debug(f"Servers received: {servers}")
                    
                    if m == "getserversExt":
                        response = UDPMessage(ip=incoming_ip, port=incoming_port, message="getserversExtResponse", netgames=servers).to_packets()
                    else:
                        response = UDPMessage(ip=incoming_ip, port=incoming_port, message="getserversResponse", netgames=servers).to_packets()
                    
                    log.debug(f"Packet responses: {response}")

                    for r in response:
                        self.transport.sendto(r, addr)

                    log.info(f'{config["hostname"]}:{config["port"]}: Returned {len(servers)} servers in {len(response)} chunks')

                case "disconnect":
                    log.info(f"Goodbye {addr}! (\"{request.message}\")")

                case _:
                    log.error(f"Message \"{request.message}\" not supported!")
                    log.debug(f"\tPacket data: {data}")
                    
        except Exception as e:
            import traceback
            log.error(f"{request.ip} Client handling error: {traceback.format_exc()}")

    #timestamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H-%M-%S')
    #print(f"{reader._transport.get_extra_info('peername')[0]} [{timestamp}] \"{MessageType(request.type).name} {request}\" {logtuple[0]} {logtuple[1]}")
    
    def error_received(self, exc):
        logging.error(f"Error: {exc}")


async def main():

    log.info( '\n'.join([
        '######### LiquidMS DarkPlaces - Master server API for Quake-like games #########\n',
        "LiquidMS Copyright (C) 2021-2026 Zibon Badi et al.",
        "LiquidMS DarkPlaces Copyright (C) 2026 Zibon Badi.\n",
        "This program is free software: you can redistribute it and/or modify",
        "it under the terms of the GNU Affero General Public License as",
        "published by the Free Software Foundation, either version 3 of the",
        "License, or (at your option) any later version.\n",
        "This program is distributed in the hope that it will be useful,",
        "but WITHOUT ANY WARRANTY; without even the implied warranty of",
        "MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the",
        "GNU Affero General Public License for more details.\n",
        "You should have received a copy of the GNU Affero General Public License",
        "along with this program.  If not, see <https://www.gnu.org/licenses/>.\n",
        "Press Ctrl-C to terminate the server.\n"
    ]))

    loop = asyncio.get_running_loop()
    transport,protocol = await loop.create_datagram_endpoint(
        UDPDarkplacesProtocol,
        local_addr=(config["hostname"], config["port"])
    )
    
    try:
        await asyncio.sleep(float('inf'))
    finally:
        transport.close()


if __name__ == '__main__':
    
    import argparse

    ap_main = argparse.ArgumentParser(description='LiquidMS DarkPlaces - Master server API for Quake-like games')
    ap_main.add_argument('config', nargs='?', default='./config.yaml', help='Configuration to use (YAML)')

    args = ap_main.parse_args()

    config = {
        "hostname": "localhost",
        "port": 27950,
        "buffer_size": 2048,
        "max_timeout": 10,
        "db": {
            "tables": {
                "netgames": "chaosnet_netgames",
            },
        }
    }

    with open(args.config) as cfile:
        config = {**config, **yaml.load(cfile.read(), Loader=Loader) }
    
    db = NetgameDB( config["db"] )

    log.setLevel(logging.INFO)
    log_fmt = logging.Formatter("%(asctime)s [%(levelname)s] %(message)s")
    log_out = logging.StreamHandler()
    log_out.setFormatter(log_fmt)
    log.addHandler(log_out)

    asyncio.run(main())

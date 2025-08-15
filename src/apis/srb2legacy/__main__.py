import enum,struct,datetime,socket,logging,re,urllib,ipaddress # Built-in libs
from ipaddress import IPv4Address, IPv6Address
import yaml,sqlalchemy # External libs
#from .message import UDPMessage  # Custom modules

# Convenience imports
from yaml import CLoader as Loader

# SQL database stuff
from sqlalchemy import Table, Column
from sqlalchemy.orm import Session, DeclarativeBase
from sqlalchemy.ext.automap import automap_base

#log = logging.getLogger(__name__)
db = None


"""
New Async server
"""

import asyncio
import enum,struct,datetime,socket # Built-in libs

class MessageType(enum.IntEnum):
    ADD_SERVER_MSG          =   101
    REMOVE_SERVER_MSG       =   103
    ADD_SERVERv2_MSG        =   104
    GET_SERVER_MSG          =   200
    GET_SHORT_SERVER_MSG    =   205
    ASK_SERVER_MSG          =   206
    ANSWER_ASK_SERVER_MSG   =   207
    GET_MOTD_MSG            =   208
    SEND_MOTD_MSG           =   209
    GET_ROOMS_MSG           =   210
    SEND_ROOMS_MSG          =   211
    GET_ROOMS_HOST_MSG      =   212
    GET_VERSION_MSG         =   213
    SEND_VERSION_MSG        =   214
    GET_BANNED_MSG          =   215
    PING_SERVER_MSG         =   216
    GET_EXT_SERVER_MSG      =   217

class UDPMessage():

    def __init__(self, **kwargs):
        self.ip = kwargs["ip"] if "ip" in kwargs.keys() else None
        self.port = kwargs["port"] if "port" in kwargs.keys() else 5029

        self.id = kwargs["id"] if "id" in kwargs.keys() else 0
        self.type = kwargs["type"] if "type" in kwargs.keys() else None
        self.room = kwargs["room"] if "room" in kwargs.keys() else 0
        self.length = len(kwargs["data"]) if "data" in kwargs.keys() else 0
        self.data = kwargs["data"] if "data" in kwargs.keys() else None
    

    def __str__(self):
        keystring = ", ".join([f"{key}={value}" for key,value in self.__dict__.items() ])
        return f"UDPMessage({keystring})"

    def from_packet(self, packet):
        #self.packet = packet
        self.id,self.type,self.room,self.length = struct.unpack('!llll',packet[:16])
        self.data = packet[16:]
        return self

    def to_struct(self):
        return struct.pack(f"!llll{self.length}s", self.id, self.type, self.room, self.length, self.data)


class NetgameDB():
    def __init__(self, db, ipv6=True):
        # Global vars bc SQLAlchemy is weird
        self.sqlengine = sqlalchemy.create_engine(db["url"]) # echo=True for debug output
        
        self.tbl_servers = db["tables"]["servers"]
        self.tbl_bans = db["tables"]["bans"]
        self.tbl_rooms = db["tables"]["rooms"]

        self.ipv6_support = ipv6
        pass
    
    def map6to4(self, ip):

        ipv4_regex = re.search(r"::ffff:([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})", ip)

        if ipv4_regex.group(1):
            return IPv4Address(ipv4_regex.group(1))
        return IPv6Address(ip)

    async def addServer(self, request:UDPMessage, writer):

        """
        sv_head =   str(request.data[00:16])
        sv_ip =     str(request.data[16:32])
        sv_port =   str(request.data[32:40])
        sv_name =   str(request.data[40:72])
        sv_room =   int(request.data[72:76])
        sv_ver =    str(request.data[76:84])
        """

        sv_head =   None
        sv_ip =     None
        sv_port =   None
        sv_name =   None
        sv_room =   None
        sv_ver =    None

        # HEAD == UINT32 signature
        sv_head,sv_ip,sv_port,sv_name,sv_room,sv_ver = struct.unpack('<16s16s8s32sl8s', request.data)
        
        # Reformat output to human readable formats
        sv_port =   sv_port.split(b'\0')[0].decode('utf-8', errors="ignore")
        sv_name =    sv_name.replace(b"'",b"''").split(b'\0')[0].decode('utf-8', errors="ignore")
        sv_ver =    sv_ver.split(b'\0')[0].decode('utf-8', errors="ignore")

        #self.netgames[f"{request.ip}:{sv_port.decode('utf-8')}"] = request.ip.encode('utf-8')+b" "+sv_port+b" "+sv_name+b" "+sv_ver+b"\n\0"

        try:
            with Session(self.sqlengine) as session:
                
                # Check for valid room name
                roomname = None
                if int(sv_room) > 1 and int(sv_room) < 100:
                    # Get room name
                    rooms = session.execute(sqlalchemy.text(f"SELECT * FROM {self.tbl_rooms} WHERE _id = {sv_room}"))
                    roomname = rooms.first().roomname
                
                print(f"SLOT INTO ROOM {sv_room} - {roomname}")

                # Check if netgame exists
                sv_count = session.execute(sqlalchemy.text(f"SELECT COUNT(*) FROM {self.tbl_servers} WHERE host = '{request.ip}' AND port = {sv_port}"))
                if sv_count.first()[0] < 1 and (type(ipaddress.ip_address(request.ip)) == IPv4Address or self.ipv6_support):
                    # Construct new netgame object
                    session.execute(sqlalchemy.text(f"INSERT INTO {self.tbl_servers} (host, port, servername, roomname, version) VALUES ('{request.ip}', {urllib.parse.quote_plus(sv_port, errors='ignore')}, '{sv_name}', '{roomname}', '{sv_ver}')"))
                    print(f"ADDED NETGAME {f"{request.ip}:{sv_port}"} () TO DATABASE")
                elif type(ipaddress.ip_address(request.ip)) == IPv4Address or self.ipv6_support:
                    session.execute(sqlalchemy.text(f"UPDATE {self.tbl_servers} SET host = '{request.ip}', port = {urllib.parse.quote_plus(sv_port, errors='ignore')}, servername = '{sv_name}', room = {sv_room}, version = '{sv_ver}' WHERE host = '{request.ip}' AND port = {sv_port}"))
                    print(f"UPDATED NETGAME {f"{request.ip}:{sv_port}"}")
                session.commit()
                
        except Exception as e:
            print(f"{request.ip} [{MessageType(request.type).name}] Internal server error: {e}")

        return (0, 0)

    async def removeServer(self, request:UDPMessage, writer):
        
        sv_head =   None
        sv_ip =     None
        sv_port =   None
        sv_name =   None
        sv_room =   None
        sv_ver =    None

        sv_head,sv_ip,sv_port,sv_name,sv_room,sv_ver = struct.unpack('!16s16s8s32sl8s', request.data)

        # Truncate port
        sv_port =   sv_port.split(b'\0')[0].decode('utf-8', errors="ignore")
        
        #del self.netgames[f"{request.ip}:{sv_port.decode('utf-8')}"]
        try:
            with Session(self.sqlengine) as session:
                # Check if server exists
                sv_count = session.execute(sqlalchemy.text(f"SELECT COUNT(*) FROM {self.tbl_servers} WHERE host = '{request.ip}' AND port = {sv_port}"))
                if sv_count.first() [0] > 0:
                    session.execute(sqlalchemy.text(f"DELETE FROM {self.tbl_servers} WHERE host = '{request.ip}' AND port = {sv_port}"))   # Commit
                session.commit()

        except Exception as e:
            print(f"{request.ip} [{MessageType(request.type).name}] Internal server error: {e}")

        return (0, 0)

    async def addServer_v2(self, request:UDPMessage, writer):
        print(f"{MessageType.ADD_SERVERv2_MSG.name} not implemented yet!")
        
        return (0, 0)

    async def getServer(self, request, writer):
        res = {
            "id":       0,
            "type":     MessageType.ANSWER_ASK_SERVER_MSG,
        }

        servers = []
        try:
            with Session(self.sqlengine) as session:
                result = session.execute(sqlalchemy.text(f"SELECT host, port, servername, version FROM {self.tbl_servers}"))
                for row in result:
                    mapped_ip = self.map6to4(row.host)
                    decoded_servername = urllib.parse.unquote_plus( row.servername+"%C3%80", errors="ignore" )
                    if(type(mapped_ip) == IPv4Address or self.ipv6_support):
                        servers.append( bytes(f"{mapped_ip} {row.port} {decoded_servername} {row.version}\n\0", "utf-8") )

        except Exception as e:
            print(f"{request.ip} [{MessageType(request.type).name}] Internal server error: {e}")
            
        #print(f"TOTAL SERVERS: {servers}")

        response_size = 0

        for s in servers:
            response = UDPMessage(id=res["id"], type=res["type"], data=s)
            print(f"RESPONSE {response}")
            response = response.to_struct()
            print(f"RESPONSE DATA {response}")

            writer.write(response)
            response_size += len(response)

        response = UDPMessage(id=res["id"], type=res["type"], data=b'')
        print(f"RESPONSE {response}")
        response = response.to_struct()
        print(f"RESPONSE DATA {response}")

        writer.write(response)
        response_size += len(response)
        
        return (MessageType.ANSWER_ASK_SERVER_MSG.name, response_size)

    async def getVersion(self, request:UDPMessage, writer):
        res = {
            "id":       request.id,
            "type":     MessageType.SEND_VERSION_MSG,
            "data":     bytes(f"{config['game_version']}\0","utf-8"),
            
        }

        response = UDPMessage(id=res["id"], type=res["type"], data=res["data"])
        print(f"RESPONSE {response}")
        response = response.to_struct()
        print(f"RESPONSE DATA {response}")

        writer.write(response)

        return (MessageType.SEND_VERSION_MSG.name, len(response))

    async def getMotd(self, request, writer):

        res = {
            "id":       0,
            "type":     MessageType.SEND_MOTD_MSG,
            "data":     b"\x00\x00\x00\x00",
        }

        response = UDPMessage(id=res["id"], type=res["type"], data=res["data"])
        print(f"RESPONSE {response}")
        response = response.to_struct()
        print(f"RESPONSE DATA {response}")

        writer.write(response)

        return (MessageType.SEND_MOTD_MSG.name, len(response))

    async def getRooms(self, request:UDPMessage, writer):
        res = {
            "id":       0,
            "type":     MessageType.SEND_ROOMS_MSG,
            "room":     request.room,
        }
        
        rooms = {
            0: ("Universe", f"Powered by LiquidMS\n\nThis room queries all available rooms, local and remote.\n\n=MOTD=\n\n{config['motd']}", "localhost"),
            1: ("World", f"Powered by LiquidMS\n\nThis room queries all available rooms local to the node.\nYOU CANNOT REGISTER NETGAMES HERE!\n\n=MOTD=\n\n{config['motd']}", "localhost"),
            }

        try:
            with Session(self.sqlengine) as session:
                if res["room"] != 0: # SRB2HTTP-style single room queries, just in case
                    result = session.execute(sqlalchemy.text(f"SELECT _id, roomname, description, origin FROM {self.tbl_rooms} WHERE _id = {res["room"]} ORDER BY _id ASC"))
                else:
                    result = session.execute(sqlalchemy.text(f"SELECT _id, roomname, description, origin FROM {self.tbl_rooms} ORDER BY _id ASC"))
                for row in result:
                    rooms[row._id] = (row.roomname, row.description, row.origin)
        except Exception as e:
            print(f"{request.ip} [{MessageType(request.type).name}] Internal server error: {e}")


        response_size = 0
        
        res_head = UDPMessage(id=res["id"], type=res["type"], room=res["room"], data=b'\0')

        for roomid, (roomname, roomdesc, origin) in rooms.items():
            # Skip non-hostable rooms for GET_ROOMS_HOST_MSG
            if MessageType(request.type) == MessageType.GET_ROOMS_HOST_MSG and roomid not in range(2,99):
                continue

            # Mark non-world rooms
            if origin != "localhost":
                roomdesc = f"@{origin}\n{roomname}\n"+roomdesc
                roomname = "@"+roomname

            r_string = struct.pack(f"<16sl32s{len(roomdesc[:255])}s", \
                                   # Padding string
                                   "".encode('utf-8', errors="ignore"), \
                                   # Little Endian bc this protocol is cursed
                                   roomid, \
                                   # Encoded into bytes
                                   roomname[:32].encode('utf-8', errors="ignore"), \
                                   roomdesc[:255].encode('utf-8', errors="ignore")
                                )
            
            response = UDPMessage(id=res["id"], type=res["type"], room=res["room"], data=r_string)
            print(f"RESPONSE {response}")
            response = response.to_struct()
            print(f"RESPONSE DATA {response}")
            
            res_head.length = len(r_string)
            writer.write(res_head.to_struct()) # Yes this is stupid, but the protocol demands it
            writer.write(response)

        # Final, empty response to end the message
        response = UDPMessage(id=res["id"], type=res["type"], room=res["room"], data=b'').to_struct()
        writer.write(response)

        return (MessageType.SEND_ROOMS_MSG.name, response_size)


async def handle_client(reader, writer):
    
    packet = await reader.read(config["buffer_size"])
    request = UDPMessage(ip=reader._transport.get_extra_info('peername')[0], port=reader._transport.get_extra_info('peername')[1]).from_packet(packet)

    logtuple = (0, 0)   # (Response code, transmission size)

    match request.type:
        ### Metadata endpoints ###
        case MessageType.GET_VERSION_MSG:            
            logtuple = await db.getVersion(request, writer)

        case MessageType.GET_MOTD_MSG:
            print(f"{MessageType(request.type).name} Not implemented yet!")
            logtuple = await db.getMotd(request, writer)
        
        case MessageType.GET_ROOMS_MSG | MessageType.GET_ROOMS_HOST_MSG:
            logtuple = await db.getRooms(request, writer)
        
        ### Netgame management logic ###
        case MessageType.ADD_SERVER_MSG | MessageType.PING_SERVER_MSG | MessageType.ADD_SERVERv2_MSG:
            logtuple = await db.addServer(request, writer)
        case MessageType.REMOVE_SERVER_MSG:
            logtuple = await db.removeServer(request, writer)          
            
        case MessageType.ASK_SERVER_MSG:
            print(f"{MessageType(request.type).name} Not implemented yet!")
            logtuple = await db.askServer(request, writer)

        case MessageType.GET_SERVER_MSG | MessageType.GET_SHORT_SERVER_MSG | MessageType.GET_EXT_SERVER_MSG:
            logtuple = await db.getServer(request, writer)
        case _:
            print(f"Request type {request.type} Not supported!")
            print(f"\tPacket data: {packet}")
            
            # Send dummy response to avoid game crashes
            response = UDPMessage(id=request.id, type=request.type, data=b'').to_struct()
            writer.write(response)
            

    #response = struct.pack('!llls', id, type, length)
    #response = struct.pack('!ccccc', id, type, length)

    await writer.drain()
    writer.close()

    timestamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H-%M-%S')
    print(f"{reader._transport.get_extra_info('peername')[0]} [{timestamp}] \"{MessageType(request.type).name} {request}\" {logtuple[0]} {logtuple[1]}")


async def main():

    print( '\n'.join([
        '######### LiquidMS Legacy - Master server for SRB2 v2.1 and below #########\n',
        "LiquidMS Copyright (C) 2021-2025 Zibon Badi et al.",
        "LiquidMS Legacy Copyright (C) 2025 Zibon Badi et al.\n",
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

    server = await asyncio.start_server(handle_client, config["hostname"], config["port"])

    timestamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%S')
    print(f'[{timestamp}] {config["hostname"]}:{config["port"]} UP')

    async with server:
        try:
            await server.serve_forever()
        except KeyboardInterrupt:
            await server.shutdown()

    timestamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%S')
    print(f'[{timestamp}] {config["hostname"]}:{config["port"]} DOWN')


if __name__ == '__main__':
    
    import argparse

    ap_main = argparse.ArgumentParser(description='LiquidMS Legacy - Master Server for SRB2 v2.1 and below')
    ap_main.add_argument('config', nargs='?', default='./config.yaml', help='Configuration to use (YAML)')

    args = ap_main.parse_args()

    config = {
        "hostname": "localhost",
        "port": 28900,
        "ipv6": True,
        "buffer_size": 2048,
        "max_timeout": 10,
        "game_version": "NULL",  # Bypass version nag by default
        "db": {
            "tables": {
                "bans": "srb2http_bans",
                "rooms": "srb2http_rooms",
                "servers": "srb2http_servers",
            },
        },
        "motd": "LiquidMS is AGPLv3-licensed",
    }

    with open(args.config) as cfile:
        config = {**config, **yaml.load(cfile.read(), Loader=Loader) }

    print(f"CONFIG: {config}")
    db = NetgameDB( config["db"] )


    asyncio.run(main())

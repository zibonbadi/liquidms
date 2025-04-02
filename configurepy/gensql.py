from string import Template

from .states import states


def _generate_sql(outfile, templatefile, db_prefix, subst_vars):
    with open(templatefile, 'r') as f:
        print(f"\nGenerating SQL script {outfile}...")
        src = Template(f.read())
        result = src.substitute(subst_vars)
        with open(outfile, "w") as ofile:
            print(f"Writing SQL script into {outfile}...\n")
            ofile.write(result)
    print(f"Success. Your SQL config can be found at {outfile}\n")

def state_gensql_srb2http():
    ofilename = input('SQL script name (default "docker/db/initdb.d/srb2http.sql"): ') or 'docker/db/initdb.d/srb2http.sql'
    db_name = input('Database name (default "liquidms"): ') or "liquidms"
    db_prefix = input('Table prefix (default "srb2http"): ') or "srb2http"

    roomid = 2
    croomlist = []
    cpbanlist = []

    # --- Get custom rooms from user ---
    print("-- Custom world rooms --")
    while True:
        roomname = input('Room name (empty to skip): ') or None
        if roomname == None:
            break
        rd_lines = []
        print('Room description (empty line to end): ')
        while True:
            line = input()
            if not line:
                break
            rd_lines.append(line)
        roomdesc = '\n'.join(rd_lines)
        croomlist.append(f"({roomid}, '{roomname}', '{roomdesc}')")
        roomid = roomid + 1

    # --- Get custom permabans from user ---
    print("-- Custom permaban list --")
    while True:
        ip_start = input('Starting IP (empty to skip): ') or None
        if ip_start == None:
            break
        ip_end = input('Ending IP: ') or ip_start
        ban_comment = input('Comment (for administrative notice)')
        cpbanlist.append(f"('{ip_start}', '{ip_end}', NULL, '{ban_comment}')")

    # --- Create custom data CMDs ---
    croom_cmd = "\n".join([ f"INSERT INTO `{db_prefix}_rooms` (`_id`, `roomname`, `description`) VALUES",
                ",\n".join(croomlist),
                "ON DUPLICATE KEY UPDATE _id=VALUES(_id), roomname=VALUES(roomname), description=VALUES(description);"
                ]) if croomlist else ""

    cpban_cmd = "\n".join([ f"INSERT INTO `{db_prefix}_bans` (`ip_first`,`ip_last`,`expire`,`comment`) VALUES",
                ",\n".join(cpbanlist),
                "ON DUPLICATE KEY",
                "UPDATE _id=VALUES(_id), ip_first=VALUES(ip_first), ip_last=VALUES(ip_last), expire=VALUES(expire), comment=VALUES(comment);"
                ]) if cpbanlist else ""

    subst_vars = {
        'dbname': db_name,
        'roomtabname': f'{db_prefix}_rooms',
        'servtabname': f'{db_prefix}_servers',
        'versiontabname': f'{db_prefix}_versions',
        'bantabname': f'{db_prefix}_bans',
        'prefix': db_prefix,
        'customroomlist': croom_cmd,
        'custompermabans': cpban_cmd,
    }


    _generate_sql(ofilename, 'configurepy/gensql_templates/srb2http-template.sql', db_prefix, subst_vars)
    return states["gensql"]["main"]

def state_gensql_srb2kart():
    ofilename = input('SQL script name (default "docker/db/initdb.d/srb2kart.sql"): ') or 'docker/db/initdb.d/srb2kart.sql'
    db_name = input('Database name (default "liquidms"): ') or "liquidms"
    db_prefix = input('Table prefix (default "srb2kart"): ') or "srb2kart"

    gamelist = []
    cpbanlist = []

    # --- Get custom games from user ---
    print("-- Hosted games --")
    while True:
        game = input('Game handle (e.g. "SRB2Kart"; leave empty to skip): ') or None
        if game == None:
            break
        version_name = input('Game version name (e.g. "v1.6"): ') or None
        version_id = input('Game version ID (e.g. "10"): ') or None
        print('Game version ID (empty line to end): ')
        gamelist.append(f"('{game}', {version_id}, '{version_name}')")

    # --- Get custom permabans from user ---
    print("-- Custom permaban list --")
    while True:
        ip_start = input('Starting IP (empty to skip): ') or None
        if ip_start == None:
            break
        ip_end = input('Ending IP: ') or ip_start
        ban_comment = input('Comment (for administrative notice)')
        cpbanlist.append(f"('{ip_start}', '{ip_end}', NULL, '{ban_comment}')")

    # --- Create custom data CMDs ---
    gamelist_cmd = "\n".join([ f"INSERT INTO `{db_prefix}_versions` (`game`, `version_id`, `version_name`) VALUES",
                ",\n".join(gamelist),
                "ON DUPLICATE KEY UPDATE game=VALUES(game), version_id=VALUES(version_id), version_name=VALUES(version_name);"
                ]) if gamelist else ""

    cpban_cmd = "\n".join([ f"INSERT INTO `{db_prefix}_bans` (`ip_first`,`ip_last`,`expire`,`comment`) VALUES",
                ",\n".join(cpbanlist),
                "ON DUPLICATE KEY",
                "UPDATE _id=VALUES(_id), ip_first=VALUES(ip_first), ip_last=VALUES(ip_last), expire=VALUES(expire), comment=VALUES(comment);"
                ]) if cpbanlist else ""

    subst_vars = {
        'dbname': db_name,
        'servtabname': f'{db_prefix}_servers',
        'versiontabname': f'{db_prefix}_versions',
        'bantabname': f'{db_prefix}_bans',
        'prefix': db_prefix,
        'gamelist': gamelist_cmd,
        'custompermabans': cpban_cmd,
    }


    _generate_sql(ofilename, 'configurepy/gensql_templates/srb2kart-template.sql', db_prefix, subst_vars)
    return states["gensql"]["main"]

def state_gensql_srb2legacy():
    print("Not implemented yet.")
    return states["gensql"]["main"]

def state_gensql_menu():
    print(
        "--- SQL script generator ---\n"
        "\nThe scripts generated here are designed to be executed wholesale "
        "on your SQL server, depending on which APIs you want LiquidMS to host.\n"
        "If you are running the development Docker Compose network, place "
        "your scripts under docker/db/initdb.d/ before bulding the DB container.\n\n"
        f"(1) SRB2HTTP\n"
        f"(2) SRB2Kart/Ring Racers\n"
        f"(3) SRB2 Legacy\n"
        "\n"
        "(Q)uit to main menu\n"
    )

    while True:
        choice = input("Select an database type to generate: ")
        match choice:
            case "1":
                return states["gensql"]["srb2http"]
            case "2":
                return states["gensql"]["srb2kart"]
            case "3":
                return states["gensql"]["srb2legacy"]
            case "q"|"Q":
                return states["main"]
            case _:
                print (f"Invalid option \"{choice}\"\n")


states["gensql"] = {
    "main": state_gensql_menu,
    "srb2http": state_gensql_srb2http,
    "srb2kart": state_gensql_srb2kart,
    "srb2legacy": state_gensql_srb2legacy,
}

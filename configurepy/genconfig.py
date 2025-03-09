import yaml
from getpass import getpass

from .states import states


def _generate_yaml(outfile, data):
    print(f"Generating YAML config {outfile}...")
    with open(outfile, "w") as ofile:
        print(f"Writing YAML config into {outfile}...")
        yaml.dump(data, ofile)
    print(f"Success. Your YAML config can be found at {outfile}")


### SRB2HTTP CONFIG ###
def state_genconfig_srb2http():
    ofilename = input('Filename (default "dist/apis/srb2http/config.yaml"): ') or 'dist/apis/srb2http/config.yaml'

    data = {
        'netgame_query_limit': {
            "seconds": input('Rate limit cooldown (in seconds; default 1): ') or 1,
            "n": input('Rate limit threshold (in number; default 5): ') or 5,
        },
        'db': {
            "dsn": input('DSN connection string: '),
            "user": input('Database user (default: "lmsnode"): ') or 'lmsnode',
            "password": getpass('DB User password: '),
        },
        'tables': {
            "bans": input('Banned IP table name (default: "srb2http_bans"): ') or  "srb2http_bans",
            "rooms": input('Room table table name (default: "srb2http_rooms"): ') or  "srb2http_rooms",
            "servers": input('Server table name (default: "srb2http_servers"): ') or  "srb2http_servers",
            "versions": input('Game version table name (default: "srb2http_versions"): ') or  "srb2http_versions",
        },
    }

    # --- Get custom rooms from user ---
    print('Message Of The Day (empty line to end): ')
    motd_lines = []
    while True:
        line = input()
        if not line:
            break
        motd_lines.append(line)
    data["motd"] = '\n'.join(motd_lines)

    _generate_yaml(ofilename, data)
    return states["genconfig"]["main"]


### SRB2Kart CONFIG ###
def state_genconfig_srb2kart():
    print("Not implemented yet.")
    return states["genconfig"]["main"]


### SRB2 Legacy CONFIG ###
def state_genconfig_srb2legacy():
    print("Not implemented yet.")
    return states["genconfig"]["main"]


### SRB2Query CONFIG ###
def state_genconfig_srb2query():
    print("Not implemented yet.")
    return states["genconfig"]["main"]


### Snitch CONFIG ###
def state_genconfig_snitch():
    print("Not implemented yet.")
    return states["genconfig"]["main"]


### MAIN MENU ###
def state_genconfig_menu():
    print(
        "--- YAML config generator ---\n"
        "\nThe scripts generated here are designed to be executed wholesale "
        "on your SQL server, depending on which APIs you want LiquidMS to host.\n"
        "If you are running the development Docker Compose network, place "
        "your scripts under docker/db/initdb.d/ before bulding the DB container.\n\n"
        "(1) SRB2HTTP\n"
        "(2) SRB2Kart/Ring Racers\n"
        "(3) SRB2 Legacy\n"
        "(4) LiquidMS Snitch\n"
        "(5) Web frontend\n"
        "(6) SRB2Query\n"
        "\n"
        "(Q)uit to main menu\n"
    )

    while True:
        choice = input("Select endpoint which you'd like to configure: ")
        match choice:
            case "1":
                return states["genconfig"]["srb2http"]
            case "2":
                return states["genconfig"]["srb2kart"]
            case "3":
                return states["genconfig"]["srb2legacy"]
            case "q"|"Q":
                return states["main"]
            case _:
                print (f"Invalid option \"{choice}\"\n")

states["genconfig"] = {
    "main": state_genconfig_menu,
    "srb2http": state_genconfig_srb2http,
    "srb2kart": state_genconfig_srb2kart,
    "srb2legacy": state_genconfig_srb2legacy,
    "srb2query": state_genconfig_srb2query,
    "snitch": state_genconfig_snitch,
}

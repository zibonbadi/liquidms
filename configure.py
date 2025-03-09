#!/usr/env python3

###
### TODO:
###
### Make SQL script generator for shared hosters
###

import string

##### ===============
##### State behaviors
##### ===============

def state_api_main():
    while True:
        print(
            "--- API configuration ---\n"
            f"(1)[] LiquidMS Snitch\n"
            f"(2)[] SRB2HTTP\n"
            f"(3)[] SRB2Kart/Ring Racers\n"
            f"(4)[] SRB2 Legacy\n"
            "\n"
            "(Q)uit to main menu\n"
        )

        while True:
            choice = input("Select an API to configure: ")
            match choice:
                case "1":
                    return states["api"]["snitch"]
                case "2":
                    return states["api"]["srb2http"]
                case "3":
                    return states["api"]["srb2kart"]
                case "4":
                    return states["api"]["srb2legacy"]
                case "q"|"Q":
                    return states["main"]
                case _:
                    print (f"Invalid option \"{choice}\"\n")

def state_api_snitch():
    return states["main"]

def state_api_srb2http():
    import configurepy.srb2http

    while True:
        print(
            "--- API configuration ---\n"
            f"(E)[] Enable SRB2HTTP endpoint\n"
            f"(B) Blacklist configuration\n"
            f"(R) World room configuration\n"
            f"(V) Version configuration\n"
            "\n"
            "(Q)uit to API configuration\n"
        )

        while True:
            choice = input("Select an API to configure: ")
            match choice:
                case "e"|"E":
                    print(f"Option not implemented yet.\n")
                    return states["api"]["srb2http"]
                case "b"|"B":
                    print(f"Option not implemented yet.\n")
                    return states["api"]["srb2http"]
                case "r"|"R":
                    print(f"Option not implemented yet.\n")
                    return states["api"]["srb2http"]
                case "v"|"V":
                    print(f"Option not implemented yet.\n")
                    return states["api"]["srb2http"]
                case "q"|"Q":
                    return states["main"]
                case _:
                    print(f"Invalid option \"{choice}\"\n")
    return states["api"]["main"]

def state_api_srb2kart():
    return states["main"]

def state_api_srb2legacy():
    ### TBA after the legacy server is actually done
    print (f"The Legacy SRB2 API is not implemented yet. :(\n")
    return states["main"]

def state_build():
    print("Gathering settings...\n")

    while True:
        choice = input("Are these settings correct? [y/N]\n")
        if choice == "y" or choice == "Y":
            return states["quit"]
        else:
            return states["main"]

def state_db_main():
    a = input("MYSQL_USER=")
    a = input("MYSQL_PASSWORD=")
    a = input("MYSQL_ROOT_PASSWORD=")
    a = input("MYSQL_USER=")
    a = input("MYSQL_USER=")
    return states["main"]

def state_frontend_main():
    return states["main"]

def state_http_main():
    return states["main"]

def state_main():
    while True:
        print(
            "--- Main menu ---\n"
            "(A)PI setup\n"
            "(D)atabase setup\n"
            "(G)enerate SQL config\n"
            "Generate (Y)AML config\n"
            "(F)rontend setup\n"
            "(H)TTP router setup (Caddy)\n"
            "Snitch (R)elay setup\n"
            "\n"
            "(S)ave config and exit\n"
            "(Q)uit without saving\n"
        )

        while True:
            choice = input("Select an option: ")
            match choice:
                case "a"|"A":
                    return states["gensql"]["main"]
                case "d"|"D":
                    return states["db"]["main"]
                case "y"|"Y":
                    return states["genconfig"]["main"]
                case "g"|"G":
                    return states["gensql"]["main"]
                case "f"|"F":
                    return states["frontend"]["main"]
                case "h"|"H":
                    return states["http"]["main"]
                case "r"|"R":
                    return states["relay"]["main"]
                case "s"|"S":
                    return states["build"]
                case "q"|"Q":
                    return states["quit"]
                case _:
                    print (f"Invalid option \"{choice}\"\n")
    return states["main"]

def state_relay_main():
    return states["main"]

def state_relay_jobs():
    return states["main"]

##### ================
##### Data definitions
##### ================

##### --------------------------------------------------
##### State machine (state transitions == return values)
##### --------------------------------------------------

from configurepy import genconfig, gensql # Initialize state object
from configurepy.states import states # Initialize state object
### Define states by mutating shared object
states["main"] = state_main
states["api"] = {
    "main": state_api_main,
    "snitch": state_api_snitch,
    "srb2http": state_api_srb2http,
    "srb2kart": state_api_srb2kart,
    "srb2legacy": state_api_srb2legacy,
}
states["build"] = state_build
states["db"] = {
    "main": state_db_main
}
states["frontend"] = {
    "main": state_frontend_main,
}
states["http"] = {
    "main": state_http_main,
}
states["relay"] = {
    "main": state_relay_main,
    "jobs": state_relay_jobs,
}


##### ---------------
##### Config Settings
##### ---------------

SETTINGS = {
    'USE': {
        "SNITCHAPI": "Y", # Possible values Y/N/L/2
        "SNITCHRELAY": "Y",
        "SRB2HTTP": "Y",
        "SRB2KART": "Y",
        #"SRB2LEGACY": "Y",
        "MARIADB": "Y",
    },
    "DB": {
    },
    "KARTAPIS": {
    },
}


if __name__ == '__main__':
    currentState = states["main"]
    print (
        "\n====== LiquidMS installer ======\n"
        "\n"
        "LiquidMS (c) 2021-2025 Liquid Underground.\n"
        "\n"
        "This software is licensed under GNU AGPLv3. You can read it here:\n"
        "<https://www.gnu.org/licenses/agpl-3.0.en.html>\n"
        "\n"
        "Welcome to the LiquidMS installer. You can customize your LiquidMS\n"
        "node using any of the options below. If you're just starting out\n"
        "and want to run a default setup, just save & exit the installer.\n"
    )
    while True:
        currentState = (currentState)()

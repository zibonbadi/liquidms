#!/bin/env python3

###
### TODO:
###
### Make SQL script generator for shared hosters
###

import string

##### ===============
##### State behaviors
##### ===============

def state_main():
    while True:
        print(
            "--- Main menu ---\n"
            "Generate (S)QL config\n"
            "Generate (Y)AML config\n"
            "\n"
            "(Q)uit without saving\n"
        )

        while True:
            choice = input("Select an option: ")
            match choice:
                case "y"|"Y":
                    return states["genconfig"]["main"]
                case "s"|"S":
                    return states["gensql"]["main"]
                case "q"|"Q":
                    return states["quit"]
                case _:
                    print (f"Invalid option \"{choice}\"\n")
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

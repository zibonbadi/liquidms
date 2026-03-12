### Configure.py states
# Deliberately left empty; the modules fill them on their own
# state_quit is an example state to demonstrate how this is to be used

def state_quit():
    print("Goodbye!\n")
    exit(0)


states = {
    "quit": state_quit,
}


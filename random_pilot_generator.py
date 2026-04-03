import random

PILOT_NAMES = [
    "Amir Ben Salem",
    "Youssef Haddad",
    "Nabil Karim",
    "Sami Trabelsi",
    "Omar Mansouri",
]

AGENCY_NAMES = [
    "GoVibe Airways",
    "SkyLine Travel",
    "AeroPlus Agency",
    "BlueWing Services",
    "Nimbus Aviation",
]

AIRCRAFT_NUMBERS = [
    "TU-101",
    "TU-107",
    "TU-204",
    "A320-7",
    "B737-9",
]


def generate_random_assignment():
    pilot_name = random.choice(PILOT_NAMES)
    agency_name = random.choice(AGENCY_NAMES)
    aircraft_number = random.choice(AIRCRAFT_NUMBERS)

    return pilot_name, agency_name, aircraft_number


def render_details_card(pilot_name, agency_name, aircraft_number):
    print("+--------------------------------------+")
    print("|           DETAILS CARD               |")
    print("+--------------------------------------+")
    print(f"| Pilot Name   : {pilot_name}")
    print(f"| Agency Name   : {agency_name}")
    print(f"| Avion Number  : {aircraft_number}")
    print("+--------------------------------------+")


if __name__ == "__main__":
    pilot_name, agency_name, aircraft_number = generate_random_assignment()

    render_details_card(pilot_name, agency_name, aircraft_number)

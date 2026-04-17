import os

file_path = 'templates/checkout/index.html.twig'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

replacements = {
    'RÃ©servations': 'Réservations',
    'rÃ©servations': 'réservations',
    'rÃ©servation': 'réservation',
    'ConfirmÃ©es': 'Confirmées',
    'ConfirmÃ©e': 'Confirmée',
    'RefusÃ©es': 'Refusées',
    'RefusÃ©e': 'Refusée',
    'rÃ©sultat': 'résultat',
    'ModÃ¨le de PrÃ©diction': 'Modèle de Prédiction',
    'DÃ©tails': 'Détails',
    'trouvÃ©': 'trouvé',
    'CrÃ©ez votre premiÃ¨re': 'Créez votre première',
    'basÃ©': 'basé',
    'dÃ©lai': 'délai',
    'DÃ©tectÃ©': 'Détecté',
    'ParamÃ¨tres': 'Paramètres',
    'SÃ»r': 'Sûr'
}

for old, new in replacements.items():
    content = content.replace(old, new)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Checkouts index fixed")

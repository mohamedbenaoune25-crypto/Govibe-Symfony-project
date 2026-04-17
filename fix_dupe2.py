import re
with open('templates/checkout/index.html.twig', 'r', encoding='utf-8') as f:
    html = f.read()

# Fix the duplicate block
html = html.replace('<div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; margin-bottom: 2rem; gap:1rem;">\n      <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; margin-bottom: 2rem; gap:1rem;">',
                   '<div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; margin-bottom: 1rem; gap:1rem;">')

replacements = {
    'ConfirmÃ©es': 'Confirmées',
    'RefusÃ©es': 'Refusées',
    'rÃ©sultat': 'résultat',
    'ModÃ¨le de PrÃ©diction': 'Modèle de Prédiction',
    'DÃ©tails': 'Détails',
    'trouvÃ©': 'trouvé',
    'CrÃ©ez votre premiÃ¨re': 'Créez votre première',
    'basÃ©': 'basé',
    'dÃ©lai': 'délai',
    'rÃ©servation': 'réservation',
    'DÃ©tectÃ©': 'Détecté',
    'ParamÃ¨tres': 'Paramètres',
    'SÃ»r': 'Sûr',
    'rÃ©servations': 'réservations'
}
for old, new in replacements.items():
    html = html.replace(old, new)

with open('templates/checkout/index.html.twig', 'w', encoding='utf-8') as f:
    f.write(html)
print("done")

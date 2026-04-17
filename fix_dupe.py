import re
with open('templates/checkout/index.html.twig', 'r', encoding='utf-8') as f:
    html = f.read()

# Fix the duplicate wrappers and broken encoding
# We will use latin1 trick or just replace it based on what's there
html = re.sub(r'<div style=\"display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; margin-bottom: 2rem; gap:1rem;\">\s*<div style=\"display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; margin-bottom: 2rem; gap:1rem;\">',
    '<div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; margin-bottom: 0; gap:1rem;">', html)

# The form is currently after the second one
# Actually let's just strip out the wrappers and re-add cleanly:

clean_regex = r'<div style=\"display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; margin-bottom: 0; gap:1rem;\">\s*(.*?<div class=\"gv-filter-pills\">.*?</div>)\s*<form method=\"get\" action=\"{{ path\(\'app_checkout_index\'\) }}\" style=\"display:flex; width:100%; max-width:350px;\">.*?</form>\s*</div>\s*<form'
# Let's just fix the encoding the standard way
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
for (old, new) in replacements.items():
    html = html.replace(old, new)

with open('templates/checkout/index.html.twig', 'w', encoding='utf-8') as f:
    f.write(html)
print("Encoding Fixed.")

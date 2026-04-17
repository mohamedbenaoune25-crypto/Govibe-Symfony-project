import os

file_path = 'templates/vol/index.html.twig'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

replacements = {
    'RÃ©servations': 'Réservations',
    'rÃ©servations': 'réservations',
    'DÃ©couvrez': 'Découvrez',
    'prÃ©sentation': 'présentation',
    'cohÃ©rente': 'cohérente',
    'rÃ©server': 'réserver',
    'rÃ©el': 'réel',
    'mÃ©tÃ©o': 'météo',
    'MÃ©tÃ©o': 'Météo',
    'PrÃ©dictions': 'Prédictions',
    'AÃ©roport': 'Aéroport',
    'Ã‰conomique': 'Économique',
    'DÃ©part': 'Départ',
    'tÃ´t': 'tôt',
    'DÃ©croissant': 'Décroissant',
    'RÃ©initialiser': 'Réinitialiser',
    'hÃ´tels': 'hôtels',
    'rÃ©sultat': 'résultat',
    'ArrivÃ©e': 'Arrivée',
    'RÃ©server': 'Réserver',
    'trouvÃ©': 'trouvé',
    'RafraÃ®chir': 'Rafraîchir',
    
    # Emojis that got mangled (if we can map them)
    'âš¡ï¸': '⚡',
    'ðŸŒ¦ï¸': '🌤️',
    'ðŸ“ˆ': '📈',
    'ðŸ“‰': '📉',
    'ðŸ¤–': '🤖',
    'ðŸ¨': '🏨',
    
    # Checkouts JS similar issues inside the template
    'RisquÃ©': 'Risqué',
    'â˜€ï¸': '☀️',
    'â˜ï¸': '☁️',
    'â›ˆï¸': '⛈️',
    'TrÃ¨s forte': 'Très forte',
    'ModÃ©rÃ©e': 'Modérée',
    'RecommandÃ©': 'Recommandé',
    'âœ…': '✅',
    'ðŸ‘': '👍',
    'Ã€ Ã©viter': 'À éviter',
    'âšï¸': '⚠️'
}

for old, new in replacements.items():
    content = content.replace(old, new)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Vols index fixed")

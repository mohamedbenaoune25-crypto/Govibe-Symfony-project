# Configuration - Gestion des Locations GoVibe

## Dépendances Requises

Pour que toutes les fonctionnalités de la gestion des locations fonctionnent, vous devez installer les dépendances suivantes :

```bash
# Pour la génération de fichiers Excel
composer require phpoffice/phpspreadsheet

# Pour la génération de QR Codes
composer require endroid/qr-code

# Pour la génération de PDF (optionnel mais recommandé)
composer require knplabs/knp-snappy
composer require h4cc/wkhtmltopdf-amd64  # Pour Linux/Unix
# ou
composer require h4cc/wkhtmltopdf-i386   # Pour 32-bit Linux
# ou installer wkhtmltopdf manuellement depuis http://wkhtmltopdf.org/

# Pour TCPDF (alternative pour PDF)
composer require tecnickcom/tcpdf
```

## Installation Étape par Étape

### 1. Installer les dépendances PHP
```bash
cd c:\Users\Hp\Desktop\Govibe-Symfony-project

# Google Sheets/Excel
composer require phpoffice/phpspreadsheet

# QR Code
composer require endroid/qr-code:^2.0

# PDF (avec TCPDF qui ne nécessite pas de dépendance système)
composer require tecnickcom/tcpdf
```

### 2. Configuration Symfony

Les services sont automatiquement configurés s'ils sont installés. Vérifiez que les fichiers de configuration sont présents dans `config/services.yaml`.

### 3. Répertoires d'Upload

Créez les répertoires nécessaires pour stocker les fichiers générés :
```bash
mkdir -p public/uploads/qrcodes
mkdir -p public/uploads/contrats
mkdir -p public/uploads/pdfs
chmod -R 755 public/uploads
```

### 4. Vérifier la configuration

Exécutez la commande pour vérifier que les services sont bien chargés :
```bash
php bin/console debug:container | grep location
php bin/console debug:container | grep qr
php bin/console debug:container | grep excel
```

## Routes Disponibles

### Pour les Clients (Nécessite ROLE_USER)

- **GET/POST** `/locations/` - Index (lister mes locations)
- **GET/POST** `/locations/new` - Créer une nouvelle location
- **GET** `/locations/{id}` - Voir les détails d'une location
- **GET/POST** `/locations/{id}/edit` - Modifier une location
- **POST** `/locations/{id}/cancel` - Annuler une location
- **GET** `/locations/{id}/pdf` - Télécharger en PDF
- **GET** `/locations/{id}/excel` - Télécharger en Excel
- **GET** `/locations/{id}/contrat` - Télécharger le contrat
- **GET** `/locations/{id}/qrcode` - Afficher le QR Code
- **GET** `/locations/stats/export` - Exporter les statistiques

### Pour les Admins (Nécessite ROLE_ADMIN)

- **GET** `/admin/locations/` - Index (lister toutes les locations)
- **GET** `/admin/locations/{id}` - Voir les détails d'une location
- **POST** `/admin/locations/{id}/confirm` - Confirmer une location
- **POST** `/admin/locations/{id}/cancel` - Annuler une location
- **GET** `/admin/locations/export/excel` - Exporter en Excel

## Sécurité

- Seul le propriétaire de la location peut voir/modifier/annuler sa location
- Les admins peuvent gérer toutes les locations
- Les modificati ons ne sont possibles que pour les locations en attente
- La disponibilité des voitures est vérifiée avant confirmation

## Fonctionnalités Implémentées

### Côté Client
✅ CRUD complet (Créer, Lire, Modifier, Annuler)
✅ Recherche par référence
✅ Tri par date, montant, etc.
✅ Génération automatique du nombre de jours et du montant total
✅ Génération de QR Code unique
✅ Génération de contrat PDF
✅ Export PDF et Excel des détails
✅ Validation des dates (fin > début)
✅ Pagination

### Côté Admin
✅ Gestion de toutes les locations
✅ Confirmation/Annulation des locations
✅ Filtre par référence, client, voiture, statut, dates
✅ Tri et recherche avancée
✅ Statistiques (total, par statut, revenue)
✅ Export Excel complet
✅ Vérification de disponibilité des voitures

### Métiers
✅ Calcul automatique du nombre de jours
✅ Calcul automatique du montant total
✅ Génération unique de références
✅ Génération QR Codes
✅ Génération contrats PDF
✅ Export PDF personnalisés
✅ Export Excel avec statistiques
✅ Gestion des statuts (EN_ATTENTE, CONFIRMEE, ANNULEE, TERMINEE)

## Notes Importantes

1. **Voitures indisponibles**: Une voiture n'apparaît plus dans le formulaire si elle a déjà une location confirmée ou en attente pour la même période.

2. **Contrats**: Les contrats sont générés automatiquement à la création de la location avec les conditions générales.

3. **QR Code**: Chaque location reçoit un QR Code unique contenant sa référence.

4. **Montants**: Les montants sont calculés automatiquement: nombre de jours × prix journalier de la voiture.

5. **Permissions**: Vérifiez que les utilisateurs ont le rôle approprié:
   - `admin` pour l'accès aux pages d'administration
   - `user` pour l'accès aux pages client

## Dépannage

### La génération de PDF ne fonctionne pas
- Installez TCPDF: `composer require tecnickcom/tcpdf`
- Ou installez wkhtmltopdf sur votre système

### Les QR Codes ne s'affichent pas
- Installez: `composer require endroid/qr-code`
- Vérifiez que le répertoire `public/uploads/qrcodes` existe et est accessible en écriture

### L'export Excel ne fonctionne pas
- Installez: `composer require phpoffice/phpspreadsheet`
- Vérifiez les permissions du répertoire `public/uploads`

## Support

Pour toute question ou problème, consultez la documentation Symfony officielle:
- Formulaires: https://symfony.com/doc/current/forms.html
- Sécurité: https://symfony.com/doc/current/security.html
- Génération PDF: https://symfony.com/doc/current/pdf_generation.html

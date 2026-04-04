# Guide d'Installation Rapide - Locations GoVibe

## 🚀 Installation Rapide (5 minutes)

### Étape 1 : Installer les Dépendances
```bash
cd c:\Users\Hp\Desktop\Govibe-Symfony-project

composer require phpoffice/phpspreadsheet endroid/qr-code tecnickcom/tcpdf
```

### Étape 2 : Créer les Répertoires
```bash
mkdir -p public/uploads/qrcodes
mkdir -p public/uploads/contrats
mkdir -p public/uploads/pdfs
```

### Étape 3 : Lancer le Serveur
```bash
php -S localhost:8000 -t public
```

### Étape 4 : Accéder aux Locations
- **Client**: http://localhost:8000/locations
- **Admin**: http://localhost:8000/admin/locations (pour les admins)

---

## 📋 Menu de Navigation

Vous trouverez le lien "Locations" dans la barre de navigation GoVibe, placé entre "Voitures" et "Vols".

---

## 👤 Utilisation Client

### Créer une Location
1. Cliquez sur "Locations" dans la navbar
2. Cliquez sur "Nouvelle Location"
3. Sélectionnez une voiture disponible
4. Entrez les dates (début et fin)
5. Le montant est calculé automatiquement
6. Cliquez sur "Créer la Location"

### Gestion des Locations
- **Voir détails**: Cliquez sur "Voir détails"
- **Modifier**: Cliquez sur "Modifier" (seulement si en attente)
- **Annuler**: Cliquez sur "Annuler" (seulement si en attente)
- **Télécharger contrat**: "Contrat" PDF
- **Télécharger QR Code**: "QR Code"
- **Détails PDF**: "Détails PDF"
- **Détails Excel**: "Détails Excel"

---

## 👨‍💼 Utilisation Administrateur

### Accéder au Panel Admin
1. Connectez-vous avec un compte administrateur
2. Un lien "Admin Locations" apparaît dans la navbar
3. Vous pouvez voir toutes les locations du système

### Actions Admin
- **Confirmée**: Approuve une location (rend la voiture indisponible)
- **Annulée**: Annule une location (libère la voiture)
- **Filtrer**: Par référence, client, voiture, date, statut
- **Exporter**: Générer un rapport Excel complet

---

## 📊 Statuts de Location

| Statut | Signification | Actions Possibles |
|--------|---------------|-------------------|
| EN_ATTENTE | En attente de confirmation | Modifier, Annuler, Confirmer (admin) |
| CONFIRMEE | Approuvée et voiture réservée | Annuler (admin) |
| ANNULEE | Annulée | Aucune (définitive) |
| TERMINEE | Terminée (auto) | Aucune |

---

## 🎯 Cas d'Usage Principales

### Cas 1 : Client loue une voiture
1. Client accède à `/locations/new`
2. Sélectionne voiture, dates
3. Crée la location (statut EN_ATTENTE)
4. Attend confirmation admin

### Cas 2 : Admin confirme une location
1. Admin voit location en EN_ATTENTE
2. Clique "Confirmer"
3. Voiture devient indisponible pour la période
4. Client reçoit la confirmation

### Cas 3 : Admin annule une location
1. Admin clique "Annuler"
2. Location passe à ANNULEA
3. Voiture redevient disponible

### Cas 4 : Client annule sa location
1. Client clique "Annuler" sur sa location EN_ATTENTE
2. Location passe à ANNULEA
3. Peut en créer une autre

---

## 🔍 Troubleshooting

### La page de locations est vide / erreur 404
```bash
# Vérifier les routes
php bin/console debug:router | grep location

# Vérifier la configuration
php bin/console config:dump
```

### Impossible de télécharger les fichiers
- Vérifier que `public/uploads` est accessible en écriture
- Vérifier les permissions: `chmod -R 755 public/uploads`

### Les images QR ne s'affichent pas
- Vérifier l'installation: `php bin/console debug:container | grep qr`
- Réinstaller si nécessaire: `composer require endroid/qr-code`

### Erreur "Access Denied"
- Vérifier le rôle de l'utilisateur
- Les clients ont besoin de ROLE_USER
- Les admins ont besoin de ROLE_ADMIN

---

## 💾 Base de Données

L'entité `Location` è déjà définie. Elle contient:
- Voiture louée
- Utilisateur (client)
- Dates de début/fin
- Nombre de jours (calculé)
- Montant total (calculé)
- Statut
- Fichiers associés (contrat, QR code)

Aucune migration n'est nécessaire si la base est à jour.

---

## 📚 Documentation Complète

Voir les fichiers:
- `LOCATIONS_SETUP.md` - Configuration technique détaillée
- `IMPLEMENTATION_SUMMARY.md` - Résumé technique complet

---

## ⚡ Performance

- Pagination: 15 locations par page (admin), 10 (client)
- Searches: Indexées sur référence et dates
- Queries: Optimisées avec QueryBuilder

---

## 🐛 Logs et Debugging

```bash
# Voir les logs
tail -f var/log/dev.log

# Activer le profiler Symfony
# (automatiquement en mode dev)
```

---

## ✅ Checklist Avant Production

- [ ] Composer packages installés
- [ ] Répertoires d'upload créés
- [ ] Tests locations client et admin
- [ ] Tests confirmations/annulations
- [ ] Tests exports PDF/Excel
- [ ] Tests droits d'accès
- [ ] Emails de notification (optionnel)

---

**Vous êtes prêt à utiliser la gestion des locations GoVibe!** 🎉

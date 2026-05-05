# Project Title
## GoVibe

---

## Overview
**GoVibe** est une application **bureau / web** conçue pour simplifier la gestion quotidienne d’une **agence de voyage**.  
Elle centralise les opérations clés : gestion des utilisateurs, véhicules, vols, hôtels, activités et forum, avec une couche d’**IA** (recommandations, chatbot, assistance) pour améliorer l’expérience.

> Projet réalisé dans le cadre du **PIDEV (3ème année Ingénierie) – Esprit School of Engineering**, année universitaire **2025–2026**.

---

## Features
- **Gestion des utilisateurs**
  - Authentification avec **Face Recognition**
  - **Sign in with Google**
- **Gestion des voitures**
  - **AI Recommendation** (recommandation intelligente)
  - Intégration **Maps**
- **Gestion des vols**
  - Paiement via **Stripe**
  - **Air Signature**
- **Gestion des hôtels**
  - Intégration **Météo**
  - **Chatbot**
- **Gestion des activités**
  - Intégration **Maps**
  - **IA**
- **Gestion forum**
  - Modération / assistance via **IA**

---

## Tech Stack
### Frontend
- **Java / JavaFX** (Desktop)
- **Symfony Twig** (Web UI)

### Backend
- **Java**
- **Symfony**

---

## Architecture
- **MVC (Model – View – Controller)**

### Project Structure (high-level)
- `entities`
- `controllers`
- `services`
- `utils`
- `mains`
- `resources`


---

## Contributors
- Chaabi Salma 
- Mchala Mohamed Aziz
- Mohamed Ben Aoune
- Omar Bouhouch
- Khaled Bakhtri 
- Youssef Saaidi 

---

## Academic Context
Ce projet a été développé dans le cadre du module **PIDEV** (Projet Intégré de Développement) en **3ème année** à **Esprit School of Engineering**, pour l’année **2025–2026**.

**Encadrante :** Emna Charfi

---

## Getting Started
### Prérequis
- **Java 21**
- **XAMPP** (MySQL + Apache)
- **Symfony**
- **Maven** (pour exécuter le module JavaFX)

### Installation
1. Cloner le projet :
   ```bash
   git clone https://github.com/YoussefSaaidi2004/Esprit-PIDEV-3A23-2526-GoVibe.git
   cd Esprit-PIDEV-3A23-2526-GoVibe
   ```

2. Configurer la base de données (MySQL via XAMPP) :
   - Démarrer **Apache** et **MySQL** depuis XAMPP
   - Créer la base de données
   - Renseigner les identifiants dans la configuration (Java/Symfony selon votre setup)

### Lancer l’application
#### Desktop (JavaFX)
Depuis le module Java (Maven) :
```bash
mvn javafx:run
```

#### Web (Symfony)
Depuis le projet Symfony :
```bash
symfony serve
```

---

## Acknowledgments
- **Esprit School of Engineering**
- **Emna Charfi** (Encadrante)
- Services / APIs : Stripe, Google Sign-In, Maps, etc.

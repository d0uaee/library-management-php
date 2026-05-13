# 📚 Système de Gestion de Bibliothèque - ENSAM Meknès

Un système complet de gestion de bibliothèque développé pour l'ENSAM Meknès, permettant la gestion des emprunts, réservations, notifications et communication entre étudiants et administrateurs.

## 🎯 Fonctionnalités Principales

### Pour les Administrateurs
- **Dashboard complet** : Vue d'ensemble des statistiques de la bibliothèque
- **Gestion des livres** : Ajout, modification et suppression de livres avec catégories
- **Gestion des emprunts** : Validation, suivi et traitement des demandes d'emprunt
- **Gestion des pénalisations** : Blocage temporaire des étudiants en retard
- **Système de messagerie** : Communication directe avec les étudiants
- **Contact et support** : Traitement des demandes des étudiants
- **Statistiques avancées** : Exportation PDF des rapports
- **Traitement des prolongations** : Validation des demandes de prolongation

### Pour les Étudiants
- **Catalogue interactif** : Recherche et consultation des livres disponibles
- **Système de réservation** : Réservation de livres non disponibles
- **Demandes d'emprunt** : Soumission de demandes d'emprunt en ligne
- **Suivi des emprunts** : Consultation de l'historique et des emprunts en cours
- **Notifications en temps réel** : Alertes de retour, retards et disponibilité
- **Messagerie** : Communication avec l'administration
- **Évaluation des livres** : Notation des livres (1-5 étoiles)
- **Profil personnel** : Gestion des informations personnelles
- **Statistiques personnelles** : Suivi de l'activité de lecture

## 🛠 Technologies Utilisées

- **Backend** : PHP 7.4+
- **Base de données** : MySQL (via PDO)
- **Frontend** : HTML5, CSS3, JavaScript
- **Serveur** : Apache (XAMPP)
- **Architecture** : MVC (Modèle-Vue-Contrôleur)

## 📋 Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Serveur web Apache
- XAMPP (recommandé) ou équivalent (WAMP, MAMP)

## 🚀 Installation

### 1. Cloner/Télécharger le projet
```bash
# Placer le projet dans le dossier htdocs de XAMPP
cd c:\xampp\htdocs\
# Le dossier du projet devrait être : c:\xampp\htdocs\bibliotheque
```

### 2. Configuration de la base de données

#### Créer la base de données
```sql
CREATE DATABASE bibliotheque CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### Importer le schéma
```bash
# Via phpMyAdmin ou en ligne de commande :
mysql -u root -p bibliotheque < sql/schema.sql
```

#### Importer les données de test (optionnel)
```bash
mysql -u root -p bibliotheque < sql/data.sql
```

### 3. Configuration de l'application

Modifier le fichier `config/database.php` si nécessaire :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bibliotheque');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Initialisation des comptes administrateurs

Exécuter le script de mise à jour des mots de passe :
```bash
# Via navigateur :
http://localhost/bibliotheque/utils/hash_passwords.php
```

### 5. Démarrage de l'application

1. Démarrer Apache et MySQL via XAMPP Control Panel
2. Accéder à l'application : `http://localhost/bibliotheque/`

## 👥 Comptes par Défaut

### Compte Administrateur
- **Email** : admin@ensam.ma
- **Mot de passe** : Consulter `utils/hash_passwords.php` ou `data.sql`

### Compte Étudiant (Test)
- **Email** : etudiant@ensam.ma
- **Mot de passe** : Consulter les données importées

## 📁 Structure du Projet

```
bibliotheque/
├── assets/                    # Ressources statiques
│   ├── css/                  # Feuilles de style
│   │   ├── index.css        # Style page d'accueil
│   │   ├── admin/           # Styles interface admin
│   │   ├── auth/            # Styles authentification
│   │   └── etudiant/        # Styles interface étudiant
│   └── img/                 # Images
│       ├── covers/          # Couvertures de livres
│       └── home/            # Images page d'accueil
│
├── config/                   # Configuration
│   ├── config.php           # Configuration générale
│   └── database.php         # Configuration base de données
│
├── sql/                      # Scripts SQL
│   ├── schema.sql           # Structure de la base de données
│   └── data.sql             # Données de test
│
├── utils/                    # Utilitaires
│   ├── hash_passwords.php   # Hachage des mots de passe
│   ├── security.php         # Fonctions de sécurité
│   └── update-admin.php     # Mise à jour admin
│
├── views/                    # Vues de l'application
│   ├── admin/               # Interface administrateur
│   │   ├── dashboard.php    # Tableau de bord
│   │   ├── livres.php       # Gestion des livres
│   │   ├── emprunts.php     # Gestion des emprunts
│   │   ├── messagerie.php   # Messagerie
│   │   ├── contact.php      # Support étudiant
│   │   ├── penalisation.php # Gestion pénalisations
│   │   ├── statistiques.php # Statistiques
│   │   └── export_pdf.php   # Export PDF
│   │
│   ├── auth/                # Authentification
│   │   ├── login-admin.php
│   │   ├── login-etudiant.php
│   │   ├── register-etudiant.php
│   │   └── logout.php
│   │
│   └── etudiant/            # Interface étudiant
│       ├── catalogue.php    # Catalogue des livres
│       ├── mes-emprunts.php # Mes emprunts
│       ├── reservation.php  # Réservations
│       ├── messages.php     # Messagerie
│       ├── notifications.php# Notifications
│       ├── profil.php       # Profil
│       └── stats.php        # Statistiques personnelles
│
└── index.php                 # Page d'accueil
```

## 🗃 Modèle de Base de Données

### Tables Principales

- **admin** : Comptes administrateurs
- **etudiant** : Comptes étudiants
- **livre** : Catalogue des livres
- **categorie** : Catégories de livres
- **demande_emprunt** : Demandes d'emprunt en attente
- **emprunt** : Emprunts validés et historique
- **reservation** : Réservations de livres
- **notification** : Système de notifications
- **message** : Messagerie admin/étudiant
- **evaluation** : Notations des livres
- **penalisation** : Pénalisations temporaires
- **retard** : Suivi des retards et amendes

### Relations
- Un étudiant peut avoir plusieurs emprunts, réservations et notifications
- Un livre peut avoir plusieurs exemplaires et appartient à une catégorie
- Les emprunts sont liés aux demandes d'emprunt
- Système de contraintes d'intégrité référentielle (FK)

## 🔐 Sécurité

- **Hachage des mots de passe** : Utilisation de `password_hash()` et `password_verify()`
- **Requêtes préparées** : Protection contre les injections SQL via PDO
- **Sessions sécurisées** : Gestion des sessions utilisateur
- **Validation des entrées** : Filtrage et validation côté serveur
- **Séparation des rôles** : Interfaces distinctes admin/étudiant

## 📊 Fonctionnalités Détaillées

### Système d'Emprunt
1. L'étudiant soumet une demande d'emprunt
2. L'admin valide ou refuse la demande
3. Si validée, l'emprunt est créé avec une date de retour prévue
4. Possibilité de prolongation (une seule fois)
5. Alertes automatiques avant la date de retour
6. Gestion des retards et pénalisations

### Système de Réservation
- Réservation de livres non disponibles
- Notification automatique lors de la disponibilité
- Annulation possible par l'étudiant

### Système de Notification
- Rappels de retour
- Alertes de retard
- Disponibilité de livres réservés
- Informations générales
- Notifications de compte bloqué

### Messagerie Interne
- Communication bidirectionnelle admin/étudiant
- Marquage des messages lus/non lus
- Filtrage par sujet/intérêt

### Statistiques
- Statistiques globales (dashboard admin)
- Statistiques personnelles (interface étudiant)
- Exportation PDF des rapports
- Analyse des tendances d'emprunt

## 🎨 Interface Utilisateur

- Design responsive et moderne
- Navigation intuitive
- Indicateurs visuels de statut
- Messages de confirmation/erreur
- Tableaux interactifs avec recherche et tri

## 📝 Maintenance

### Sauvegarde de la Base de Données
```bash
mysqldump -u root -p bibliotheque > backup_$(date +%Y%m%d).sql
```

### Mise à Jour du Schéma
```bash
mysql -u root -p bibliotheque < sql/schema.sql
```

## 🐛 Dépannage

### Problème de Connexion à la Base de Données
- Vérifier que MySQL est démarré
- Vérifier les paramètres dans `config/database.php`
- Vérifier que la base de données existe

### Erreur 404
- Vérifier que le projet est dans `c:\xampp\htdocs\bibliotheque`
- Vérifier que Apache est démarré
- Vérifier l'URL : `http://localhost/bibliotheque/`

### Problème de Session
- Vérifier les permissions du dossier `tmp` de PHP
- Vérifier que `session_start()` est appelé dans `config.php`

### Images non affichées
- Vérifier les permissions du dossier `assets/img/`
- Vérifier les chemins relatifs dans le code

## 🔄 Améliorations Futures

- [ ] API RESTful pour application mobile
- [ ] Système de recommandation de livres
- [ ] Intégration avec des API de bibliothèques externes
- [ ] Chat en temps réel
- [ ] Système de QR code pour les livres
- [ ] Application mobile (Android/iOS)
- [ ] Multi-langue (Français/Arabe/Anglais)
- [ ] Système de paiement en ligne pour les amendes
- [ ] Gestion des e-books
- [ ] Statistiques avancées avec graphiques interactifs

## 👨‍💻 Contributeurs

Projet développé pour l'ENSAM Meknès

## 📄 Licence

Ce projet est développé dans un cadre éducatif pour l'ENSAM Meknès.


---

**Version** : 1.0.0  
**Dernière mise à jour** : Décembre 2025  
**Développé avec** ❤️ pour l'ENSAM Meknès

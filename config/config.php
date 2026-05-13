<?php
/*
 CONFIGURATION GÉNÉRALE DU PROJET (les constantes importantes)
 */

session_start();


define('ROOT_PATH', dirname(__DIR__));
define('ASSETS_PATH', '/bibliotheque/assets');      // Chemin web des assets 

define('BASE_URL', 'http://localhost/bibliotheque');
define('ASSETS_URL', BASE_URL . '/assets');

// RÈGLES MÉTIER
define('MAX_EMPRUNTS', 2);           // Nombre maximum d'emprunts simultanés
define('DUREE_EMPRUNT', 15);         // Durée d'emprunt en jours
define('DELAI_RETARD_AVERTISSEMENT', 1);  // Jours avant premier avertissement
define('DELAI_RETARD_BLOCAGE', 7);   // Jours avant restriction d'emprunt
define('NOMBRE_RETARDS_BLOCAGE', 3); // Nombre de retards avant blocage

define('LIVRES_PAR_PAGE', 12);       // Nombre de livres par page dans le catalogue


// EMAIL ACADÉMIQUE + TELEPHONE
define('EMAIL_ETUDIANT_DOMAIN', '@ensam.ma');
define('EMAIL_ADMIN_DOMAIN', '@bibliotheque.com');
define('EMAIL_ETUDIANT_REGEX', '/^[a-z]+\.[a-z]+@ensam\.ma$/i'); 
define('TELEPHONE_REGEX', '/^\+212[5-7]\d{8}$/');

// SÉCURITÉ
define('HASH_ALGO', PASSWORD_DEFAULT); // Algorithme de hachage des mots de passe


// MESSAGES D'ERREUR
define('MSG_CONNEXION_REQUISE', 'Vous devez être connecté pour accéder à cette page');
define('MSG_ADMIN_REQUIS', 'Accès réservé aux administrateurs');
define('MSG_ETUDIANT_REQUIS', 'Accès réservé aux étudiants');
define('MSG_COMPTE_BLOQUE', 'Votre compte est bloqué. Contactez la bibliothèque');
define('MSG_LIMITE_EMPRUNTS', "Vous avez atteint le maximum de " . MAX_EMPRUNTS . " emprunts simultanés");

require_once ROOT_PATH . '/config/database.php';

date_default_timezone_set('Africa/Casablanca');

?>
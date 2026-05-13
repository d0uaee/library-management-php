<?php
/** 
* FICHIER DE CONFIGURATION DE LA BASE DE DONNÉES 
 *Créer un pont sécurisé entre PHP et MySQL using PDO
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'bibliotheque'); // Nom de ta base de données
define('DB_USER', 'root');            
define('DB_PASS', '');
define('DB_PORT', '3307');
define('DB_CHARSET', 'utf8mb4');// Pour supporter les accents et emojis

// try/catch pour securité+ Contrôler un crash
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    //comment PDO doit se comporter
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,                    //retourner false
        
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,               //tableaux associatifs
        
        PDO::ATTR_EMULATE_PREPARES => false                             // Désactive l'émulation des requêtes préparées
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
     
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

?>

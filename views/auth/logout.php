<?php
/**
 * PAGE DE DÉCONNEXION
 * 
 * Déconnecte l'utilisateur et détruit la session
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';

// Appel de la fonction de déconnexion
logout();
?>
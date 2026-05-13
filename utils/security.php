<?php
/*
  FONCTIONS DE SÉCURITÉ réutilisables  
*/

// ==========================
// GESTION DE LA SESSION
// ==========================
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // démarre la session si elle n'existe pas
}

// Vérifie si un utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

//Vérifiel utilisateur = étudiant
function isEtudiant() {
    return isLoggedIn() && $_SESSION['user_type'] === 'etudiant';
}

//Vérifie  l'utilisateur = admin
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_type'] === 'admin';
}

// redirige vers la page d'accueil si non connecté
function requireLogin($message = MSG_CONNEXION_REQUISE) {
    if (!isLoggedIn()) {
        $_SESSION['error_message'] = $message;
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Force rôle étudiant
function requireEtudiant() {
    requireLogin();
    if (!isEtudiant()) {
        $_SESSION['error_message'] = 'Accès réservé aux étudiants';
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Force rôle admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['error_message'] = 'Accès réservé aux administrateurs';
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Déconnexion simple
function logout() {
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Nettoie une chaîne pour éviter les problèmes d'affichage
function cleanInput($s) {
    return htmlspecialchars(trim((string)$s), ENT_QUOTES, 'UTF-8');
}

// Validation simple d'email étudiant (utilise la constante si définie)
function validateEmailEtudiant($email) {
    return preg_match(EMAIL_ETUDIANT_REGEX, $email); //preg_match car le format étudiant est très spécifique: prenom.nom@ensam.ma
}

// Valide telephone marocain

function validateTelephone($telephone) {
    return preg_match(TELEPHONE_REGEX, $telephone);
}

// Valide un email administrateur
function validateEmailAdmin($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) &&  // vérifie le format général d'email
           str_ends_with($email, EMAIL_ADMIN_DOMAIN); // environnement doit être PHP 8+ et vérifie que ça se termine par @ensam.ac.ma
}

//Valide un ISBN
function validateISBN($isbn) {
    // Retire les tirets
    $isbn = str_replace('-', '', $isbn);
    // Vérifie que c'est 10 ou 13 chiffres
    return preg_match('/^[0-9]{10,13}$/', $isbn);
}


// Affiche un message d'erreur ou succès (HTML minimal)
function showError($message) {
    return '<div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> ' . 
                htmlspecialchars($message) . // échappe les caractères spéciaux pour éviter XSS
            '</div>';
}

function showSuccess($message) {
    return '<div class="alert alert-success">
                <i class="fas fa-check-circle"></i> ' . 
                htmlspecialchars($message) . // échappe les caractères spéciaux pour éviter XSS
            '</div>';
}

// Vérifie rapidement si un étudiant peut emprunter 
function canBorrow($pdo, $id_etudiant) {
    // Récupère les infos de l'étudiant
    $stmt = $pdo->prepare("
        SELECT statut, nombre_retards 
        FROM etudiant 
        WHERE id_etudiant = ?
    ");// prépare la requête
    $stmt->execute([$id_etudiant]); // execute avec tableau pour éviter SQL injection
    $etudiant = $stmt->fetch(); // fetch car on attend une seule ligne // Alternative fetchAll()
    
    // Vérifie si le compte est bloqué
    if ($etudiant['statut'] === 'bloque') {
        return [
            'can_borrow' => false,
            'message' => MSG_COMPTE_BLOQUE
        ];
    }
    
    // Compte le nombre d'emprunts en cours et en retard 
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as nb_emprunts 
        FROM emprunt 
        WHERE id_etudiant = ? 
        AND statut IN ('en_cours', 'en_retard')
    ");
    $stmt->execute([$id_etudiant]);
    $result = $stmt->fetch();
    
    // Vérifie si l'étudiant a atteint la limite
    if ($result['nb_emprunts'] >= MAX_EMPRUNTS) {
        return [
            'can_borrow' => false,
            'message' => MSG_LIMITE_EMPRUNTS
        ];
    }
    return [
        'can_borrow' => true,
        'message' => ''
    ];
}

?>
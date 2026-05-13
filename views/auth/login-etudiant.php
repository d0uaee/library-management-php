<?php
// Student login page
require_once '../../config/config.php';
require_once '../../utils/security.php';

if (isEtudiant()) {
    header('Location: ../etudiant/catalogue.php');
    exit;
}

$error = '';

// TRAITEMENT DU FORMULAIRE DE CONNEXION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (!validateEmailEtudiant($email)) {
        $error = 'Veuillez utiliser votre email académique ENSAM.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id_etudiant, nom, prenom, email, mot_de_passe, statut FROM etudiant WHERE email = ?');
            $stmt->execute([$email]);
            $etudiant = $stmt->fetch();

            if (!$etudiant || !password_verify($password, $etudiant['mot_de_passe'])) {
                $error = 'Email ou mot de passe incorrect.';
            } elseif ($etudiant['statut'] === 'bloque') {
                $error = MSG_COMPTE_BLOQUE;
            } else {
                $_SESSION['user_id'] = $etudiant['id_etudiant'];
                $_SESSION['user_type'] = 'etudiant';
                $_SESSION['user_nom'] = $etudiant['nom'];
                $_SESSION['user_prenom'] = $etudiant['prenom'];
                $_SESSION['user_email'] = $etudiant['email'];
                session_regenerate_id(true);
                header('Location: ../etudiant/catalogue.php');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Erreur serveur. Réessayez plus tard.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Étudiant - Bibliothèque ENSAM</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/auth/login-etudiant.css">
</head>
<body>
    <div class="login-wrapper">
        <!-- Left Side -->
        <div class="login-brand">
            <div class="brand-content">
                <div class="brand-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h1>Bienvenue!</h1>
                <p>Accédez à votre espace étudiant ENSAM Meknès</p>
                
                <div class="brand-features">
                    <div class="feature-item">
                        <i class="fas fa-book-open"></i>
                        <span>Catalogue complet de la bibliothèque</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-bookmark"></i>
                        <span>Gérez vos emprunts facilement</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-clock"></i>
                        <span>Réservations en ligne 24/7</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side -->
        <div class="login-form-container">
            <div class="form-header">
                <h2>Connexion</h2>
                <p>Entrez vos identifiants pour continuer</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire -->
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="email">Email Académique</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input 
                            type="email" 
                            class="form-control" 
                            id="email" 
                            name="email" 
                            placeholder="prenom.nom@ensam.ma"
                            value="<?php echo htmlspecialchars($email ?? ''); ?>"
                            required
                        >
                    </div>
                    <div class="form-hint">Utilisez votre email ENSAM</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Mot de passe</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            placeholder="Entrez votre mot de passe"
                            required
                        >
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>

            <div class="divider">
                <span>OU</span>
            </div>

            <div class="register-link">
                <p>Pas encore de compte ? 
                    <a href="register-etudiant.php">
                        <i class="fas fa-user-plus"></i> Créer un compte
                    </a>
                </p>
            </div>

            <div class="info-box">
                <p>
                    <strong><i class="fas fa-info-circle"></i> Compte de test</strong><br>
                    Email: youssef.alami@ensam.ma<br>
                    Mot de passe: pass1y
                </p>
            </div>

            <div class="back-link">
                <a href="../../index.php">
                    <i class="fas fa-arrow-left"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

    </script>
</body>
</html>
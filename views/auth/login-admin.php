<?php
// Page de connexion administrateur

require_once '../../config/config.php';
require_once '../../utils/security.php';

if (isAdmin()) {
    header('Location: ../admin/dashboard.php');
    exit;
}

$error = '';

// TRAITEMENT DU FORMULAIRE DE CONNEXION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    }
    elseif (!validateEmailAdmin($email)) {
        $error = "Email administrateur invalide. Utilisez une adresse @bibliotheque.com";
    }
    else {
        try {
            $stmt = $pdo->prepare("
                SELECT id_admin, nom, prenom, email, mot_de_passe 
                FROM admin 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                $error = "Email ou mot de passe incorrect";
            }
            elseif (!password_verify($password, $admin['mot_de_passe'])) {
                $error = "Email ou mot de passe incorrect";
            }
            else {
                $_SESSION['user_id'] = $admin['id_admin'];
                $_SESSION['user_type'] = 'admin';
                $_SESSION['user_nom'] = $admin['nom'];
                $_SESSION['user_prenom'] = $admin['prenom'];
                $_SESSION['user_email'] = $admin['email'];
                
                session_regenerate_id(true);
                
                header('Location: ../admin/dashboard.php');
                exit;
            }
            
        } catch (Exception $e) {
            $error = "Erreur de connexion. Veuillez réessayer.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Bibliothèque ENSAM</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/auth/login-admin.css">
   
</head>
<body>
    <div class="login-wrapper">
        <!-- Left Side  -->
        <div class="login-brand">
            <div class="brand-content">
                <div class="admin-badge">
                    <i class="fas fa-lock"></i>
                    <span>Accès Restreint</span>
                </div>
                
                <div class="brand-icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                
                <h1>Administration</h1>
                <p>Panneau de contrôle de la bibliothèque ENSAM Meknès</p>
                
                <div class="brand-features">
                    <div class="feature-item">
                        <i class="fas fa-users-cog"></i>
                        <span>Gestion complète des utilisateurs</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-book-medical"></i>
                        <span>Catalogue et inventaire</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Statistiques et rapports</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Form -->
        <div class="login-form-container">
            <div class="form-header">
                <h2>Connexion Admin</h2>
                <p>Accédez au panneau d'administration</p>
            </div>

            <div class="security-notice">
                <i class="fas fa-shield-alt"></i>
                <span>Connexion sécurisée réservée aux administrateurs autorisés</span>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire -->
            <form method="POST" action="">
                <!-- Email -->
                <div class="form-group">
                    <label class="form-label" for="email">Email Administrateur</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input 
                            type="email" 
                            class="form-control" 
                            id="email" 
                            name="email" 
                            placeholder="admin@bibliotheque.com"
                            value="<?php echo htmlspecialchars($email ?? ''); ?>"
                            required
                        >
                    </div>
                    <div class="form-hint">Format: @bibliotheque.com</div>
                </div>

                <!-- Mot de passe -->
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

                <!-- Bouton de connexion -->
                <button type="submit" class="btn-login">
                    <i class="fas fa-shield-alt"></i> Accéder à l'administration
                </button>
            </form>

            <div class="info-box">
                <p>
                    <strong><i class="fas fa-info-circle"></i> Compte de test</strong><br>
                    Email: admin@bibliotheque.com<br>
                    Mot de passe: admin123
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
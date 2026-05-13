<?php
/**
 * STUDENT REGISTRATION PAGE
 * Students register using their academic email (@ensam.ma)
 */

// Include required files
require_once '../../config/config.php';
require_once '../../utils/security.php';

// ============================================================================
// REDIRECT IF ALREADY LOGGED IN
// ============================================================================
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'etudiant') {
    header('Location: ../etudiant/catalogue.php');
    exit;
}

// ============================================================================
// INITIALIZE VARIABLES
// ============================================================================
$error = '';
$success = '';

// ============================================================================
// PROCESS FORM SUBMISSION
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Step 1: Get and clean form data
    $numero_etudiant = cleanInput($_POST['numero_etudiant'] ?? '');
    $nom = cleanInput($_POST['nom'] ?? '');
    $prenom = cleanInput($_POST['prenom'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $telephone = cleanInput($_POST['telephone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Step 2: Validate the form data
    $error = validateRegistrationForm(
        $numero_etudiant, 
        $nom, 
        $prenom, 
        $email, 
        $telephone, 
        $password, 
        $confirm_password
    );
    
    // Step 3: If no validation errors, register the student
    if (empty($error)) {
        $result = registerStudent(
            $pdo,
            $numero_etudiant,
            $nom,
            $prenom,
            $email,
            $telephone,
            $password
        );
        
        if ($result['success']) {
            $success = "Inscription réussie ! Redirection vers la page de connexion...";
            // Clear form data for security
            $numero_etudiant = $nom = $prenom = $email = $telephone = '';
            // Redirect after 2 seconds
            header("Refresh: 2; url=login-etudiant.php");
        } else {
            $error = $result['error'];
        }
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Validate all registration form fields
 * Returns error message if validation fails, empty string if all valid
 */
function validateRegistrationForm($numero, $nom, $prenom, $email, $tel, $pass, $confirm_pass) {
    // Check required fields
    if (empty($numero) || empty($nom) || empty($prenom) || empty($email) || empty($pass)) {
        return "Veuillez remplir tous les champs obligatoires";
    }
    
    // Validate student number format (ex: ET2024001)
    if (!preg_match('/^ET\d{7}$/', $numero)) {
        return "Format du numéro étudiant invalide (ex: ET2024001)";
    }
    
    // Validate academic email
    if (!validateEmailEtudiant($email)) {
        return "Veuillez utiliser votre email académique ENSAM (format: prenom.nom@ensam.ma)";
    }
    
    // Check if passwords match
    if ($pass !== $confirm_pass) {
        return "Les mots de passe ne correspondent pas";
    }
    
    // Check password length
    if (strlen($pass) < 6) {
        return "Le mot de passe doit contenir au moins 6 caractères";
    }
    
    // Validate phone number if provided (optional field)
    if (!empty($tel) && !preg_match('/^\+212[5-7][0-9]{8}$/', $tel)) {
        return "Format de téléphone invalide (ex: +2126XXXXXXXX)";
    }
    
    // All validations passed
    return '';
}

/**
 * Register a new student in the database
 * Returns array with 'success' (boolean) and 'error' (string) keys
 */
function registerStudent($pdo, $numero, $nom, $prenom, $email, $tel, $password) {
    try {
        $pdo->beginTransaction();
        
        if (emailExists($pdo, $email)) {
            $pdo->rollBack();
            return ['success' => false, 'error' => "Cet email est déjà utilisé"];
        }
        
        if (studentNumberExists($pdo, $numero)) {
            $pdo->rollBack();
            return ['success' => false, 'error' => "Ce numéro étudiant est déjà utilisé"];
        }
        
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Insert student into database
        $stmt = $pdo->prepare("
            INSERT INTO etudiant (
                numero_etudiant, 
                nom, 
                prenom, 
                email, 
                telephone, 
                mot_de_passe, 
                statut, 
                date_inscription
            ) VALUES (?, ?, ?, ?, ?, ?, 'actif', NOW())
        ");
        
        $result = $stmt->execute([
            $numero,
            $nom,
            $prenom,
            $email,
            $tel ?: null,
            $password_hash
        ]);
        
        if ($result) {
            $pdo->commit();
            return ['success' => true, 'error' => ''];
        } else {
            $pdo->rollBack();
            return ['success' => false, 'error' => "Erreur lors de l'inscription. Veuillez réessayer."];
        }
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Erreur inscription: " . $e->getMessage());
        
        return ['success' => false, 'error' => "Erreur technique. Veuillez réessayer plus tard."];
    }
}

//Check if an email already exists in database
function emailExists($pdo, $email) {
    $stmt = $pdo->prepare("SELECT id_etudiant FROM etudiant WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    return $stmt->fetch() !== false;
}

//Check if a student number already exists in database
function studentNumberExists($pdo, $numero) {
    $stmt = $pdo->prepare("SELECT id_etudiant FROM etudiant WHERE numero_etudiant = ? LIMIT 1");
    $stmt->execute([$numero]);
    return $stmt->fetch() !== false;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Inscription Étudiant - Bibliothèque ENSAM</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/auth/register-etudiant.css">
</head>
<body>
    <div class="register-wrapper">
        <!-- Left Side - Branding -->
        <div class="register-brand">
            <div class="brand-content">
                <div class="brand-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <h1>Rejoignez-nous!</h1>
                <p>Créez votre compte et accédez à tous les services de la bibliothèque ENSAM</p>
                
                <div class="benefits-list">
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Accès au catalogue complet</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Réservations en ligne</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Historique des emprunts</span>
                    </div>
                    <div class="benefit-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Notifications automatiques</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Form -->
        <div class="register-form-container">
            <div class="form-header">
                <h2>Créer un compte</h2>
                <p>Remplissez les informations ci-dessous</p>
            </div>

            <div class="progress-steps">
                <div class="step active">
                    <div class="step-circle">1</div>
                    <div class="step-label">Infos</div>
                </div>
                <div class="step active">
                    <div class="step-circle">2</div>
                    <div class="step-label">Identité</div>
                </div>
                <div class="step active">
                    <div class="step-circle">3</div>
                    <div class="step-label">Sécurité</div>
                </div>
            </div>

            <div class="info-box">
                <p><strong><i class="fas fa-info-circle"></i> Informations importantes</strong></p>
                <ul>
                    <li>Email académique ENSAM requis (@ensam.ma)</li>
                    <li>Numéro étudiant: ET + 7 chiffres (ex: ET2024001)</li>
                    <li>Mot de passe: minimum 6 caractères</li>
                </ul>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="" id="registerForm" novalidate>
                <!-- Numéro étudiant -->
                <div class="form-group">
                    <label class="form-label" for="numero_etudiant">
                        Numéro étudiant <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-id-card input-icon"></i>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="numero_etudiant" 
                            name="numero_etudiant" 
                            placeholder="ET2024001"
                            value="<?php echo htmlspecialchars($numero_etudiant ?? ''); ?>"
                            maxlength="9"
                            required
                        >
                    </div>
                </div>

                <!-- Nom et Prénom -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="nom">
                            Nom <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control no-icon" 
                            id="nom" 
                            name="nom" 
                            placeholder="Nom"
                            value="<?php echo htmlspecialchars($nom ?? ''); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="prenom">
                            Prénom <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control no-icon" 
                            id="prenom" 
                            name="prenom" 
                            placeholder="Prénom"
                            value="<?php echo htmlspecialchars($prenom ?? ''); ?>"
                            required
                        >
                    </div>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label class="form-label" for="email">
                        Email académique ENSAM <span class="required">*</span>
                    </label>
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
                </div>

                <!-- Téléphone -->
                <div class="form-group">
                    <label class="form-label" for="telephone">
                        Téléphone (optionnel)
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone input-icon"></i>
                        <input 
                            type="tel" 
                            class="form-control" 
                            id="telephone" 
                            name="telephone" 
                            placeholder="+2126XXXXXXXX"
                            value="<?php echo htmlspecialchars($telephone ?? ''); ?>"
                            maxlength="13"
                        >
                    </div>
                </div>

                <!-- Mots de passe -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password">
                            Mot de passe <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password" 
                                name="password" 
                                placeholder="Min. 6 caractères"
                                minlength="6"
                                required
                            >
                            <span class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                                </span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">
                        Confirmer <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Répétez le mot de passe"
                            minlength="6"
                            required
                        >
                        <span class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                            <i class="fas fa-eye" id="toggleIcon2"></i>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-register" id="submitBtn">
                <i class="fas fa-rocket"></i> Créer mon compte
            </button>
        </form>

        <div class="login-link">
            <p>Vous avez déjà un compte ? 
                <a href="login-etudiant.php">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </a>
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
    // Toggle password visibility
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // Validation en temps réel du numéro étudiant
    const numeroInput = document.getElementById('numero_etudiant');
    numeroInput.addEventListener('input', function(e) {
        let value = e.target.value.toUpperCase();
        e.target.value = value;
        
        if (value && !/^ET\d{0,7}$/.test(value)) {
            e.target.setCustomValidity('Format invalide. Exemple: ET2024001');
        } else {
            e.target.setCustomValidity('');
        }
    });

    // Validation en temps réel de l'email
    const emailInput = document.getElementById('email');
    emailInput.addEventListener('input', function(e) {
        let value = e.target.value.toLowerCase();
        e.target.value = value;
        
        if (value && !/^[a-z]+\.[a-z]+@ensam\.ma$/.test(value)) {
            e.target.setCustomValidity('Format invalide. Exemple: prenom.nom@ensam.ma');
        } else {
            e.target.setCustomValidity('');
        }
    });

    // Validation du téléphone (optionnel)
    const telInput = document.getElementById('telephone');
    telInput.addEventListener('input', function(e) {
        let value = e.target.value;
        
        if (value && !/^\+212[5-7][0-9]{0,8}$/.test(value)) {
            e.target.setCustomValidity('Format invalide. Exemple: +2126XXXXXXXX');
        } else {
            e.target.setCustomValidity('');
        }
    });

    // Validation du formulaire avant soumission
    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    
    form.addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        // Vérifier la correspondance des mots de passe
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Les mots de passe ne correspondent pas !');
            document.getElementById('confirm_password').focus();
            return false;
        }
        
        // Désactiver le bouton pour éviter les doubles soumissions
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Inscription en cours...';
    });

</script>       
</body>
</html>
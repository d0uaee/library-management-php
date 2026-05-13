<?php
/**
 * INDEX.PHP 
 */

// CONFIGURATION (déjà session_start() dedans)
require_once 'config/config.php';

// REDIRECTION SI DÉJÀ CONNECTÉ
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: views/admin/dashboard.php');
    } else {
        header('Location: views/etudiant/catalogue.php');
    }
    exit;
}

// CHARGER LES STATS
$stats = [
    'total_livres' => 0,
    'etudiants_actifs' => 0,
    'emprunts_mois' => 0
];

try {
    if (isset($pdo)) {
        $stmt = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM livre) AS total_livres,
                (SELECT COUNT(*) FROM etudiant WHERE statut='actif') AS etudiants_actifs,
                (SELECT COUNT(*) FROM emprunt WHERE DATE_FORMAT(date_emprunt, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')) AS emprunts_mois
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats = $result;
        }
    }
} catch (Exception $e) {
    error_log("Erreur lors du chargement des statistiques : " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliothèque ENSAM Meknès - Accueil</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>
    <div class="particles">
        <?php for ($i = 0; $i < 8; $i++): ?>
            <div class="particle"></div>
        <?php endfor; ?>
    </div>

    <div class="hero-section">
        <div class="container">
            <!-- Header -->
            <div class="header">
                <div class="logo">
                    <i class="fas fa-book-reader"></i>
                </div>
                <h1>Bibliothèque ENSAM Meknès</h1>
                <p class="subtitle">Plateforme moderne de gestion des emprunts</p>
                <p style="color: rgba(255,255,255,0.75); max-width: 650px; margin: 0 auto; font-weight: 300; font-size: 0.95rem;">
                    Découvrez une expérience de lecture enrichissante avec notre système intelligent
                </p>
            </div>

            <!-- Stats  -->
            <div class="stats-section">
                <div class="stat-item">
                    <span class="stat-number"><?php echo htmlspecialchars(number_format($stats['total_livres']), ENT_QUOTES, 'UTF-8'); ?>+</span>
                    <span class="stat-label">Livres au catalogue</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo htmlspecialchars(number_format($stats['etudiants_actifs']), ENT_QUOTES, 'UTF-8'); ?>+</span>
                    <span class="stat-label">Étudiants actifs</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo htmlspecialchars(number_format($stats['emprunts_mois']), ENT_QUOTES, 'UTF-8'); ?>+</span>
                    <span class="stat-label">Emprunts ce mois</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">24/7</span>
                    <span class="stat-label">Accès en ligne</span>
                </div>
            </div>

            <!-- Cards -->
            <div class="cards-container">
                <!-- Étudiant -->
                <div class="card-choice student">
                    <div class="card-photo">
                        <img src="assets/img/home/homeold-books.png" alt="Livres anciens empilés" loading="lazy" onerror="this.onerror=null;this.src='https://via.placeholder.com/300x200?text=Image+indisponible';">
                    </div>

                    <div class="card-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3>Espace Étudiant</h3>
                    <p>Accédez à notre catalogue complet et gérez vos emprunts facilement</p>
                    
                    <div class="features">
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Catalogue de milliers de livres</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Emprunts en ligne 24/7</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Suivi en temps réel</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Notifications intelligentes</span>
                        </div>
                    </div>

                    <a href="views/auth/login-etudiant.php" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Se connecter
                    </a>
                </div>

                <!-- Admin -->
                <div class="card-choice admin">
                    <div class="card-photo">
                        <img src="assets/img/home/professional-business.jpg" alt="Photo professionnelle business" loading="lazy" onerror="this.onerror=null;this.src='assets/img/placeholder.jpg';">
                    </div>

                    <div class="card-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3>Espace Administration</h3>
                    <p>Tableau de bord complet pour gérer la bibliothèque efficacement</p>
                    
                    <div class="features">
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Gestion des livres</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Validation des emprunts</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Analytics avancées</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Rapports exportables</span>
                        </div>
                    </div>

                    <a href="views/auth/login-admin.php" class="btn-login">
                        <i class="fas fa-shield-alt"></i> Administration
                    </a>
                </div>
            </div>

            <!-- Features Grid -->
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-search"></i>
                    <h4>Recherche Intelligente</h4>
                    <p>Trouvez instantanément ce que vous cherchez avec notre moteur de recherche avancé et filtres intelligents</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-mobile-alt"></i>
                    <h4>100% Responsive</h4>
                    <p>Accédez depuis n'importe quel appareil - ordinateur, tablette ou smartphone en toute simplicité</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-bell"></i>
                    <h4>Notifications Temps Réel</h4>
                    <p>Restez informé instantanément de vos retours, réservations et des nouvelles acquisitions</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-star"></i>
                    <h4>Système d'Évaluations</h4>
                    <p>Notez et commentez vos lectures pour enrichir la communauté et aider les autres étudiants</p>
                </div>
            </div>
        </div>
    </div>


</body>
</html>
<?php
// Compteurs initialisés par défaut
$nb_messages_non_lus = 0;
$nb_notifications_non_lues = 0;

if (isset($pdo) && isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'etudiant') {
    try {
        // Compter les messages non lus de l'admin
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM message WHERE id_etudiant = ? AND role_expediteur = 'admin' AND lu = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $nb_messages_non_lus = (int) $stmt->fetchColumn();

        // Compter les notifications non lues
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE id_etudiant = ? AND lu = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $nb_notifications_non_lues = (int) $stmt->fetchColumn();

    } catch (PDOException $e) {
        error_log("Erreur nav-etudiant compteurs: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $pageTitle ?? "Bibliothèque ENSAM"; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        .top-navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 0;
        }

        .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.4rem;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand i {
            font-size: 1.8rem;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 0 20px;
        }

        .nav-link-item {
            color: rgba(255,255,255,0.9) !important;
            padding: 15px 18px !important;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            position: relative;
        }

        .nav-link-item:hover {
            background: rgba(255,255,255,0.15);
            color: white !important;
        }

        .nav-link-item.active {
            background: rgba(255,255,255,0.2);
            color: white !important;
        }

        .badge-notification {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #ff4757;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
            min-width: 18px;
            text-align: center;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 20px;
            margin-left: auto;
        }

        .user-info {
            color: white;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 20px;
            border-radius: 20px;
            transition: all 0.3s ease;
            font-weight: 500;
            text-decoration: none;
        }

        .btn-logout:hover {
            background: white;
            color: #667eea;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg top-navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="catalogue.php">
                <i class="fas fa-book-reader"></i>
                <span>Bibliothèque ENSAM</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    style="background: white;">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="nav-links ms-auto">
                    <a href="catalogue.php" class="nav-link-item <?php if (basename($_SERVER['PHP_SELF']) == 'catalogue.php') echo 'active'; ?>">
                        <i class="fas fa-book"></i> Catalogue
                    </a>

                    <a href="mes-emprunts.php" class="nav-link-item <?php if (basename($_SERVER['PHP_SELF']) == 'mes-emprunts.php') echo 'active'; ?>">
                        <i class="fas fa-bookmark"></i> Mes Emprunts
                    </a>

                    <a href="reservation.php" class="nav-link-item <?php if (basename($_SERVER['PHP_SELF']) == 'reservation.php') echo 'active'; ?>">
                        <i class="fas fa-calendar-check"></i> Réservations
                    </a>

                    <a href="messages.php" class="nav-link-item <?php if (basename($_SERVER['PHP_SELF']) == 'messages.php') echo 'active'; ?>">
                        <i class="fas fa-envelope"></i> Messages
                        <?php if ($nb_messages_non_lus > 0): ?>
                            <span class="badge-notification"><?php echo $nb_messages_non_lus; ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="notifications.php" class="nav-link-item <?php if (basename($_SERVER['PHP_SELF']) == 'notifications.php') echo 'active'; ?>">
                        <i class="fas fa-bell"></i> Notifications
                        <?php if ($nb_notifications_non_lues > 0): ?>
                            <span class="badge-notification"><?php echo $nb_notifications_non_lues; ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- Dropdown More -->
                    <div class="dropdown">
                        <a class="nav-link-item dropdown-toggle" href="#" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ellipsis-h"></i> Plus
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="stats.php">
                                <i class="fas fa-chart-line"></i> Statistiques
                            </a></li>
                            <li><a class="dropdown-item" href="profil.php">
                                <i class="fas fa-user"></i> Mon Profil
                            </a></li>
                        </ul>
                    </div>
                </div>

                <div class="user-section ">
                    <span class="user-info">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?>
                    </span>
                    <a href="../auth/logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>
</body>
</html>
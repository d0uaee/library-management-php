<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? "Bibliothèque ENSAM"; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>  
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
        } 
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .sidebar-brand {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .sidebar-menu li {
            margin-bottom: 10px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }
        .sidebar-menu a i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-shield-alt"></i> Admin Panel
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="<?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php') echo 'active'; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="livres.php" class="<?php if (basename($_SERVER['PHP_SELF']) == 'livres.php') echo 'active'; ?>">
                    <i class="fas fa-book"></i>
                    <span>Gestion des livres</span>
                </a>
            </li>
            <li>
                <a href="emprunts.php" class="<?php if (basename($_SERVER['PHP_SELF']) == 'emprunts.php') echo 'active'; ?>">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Emprunts</span>
                </a>
            </li>
            <li>
                <a href="messagerie.php" class="<?php if (basename($_SERVER['PHP_SELF']) == 'messagerie.php') echo 'active'; ?>">
                    <i class="fas fa-envelope"></i>
                    <span>Messagerie</span>
                </a>
            </li>
            <li>
                <a href="contact.php" class="<?php if (basename($_SERVER['PHP_SELF']) == 'contact.php') echo 'active'; ?>">
                    <i class="fas fa-address-book"></i>
                    <span>Étudiants</span>
                </a>
            </li>
            <li>
                <a href="statistiques.php" class="<?php if (basename($_SERVER['PHP_SELF']) == 'statistiques.php') echo 'active'; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Statistiques</span>
                </a>
            </li>
            <li>
                <a href="penalisation.php" class="<?php if (basename($_SERVER['PHP_SELF']) == 'penalisation.php') echo 'active'; ?>">
                    <i class="fas fa-gavel"></i>
                    <span>Pénalisation</span>
                </a>
            </li>
            <li>
                <a href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </div>

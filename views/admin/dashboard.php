<?php
/**
 * DASHBOARD ADMINISTRATEUR
 * 
 * Vue d'ensemble de la bibliothèque avec statistiques en temps réel
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';
require_once '../../utils/update-admin.php';
require_once '../../views/admin/panel.php';

requireAdmin();

// ========================================
// RÉCUPÉRATION DES STATISTIQUES
// ========================================

// 1. Nombre total de livres et disponibles
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_livres,
        SUM(nombre_exemplaires) as total_exemplaires,
        SUM(nombre_disponibles) as total_disponibles
    FROM livre
");
$stats_livres = $stmt->fetch();

// 2. Nombre d'emprunts en cours et en retard
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_emprunts,
        SUM(CASE WHEN statut = 'en_retard' THEN 1 ELSE 0 END) as emprunts_retard
    FROM emprunt 
    WHERE statut IN ('en_cours', 'en_retard')
");
$stats_emprunts = $stmt->fetch();

// 3. Nombre d'étudiants actifs et bloqués
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_etudiants,
        SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as etudiants_actifs,
        SUM(CASE WHEN statut = 'bloque' THEN 1 ELSE 0 END) as etudiants_bloques
    FROM etudiant
");
$stats_etudiants = $stmt->fetch();

// 4. Demandes d'emprunt en attente
$stmt = $pdo->query("
    SELECT COUNT(*) as demandes_attente 
    FROM demande_emprunt 
    WHERE statut = 'en_attente'
");
$demandes_attente = $stmt->fetchColumn();

// 5. Top 5 des livres les plus empruntés
$stmt = $pdo->query("
    SELECT l.titre, l.auteur, COUNT(e.id_emprunt) as nb_emprunts
    FROM livre l
    LEFT JOIN emprunt e ON l.id_livre = e.id_livre
    GROUP BY l.id_livre
    ORDER BY nb_emprunts DESC
    LIMIT 5
");
$top_livres = $stmt->fetchAll();

// 6. Emprunts récents (derniers 5)
$stmt = $pdo->query("
    SELECT e.*, l.titre, et.nom, et.prenom
    FROM emprunt e
    JOIN livre l ON e.id_livre = l.id_livre
    JOIN etudiant et ON e.id_etudiant = et.id_etudiant
    ORDER BY e.date_emprunt DESC
    LIMIT 5
");
$emprunts_recents = $stmt->fetchAll();

// 7. Retards critiques (> 7 jours)
$stmt = $pdo->query("
    SELECT e.*, l.titre, et.nom, et.prenom, et.email,
           DATEDIFF(CURDATE(), e.date_retour_prevue) as jours_retard
    FROM emprunt e
    JOIN livre l ON e.id_livre = l.id_livre
    JOIN etudiant et ON e.id_etudiant = et.id_etudiant
    WHERE e.statut = 'en_retard'
    AND DATEDIFF(CURDATE(), e.date_retour_prevue) > 7
    ORDER BY jours_retard DESC
    LIMIT 5
");
$retards_critiques = $stmt->fetchAll();

// Calcul du taux d'utilisation
$taux_utilisation = 0;
if ($stats_livres['total_exemplaires'] > 0) {
    $taux_utilisation = round(
        (($stats_livres['total_exemplaires'] - $stats_livres['total_disponibles']) / $stats_livres['total_exemplaires']) * 100, 
        1
    );
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Bibliothèque ENSAM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin/dashboard.css">

</head>
<body>
    <!-- Contenu principal -->
    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 style="margin: 0; color: #333;">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </h1>
                <p style="margin: 5px 0 0 0; color: #666;">
                    Vue d'ensemble de la bibliothèque
                </p>
            </div>
            <div>
                <span style="color: #666;">
                    <i class="fas fa-user-shield"></i> 
                    <?php echo htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?>
                </span>
            </div>
        </div>

        <!-- Alertes critiques -->
        <?php if ($retards_critiques && count($retards_critiques) > 0): ?>
            <div class="alert-danger-custom">
                <h5><i class="fas fa-exclamation-triangle"></i> Alertes critiques</h5>
                <p>Il y a <?php echo count($retards_critiques); ?> retard(s) de plus de 7 jours qui nécessitent une intervention urgente !</p>
            </div>
        <?php endif; ?>

        <!-- Cartes de statistiques -->
        <div class="stats-grid">
            <!-- Total Livres -->
            <div class="stat-card blue">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-value"><?php echo $stats_livres['total_livres']; ?></div>
                <div class="stat-label">Livres au catalogue</div>
            </div>

            <!-- Livres disponibles -->
            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats_livres['total_disponibles']; ?></div>
                <div class="stat-label">Exemplaires disponibles</div>
            </div>

            <!-- Emprunts en cours -->
            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-value"><?php echo $stats_emprunts['total_emprunts']; ?></div>
                <div class="stat-label">Emprunts en cours</div>
            </div>

            <!-- Retards -->
            <div class="stat-card red">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats_emprunts['emprunts_retard']; ?></div>
                <div class="stat-label">Livres en retard</div>
            </div>
        </div>

        <!-- Taux d'utilisation -->
        <div class="card">
            <h5><i class="fas fa-chart-bar"></i> Taux d'utilisation de la bibliothèque</h5>
            <div class="progress-custom">
                <div class="progress-bar-custom" style="width: <?php echo $taux_utilisation; ?>%">
                    <?php echo $taux_utilisation; ?>%
                </div>
            </div>
            <p style="margin-top: 10px; color: #666; font-size: 0.9rem;">
                <?php echo ($stats_livres['total_exemplaires'] - $stats_livres['total_disponibles']); ?> 
                livres empruntés sur <?php echo $stats_livres['total_exemplaires']; ?> exemplaires
            </p>
        </div>

        <div class="row">
            <!-- Top 5 livres -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-star"></i> Top 5 Livres
                        </h5>
                    </div>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Auteur</th>
                                <th>Emprunts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($top_livres) > 0): ?>
                                <?php foreach ($top_livres as $livre): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($livre['titre']); ?></td>
                                        <td><?php echo htmlspecialchars($livre['auteur']); ?></td>
                                        <td><span class="badge-custom badge-info"><?php echo $livre['nb_emprunts']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #999;">Aucune donnée disponible</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Emprunts récents -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-clock"></i> Emprunts Récents
                        </h5>
                    </div>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Étudiant</th>
                                <th>Livre</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($emprunts_recents) > 0): ?>
                                <?php foreach ($emprunts_recents as $emprunt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($emprunt['prenom'] . ' ' . $emprunt['nom']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($emprunt['titre'], 0, 30)) . '...'; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($emprunt['date_emprunt'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #999;">Aucun emprunt récent</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Retards critiques -->
        <?php if (count($retards_critiques) > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-exclamation-triangle"></i> Retards Critiques (> 7 jours)
                    </h5>
                </div>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Étudiant</th>
                            <th>Email</th>
                            <th>Livre</th>
                            <th>Jours de retard</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($retards_critiques as $retard): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($retard['prenom'] . ' ' . $retard['nom']); ?></td>
                                <td><?php echo htmlspecialchars($retard['email']); ?></td>
                                <td><?php echo htmlspecialchars($retard['titre']); ?></td>
                                <td>
                                    <span class="badge-custom badge-danger">
                                        <?php echo $retard['jours_retard']; ?> jours
                                    </span>
                                </td>
                                <td>
                                    <a href="messagerie.php?id_etudiant=<?php echo $retard['id_etudiant']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-envelope"></i> Rappel
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
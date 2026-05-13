<?php
/**
 * STATISTIQUES DYNAMIQUES - Version optimisée avec auto-refresh
 * - Mise à jour automatique toutes les 5 secondes
 * - Animations fluides lors des changements
 * - Notifications en temps réel des nouveaux emprunts
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';
require_once '../../views/admin/panel.php';


requireAdmin();

// ---------------------------
// ENDPOINT AJAX pour fetch_stats
// ---------------------------
if (isset($_GET['action']) && $_GET['action'] === 'fetch_stats') {
    // KPIs en temps réel
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM livre) as total_livres,
            (SELECT SUM(nombre_exemplaires) FROM livre) as total_exemplaires,
            (SELECT SUM(nombre_disponibles) FROM livre) as total_disponibles,
            (SELECT COUNT(*) FROM emprunt WHERE statut IN ('en_cours', 'en_retard')) as emprunts_actifs,
            (SELECT COUNT(*) FROM emprunt WHERE statut = 'en_retard') as emprunts_retard,
            (SELECT COUNT(*) FROM emprunt WHERE statut = 'retourne') as emprunts_retournes,
            (SELECT COUNT(*) FROM etudiant WHERE statut = 'actif') as etudiants_actifs,
            (SELECT COUNT(*) FROM etudiant WHERE statut = 'bloque') as etudiants_bloques
    ");
    $kpis = $stmt->fetch(PDO::FETCH_ASSOC);

    $taux_util = 0;
    if ($kpis['total_exemplaires'] > 0) {
        $taux_util = round((($kpis['total_exemplaires'] - $kpis['total_disponibles']) / $kpis['total_exemplaires']) * 100, 1);
    }
    $taux_ret = 0;
    if ($kpis['emprunts_actifs'] > 0) {
        $taux_ret = round(($kpis['emprunts_retard'] / $kpis['emprunts_actifs']) * 100, 1);
    }

    // Durée moyenne
    $stmt = $pdo->query("
        SELECT ROUND(AVG(DATEDIFF(date_retour_effective, date_emprunt)), 1) as duree_moyenne
        FROM emprunt
        WHERE statut = 'retourne' AND date_retour_effective IS NOT NULL
    ");
    $duree_moyenne = $stmt->fetchColumn() ?: 0;

    // Évolution emprunts 12 mois
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(date_emprunt, '%Y-%m') as mois,
               DATE_FORMAT(date_emprunt, '%M %Y') as mois_nom,
               COUNT(*) as nombre_emprunts
        FROM emprunt
        WHERE date_emprunt >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY mois
        ORDER BY mois ASC
    ");
    $emprunts_mois = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $mois_labels = array_map(function($r){ return $r['mois_nom']; }, $emprunts_mois);
    $mois_values = array_map(function($r){ return (int)$r['nombre_emprunts']; }, $emprunts_mois);

    // Catégories
    $stmt = $pdo->query("
        SELECT c.nom as categorie, COUNT(e.id_emprunt) as nb_emprunts
        FROM categorie c
        LEFT JOIN livre l ON c.id_categorie = l.id_categorie
        LEFT JOIN emprunt e ON l.id_livre = e.id_livre
        GROUP BY c.id_categorie
        ORDER BY nb_emprunts DESC
    ");
    $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $cat_labels = array_map(function($r){ return $r['categorie']; }, $cats);
    $cat_values = array_map(function($r){ return (int)$r['nb_emprunts']; }, $cats);

    // Top 10 livres
    $stmt = $pdo->query("
        SELECT l.id_livre, l.titre, l.auteur, c.nom as categorie, COUNT(e.id_emprunt) as nb_emprunts
        FROM livre l
        LEFT JOIN emprunt e ON l.id_livre = e.id_livre
        LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
        GROUP BY l.id_livre
        ORDER BY nb_emprunts DESC
        LIMIT 10
    ");
    $top_livres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top 10 étudiants
    $stmt = $pdo->query("
        SELECT et.id_etudiant, et.nom, et.prenom, et.numero_etudiant, 
               IFNULL(et.nombre_retards, 0) as nombre_retards,
               COUNT(e.id_emprunt) as nb_emprunts
        FROM etudiant et
        LEFT JOIN emprunt e ON et.id_etudiant = e.id_etudiant
        GROUP BY et.id_etudiant
        HAVING nb_emprunts > 0
        ORDER BY nb_emprunts DESC
        LIMIT 10
    ");
    $top_etuds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats catégories détaillées
    $stmt = $pdo->query("
        SELECT c.nom as categorie, 
               COUNT(DISTINCT l.id_livre) as nb_livres,
               COUNT(e.id_emprunt) as nb_emprunts,
               ROUND(COUNT(e.id_emprunt) * 100.0 / 
                     NULLIF((SELECT COUNT(*) FROM emprunt), 0), 2) as pourcentage
        FROM categorie c
        LEFT JOIN livre l ON c.id_categorie = l.id_categorie
        LEFT JOIN emprunt e ON l.id_livre = e.id_livre
        GROUP BY c.id_categorie
        ORDER BY nb_emprunts DESC
    ");
    $stats_cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dernier emprunt (pour notification)
    $stmt = $pdo->query("
        SELECT e.id_emprunt, e.date_emprunt, l.titre, et.prenom, et.nom
        FROM emprunt e
        JOIN livre l ON e.id_livre = l.id_livre
        JOIN etudiant et ON e.id_etudiant = et.id_etudiant
        ORDER BY e.date_emprunt DESC
        LIMIT 1
    ");
    $dernier_emprunt = $stmt->fetch(PDO::FETCH_ASSOC);

    $payload = [
        'success' => true,
        'kpis' => [
            'total_livres' => (int)$kpis['total_livres'],
            'total_exemplaires' => (int)$kpis['total_exemplaires'],
            'total_disponibles' => (int)$kpis['total_disponibles'],
            'emprunts_actifs' => (int)$kpis['emprunts_actifs'],
            'emprunts_retard' => (int)$kpis['emprunts_retard'],
            'emprunts_retournes' => (int)$kpis['emprunts_retournes'],
            'etudiants_actifs' => (int)$kpis['etudiants_actifs'],
            'etudiants_bloques' => (int)$kpis['etudiants_bloques'],
            'taux_utilisation' => $taux_util,
            'taux_retard' => $taux_ret,
            'duree_moyenne' => $duree_moyenne
        ],
        'charts' => [
            'mois_labels' => $mois_labels,
            'mois_values' => $mois_values,
            'cat_labels' => $cat_labels,
            'cat_values' => $cat_values
        ],
        'top_livres' => $top_livres,
        'top_etudiants' => $top_etuds,
        'stats_categories' => $stats_cats,
        'dernier_emprunt' => $dernier_emprunt,
        'timestamp' => time()
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

// ---------------------------
// Chargement initial des données
// ---------------------------
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM livre) as total_livres,
        (SELECT SUM(nombre_exemplaires) FROM livre) as total_exemplaires,
        (SELECT SUM(nombre_disponibles) FROM livre) as total_disponibles,
        (SELECT COUNT(*) FROM emprunt WHERE statut IN ('en_cours', 'en_retard')) as emprunts_actifs,
        (SELECT COUNT(*) FROM emprunt WHERE statut = 'en_retard') as emprunts_retard,
        (SELECT COUNT(*) FROM emprunt WHERE statut = 'retourne') as emprunts_retournes,
        (SELECT COUNT(*) FROM etudiant WHERE statut = 'actif') as etudiants_actifs,
        (SELECT COUNT(*) FROM etudiant WHERE statut = 'bloque') as etudiants_bloques
");
$kpis = $stmt->fetch(PDO::FETCH_ASSOC);

$taux_utilisation = 0;
if ($kpis['total_exemplaires'] > 0) {
    $taux_utilisation = round((($kpis['total_exemplaires'] - $kpis['total_disponibles']) / $kpis['total_exemplaires']) * 100, 1);
}

$taux_retard = 0;
if ($kpis['emprunts_actifs'] > 0) {
    $taux_retard = round(($kpis['emprunts_retard'] / $kpis['emprunts_actifs']) * 100, 1);
}

$stmt = $pdo->query("
    SELECT ROUND(AVG(DATEDIFF(date_retour_effective, date_emprunt)), 1) as duree_moyenne
    FROM emprunt
    WHERE statut = 'retourne' AND date_retour_effective IS NOT NULL
");
$duree_moyenne = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->query("
    SELECT DATE_FORMAT(date_emprunt, '%Y-%m') as mois,
           DATE_FORMAT(date_emprunt, '%M %Y') as mois_nom,
           COUNT(*) as nombre_emprunts
    FROM emprunt
    WHERE date_emprunt >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY mois
    ORDER BY mois ASC
");
$emprunts_par_mois = $stmt->fetchAll(PDO::FETCH_ASSOC);
$mois_labels = array_map(function($r){ return $r['mois_nom']; }, $emprunts_par_mois);
$mois_values = array_map(function($r){ return (int)$r['nombre_emprunts']; }, $emprunts_par_mois);

$stmt = $pdo->query("
    SELECT c.nom as categorie, COUNT(e.id_emprunt) as nb_emprunts
    FROM categorie c
    LEFT JOIN livre l ON c.id_categorie = l.id_categorie
    LEFT JOIN emprunt e ON l.id_livre = e.id_livre
    GROUP BY c.id_categorie
    ORDER BY nb_emprunts DESC
");
$stats_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categories_labels = array_map(function($r){ return $r['categorie']; }, $stats_categories);
$categories_values = array_map(function($r){ return (int)$r['nb_emprunts']; }, $stats_categories);

// Get initial top 10 data
$stmt = $pdo->query("
    SELECT l.id_livre, l.titre, l.auteur, c.nom as categorie, COUNT(e.id_emprunt) as nb_emprunts
    FROM livre l
    LEFT JOIN emprunt e ON l.id_livre = e.id_livre
    LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
    GROUP BY l.id_livre
    ORDER BY nb_emprunts DESC
    LIMIT 10
");
$top_livres_init = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT et.id_etudiant, et.nom, et.prenom, et.numero_etudiant, 
           IFNULL(et.nombre_retards, 0) as nombre_retards,
           COUNT(e.id_emprunt) as nb_emprunts
    FROM etudiant et
    LEFT JOIN emprunt e ON et.id_etudiant = e.id_etudiant
    GROUP BY et.id_etudiant
    HAVING nb_emprunts > 0
    ORDER BY nb_emprunts DESC
    LIMIT 10
");
$top_etuds_init = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT c.nom as categorie, 
           COUNT(DISTINCT l.id_livre) as nb_livres,
           COUNT(e.id_emprunt) as nb_emprunts,
           ROUND(COUNT(e.id_emprunt) * 100.0 / 
                 NULLIF((SELECT COUNT(*) FROM emprunt), 0), 2) as pourcentage
    FROM categorie c
    LEFT JOIN livre l ON c.id_categorie = l.id_categorie
    LEFT JOIN emprunt e ON l.id_livre = e.id_livre
    GROUP BY c.id_categorie
    ORDER BY nb_emprunts DESC
");
$stats_cats_init = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques Dynamiques - Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" href="../../assets/css/admin/statistiques.css">
    
</head>
<body>
    <!-- Toast Notification -->
    <div class="toast-notification" id="toastNotification">
        <div class="toast-header">
            <i class="fas fa-bell toast-icon"></i>
            <span class="toast-title">Nouveau</span>
        </div>
        <div class="toast-body" id="toastBody">
            <!-- Dynamic content -->
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div>
                <h1><i class="fas fa-chart-line"></i> Statistiques Dynamiques</h1>
                <p>Mises à jour automatiques toutes les 5 secondes</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <div class="status-indicator">
                    <span class="status-dot"></span>
                    <span>En direct</span>
                    <span id="lastUpdate" style="font-size:0.75rem;">(chargement...)</span>
                </div>
                <button class="btn-export" id="exportPdfBtn">
                    <i class="fas fa-file-pdf"></i> Exporter PDF
                </button>
                <button class="btn btn-outline-secondary" id="refreshBtn" title="Rafraîchir maintenant">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>

        <!-- KPIs Grid -->
        <div class="kpi-grid" id="kpiGrid">
            <div class="card kpi-card" data-kpi="total_livres">
                <div class="kpi-icon"><i class="fas fa-book"></i></div>
                <div class="kpi-value"><?php echo (int)$kpis['total_livres']; ?></div>
                <div class="kpi-label">Titres au catalogue</div>
            </div>

            <div class="card kpi-card" data-kpi="total_exemplaires">
                <div class="kpi-icon"><i class="fas fa-boxes"></i></div>
                <div class="kpi-value"><?php echo (int)$kpis['total_exemplaires']; ?></div>
                <div class="kpi-label">Exemplaires total</div>
            </div>

            <div class="card kpi-card" data-kpi="taux_utilisation">
                <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
                <div class="kpi-value"><?php echo $taux_utilisation; ?>%</div>
                <div class="kpi-label">Taux d'utilisation</div>
            </div>

            <div class="card kpi-card" data-kpi="duree_moyenne">
                <div class="kpi-icon"><i class="fas fa-clock"></i></div>
                <div class="kpi-value"><?php echo $duree_moyenne; ?></div>
                <div class="kpi-label">Durée moyenne (jours)</div>
            </div>

            <div class="card kpi-card" data-kpi="etudiants_actifs">
                <div class="kpi-icon"><i class="fas fa-users"></i></div>
                <div class="kpi-value"><?php echo (int)$kpis['etudiants_actifs']; ?></div>
                <div class="kpi-label">Étudiants actifs</div>
            </div>

            <div class="card kpi-card" data-kpi="taux_retard">
                <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="kpi-value"><?php echo $taux_retard; ?>%</div>
                <div class="kpi-label">Taux de retard</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row gx-3">
            <div class="col-lg-8">
                <div class="card">
                    <h5 class="mb-3"><i class="fas fa-chart-area"></i> Évolution des emprunts (12 derniers mois)</h5>
                    <div class="chart-container">
                        <canvas id="chartEmprunts"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <h5 class="mb-3"><i class="fas fa-chart-pie"></i> Répartition par catégorie</h5>
                    <div class="chart-container" style="height:320px;">
                        <canvas id="chartCategories"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Lists -->
        <div class="row gx-3 mt-3">
            <div class="col-lg-6">
                <div class="card">
                    <h5 class="mb-3"><i class="fas fa-trophy"></i> Top 10 des livres</h5>
                    <div class="table-responsive">
                        <table class="table-custom" id="tableTopLivres">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Titre</th>
                                    <th>Auteur</th>
                                    <th>Catégorie</th>
                                    <th>Emprunts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_livres_init as $idx => $livre): ?>
                                <tr>
                                    <td><?php 
                                        if ($idx === 0) echo '<span class="badge-custom badge-gold"><i class="fas fa-trophy"></i> 1</span>';
                                        elseif ($idx === 1) echo '<span class="badge-custom badge-silver"><i class="fas fa-medal"></i> 2</span>';
                                        elseif ($idx === 2) echo '<span class="badge-custom badge-bronze"><i class="fas fa-award"></i> 3</span>';
                                        else echo ($idx + 1);
                                    ?></td>
                                    <td><?php echo htmlspecialchars($livre['titre'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($livre['auteur'] ?? '-'); ?></td>
                                    <td><span class="badge-custom badge-info"><?php echo htmlspecialchars($livre['categorie'] ?? '-'); ?></span></td>
                                    <td><strong><?php echo $livre['nb_emprunts'] ?? 0; ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <h5 class="mb-3"><i class="fas fa-user-graduate"></i> Top 10 des étudiants</h5>
                    <div class="table-responsive">
                        <table class="table-custom" id="tableTopEtudiants">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Étudiant</th>
                                    <th>Numéro</th>
                                    <th>Emprunts</th>
                                    <th>Retards</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_etuds_init as $idx => $et): ?>
                                <tr>
                                    <td><?php 
                                        if ($idx === 0) echo '<span class="badge-custom badge-gold">' . ($idx + 1) . '</span>';
                                        elseif ($idx === 1) echo '<span class="badge-custom badge-silver">' . ($idx + 1) . '</span>';
                                        elseif ($idx === 2) echo '<span class="badge-custom badge-bronze">' . ($idx + 1) . '</span>';
                                        else echo ($idx + 1);
                                    ?></td>
                                    <td><?php echo htmlspecialchars(($et['prenom'] ?? '') . ' ' . ($et['nom'] ?? '')); ?></td>
                                    <td><small><?php echo htmlspecialchars($et['numero_etudiant'] ?? ''); ?></small></td>
                                    <td><strong><?php echo $et['nb_emprunts'] ?? 0; ?></strong></td>
                                    <td><span style="color: <?php echo ($et['nombre_retards'] > 0) ? '#dc3545' : '#28a745'; ?>;"><?php echo $et['nombre_retards'] ?? 0; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Categories -->
        <div class="card mt-3">
            <h5 class="mb-3"><i class="fas fa-tags"></i> Statistiques détaillées par catégorie</h5>
            <div class="table-responsive">
                <table class="table-custom" id="tableStatsCategories">
                    <thead>
                        <tr>
                            <th>Catégorie</th>
                            <th>Nombre de livres</th>
                            <th>Nombre d'emprunts</th>
                            <th>Pourcentage</th>
                            <th>Graphique</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats_cats_init as $cat): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($cat['categorie'] ?? '-'); ?></strong></td>
                            <td><?php echo $cat['nb_livres'] ?? 0; ?></td>
                            <td><?php echo $cat['nb_emprunts'] ?? 0; ?></td>
                            <td><?php echo $cat['pourcentage'] ?? 0; ?>%</td>
                            <td>
                                <div style="width:100%; background:#eef6fb; height:14px; border-radius:8px; overflow:hidden;">
                                    <div style="width:<?php echo $cat['pourcentage'] ?? 0; ?>%; background:linear-gradient(90deg,var(--accent-1),var(--accent-2)); height:100%; transition:width 0.3s ease;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Configuration
        const AUTO_REFRESH_INTERVAL = 5000; // 5 secondes
        const CHART_COLORS = [
            'rgba(255,107,129,0.9)',
            'rgba(109,211,199,0.9)',
            'rgba(17,120,255,0.9)',
            'rgba(255,193,7,0.9)',
            'rgba(123,31,162,0.9)',
            'rgba(255,159,64,0.9)',
            'rgba(75,192,192,0.9)',
            'rgba(153,102,255,0.9)'
        ];

        // État global
        let lastEmpruntId = null;
        let fetchInProgress = false;
        let autoRefreshTimer = null;
        let chartEmprunts = null;
        let chartCategories = null;

        // Initial data from PHP
        const initialData = {
            mois_labels: <?php echo json_encode($mois_labels); ?>,
            mois_values: <?php echo json_encode($mois_values); ?>,
            cat_labels: <?php echo json_encode($categories_labels); ?>,
            cat_values: <?php echo json_encode($categories_values); ?>
        };

        // Initialize Charts
        function initCharts() {
            // Chart Emprunts (Line)
            const ctxEmprunts = document.getElementById('chartEmprunts').getContext('2d');
            const gradient = ctxEmprunts.createLinearGradient(0, 0, 0, 350);
            gradient.addColorStop(0, 'rgba(255,107,129,0.18)');
            gradient.addColorStop(1, 'rgba(109,211,199,0.05)');

            chartEmprunts = new Chart(ctxEmprunts, {
                type: 'line',
                data: {
                    labels: initialData.mois_labels,
                    datasets: [{
                        label: 'Emprunts',
                        data: initialData.mois_values,
                        borderColor: 'rgb(255,107,129)',
                        backgroundColor: gradient,
                        tension: 0.3,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#fff',
                        pointBorderWidth: 2,
                        pointBorderColor: 'rgb(255,107,129)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0,0,0,0.8)',
                            padding: 12,
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: 'rgba(255,255,255,0.2)',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    },
                    animation: {
                        duration: 750,
                        easing: 'easeInOutQuart'
                    }
                }
            });

            // Chart Categories (Pie)
            const ctxCategories = document.getElementById('chartCategories').getContext('2d');
            chartCategories = new Chart(ctxCategories, {
                type: 'pie',
                data: {
                    labels: initialData.cat_labels,
                    datasets: [{
                        data: initialData.cat_values,
                        backgroundColor: CHART_COLORS.slice(0, initialData.cat_labels.length)
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.8)',
                            padding: 12,
                            titleColor: '#fff',
                            bodyColor: '#fff'
                        }
                    },
                    animation: {
                        duration: 750,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }

        // Update KPI with animation
        function updateKPI(kpiName, newValue) {
            const card = $(`[data-kpi="${kpiName}"]`);
            const valueElement = card.find('.kpi-value');
            const currentValue = valueElement.text().replace('%', '').trim();
            const newValueStr = (kpiName.includes('taux') ? newValue + '%' : newValue);

            if (currentValue != newValue.toString()) {
                // Animation
                valueElement.addClass('updating');
                card.addClass('updated');
                
                setTimeout(() => {
                    valueElement.text(newValueStr);
                    valueElement.removeClass('updating');
                }, 150);

                setTimeout(() => {
                    card.removeClass('updated');
                }, 600);
            }
        }

        // Update all KPIs
        function updateKPIs(kpis) {
            updateKPI('total_livres', kpis.total_livres);
            updateKPI('total_exemplaires', kpis.total_exemplaires);
            updateKPI('taux_utilisation', kpis.taux_utilisation);
            updateKPI('duree_moyenne', kpis.duree_moyenne);
            updateKPI('etudiants_actifs', kpis.etudiants_actifs);
            updateKPI('taux_retard', kpis.taux_retard);
        }

        // Update charts
        function updateCharts(chartsData) {
            // Emprunts chart
            chartEmprunts.data.labels = chartsData.mois_labels;
            chartEmprunts.data.datasets[0].data = chartsData.mois_values;
            chartEmprunts.update('none'); // Update without animation for smooth experience

            // Categories chart
            chartCategories.data.labels = chartsData.cat_labels;
            chartCategories.data.datasets[0].data = chartsData.cat_values;
            chartCategories.data.datasets[0].backgroundColor = CHART_COLORS.slice(0, chartsData.cat_labels.length);
            chartCategories.update('none');
        }

        // Update top livres table
        function updateTopLivres(livres) {
            const tbody = $('#tableTopLivres tbody');
            tbody.empty();

            livres.forEach((livre, idx) => {
                let rankHtml;
                if (idx === 0) {
                    rankHtml = '<span class="badge-custom badge-gold"><i class="fas fa-trophy"></i> 1</span>';
                } else if (idx === 1) {
                    rankHtml = '<span class="badge-custom badge-silver"><i class="fas fa-medal"></i> 2</span>';
                } else if (idx === 2) {
                    rankHtml = '<span class="badge-custom badge-bronze"><i class="fas fa-award"></i> 3</span>';
                } else {
                    rankHtml = idx + 1;
                }

                const row = `
                    <tr>
                        <td>${rankHtml}</td>
                        <td>${escapeHtml(livre.titre || '-')}</td>
                        <td>${escapeHtml(livre.auteur || '-')}</td>
                        <td><span class="badge-custom badge-info">${escapeHtml(livre.categorie || '-')}</span></td>
                        <td><strong>${livre.nb_emprunts || 0}</strong></td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        // Update top étudiants table
        function updateTopEtudiants(etudiants) {
            const tbody = $('#tableTopEtudiants tbody');
            tbody.empty();

            etudiants.forEach((et, idx) => {
                let rankHtml;
                if (idx === 0) {
                    rankHtml = `<span class="badge-custom badge-gold">${idx + 1}</span>`;
                } else if (idx === 1) {
                    rankHtml = `<span class="badge-custom badge-silver">${idx + 1}</span>`;
                } else if (idx === 2) {
                    rankHtml = `<span class="badge-custom badge-bronze">${idx + 1}</span>`;
                } else {
                    rankHtml = idx + 1;
                }

                const retardColor = (et.nombre_retards > 0) ? '#dc3545' : '#28a745';
                const row = `
                    <tr>
                        <td>${rankHtml}</td>
                        <td>${escapeHtml((et.prenom || '') + ' ' + (et.nom || ''))}</td>
                        <td><small>${escapeHtml(et.numero_etudiant || '')}</small></td>
                        <td><strong>${et.nb_emprunts || 0}</strong></td>
                        <td><span style="color:${retardColor};">${et.nombre_retards || 0}</span></td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        // Update stats categories table
        function updateStatsCategories(categories) {
            const tbody = $('#tableStatsCategories tbody');
            tbody.empty();

            categories.forEach(cat => {
                const pct = cat.pourcentage || 0;
                const row = `
                    <tr>
                        <td><strong>${escapeHtml(cat.categorie || '-')}</strong></td>
                        <td>${cat.nb_livres || 0}</td>
                        <td>${cat.nb_emprunts || 0}</td>
                        <td>${pct}%</td>
                        <td>
                            <div style="width:100%; background:#eef6fb; height:14px; border-radius:8px; overflow:hidden;">
                                <div style="width:${pct}%; background:linear-gradient(90deg,var(--accent-1),var(--accent-2)); height:100%; transition:width 0.3s ease;"></div>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        // Show notification toast
        function showNotification(message) {
            const toast = $('#toastNotification');
            const body = $('#toastBody');
            
            body.html(message);
            toast.addClass('show');

            setTimeout(() => {
                toast.removeClass('show');
            }, 4000);
        }

        // Escape HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        // Format time
        function formatTime(date) {
            const h = String(date.getHours()).padStart(2, '0');
            const m = String(date.getMinutes()).padStart(2, '0');
            const s = String(date.getSeconds()).padStart(2, '0');
            return `${h}:${m}:${s}`;
        }

        // Fetch stats from server
        function fetchStats() {
            if (fetchInProgress) return;

            fetchInProgress = true;
            const startTime = Date.now();

            $.ajax({
                url: 'statistiques.php',
                method: 'GET',
                data: { action: 'fetch_stats' },
                dataType: 'json',
                cache: false,
                success: function(data) {
                    if (data && data.success) {
                        // Update KPIs
                        updateKPIs(data.kpis);

                        // Update charts
                        updateCharts(data.charts);

                        // Update tables
                        updateTopLivres(data.top_livres);
                        updateTopEtudiants(data.top_etudiants);
                        updateStatsCategories(data.stats_categories);

                        // Check for new emprunts
                        if (data.dernier_emprunt) {
                            const newId = data.dernier_emprunt.id_emprunt;
                            if (lastEmpruntId && newId > lastEmpruntId) {
                                const msg = `Nouvel emprunt : <strong>${escapeHtml(data.dernier_emprunt.titre)}</strong> par ${escapeHtml(data.dernier_emprunt.prenom)} ${escapeHtml(data.dernier_emprunt.nom)}`;
                                showNotification(msg);
                            }
                            lastEmpruntId = newId;
                        }

                        // Update last update time
                        const now = new Date();
                        $('#lastUpdate').text(`(${formatTime(now)})`);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erreur lors de la récupération des stats:', error);
                },
                complete: function() {
                    fetchInProgress = false;
                }
            });
        }

        // Manual refresh
        $('#refreshBtn').on('click', function() {
            const btn = $(this);
            const icon = btn.find('i');
            
            icon.addClass('fa-spin');
            fetchStats();
            
            setTimeout(() => {
                icon.removeClass('fa-spin');
            }, 1000);
        });

        // Auto-refresh timer
        function startAutoRefresh() {
            if (autoRefreshTimer) {
                clearInterval(autoRefreshTimer);
            }
            autoRefreshTimer = setInterval(fetchStats, AUTO_REFRESH_INTERVAL);
        }

        // Export PDF
        async function exportPDF() {
            const btn = $('#exportPdfBtn');
            const originalHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Préparation...');

            try {
                // Load libraries dynamically
                if (typeof html2canvas === 'undefined') {
                    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js');
                }
                if (typeof jsPDF === 'undefined' && typeof window.jspdf === 'undefined') {
                    await loadScript('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js');
                }

                const element = document.querySelector('.main-content');
                const canvas = await html2canvas(element, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    logging: false
                });

                const imgData = canvas.toDataURL('image/png');
                const { jsPDF: JsPDF } = window.jspdf || window;
                const pdf = new JsPDF({
                    orientation: 'landscape',
                    unit: 'pt',
                    format: [canvas.width, canvas.height]
                });

                pdf.addImage(imgData, 'PNG', 0, 0, canvas.width, canvas.height);
                
                const filename = `statistiques_bibliotheque_${new Date().toISOString().slice(0,10)}.pdf`;
                pdf.save(filename);

                showNotification('PDF exporté avec succès !');
            } catch (error) {
                alert('Erreur lors de l\'export PDF : ' + error.message);
                console.error(error);
            } finally {
                btn.prop('disabled', false).html(originalHtml);
            }
        }

        // Load external script
        function loadScript(src) {
            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = src;
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }

        // Initialize on document ready
        $(document).ready(function() {
            // Initialize charts
            initCharts();

            // Initial fetch after short delay
            setTimeout(() => {
                fetchStats();
            }, 1000);

            // Start auto-refresh
            startAutoRefresh();

            // Export PDF handler
            $('#exportPdfBtn').on('click', exportPDF);

            // Pause auto-refresh when page is not visible
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    if (autoRefreshTimer) {
                        clearInterval(autoRefreshTimer);
                        autoRefreshTimer = null;
                    }
                } else {
                    startAutoRefresh();
                    fetchStats(); // Immediate refresh when returning to page
                }
            });
        });
    </script>
</body>
</html>
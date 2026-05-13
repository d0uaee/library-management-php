<?php
require_once '../../config/config.php';
require_once '../../utils/security.php';

$action = isset($_GET['action']) ? cleanInput($_GET['action']) : '';

if ($action !== 'fetch_stats') {
    require_once '../../views/etudiant/nav-etudiant.php';
}

$id_etudiant = $_SESSION['user_id'];

// AJAX Endpoint
if ($action === 'fetch_stats') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        // Personal stats
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM emprunt WHERE id_etudiant = ? AND statut IN ('en_cours', 'en_retard')) as emprunts_actifs,
                (SELECT COUNT(*) FROM emprunt WHERE id_etudiant = ? AND statut = 'en_retard') as emprunts_retard,
                (SELECT COUNT(*) FROM emprunt WHERE id_etudiant = ? AND statut = 'retourne') as emprunts_termines,
                (SELECT COUNT(*) FROM reservation WHERE id_etudiant = ? AND statut = 'en_attente') as reservations_actives,
                (SELECT COUNT(*) FROM evaluation WHERE id_etudiant = ?) as favoris_count,
                (SELECT nombre_retards FROM etudiant WHERE id_etudiant = ?) as total_retards
        ");
        $stmt->execute([$id_etudiant, $id_etudiant, $id_etudiant, $id_etudiant, $id_etudiant, $id_etudiant]);
        $mes_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Library stats
        $stmt = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM livre) as total_livres,
                (SELECT SUM(nombre_disponibles) FROM livre) as livres_disponibles,
                (SELECT COUNT(*) FROM livre WHERE nombre_disponibles = 0) as livres_indisponibles,
                (SELECT COUNT(*) FROM etudiant WHERE statut = 'actif') as etudiants_actifs,
                (SELECT COUNT(*) FROM emprunt WHERE statut IN ('en_cours', 'en_retard')) as emprunts_en_cours_global
        ");
        $stats_biblio = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Favorite categories (ratings >= 3)
        $stmt = $pdo->prepare("
            SELECT c.nom as categorie, COUNT(e.id_evaluation) as nombre
            FROM evaluation e
            JOIN livre l ON e.id_livre = l.id_livre
            JOIN categorie c ON l.id_categorie = c.id_categorie
            WHERE e.id_etudiant = ? AND e.note >= 3
            GROUP BY c.id_categorie
            ORDER BY nombre DESC
            LIMIT 6
        ");
        $stmt->execute([$id_etudiant]);
        $mes_categories_favoris = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Borrowed categories
        $stmt = $pdo->prepare("
            SELECT c.nom as categorie, COUNT(e.id_emprunt) as nombre
            FROM emprunt e
            JOIN livre l ON e.id_livre = l.id_livre
            JOIN categorie c ON l.id_categorie = c.id_categorie
            WHERE e.id_etudiant = ?
            GROUP BY c.id_categorie
            ORDER BY nombre DESC
            LIMIT 6
        ");
        $stmt->execute([$id_etudiant]);
        $mes_categories_emprunts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Popular categories
        $stmt = $pdo->query("
            SELECT c.nom as categorie, COUNT(e.id_evaluation) as nombre
            FROM evaluation e
            JOIN livre l ON e.id_livre = l.id_livre
            JOIN categorie c ON l.id_categorie = c.id_categorie
            WHERE e.note >= 3
            GROUP BY c.id_categorie
            ORDER BY nombre DESC
            LIMIT 8
        ");
        $categories_populaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top borrowed books
        $stmt = $pdo->query("
            SELECT l.id_livre, l.titre, l.auteur, 
                   c.nom as categorie, COUNT(e.id_emprunt) as nb_emprunts
            FROM livre l
            LEFT JOIN emprunt e ON l.id_livre = e.id_livre
            LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
            GROUP BY l.id_livre
            ORDER BY nb_emprunts DESC
            LIMIT 10
        ");
        $top_livres_emprunts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top rated books
        $stmt = $pdo->query("
            SELECT l.id_livre, l.titre, l.auteur,
                   c.nom as categorie, COUNT(e.id_evaluation) as nb_favoris
            FROM livre l
            LEFT JOIN evaluation e ON l.id_livre = e.id_livre AND e.note >= 3
            LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
            GROUP BY l.id_livre
            HAVING nb_favoris > 0
            ORDER BY nb_favoris DESC
            LIMIT 10
        ");
        $top_livres_favoris = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // My borrowing history (12 months)
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(date_emprunt, '%b %Y') as mois,
                   DATE_FORMAT(date_emprunt, '%Y-%m') as mois_tri,
                   COUNT(*) as nb_emprunts
            FROM emprunt
            WHERE id_etudiant = ? AND date_emprunt >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(date_emprunt, '%Y-%m')
            ORDER BY mois_tri ASC
        ");
        $stmt->execute([$id_etudiant]);
        $mon_historique = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Global activity (12 months)
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(date_emprunt, '%b %Y') as mois,
                   DATE_FORMAT(date_emprunt, '%Y-%m') as mois_tri,
                   COUNT(*) as nb_emprunts
            FROM emprunt
            WHERE date_emprunt >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(date_emprunt, '%Y-%m')
            ORDER BY mois_tri ASC
        ");
        $activite_globale = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Current borrows
        $stmt = $pdo->prepare("
            SELECT e.id_emprunt, e.date_emprunt, e.date_retour_prevue, e.statut,
                   l.titre, l.auteur, l.image_couverture,
                   DATEDIFF(e.date_retour_prevue, CURDATE()) as jours_restants
            FROM emprunt e
            JOIN livre l ON e.id_livre = l.id_livre
            WHERE e.id_etudiant = ? AND e.statut IN ('en_cours', 'en_retard')
            ORDER BY e.date_retour_prevue ASC
            LIMIT 5
        ");
        $stmt->execute([$id_etudiant]);
        $mes_emprunts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Comparison
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM emprunt WHERE id_etudiant = ?) as mes_emprunts,
                (SELECT COUNT(*) / COUNT(DISTINCT id_etudiant) FROM emprunt) as moyenne_emprunts
        ");
        $stmt->execute([$id_etudiant]);
        $comparaison = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'mes_stats' => $mes_stats,
            'stats_biblio' => $stats_biblio,
            'mes_categories_favoris' => $mes_categories_favoris,
            'mes_categories_emprunts' => $mes_categories_emprunts,
            'categories_populaires' => $categories_populaires,
            'top_livres_emprunts' => $top_livres_emprunts,
            'top_livres_favoris' => $top_livres_favoris,
            'mon_historique' => $mon_historique,
            'activite_globale' => $activite_globale,
            'mes_emprunts' => $mes_emprunts,
            'comparaison' => $comparaison,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    exit;
}

// Initial page load
try {
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM emprunt WHERE id_etudiant = ? AND statut IN ('en_cours', 'en_retard')) as emprunts_actifs,
            (SELECT COUNT(*) FROM emprunt WHERE id_etudiant = ? AND statut = 'retourne') as emprunts_termines,
            (SELECT COUNT(*) FROM reservation WHERE id_etudiant = ? AND statut = 'en_attente') as reservations_actives,
            (SELECT COUNT(*) FROM evaluation WHERE id_etudiant = ?) as favoris_count
    ");
    $stmt->execute([$id_etudiant, $id_etudiant, $id_etudiant, $id_etudiant]);
    $initial_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM livre) as total_livres,
            (SELECT SUM(nombre_disponibles) FROM livre) as livres_disponibles,
            (SELECT COUNT(*) FROM livre WHERE nombre_disponibles = 0) as livres_indisponibles,
            (SELECT COUNT(*) FROM etudiant WHERE statut = 'actif') as etudiants_actifs
    ");
    $initial_biblio = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $initial_stats = [
        'emprunts_actifs' => 0,
        'emprunts_termines' => 0,
        'reservations_actives' => 0,
        'favoris_count' => 0
    ];
    $initial_biblio = [
        'total_livres' => 0,
        'livres_disponibles' => 0,
        'livres_indisponibles' => 0,
        'etudiants_actifs' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - Biblioth�que ENSAM</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/etudiant/stats.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <div class="main-container">
        <div class="page-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h1><i class="fas fa-chart-line"></i> Statistiques Avancées</h1>
                    <p>Analyse complète de votre activité et des tendances de la bibliothèque</p>
                </div>
                <div class="status-live">
                    <span class="status-dot"></span>
                    <span>Actualisation manuelle</span>
                </div>
            </div>
        </div>

        <!-- Personal Stats -->
        <div class="section-card">
            <h2 class="section-title"><i class="fas fa-user-chart"></i> Mon Activité</h2>
            <div class="stats-grid">
                <div class="stat-box" data-stat="emprunts_actifs">
                    <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                    <div class="stat-number"><?php echo (int)$initial_stats['emprunts_actifs']; ?></div>
                    <div class="stat-label">Emprunts actifs</div>
                </div>
                <div class="stat-box alt" data-stat="emprunts_termines">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo (int)$initial_stats['emprunts_termines']; ?></div>
                    <div class="stat-label">Emprunts terminés</div>
                </div>
                <div class="stat-box alt2" data-stat="reservations_actives">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-number"><?php echo (int)$initial_stats['reservations_actives']; ?></div>
                    <div class="stat-label">Réservations</div>
                </div>
                <div class="stat-box alt3" data-stat="favoris_count">
                    <div class="stat-icon"><i class="fas fa-heart"></i></div>
                    <div class="stat-number"><?php echo (int)$initial_stats['favoris_count']; ?></div>
                    <div class="stat-label">Favoris</div>
                </div>
            </div>
        </div>

        <!-- Personal Charts -->
        <div class="section-card">
            <h2 class="section-title"><i class="fas fa-chart-pie"></i> Mes Préférences & Activité</h2>
            <p class="section-subtitle">Analyse de vos favoris et de votre historique d'emprunts</p>
            
            <div class="chart-grid">
                <div class="chart-box">
                    <h3><i class="fas fa-heart text-danger"></i> Catégories de mes favoris (=3?)</h3>
                    <div class="chart-container">
                        <canvas id="chartMesFavoris"></canvas>
                    </div>
                </div>
                
                <div class="chart-box">
                    <h3><i class="fas fa-book text-primary"></i> Catégories que j'emprunte</h3>
                    <div class="chart-container">
                        <canvas id="chartMesEmprunts"></canvas>
                    </div>
                </div>
                
                <div class="chart-box">
                    <h3><i class="fas fa-calendar-alt text-success"></i> Mon historique (12 mois)</h3>
                    <div class="chart-container">
                        <canvas id="chartMonHistorique"></canvas>
                    </div>
                </div>
                
                <div class="chart-box">
                    <h3><i class="fas fa-balance-scale text-warning"></i> Moi vs Moyenne</h3>
                    <div class="chart-container">
                        <canvas id="chartComparaison"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Trends -->
        <div class="section-card">
            <h2 class="section-title"><i class="fas fa-globe"></i> Tendances de la Bibliothèque</h2>
            <p class="section-subtitle">Ce que tous les étudiants aiment et empruntent</p>
            
            <div class="chart-grid">
                <div class="chart-box">
                    <h3><i class="fas fa-fire text-danger"></i> Catégories populaires (Favoris =3?)</h3>
                    <div class="chart-container">
                        <canvas id="chartCategoriesPopulaires"></canvas>
                    </div>
                </div>
                
                <div class="chart-box">
                    <h3><i class="fas fa-chart-area text-primary"></i> Activité globale (12 mois)</h3>
                    <div class="chart-container">
                        <canvas id="chartActiviteGlobale"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rankings -->
        <div class="section-card">
            <h2 class="section-title"><i class="fas fa-trophy"></i> Rankings & Top 10</h2>
            
            <div class="stats-tabs">
                <button class="tab-btn active" onclick="switchRanking('emprunts')">
                    <i class="fas fa-book-reader"></i> Top Emprunts
                </button>
                <button class="tab-btn" onclick="switchRanking('favoris')">
                    <i class="fas fa-heart"></i> Top Favoris (=3?)
                </button>
            </div>
            
            <div id="ranking-emprunts" class="ranking-list">
                <p class="text-center text-muted">Chargement...</p>
            </div>
            
            <div id="ranking-favoris" class="ranking-list" style="display: none;">
                <p class="text-center text-muted">Chargement...</p>
            </div>
        </div>

        <!-- Current Borrows -->
        <div class="section-card">
            <h2 class="section-title"><i class="fas fa-clock"></i> Mes Emprunts en Cours</h2>
            <div class="emprunts-list" id="empruntsListe">
                <p class="text-center text-muted">Chargement...</p>
            </div>
        </div>

        <!-- Library Stats -->
        <div class="section-card">
            <h2 class="section-title"><i class="fas fa-library"></i> Statistiques Bibliothèque</h2>
            <div class="stats-grid">
                <div class="stat-box alt4" data-stat="total_livres">
                    <div class="stat-icon"><i class="fas fa-books"></i></div>
                    <div class="stat-number"><?php echo (int)$initial_biblio['total_livres']; ?></div>
                    <div class="stat-label">Livres au catalogue</div>
                </div>
                <div class="stat-box alt5" data-stat="livres_disponibles">
                    <div class="stat-icon"><i class="fas fa-check"></i></div>
                    <div class="stat-number"><?php echo (int)$initial_biblio['livres_disponibles']; ?></div>
                    <div class="stat-label">Livres disponibles</div>
                </div>
                <div class="stat-box alt6" data-stat="livres_indisponibles">
                    <div class="stat-icon"><i class="fas fa-times"></i></div>
                    <div class="stat-number"><?php echo (int)$initial_biblio['livres_indisponibles']; ?></div>
                    <div class="stat-label">Livres indisponibles</div>
                </div>
                <div class="stat-box" data-stat="etudiants_actifs">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo (int)$initial_biblio['etudiants_actifs']; ?></div>
                    <div class="stat-label">Étudiants actifs</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
// Configuration
const CONFIG = {
    CHART_COLORS: {
        primary: ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe', '#43e97b', '#38f9d7'],
        gradient1: ['#667eea', '#764ba2', '#f093fb', '#f5576c'],
        gradient2: ['#4facfe', '#00f2fe', '#43e97b', '#38f9d7'],
        warm: ['#ff9a9e', '#fecfef', '#ffecd2', '#fcb69f'],
        cool: ['#a1c4fd', '#c2e9fb', '#d4fc79', '#96e6a1']
    }
};

const FALLBACK_COVER = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="160" height="220"><rect width="160" height="220" fill="%23f5f5f5"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="%23999" font-family="Arial" font-size="26">📚</text></svg>';

let charts = {};
let fetchInProgress = false;
let currentRanking = 'emprunts';

// Navigation
function switchRanking(type) {
    currentRanking = type;
    $('.tab-btn').removeClass('active');
    
    if (type === 'emprunts') {
        $('.tab-btn:first').addClass('active');
        $('#ranking-emprunts').show();
        $('#ranking-favoris').hide();
    } else {
        $('.tab-btn:last').addClass('active');
        $('#ranking-emprunts').hide();
        $('#ranking-favoris').show();
    }
}

// Initialize charts
function initCharts() {
    try {
        // Mes Favoris (Doughnut)
        const ctxFavoris = document.getElementById('chartMesFavoris');
        if (ctxFavoris) {
            if (charts.mesFavoris) charts.mesFavoris.destroy();
            charts.mesFavoris = new Chart(ctxFavoris.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Aucune donnée'],
                    datasets: [{
                        data: [1],
                        backgroundColor: ['#e0e0e0'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 10, font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed + ' livre(s)';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Mes Emprunts (Polar Area)
        const ctxEmprunts = document.getElementById('chartMesEmprunts');
        if (ctxEmprunts) {
            if (charts.mesEmprunts) charts.mesEmprunts.destroy();
            charts.mesEmprunts = new Chart(ctxEmprunts.getContext('2d'), {
                type: 'polarArea',
                data: {
                    labels: ['Aucune donnée'],
                    datasets: [{
                        data: [1],
                        backgroundColor: ['#e0e0e0']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 10, font: { size: 11 } } }
                    }
                }
            });
        }

        // Mon Historique (Line)
        const ctxHistorique = document.getElementById('chartMonHistorique');
        if (ctxHistorique) {
            if (charts.monHistorique) charts.monHistorique.destroy();
            charts.monHistorique = new Chart(ctxHistorique.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Mes emprunts',
                        data: [],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        // Comparaison (Bar)
        const ctxComparaison = document.getElementById('chartComparaison');
        if (ctxComparaison) {
            if (charts.comparaison) charts.comparaison.destroy();
            charts.comparaison = new Chart(ctxComparaison.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Moi', 'Moyenne'],
                    datasets: [{
                        label: 'Nombre d\'emprunts',
                        data: [0, 0],
                        backgroundColor: ['#667eea', '#f5576c'],
                        borderRadius: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        // Catégories Populaires (Horizontal Bar)
        const ctxPopulaires = document.getElementById('chartCategoriesPopulaires');
        if (ctxPopulaires) {
            if (charts.categoriesPopulaires) charts.categoriesPopulaires.destroy();
            charts.categoriesPopulaires = new Chart(ctxPopulaires.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Favoris',
                        data: [],
                        backgroundColor: CONFIG.CHART_COLORS.warm,
                        borderRadius: 8
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        // Activité Globale (Area)
        const ctxActivite = document.getElementById('chartActiviteGlobale');
        if (ctxActivite) {
            if (charts.activiteGlobale) charts.activiteGlobale.destroy();
            charts.activiteGlobale = new Chart(ctxActivite.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Emprunts globaux',
                        data: [],
                        borderColor: '#4facfe',
                        backgroundColor: 'rgba(79, 172, 254, 0.2)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#4facfe',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        return true;
    } catch (error) {
        console.error('Erreur initialisation graphiques:', error);
        return false;
    }
}

// Update statistics
function updateStat(statName, newValue) {
    const box = $(`.stat-box[data-stat="${statName}"]`);
    const numberEl = box.find('.stat-number');
    const currentValue = parseInt(numberEl.text()) || 0;

    if (currentValue !== newValue) {
        numberEl.text(newValue);
    }
}

// Update charts
function updateCharts(data) {
    try {
        if (charts.mesFavoris && data.mes_categories_favoris) {
            if (data.mes_categories_favoris.length > 0) {
                charts.mesFavoris.data.labels = data.mes_categories_favoris.map(c => c.categorie);
                charts.mesFavoris.data.datasets[0].data = data.mes_categories_favoris.map(c => parseInt(c.nombre));
                charts.mesFavoris.data.datasets[0].backgroundColor = CONFIG.CHART_COLORS.primary;
            }
            charts.mesFavoris.update('none');
        }

        if (charts.mesEmprunts && data.mes_categories_emprunts) {
            if (data.mes_categories_emprunts.length > 0) {
                charts.mesEmprunts.data.labels = data.mes_categories_emprunts.map(c => c.categorie);
                charts.mesEmprunts.data.datasets[0].data = data.mes_categories_emprunts.map(c => parseInt(c.nombre));
                charts.mesEmprunts.data.datasets[0].backgroundColor = CONFIG.CHART_COLORS.gradient2;
            }
            charts.mesEmprunts.update('none');
        }

        if (charts.monHistorique && data.mon_historique) {
            if (data.mon_historique.length > 0) {
                charts.monHistorique.data.labels = data.mon_historique.map(h => h.mois);
                charts.monHistorique.data.datasets[0].data = data.mon_historique.map(h => parseInt(h.nb_emprunts));
            }
            charts.monHistorique.update('none');
        }

        if (charts.comparaison && data.comparaison) {
            const mesEmprunts = parseInt(data.comparaison.mes_emprunts) || 0;
            const moyenneEmprunts = parseFloat(data.comparaison.moyenne_emprunts) || 0;
            charts.comparaison.data.datasets[0].data = [mesEmprunts, moyenneEmprunts.toFixed(1)];
            charts.comparaison.update('none');
        }

        if (charts.categoriesPopulaires && data.categories_populaires) {
            if (data.categories_populaires.length > 0) {
                charts.categoriesPopulaires.data.labels = data.categories_populaires.map(c => c.categorie);
                charts.categoriesPopulaires.data.datasets[0].data = data.categories_populaires.map(c => parseInt(c.nombre));
            }
            charts.categoriesPopulaires.update('none');
        }

        if (charts.activiteGlobale && data.activite_globale) {
            if (data.activite_globale.length > 0) {
                charts.activiteGlobale.data.labels = data.activite_globale.map(a => a.mois);
                charts.activiteGlobale.data.datasets[0].data = data.activite_globale.map(a => parseInt(a.nb_emprunts));
            }
            charts.activiteGlobale.update('none');
        }
    } catch (error) {
        console.error('Erreur mise à jour graphiques:', error);
    }
}

// Update rankings
function updateRankings(emprunts, favoris) {
    const rankingEmprunts = $('#ranking-emprunts');
    if (emprunts && emprunts.length > 0) {
        const html = emprunts.map((livre, index) => {
            let badgeClass = 'normal';
            if (index === 0) badgeClass = 'gold';
            else if (index === 1) badgeClass = 'silver';
            else if (index === 2) badgeClass = 'bronze';
            
            return `
                <div class="ranking-item">
                    <div class="ranking-badge ${badgeClass}">${index + 1}</div>
                    <div class="ranking-info">
                        <div class="ranking-title">${livre.titre}</div>
                        <div class="ranking-author">${livre.auteur} • ${livre.categorie || 'Non catégorisé'}</div>
                    </div>
                    <div class="ranking-count">
                        <i class="fas fa-book-reader"></i> ${livre.nb_emprunts}
                    </div>
                </div>
            `;
        }).join('');
        rankingEmprunts.html(html);
    } else {
        rankingEmprunts.html('<p class="text-center text-muted">Aucune donnée disponible</p>');
    }

    const rankingFavoris = $('#ranking-favoris');
    if (favoris && favoris.length > 0) {
        const html = favoris.map((livre, index) => {
            let badgeClass = 'normal';
            if (index === 0) badgeClass = 'gold';
            else if (index === 1) badgeClass = 'silver';
            else if (index === 2) badgeClass = 'bronze';
            
            return `
                <div class="ranking-item">
                    <div class="ranking-badge ${badgeClass}">${index + 1}</div>
                    <div class="ranking-info">
                        <div class="ranking-title">${livre.titre}</div>
                        <div class="ranking-author">${livre.auteur} • ${livre.categorie || 'Non catégorisé'}</div>
                    </div>
                    <div class="ranking-count">
                        <i class="fas fa-heart text-danger"></i> ${livre.nb_favoris}
                    </div>
                </div>
            `;
        }).join('');
        rankingFavoris.html(html);
    } else {
        rankingFavoris.html('<p class="text-center text-muted">Aucune donnée disponible</p>');
    }
}

// Update emprunts list
function updateEmpruntsList(emprunts) {
    const list = $('#empruntsListe');
    
    if (!emprunts || emprunts.length === 0) {
        list.html(`
            <div class="text-center text-muted py-4">
                <i class="fas fa-inbox fa-3x mb-3" style="opacity: 0.3;"></i>
                <p>Aucun emprunt en cours</p>
            </div>
        `);
        return;
    }

    const html = emprunts.map(e => {
        let countdownClass = '';
        let statusIcon = '';
        
        if (e.jours_restants < 0) {
            countdownClass = 'danger';
            statusIcon = '<i class="fas fa-exclamation-triangle text-danger"></i>';
        } else if (e.jours_restants <= 3) {
            countdownClass = 'warning';
            statusIcon = '<i class="fas fa-clock text-warning"></i>';
        } else {
            statusIcon = '<i class="fas fa-check-circle text-success"></i>';
        }

        const img = e.image_couverture ? '../../assets/img/covers/' + e.image_couverture : FALLBACK_COVER;

        return `
            <div class="emprunt-card">
                <img src="${img}" alt="${e.titre}" class="emprunt-cover" onerror="this.onerror=null;this.src='${FALLBACK_COVER}'">
                <div class="emprunt-info">
                    <div class="emprunt-title">${e.titre}</div>
                    <div class="emprunt-author">${e.auteur}</div>
                </div>
                <div class="emprunt-countdown">
                    ${statusIcon}
                    <div class="countdown-number ${countdownClass}">${Math.abs(e.jours_restants)}</div>
                    <div class="countdown-label">${e.jours_restants < 0 ? 'jours de retard' : 'jours restants'}</div>
                </div>
            </div>
        `;
    }).join('');

    list.html(html);
}

// Fetch statistics
function fetchStats() {
    if (fetchInProgress) return;
    
    fetchInProgress = true;
    $('#loadingOverlay').addClass('show');

    $.ajax({
        url: '?action=fetch_stats',
        method: 'GET',
        dataType: 'json',
        timeout: 15000,
        success: function(response) {
            if (response.success) {
                if (response.mes_stats) {
                    $.each(response.mes_stats, function(stat, value) {
                        updateStat(stat, parseInt(value) || 0);
                    });
                }
                
                if (response.stats_biblio) {
                    $.each(response.stats_biblio, function(stat, value) {
                        updateStat(stat, parseInt(value) || 0);
                    });
                }
                
                updateCharts(response);
                updateRankings(response.top_livres_emprunts, response.top_livres_favoris);
                updateEmpruntsList(response.mes_emprunts);
            }
        },
        error: function(xhr, status, error) {
            console.error('Erreur AJAX:', status, error);
        },
        complete: function() {
            fetchInProgress = false;
            $('#loadingOverlay').removeClass('show');
        }
    });
}

// Manual refresh
function refreshStats() {
    fetchStats();
}

// Initialize on page load
$(document).ready(function() {
    setTimeout(() => {
        if (initCharts()) {
            setTimeout(() => {
                fetchStats();
            }, 500);
        }
    }, 100);
    
    const refreshBtn = `
        <button onclick="refreshStats()" class="btn btn-sm btn-outline-primary" 
                style="position: fixed; bottom: 20px; right: 20px; z-index: 1000; border-radius: 50px; padding: 10px 20px;">
            <i class="fas fa-sync-alt"></i> Actualiser
        </button>
    `;
    $('body').append(refreshBtn);
});
    </script>
</body>
</html>

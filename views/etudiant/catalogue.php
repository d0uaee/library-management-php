<?php
/**
 * CATALOGUE DES LIVRES - ESPACE ÉTUDIANT
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';
require_once '../../views/etudiant/nav-etudiant.php';

requireEtudiant();

$idEtudiant = $_SESSION['user_id'];


$categories = [];
$livres = [];
$nb_emprunts_actifs = 0;
$nb_reservations = 0;
$total_livres = 0;
$total_pages = 1;


$search = getCleanInput('search');
$categorie_filter = getCleanInput('categorie');
$disponibilite_filter = getCleanInput('disponibilite');


$livres_par_page = LIVRES_PAR_PAGE;
$page_actuelle = max(1, intval(getCleanInput('page')));
$offset = ($page_actuelle - 1) * $livres_par_page;

// LOAD DATA
$categories = getAllCategories($pdo);

$studentStats = getStudentStatistics($pdo, $idEtudiant);
$nb_emprunts_actifs = $studentStats['nb_emprunts'];
$nb_reservations = $studentStats['nb_reservations'];

$total_livres = countBooks($pdo, $search, $categorie_filter, $disponibilite_filter);
$total_pages = ceil($total_livres / $livres_par_page);

$livres = getBooks(
    $pdo,
    $idEtudiant,
    $search,
    $categorie_filter,
    $disponibilite_filter,
    $livres_par_page,
    $offset
);

$canBorrowResult = canBorrow($pdo, $idEtudiant);

// ============================================================================
//  FUNCTIONS
// ============================================================================

function getCleanInput($key) {
    if (isset($_GET[$key])) {
        return trim(cleanInput($_GET[$key]));
    }
    return '';
}

function getAllCategories($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id_categorie, nom FROM categorie ORDER BY nom");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error loading categories: " . $e->getMessage());
        return [];
    }
}

function getStudentStatistics($pdo, $studentId) {
    $stats = [
        'nb_emprunts' => 0,
        'nb_reservations' => 0
    ];
    
    try {
        // Count active emprunts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as nb 
            FROM emprunt 
            WHERE id_etudiant = ? 
            AND statut IN ('en_cours', 'en_retard')
        ");
        $stmt->execute([$studentId]);
        $stats['nb_emprunts'] = $stmt->fetch()['nb'];
        
        // Count active reservations
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as nb 
            FROM reservation 
            WHERE id_etudiant = ? 
            AND statut = 'en_attente'
        ");
        $stmt->execute([$studentId]);
        $stats['nb_reservations'] = $stmt->fetch()['nb'];
        
    } catch (PDOException $e) {
        error_log("Error loading student stats: " . $e->getMessage());
    }
    
    return $stats;
}

function countBooks($pdo, $search, $categoryFilter, $availabilityFilter) {
    $sql = "SELECT COUNT(DISTINCT l.id_livre) as total FROM livre l WHERE 1=1";
    $params = [];
    
    // search filter
    if (!empty($search)) {
        $sql .= " AND (l.titre LIKE ? OR l.auteur LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    
    // category filter
    if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
        $sql .= " AND l.id_categorie = ?";
        $params[] = (int)$categoryFilter;
    }
    
    // availability filter
    if ($availabilityFilter === 'disponible') {
        $sql .= " AND l.nombre_disponibles > 0";
    } elseif ($availabilityFilter === 'indisponible') {
        $sql .= " AND l.nombre_disponibles = 0";
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch()['total'];
    } catch (PDOException $e) {
        error_log("Error counting books: " . $e->getMessage());
        return 0;
    }
}

function getBooks($pdo, $studentId, $search, $categoryFilter, $availabilityFilter, $limit, $offset) {
    $sql = "
        SELECT
            l.id_livre,
            l.titre,
            l.auteur,
            l.nombre_disponibles,
            l.annee_publication,
            l.isbn,
            l.description,
            l.image_couverture,
            l.nombre_exemplaires,
            c.nom as categorie_nom,
            COALESCE(AVG(e_tous.note), 0) as note_moyenne,
            COALESCE(COUNT(DISTINCT e_tous.id_evaluation), 0) as nb_evaluations,
            COALESCE(e_moi.note, 0) as ma_note
        FROM livre l
        LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
        LEFT JOIN evaluation e_tous ON l.id_livre = e_tous.id_livre
        LEFT JOIN evaluation e_moi ON l.id_livre = e_moi.id_livre AND e_moi.id_etudiant = ?
        WHERE 1=1
    ";
    
    $params = [$studentId];
    
    if (!empty($search)) {
        $sql .= " AND (l.titre LIKE ? OR l.auteur LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if (!empty($categoryFilter) && is_numeric($categoryFilter)) {
        $sql .= " AND l.id_categorie = ?";
        $params[] = (int)$categoryFilter;
    }
    if ($availabilityFilter === 'disponible') {
        $sql .= " AND l.nombre_disponibles > 0";
    } elseif ($availabilityFilter === 'indisponible') {
        $sql .= " AND l.nombre_disponibles = 0";
    }
    // Add grouping, sorting, and pagination
    $sql .= " GROUP BY l.id_livre ORDER BY l.titre ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error loading books: " . $e->getMessage());
        return [];
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue - Bibliothèque ENSAM</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/etudiant/catalogue.css">
</head>
<body>

    <div class="main-container">
        <div class="page-header">
            <h1><i class="fas fa-book"></i> Catalogue des livres</h1>
            <p>Découvrez notre collection et empruntez vos livres préférés</p>
        </div>

        <?php if (!$canBorrowResult['can_borrow']): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Attention :</strong> <?php echo htmlspecialchars($canBorrowResult['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_livres; ?></h3>
                    <p>Livres trouvés</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-bookmark"></i></div>
                <div class="stat-info">
                    <h3><?php echo $nb_emprunts_actifs; ?></h3>
                    <p>Emprunts en cours</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo $nb_reservations; ?></h3>
                    <p>Réservations actives</p>
                </div>
            </div>
        </div>

        <div class="search-filters">
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-5">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" name="search" 
                                placeholder="Rechercher par titre, auteur ou ISBN..."
                                value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="categorie">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id_categorie']; ?>" 
                                    <?php echo $categorie_filter == $cat['id_categorie'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="disponibilite">
                            <option value="">Tous les livres</option>
                            <option value="disponible" <?php echo $disponibilite_filter === 'disponible' ? 'selected' : ''; ?>>
                                Disponibles
                            </option>
                            <option value="indisponible" <?php echo $disponibilite_filter === 'indisponible' ? 'selected' : ''; ?>>
                                Indisponibles
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-search w-100">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($livres)): ?>
            <div class="books-grid">
                <?php foreach ($livres as $livre): ?>
                    <div class="book-card">
                        <?php 
                        
                        $image_path = '../../assets/img/covers/' . htmlspecialchars($livre['image_couverture'] ?? '');
                        
                    ?>
                        <div class="livre-couverture-container" 
                             onclick='ouvrirModal(<?php echo htmlspecialchars(json_encode($livre), ENT_QUOTES, 'UTF-8'); ?>)'>
                            <span class="book-badge <?php echo $livre['nombre_disponibles'] > 0 ? 'badge-disponible' : 'badge-indisponible'; ?>">
                                <?php echo $livre['nombre_disponibles'] > 0 ? 'Disponible' : 'Indisponible'; ?>
                            </span>
                            <?php if (!empty($livre['image_couverture']) && file_exists($image_path)): ?>
                                <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                     class="livre-couverture" 
                                     alt="<?php echo htmlspecialchars($livre['titre']); ?>">
                            <?php else: ?>
                                <div><i class="fas fa-book-open fa-4x text-muted"></i></div>
                            <?php endif; ?>
                        </div>

                        <div class="book-body">
                            <span class="book-category">
                                <i class="fas fa-tag"></i> 
                                <?php echo htmlspecialchars($livre['categorie_nom'] ?? 'Non catégorisé'); ?>
                            </span>

                            <h3 class="book-title">
                                <?php echo htmlspecialchars($livre['titre']); ?>
                            </h3>

                            <p class="book-author">
                                <i class="fas fa-user-edit"></i>
                                <?php echo htmlspecialchars($livre['auteur']); ?>
                            </p>

                            <?php if ($livre['note_moyenne'] > 0): ?>
                                <div class="book-rating">
                                    <span class="stars">
                                        <?php 
                                        $note = round($livre['note_moyenne']);
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $note ? '★' : '☆';
                                        }
                                        ?>
                                    </span>
                                    <span class="rating-text">
                                        <?php echo number_format($livre['note_moyenne'], 1); ?> 
                                        (<?php echo $livre['nb_evaluations']; ?> avis)
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="book-info">
                                <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($livre['annee_publication']); ?></span>
                                <span><i class="fas fa-copy"></i> <?php echo $livre['nombre_disponibles']; ?>/<?php echo $livre['nombre_exemplaires']; ?></span>
                            </div>

                            <?php if ($livre['nombre_disponibles'] > 0 && $canBorrowResult['can_borrow']): ?>
                                <a href="traiter-emprunt.php?id=<?php echo intval($livre['id_livre']); ?>" class="btn btn-emprunter">
                                    <i class="fas fa-hand-holding-heart"></i> Emprunter
                                </a>
                            <?php elseif ($livre['nombre_disponibles'] == 0): ?>
                                <a href="traiter-reservation.php?id=<?php echo intval($livre['id_livre']); ?>" class="btn btn-reserver">
                                    <i class="fas fa-bookmark"></i> Réserver
                                </a>
                            <?php else: ?>
                                <button class="btn btn-emprunter" disabled style="opacity: 0.6; cursor: not-allowed;">
                                    <i class="fas fa-ban"></i> Emprunt impossible
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&categorie=<?php echo urlencode($categorie_filter); ?>&disponibilite=<?php echo urlencode($disponibilite_filter); ?>" 
                           class="<?php echo $i == $page_actuelle ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center p-5 bg-white rounded">
                <i class="fas fa-search fa-4x text-muted mb-3"></i>
                <h3 class="text-muted">Aucun livre trouvé</h3>
                <p class="text-muted">Essayez de modifier vos critères de recherche</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- MODAL -->
    <div class="modal fade modal-livre" id="modalLivre" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitre"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <img id="modalImage" src="" class="modal-cover" alt="">
                        </div>
                        <div class="col-md-8">
                            <div class="info-row">
                                <strong>Auteur:</strong>
                                <span id="modalAuteur"></span>
                            </div>
                            <div class="info-row">
                                <strong>Catégorie:</strong>
                                <span id="modalCategorie"></span>
                            </div>
                            <div class="info-row">
                                <strong>Année:</strong>
                                <span id="modalAnnee"></span>
                            </div>
                            <div class="info-row">
                                <strong>ISBN:</strong>
                                <span id="modalISBN"></span>
                            </div>
                            <div class="info-row">
                                <strong>Disponibilité:</strong>
                                <span id="modalDispo"></span>
                            </div>

                            <div class="rating-section">
                                <h5><i class="fas fa-star"></i> Évaluation du livre</h5>
                                <div class="rating-stats">
                                    <div class="rating-average">
                                        <div class="number" id="avgRating">0.0</div>
                                        <div class="stars" id="avgStars">☆☆☆☆☆</div>
                                        <div class="count" id="ratingCount">0 évaluations</div>
                                    </div>
                                    <div class="your-rating">
                                        <p><strong>Votre note :</strong></p>
                                        <div class="stars-container" id="starsContainer">
                                            <i class="fas fa-star star" data-value="1"></i>
                                            <i class="fas fa-star star" data-value="2"></i>
                                            <i class="fas fa-star star" data-value="3"></i>
                                            <i class="fas fa-star star" data-value="4"></i>
                                            <i class="fas fa-star star" data-value="5"></i>
                                        </div>
                                        <div id="ratingMessage" class="rating-message" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="resume-section">
                                <h5><i class="fas fa-book-open"></i> Résumé</h5>
                                <p id="modalResume"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let currentLivreId = null;
        let currentUserRating = 0;
        const modalElement = document.getElementById('modalLivre');
        const modal = new bootstrap.Modal(modalElement);

        function ouvrirModal(livre) {
            console.log('Ouverture modal:', livre);
            
            currentLivreId = livre.id_livre;
            currentUserRating = parseInt(livre.ma_note) || 0;
            
            document.getElementById('modalTitre').textContent = livre.titre || 'Titre indisponible';
            document.getElementById('modalAuteur').textContent = livre.auteur || 'Auteur inconnu';
            document.getElementById('modalCategorie').textContent = livre.categorie_nom || 'Non catégorisé';
            document.getElementById('modalAnnee').textContent = livre.annee_publication || 'N/A';
            document.getElementById('modalISBN').textContent = livre.isbn || 'N/A';
            document.getElementById('modalDispo').textContent = `${livre.nombre_disponibles}/${livre.nombre_exemplaires} exemplaires`;
            document.getElementById('modalResume').textContent = livre.description || 'Aucun résumé disponible pour ce livre.';
            
            const noteMoyenne = parseFloat(livre.note_moyenne) || 0;
            document.getElementById('avgRating').textContent = noteMoyenne.toFixed(1);
            
            const nbEvaluations = parseInt(livre.nb_evaluations) || 0;
            document.getElementById('ratingCount').textContent = `${nbEvaluations} évaluation${nbEvaluations > 1 ? 's' : ''}`;
            
            afficherEtoilesMoyenne(noteMoyenne);
            
           const imagePath = livre.image_couverture ? 
            '../../assets/img/covers/' + livre.image_couverture : 
            'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="300"%3E%3Crect fill="%23eee" width="200" height="300"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" fill="%23999" font-size="60"%3E📚%3C/text%3E%3C/svg%3E';
        
        document.getElementById('modalImage').src = imagePath;
            
            activerEtoiles(currentUserRating);
            
            modal.show();
        }

        function activerEtoiles(note) {
            const stars = document.querySelectorAll('.star');
            stars.forEach((star, index) => {
                if (index < note) { 
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }           
            });
        }

        function afficherEtoilesMoyenne(note) {
            let starsHTML = '';
            for (let i = 1; i <= 5; i++) {
                if (note >= i) {
                    starsHTML += '★';
                } else if (note >= i - 0.5) {
                    starsHTML += '⯨';
                } else {
                    starsHTML += '☆';
                }
            }
            document.getElementById('avgStars').textContent = starsHTML;
        }

        // ATTACHER LES ÉVÉNEMENTS DES ÉTOILES
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('starsContainer').addEventListener('click', function(event) {
                if (event.target.classList.contains('star')) {
                    const valeurCliquee = parseInt(event.target.getAttribute('data-value'));
                    
                    // Si on clique sur l'étoile déjà sélectionnée, on remet à 0
                    if (valeurCliquee === currentUserRating) {
                        noterLivre(0);
                    } else {
                        noterLivre(valeurCliquee);
                    }
                }
            });
        });

        function noterLivre(note) {
            if (!currentLivreId) {
                alert('Erreur : aucun livre sélectionné');
                return;
            }
            
            const formData = new FormData();
            formData.append('id_livre', currentLivreId);
            formData.append('note', note);
            
            fetch('traiter-evaluation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentUserRating = note;
                    activerEtoiles(note);
                    
                    const nouvelleMoyenne = parseFloat(data.nouvelle_moyenne) || 0;
                    document.getElementById('avgRating').textContent = nouvelleMoyenne.toFixed(1);
                    afficherEtoilesMoyenne(nouvelleMoyenne);
                    
                    const nbEvaluations = parseInt(data.nb_evaluations) || 0;
                    document.getElementById('ratingCount').textContent = `${nbEvaluations} évaluation${nbEvaluations > 1 ? 's' : ''}`;
                    
                    const messageDiv = document.getElementById('ratingMessage');
                    messageDiv.textContent = `Note enregistrée : ${note}/5`;
                    messageDiv.className = 'rating-message success';
                    messageDiv.style.display = 'block';
                    
                    setTimeout(() => {
                        messageDiv.style.display = 'none';
                    }, 3000);
                } else {
                    alert('Erreur : ' + (data.message || 'Erreur inconnue'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur de communication avec le serveur');
            });
        }
    </script>
</body>
</html>
<?php
/**
 * GESTION DES LIVRES - CRUD COMPLET
 * 
 * Permet à l'admin de :
 * - Afficher tous les livres
 * - Ajouter un nouveau livre
 * - Modifier un livre existant
 * - Supprimer un livre
 * - Rechercher et filtrer
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';

// Vérifier que l'utilisateur est un admin
requireAdmin();

require_once '../../views/admin/panel.php';

// Variables pour les messages
$success = '';
$error = '';

// ========================================
// TRAITEMENT DES ACTIONS
// ========================================

// SUPPRESSION D'UN LIVRE
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_livre = (int)$_GET['id'];
    
    try {
        // Vérifier si le livre a des emprunts en cours
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM emprunt 
            WHERE id_livre = ? AND statut IN ('en_cours', 'en_retard')
        ");
        $stmt->execute([$id_livre]);
        $emprunts_actifs = $stmt->fetchColumn();
        
        if ($emprunts_actifs > 0) {
            $error = "Impossible de supprimer ce livre : il y a $emprunts_actifs emprunt(s) en cours.";
        } else {
            // Supprimer le livre
            $stmt = $pdo->prepare("DELETE FROM livre WHERE id_livre = ?");
            $stmt->execute([$id_livre]);
            $success = "Livre supprimé avec succès !";
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// AJOUT D'UN NOUVEAU LIVRE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $isbn = trim($_POST['isbn']);
    $titre = trim($_POST['titre']);
    $auteur = trim($_POST['auteur']);
    $annee_publication = $_POST['annee_publication'];
    $id_categorie = (int)$_POST['id_categorie'];
    $nombre_exemplaires = (int)$_POST['nombre_exemplaires'];
    $description = trim($_POST['description']);
    
    // Validation
    if (empty($titre) || empty($auteur) || empty($id_categorie) || $nombre_exemplaires < 1) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        try {
            // Vérifier si l'ISBN existe déjà
            if (!empty($isbn)) {
                $stmt = $pdo->prepare("SELECT id_livre FROM livre WHERE isbn = ?");
                $stmt->execute([$isbn]);
                if ($stmt->fetch()) {
                    $error = "Un livre avec cet ISBN existe déjà.";
                }
            }
            
            if (empty($error)) {
                $stmt = $pdo->prepare("
                    INSERT INTO livre (isbn, titre, auteur, annee_publication, id_categorie, 
                                      nombre_exemplaires, nombre_disponibles, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $isbn ?: null,
                    $titre,
                    $auteur,
                    $annee_publication ?: null,
                    $id_categorie,
                    $nombre_exemplaires,
                    $nombre_exemplaires, // Initialement tous disponibles
                    $description ?: null
                ]);
                $success = "Livre ajouté avec succès !";
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de l'ajout : " . $e->getMessage();
        }
    }
}

// MODIFICATION D'UN LIVRE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id_livre = (int)$_POST['id_livre'];
    $isbn = trim($_POST['isbn']);
    $titre = trim($_POST['titre']);
    $auteur = trim($_POST['auteur']);
    $annee_publication = $_POST['annee_publication'];
    $id_categorie = (int)$_POST['id_categorie'];
    $nombre_exemplaires = (int)$_POST['nombre_exemplaires'];
    $description = trim($_POST['description']);
    
    // Validation
    if (empty($titre) || empty($auteur) || empty($id_categorie) || $nombre_exemplaires < 1) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        try {
            // Récupérer l'ancien nombre d'exemplaires
            $stmt = $pdo->prepare("SELECT nombre_exemplaires, nombre_disponibles FROM livre WHERE id_livre = ?");
            $stmt->execute([$id_livre]);
            $ancien_livre = $stmt->fetch();
            
            // Calculer le nouveau nombre de disponibles
            $difference = $nombre_exemplaires - $ancien_livre['nombre_exemplaires'];
            $nouveau_disponibles = $ancien_livre['nombre_disponibles'] + $difference;
            
            // S'assurer que disponibles ne soit pas négatif
            if ($nouveau_disponibles < 0) {
                $nouveau_disponibles = 0;
            }
            
            $stmt = $pdo->prepare("
                UPDATE livre 
                SET isbn = ?, titre = ?, auteur = ?, annee_publication = ?, 
                    id_categorie = ?, nombre_exemplaires = ?, nombre_disponibles = ?, description = ?
                WHERE id_livre = ?
            ");
            $stmt->execute([
                $isbn ?: null,
                $titre,
                $auteur,
                $annee_publication ?: null,
                $id_categorie,
                $nombre_exemplaires,
                $nouveau_disponibles,
                $description ?: null,
                $id_livre
            ]);
            $success = "Livre modifié avec succès !";
        } catch (PDOException $e) {
            $error = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}

// ========================================
// RÉCUPÉRATION DES DONNÉES
// ========================================

// Filtres de recherche
$search = $_GET['search'] ?? '';
$categorie_filter = $_GET['categorie'] ?? '';
$disponibilite_filter = $_GET['disponibilite'] ?? '';

// Construction de la requête
$sql = "SELECT l.*, c.nom AS nom_categorie 
        FROM livre l 
        LEFT JOIN categorie c ON l.id_categorie = c.id_categorie 
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (l.titre LIKE ? OR l.auteur LIKE ? OR l.isbn LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($categorie_filter)) {
    $sql .= " AND l.id_categorie = ?";
    $params[] = $categorie_filter;
}

if ($disponibilite_filter === 'disponible') {
    $sql .= " AND l.nombre_disponibles > 0";
} elseif ($disponibilite_filter === 'indisponible') {
    $sql .= " AND l.nombre_disponibles = 0";
}

$sql .= " ORDER BY l.titre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$livres = $stmt->fetchAll();

// Récupération des catégories
$categories = $pdo->query("SELECT * FROM categorie ORDER BY nom")->fetchAll();

// Livre à éditer (si demandé)
$livre_edit = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM livre WHERE id_livre = ?");
    $stmt->execute([$_GET['edit']]);
    $livre_edit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Livres - Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../../assets/css/admin/livres.css">
</head>
<body>
    <!-- Contenu principal -->
    <div class="main-content">
        <!-- Barre supérieure -->
        <div class="top-bar">
            <div>
                <h1 style="margin: 0; color: #333;">
                    <i class="fas fa-book"></i> Gestion des Livres
                </h1>
                <p style="margin: 5px 0 0 0; color: #666;">
                    Gérer le catalogue de la bibliothèque
                </p>
            </div>
            <button class="btn-primary-custom" onclick="openModal('addModal')">
                <i class="fas fa-plus"></i> Ajouter un livre
            </button>
        </div>

        <!-- Messages -->
        <?php if ($success): ?>
            <div class="alert-custom alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-custom alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Recherche et filtres -->
        <div class="card">
            <form method="GET" action="">
                <div class="search-section">
                    <div class="search-box">
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="Rechercher par titre, auteur ou ISBN..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>
                    
                    <select name="categorie" class="form-control-custom" style="width: auto;">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id_categorie']; ?>" 
                                    <?php echo $categorie_filter == $cat['id_categorie'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="disponibilite" class="form-control-custom" style="width: auto;">
                        <option value="">Tous</option>
                        <option value="disponible" <?php echo $disponibilite_filter === 'disponible' ? 'selected' : ''; ?>>
                            Disponibles
                        </option>
                        <option value="indisponible" <?php echo $disponibilite_filter === 'indisponible' ? 'selected' : ''; ?>>
                            Indisponibles
                        </option>
                    </select>

                    <button type="submit" class="btn-primary-custom">
                        <i class="fas fa-search"></i> Rechercher
                    </button>

                    <?php if ($search || $categorie_filter || $disponibilite_filter): ?>
                        <a href="livres.php" class="btn-primary-custom" style="background: #6c757d;">
                            <i class="fas fa-times"></i> Réinitialiser
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Liste des livres -->
        <div class="card">
            <h5 style="margin-bottom: 20px;">
                <i class="fas fa-list"></i> Liste des livres 
                <span style="color: #999; font-size: 0.9rem;">(<?php echo count($livres); ?> résultat(s))</span>
            </h5>

            <?php if (count($livres) > 0): ?>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Auteur</th>
                            <th>Catégorie</th>
                            <th>ISBN</th>
                            <th>Exemplaires</th>
                            <th>Disponibles</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($livres as $livre): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($livre['titre']); ?></strong>
                                    <?php if ($livre['annee_publication']): ?>
                                        <br><small style="color: #999;">(<?php echo $livre['annee_publication']; ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($livre['auteur']); ?></td>
                                <td>
                                    <span class="badge-custom badge-info">
                                        <?php echo htmlspecialchars($livre['nom_categorie']); ?>
                                    </span>
                                </td>
                                <td><small><?php echo htmlspecialchars($livre['isbn'] ?: 'N/A'); ?></small></td>
                                <td><strong><?php echo $livre['nombre_exemplaires']; ?></strong></td>
                                <td>
                                    <?php if ($livre['nombre_disponibles'] > 0): ?>
                                        <span class="badge-custom badge-success">
                                            <?php echo $livre['nombre_disponibles']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-custom badge-danger">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group-custom">
                                        <button class="btn-sm-custom btn-edit" 
                                                onclick="editLivre(<?php echo htmlspecialchars(json_encode($livre)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-sm-custom btn-delete" 
                                                onclick="deleteLivre(<?php echo $livre['id_livre']; ?>, '<?php echo htmlspecialchars($livre['titre'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <i class="fas fa-inbox" style="font-size: 60px; margin-bottom: 15px;"></i>
                    <p>Aucun livre trouvé</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal AJOUTER -->
    <div id="addModal" class="modal-custom">
        <div class="modal-content-custom">
            <h3 style="margin-bottom: 25px;">
                <i class="fas fa-plus-circle"></i> Ajouter un nouveau livre
            </h3>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label class="form-label">Titre <span style="color: red;">*</span></label>
                    <input type="text" name="titre" class="form-control-custom" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Auteur <span style="color: red;">*</span></label>
                    <input type="text" name="auteur" class="form-control-custom" required>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">ISBN</label>
                            <input type="text" name="isbn" class="form-control-custom" 
                                   placeholder="9780132350884">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Année de publication</label>
                            <input type="number" name="annee_publication" class="form-control-custom" 
                                   min="1800" max="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Catégorie <span style="color: red;">*</span></label>
                            <select name="id_categorie" class="form-control-custom" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id_categorie']; ?>">
                                        <?php echo htmlspecialchars($cat['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Nombre d'exemplaires <span style="color: red;">*</span></label>
                            <input type="number" name="nombre_exemplaires" class="form-control-custom" 
                                   min="1" value="1" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control-custom" 
                              placeholder="Description du livre..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                    <button type="button" class="btn-primary-custom" 
                            style="background: #6c757d;" onclick="closeModal('addModal')">
                        Annuler
                    </button>
                    <button type="submit" class="btn-primary-custom">
                        <i class="fas fa-save"></i> Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal MODIFIER -->
    <div id="editModal" class="modal-custom">
        <div class="modal-content-custom">
            <h3 style="margin-bottom: 25px;">
                <i class="fas fa-edit"></i> Modifier le livre
            </h3>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id_livre" id="edit_id_livre">
                
                <div class="form-group">
                    <label class="form-label">Titre <span style="color: red;">*</span></label>
                    <input type="text" name="titre" id="edit_titre" class="form-control-custom" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Auteur <span style="color: red;">*</span></label>
                    <input type="text" name="auteur" id="edit_auteur" class="form-control-custom" required>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">ISBN</label>
                            <input type="text" name="isbn" id="edit_isbn" class="form-control-custom">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Année de publication</label>
                            <input type="number" name="annee_publication" id="edit_annee" 
                                   class="form-control-custom" min="1800" max="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Catégorie <span style="color: red;">*</span></label>
                            <select name="id_categorie" id="edit_categorie" class="form-control-custom" required>
<?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id_categorie']; ?>">
                                        <?php echo htmlspecialchars($cat['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Nombre d'exemplaires <span style="color: red;">*</span></label>
                            <input type="number" name="nombre_exemplaires" id="edit_exemplaires" 
                                   class="form-control-custom" min="1" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_description" 
                              class="form-control-custom"></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                    <button type="button" class="btn-primary-custom" 
                            style="background: #6c757d;" onclick="closeModal('editModal')">
                        Annuler
                    </button>
                    <button type="submit" class="btn-primary-custom">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Ouvrir un modal
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        // Fermer un modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Fermer le modal si on clique en dehors
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-custom')) {
                event.target.style.display = 'none';
            }
        }

        // Fonction pour éditer un livre
        function editLivre(livre) {
            document.getElementById('edit_id_livre').value = livre.id_livre;
            document.getElementById('edit_titre').value = livre.titre;
            document.getElementById('edit_auteur').value = livre.auteur;
            document.getElementById('edit_isbn').value = livre.isbn || '';
            document.getElementById('edit_annee').value = livre.annee_publication || '';
            document.getElementById('edit_categorie').value = livre.id_categorie;
            document.getElementById('edit_exemplaires').value = livre.nombre_exemplaires;
            document.getElementById('edit_description').value = livre.description || '';
            
            openModal('editModal');
        }

        // Fonction pour supprimer un livre
        function deleteLivre(id, titre) {
            if (confirm('Êtes-vous sûr de vouloir supprimer le livre :\n"' + titre + '" ?\n\nCette action est irréversible !')) {
                window.location.href = 'livres.php?action=delete&id=' + id;
            }
        }

        // Fermer les modals avec la touche Échap
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal('addModal');
                closeModal('editModal');
            }
        });

        // Auto-fermer les alertes après 5 secondes
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-custom');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
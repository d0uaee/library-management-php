<?php
/**
 * GESTION DES CONTACTS ÉTUDIANTS
*/

require_once '../../config/config.php';
require_once '../../utils/security.php';
require_once '../../views/admin/panel.php';

requireAdmin();

// ACTIONS CRUD

$message_feedback = '';
$message_type = '';

if (isset($_POST['ajouter'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $numero_etudiant = trim($_POST['numero_etudiant']);
    $telephone = trim($_POST['telephone']);
    $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO etudiant (nom, prenom, email, numero_etudiant, telephone, mot_de_passe, statut, date_inscription) VALUES (?, ?, ?, ?, ?, ?, 'actif', NOW())");
    if ($stmt->execute([$nom, $prenom, $email, $numero_etudiant, $telephone, $mot_de_passe])) {
        $message_feedback = "Étudiant ajouté avec succès";
        $message_type = "success";
    } else {
        $message_feedback = "Erreur lors de l'ajout de l'étudiant";
        $message_type = "danger";
    }
}

if (isset($_POST['modifier'])) {
    $id_etudiant = intval($_POST['id_etudiant']);
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $numero_etudiant = trim($_POST['numero_etudiant']);
    $telephone = trim($_POST['telephone']);
    $statut = $_POST['statut'];
    
    $stmt = $pdo->prepare("UPDATE etudiant SET nom = ?, prenom = ?, email = ?, numero_etudiant = ?, telephone = ?, statut = ? WHERE id_etudiant = ?");
    if ($stmt->execute([$nom, $prenom, $email, $numero_etudiant, $telephone, $statut, $id_etudiant])) {
        $message_feedback = "Étudiant modifié avec succès";
        $message_type = "success";
    } else {
        $message_feedback = "Erreur lors de la modification";
        $message_type = "danger";
    }
}

$etudiant_edit = null;
if (isset($_GET['edit'])) {
    $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM etudiant WHERE id_etudiant = ?");
    $stmt->execute([$id_edit]);
    $etudiant_edit = $stmt->fetch();
}

// DELETE - Supprimer un étudiant
if (isset($_POST['supprimer'])) {
    $id_etudiant = intval($_POST['id_etudiant']);
    $stmt = $pdo->prepare("DELETE FROM etudiant WHERE id_etudiant = ?");
    if ($stmt->execute([$id_etudiant])) {
        $message_feedback = "Étudiant supprimé avec succès";
        $message_type = "success";
    } else {
        $message_feedback = "Erreur lors de la suppression";
        $message_type = "danger";
    }
}

// Bloquer un étudiant
if (isset($_POST['bloquer'])) {
    $id_etudiant = intval($_POST['id_etudiant']);
    $stmt = $pdo->prepare("UPDATE etudiant SET statut = 'bloque' WHERE id_etudiant = ?");
    if ($stmt->execute([$id_etudiant])) {
        $message_feedback = "Étudiant bloqué avec succès";
        $message_type = "success";
    }
}

// Activer un étudiant
if (isset($_POST['activer'])) {
    $id_etudiant = intval($_POST['id_etudiant']);
    $stmt = $pdo->prepare("UPDATE etudiant SET statut = 'actif' WHERE id_etudiant = ?");
    if ($stmt->execute([$id_etudiant])) {
        $message_feedback = "Étudiant activé avec succès";
        $message_type = "success";
    }
}

// ========================================
// RÉCUPÉRATION DES DONNÉES
// ========================================

// Filtre de statut
$filtre_statut = isset($_GET['statut']) ? $_GET['statut'] : 'tous';
$recherche = $_GET['recherche'] ?? '';

// Statistiques globales
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_etudiants,
        SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as total_actifs,
        SUM(CASE WHEN statut = 'bloque' THEN 1 ELSE 0 END) as total_bloques
    FROM etudiant
");
$stats = $stmt->fetch();

// Récupération des étudiants selon le filtre
$sql = "SELECT e.* FROM etudiant e WHERE 1=1";

if ($filtre_statut !== 'tous') {
    $sql .= " AND e.statut = ?";
}

if ($recherche) {
    $sql .= " AND (e.nom LIKE ? OR e.prenom LIKE ? OR e.email LIKE ? OR e.numero_etudiant LIKE ? OR e.telephone LIKE ?)";
}

$sql .= " ORDER BY e.date_inscription DESC";

$stmt = $pdo->prepare($sql);
$params = [];
if ($filtre_statut !== 'tous') {
    $params[] = $filtre_statut;
}
if ($recherche) {
    $recherche_param = "%$recherche%";
    for ($i = 0; $i < 5; $i++) {
        $params[] = $recherche_param;
    }
}
$stmt->execute($params);
$etudiants = $stmt->fetchAll();

$stmt = $pdo->query("SELECT COUNT(*) as total_bloques FROM etudiant WHERE statut = 'bloque'");
$etudiants_bloques = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Contacts - Bibliothèque ENSAM</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin/contact.css">
</head>
<body>
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1 class="page-title">
                <i class="fas fa-address-book"></i> Gestion des Contacts
            </h1>
            <div>
                <span class="badge-statut badge-actif">
                    <i class="fas fa-bell"></i> <?= $stats['total_actifs'] ?> actifs
                </span>
            </div>
        </div>

        <!-- Messages de feedback -->
        <?php if ($message_feedback): ?>
            <div class="alert-custom alert-<?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message_feedback) ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire d'ajout/modification -->
        <?php if (isset($_GET['add']) || $etudiant_edit): ?>
        <div class="form-section">
            <h3>
                <i class="fas fa-<?= $etudiant_edit ? 'edit' : 'plus-circle' ?>"></i>
                <?= $etudiant_edit ? 'Modifier un étudiant' : 'Ajouter un étudiant' ?>
            </h3>
            <form method="POST" action="">
                <?php if ($etudiant_edit): ?>
                    <input type="hidden" name="id_etudiant" value="<?= $etudiant_edit['id_etudiant'] ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nom *</label>
                            <input type="text" name="nom" class="form-control" required 
                                   value="<?= $etudiant_edit ? htmlspecialchars($etudiant_edit['nom']) : '' ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Prénom *</label>
                            <input type="text" name="prenom" class="form-control" required 
                                   value="<?= $etudiant_edit ? htmlspecialchars($etudiant_edit['prenom']) : '' ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" class="form-control" required 
                                   value="<?= $etudiant_edit ? htmlspecialchars($etudiant_edit['email']) : '' ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Numéro étudiant *</label>
                            <input type="text" name="numero_etudiant" class="form-control" required 
                                   value="<?= $etudiant_edit ? htmlspecialchars($etudiant_edit['numero_etudiant']) : '' ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="text" name="telephone" class="form-control" 
                                   value="<?= $etudiant_edit ? htmlspecialchars($etudiant_edit['telephone']) : '' ?>">
                        </div>
                    </div>
                    <?php if ($etudiant_edit): ?>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Statut *</label>
                            <select name="statut" class="form-control" required>
                                <option value="actif" <?= $etudiant_edit['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                                <option value="bloque" <?= $etudiant_edit['statut'] === 'bloque' ? 'selected' : '' ?>>Bloqué</option>
                            </select>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Mot de passe *</label>
                            <input type="password" name="mot_de_passe" class="form-control" required>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" name="<?= $etudiant_edit ? 'modifier' : 'ajouter' ?>" class="btn-primary">
                        <i class="fas fa-<?= $etudiant_edit ? 'save' : 'plus' ?>"></i>
                        <?= $etudiant_edit ? 'Enregistrer les modifications' : 'Ajouter l\'étudiant' ?>
                    </button>
                    <a href="contact.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Barre de recherche -->
        <div class="search-box">
            <form method="GET" action="" class="search-form">
                <input type="text" 
                       name="recherche" 
                       class="search-input" 
                       placeholder="Rechercher par nom, prénom, email, numéro étudiant ou téléphone..."
                       value="<?= htmlspecialchars($recherche) ?>">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Rechercher
                </button>
                
                <?php if ($recherche): ?>
                    <a href="contact.php" class="btn-reset">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                <?php endif; ?>
                
                <?php if (!isset($_GET['add']) && !$etudiant_edit): ?>
                    <a href="?add=1" class="btn-search">
                        <i class="fas fa-plus"></i> Nouvel étudiant
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card purple" onclick="window.location.href='?statut=tous'">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?= $stats['total_etudiants'] ?></div>
                <div class="stat-label">Total Étudiants</div>
            </div>

            <div class="stat-card green" onclick="window.location.href='?statut=actif'">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value"><?= $stats['total_actifs'] ?></div>
                <div class="stat-label">Actifs</div>
            </div>

            <div class="stat-card orange" onclick="window.location.href='?statut=bloque'">
                <div class="stat-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-value"><?= $stats['total_bloques'] ?></div>
                <div class="stat-label">Bloqués</div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filter-tabs">
            <a href="?statut=tous<?= $recherche ? '&recherche=' . urlencode($recherche) : '' ?>" 
               class="filter-btn <?= $filtre_statut === 'tous' ? 'active' : '' ?>">
                <i class="fas fa-list"></i> Tous
            </a>
            <a href="?statut=actif<?= $recherche ? '&recherche=' . urlencode($recherche) : '' ?>" 
               class="filter-btn <?= $filtre_statut === 'actif' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i> Actifs
            </a>
            <a href="?statut=bloque<?= $recherche ? '&recherche=' . urlencode($recherche) : '' ?>" 
               class="filter-btn <?= $filtre_statut === 'bloque' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i> Bloqués
            </a>
        </div>

        <!-- Liste des messages -->
        <?php if (count($etudiants) > 0): ?>
            <?php foreach ($etudiants as $msg): ?>
                <div class="message-card <?= htmlspecialchars($msg['statut']) ?>">
                    <div class="message-header">
                        <div class="message-info">
                            <div class="message-sender">
                                <i class="fas fa-user"></i>
                                <?= htmlspecialchars($msg['nom']) ?> 
                                <?= htmlspecialchars($msg['prenom']) ?>
                            </div>
                            <div class="message-meta">
                                <i class="fas fa-envelope"></i>
                                <?= htmlspecialchars($msg['email']) ?>
                                <span style="margin-left: 15px;">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d/m/Y à H:i', strtotime($msg['date_inscription'])) ?>
                                </span>
                                <?php if ($msg['telephone']): ?>
                                    <span style="margin-left: 15px;">
                                        <i class="fas fa-phone"></i>
                                        <?= htmlspecialchars($msg['telephone']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="badge-statut badge-<?= str_replace('_', '-', $msg['statut']) ?>">
                            <?php
                            $statuts = [
                                'actif' => 'Actif',
                                'bloque' => 'Bloqué'
                            ];
                            echo $statuts[$msg['statut']] ?? $msg['statut'];
                            ?>
                        </span>
                    </div>

                    <div class="message-subject">
                        <i class="fas fa-id-card"></i>
                        Numéro étudiant: <?= htmlspecialchars($msg['numero_etudiant']) ?>
                    </div>

                    <div class="message-body">
                        <strong>Informations:</strong><br>
                        Date d'inscription: <?= date('d/m/Y', strtotime($msg['date_inscription'])) ?><br>
                        Nombre de retards: <?= htmlspecialchars($msg['nombre_retards'] ?? 0) ?>
                    </div>

                    <div class="message-actions">
                        <a href="?edit=<?= $msg['id_etudiant'] ?>" class="btn-action btn-edit">
                            <i class="fas fa-edit"></i> Modifier
                        </a>

                        <a href="penalisation.php?id_etudiant=<?= $msg['id_etudiant'] ?>" class="btn-action" style="background: #f093fb; color: white;">
                            <i class="fas fa-gavel"></i> Pénaliser
                        </a>

                        <?php if ($msg['statut'] === 'actif'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id_etudiant" value="<?= $msg['id_etudiant'] ?>">
                                <button type="submit" name="bloquer" class="btn-action btn-supprimer"
                                        onclick="return confirm('Voulez-vous vraiment bloquer cet étudiant ?');">
                                    <i class="fas fa-ban"></i> Bloquer
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id_etudiant" value="<?= $msg['id_etudiant'] ?>">
                                <button type="submit" name="activer" class="btn-action btn-traite"
                                        onclick="return confirm('Voulez-vous vraiment activer cet étudiant ?');">
                                    <i class="fas fa-check-circle"></i> Activer
                                </button>
                            </form>
                        <?php endif; ?>

                        <a href="messagerie.php?id_etudiant=<?= $msg['id_etudiant'] ?>" 
                           class="btn-action btn-email">
                            <i class="fas fa-comments"></i> Envoyer un message
                        </a>

                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('Voulez-vous vraiment supprimer cet étudiant ?');">
                            <input type="hidden" name="id_etudiant" value="<?= $msg['id_etudiant'] ?>">
                            <button type="submit" name="supprimer" class="btn-action btn-supprimer">
                                <i class="fas fa-trash"></i> Supprimer
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Aucun étudiant</h3>
                <p>
                    <?php if ($recherche): ?>
                        Aucun résultat pour "<?= htmlspecialchars($recherche) ?>"
                    <?php else: ?>
                        Il n'y a aucun étudiant <?= $filtre_statut !== 'tous' ? 'avec ce statut' : 'pour le moment' ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
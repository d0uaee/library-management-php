<?php
/**
 * GESTION DES PÉNALISATIONS
 * - Pénaliser un étudiant (blocage temporaire ou permanent) - Réactiver un étudiant 
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';

requireAdmin();

require_once '../../views/admin/panel.php';

$success = '';
$error = '';
$admin_id = $_SESSION['user_id'];

// Récupérer tous les étudiants 
$stmt_all = $pdo->query("SELECT id_etudiant, nom, prenom, numero_etudiant, email FROM etudiant ORDER BY nom, prenom");
$tous_etudiants = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'ID étudiant depuis l'URL si présent
$id_etudiant_preselect = isset($_GET['id_etudiant']) ? (int)$_GET['id_etudiant'] : null;
$etudiant_preselect = null;

if ($id_etudiant_preselect) {
    $stmt = $pdo->prepare("SELECT id_etudiant, nom, prenom, numero_etudiant, email FROM etudiant WHERE id_etudiant = ?");
    $stmt->execute([$id_etudiant_preselect]);
    $etudiant_preselect = $stmt->fetch(PDO::FETCH_ASSOC);
}

// =========================================
// TRAITEMENT DES ACTIONS
// =========================================

// 1. AJOUTER UNE PÉNALISATION 
if (isset($_POST['action']) && $_POST['action'] === 'penaliser_etudiant') {
    $id_etudiant = (int)$_POST['id_etudiant'];
    $duree_ban_jours = (int)$_POST['duree_ban_jours']; // 0 pour permanent

    // Déterminer la date de désactivation
    if ($duree_ban_jours <= 0) {
        // Pénalité permanente : date_desactivation = NULL
        $date_desactivation = NULL;
    } else {
        // Pénalité temporaire : Date actuelle + N jours
        $date_desactivation = date('Y-m-d', strtotime("+$duree_ban_jours days"));
    }

    try {
        $pdo->beginTransaction();

        // l'étudiant existe
        $stmt_etudiant = $pdo->prepare("SELECT COUNT(*) FROM etudiant WHERE id_etudiant = ?");
        $stmt_etudiant->execute([$id_etudiant]);
        if ($stmt_etudiant->fetchColumn() == 0) {
            $error = "Erreur : Étudiant ID $id_etudiant introuvable.";
            $pdo->rollBack();
        } else {
            // enregistrement de pénalisation
            $stmt_penal = $pdo->prepare("
                INSERT INTO penalisation (id_admin, id_etudiant, date_penalisation, date_desactivation) 
                VALUES (?, ?, NOW(), ?)
            ");
            $stmt_penal->execute([$admin_id, $id_etudiant, $date_desactivation]);

            // update statut de l'étudiant à 'bloque'
            $stmt_update_etudiant = $pdo->prepare("
                UPDATE etudiant 
                SET statut = 'bloque' 
                WHERE id_etudiant = ?
            ");
            $stmt_update_etudiant->execute([$id_etudiant]);
            
            // C. Terminer la transaction
            $pdo->commit();
            
            $duree_msg = ($date_desactivation === NULL) ? "définitivement" : "jusqu'au " . date('d/m/Y', strtotime($date_desactivation));
            $success = "L'étudiant #$id_etudiant a été bloqué $duree_msg.";
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erreur lors de la pénalisation: " . $e->getMessage());
        $error = "Erreur SQL : Impossible d'appliquer la pénalité.";
    }
}

//  RÉACTIVER UN ÉTUDIANT (Lever la pénalité)
if (isset($_POST['action']) && $_POST['action'] === 'reactiver_etudiant') {
    $id_etudiant = (int)$_POST['id_etudiant'];

    try {
        $pdo->beginTransaction();

        // Supprimer l'enregistrement de pénalisation
        $stmt_delete = $pdo->prepare("
            DELETE FROM penalisation 
            WHERE id_etudiant = ?
        ");
        $stmt_delete->execute([$id_etudiant]);

        // update statut de l'étudiant à 'actif'
        $stmt_update_etudiant = $pdo->prepare("
            UPDATE etudiant 
            SET statut = 'actif' 
            WHERE id_etudiant = ? AND statut = 'bloque'
        ");
        $stmt_update_etudiant->execute([$id_etudiant]);

        $pdo->commit();
        $success = "L'étudiant #$id_etudiant a été réactivé et peut à nouveau emprunter.";

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erreur lors de la réactivation: " . $e->getMessage());
        $error = "Erreur SQL : Impossible de réactiver l'étudiant.";
    }
}


// STATISTIQUES
$stmt_stats = $pdo->query("
    SELECT 
        COUNT(*) as total_penalises,
        SUM(CASE WHEN date_desactivation IS NULL THEN 1 ELSE 0 END) as permanents,
        SUM(CASE WHEN date_desactivation IS NOT NULL AND date_desactivation > CURDATE() THEN 1 ELSE 0 END) as temporaires,
        SUM(CASE WHEN date_desactivation IS NOT NULL AND date_desactivation <= CURDATE() THEN 1 ELSE 0 END) as expires
    FROM penalisation
");
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// RÉCUPÉRATION DES ÉTUDIANTS PÉNALISÉS

// Récupérer tous les étudiants qui ont le statut 'bloque'
try {
    $stmt_penalises = $pdo->prepare("
        SELECT 
            p.id_penalisation,
            e.id_etudiant,
            e.nom,
            e.prenom,
            e.email,
            p.date_desactivation,
            p.date_penalisation,
            a.nom AS admin_nom,
            a.prenom AS admin_prenom
        FROM etudiant e
        LEFT JOIN penalisation p ON e.id_etudiant = p.id_etudiant
        LEFT JOIN admin a ON p.id_admin = a.id_admin
        WHERE e.statut = 'bloque'
        ORDER BY p.date_penalisation DESC
    ");
    $stmt_penalises->execute();
    $etudiants_penalises = $stmt_penalises->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur récupération pénalisés: " . $e->getMessage());
    $etudiants_penalises = [];
    $error = "Erreur lors du chargement de la liste des pénalisations.";
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Pénalisations - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin/penalisation.css">
</head>
<body>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1><i class="fas fa-gavel"></i> Gestion des Pénalisations</h1>
                <p>Gérer les blocages et sanctions des étudiants</p>
            </div>
            <a href="contact.php" class="btn-danger-custom">
                <i class="fas fa-arrow-left"></i> Retour aux contacts
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-custom">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-custom">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="card-custom" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700;"><?= $stats['total_penalises'] ?></div>
                        <div style="font-size: 0.9rem; opacity: 0.9;">Total pénalisés</div>
                    </div>
                </div>
            </div>
            <div class="card-custom" style="background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%); color: white;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <i class="fas fa-infinity"></i>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700;"><?= $stats['permanents'] ?></div>
                        <div style="font-size: 0.9rem; opacity: 0.9;">Permanents</div>
                    </div>
                </div>
            </div>
            <div class="card-custom" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700;"><?= $stats['temporaires'] ?></div>
                        <div style="font-size: 0.9rem; opacity: 0.9;">Temporaires actifs</div>
                    </div>
                </div>
            </div>
            <div class="card-custom" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <i class="fas fa-hourglass-end"></i>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700;"><?= $stats['expires'] ?></div>
                        <div style="font-size: 0.9rem; opacity: 0.9;">À réactiver</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom">
            <div class="card-header-custom">
                <h5><i class="fas fa-ban"></i> Appliquer une nouvelle Pénalité</h5>
            </div>
            <div>
                <?php if ($etudiant_preselect): ?>
                    <div style="background: #e5edff; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid var(--primary);">
                        <div style="font-weight: 600; color: var(--primary); margin-bottom: 5px;">
                            <i class="fas fa-user"></i> Étudiant sélectionné
                        </div>
                        <div style="color: var(--text);">
                            <strong><?= htmlspecialchars($etudiant_preselect['prenom'] . ' ' . $etudiant_preselect['nom']) ?></strong><br>
                            <small>
                                <i class="fas fa-id-card"></i> <?= htmlspecialchars($etudiant_preselect['numero_etudiant']) ?> |
                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($etudiant_preselect['email']) ?>
                            </small>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="penaliser_etudiant">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="id_etudiant" class="form-label">Sélectionner l'étudiant *</label>
                            <?php if ($etudiant_preselect): ?>
                                <input type="text" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($etudiant_preselect['prenom'] . ' ' . $etudiant_preselect['nom'] . ' (' . $etudiant_preselect['numero_etudiant'] . ')') ?>"
                                       readonly>
                                <input type="hidden" name="id_etudiant" value="<?= $etudiant_preselect['id_etudiant'] ?>">
                            <?php else: ?>
                                <select name="id_etudiant" class="form-select" id="id_etudiant" required>
                                    <option value="">-- Choisir un étudiant --</option>
                                    <?php foreach ($tous_etudiants as $etu): ?>
                                        <option value="<?= $etu['id_etudiant'] ?>">
                                            <?= htmlspecialchars($etu['prenom'] . ' ' . $etu['nom'] . ' - ' . $etu['numero_etudiant'] . ' - ' . $etu['email']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text" style="color: var(--muted);">Ou sélectionnez depuis la <a href="contact.php" style="color: var(--primary);">liste des contacts</a></small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-5">
                            <label for="duree_ban_jours" class="form-label">Durée de la Pénalité *</label>
                            <select name="duree_ban_jours" class="form-select" id="duree_ban_jours" required>
                                <option value="1">1 jour</option>
                                <option value="3">3 jours</option>
                                <option value="7">7 jours (1 semaine)</option>
                                <option value="15">15 jours</option>
                                <option value="30" selected>30 jours (1 mois)</option>
                                <option value="90">90 jours (3 mois)</option>
                                <option value="180">180 jours (6 mois)</option>
                                <option value="365">1 an</option>
                                <option value="0">Bloquer (Permanent)</option>
                            </select>
                        </div>

                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-danger-custom w-100">
                                <i class="fas fa-gavel"></i> Pénaliser
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <h2 class="section-title"><i class="fas fa-list-alt"></i> Étudiants Actuellement Pénalisés</h2>

        <?php if (empty($etudiants_penalises)): ?>
            <div class="empty-state card-custom">
                <i class="fas fa-user-check"></i>
                <p>Aucun étudiant n'est actuellement bloqué.</p>
            </div>
        <?php else: ?>
            <div class="table-custom">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom & Prénom</th>
                            <th>Email</th>
                            <th>Date de Pénalisation</th>
                            <th>Durée</th>
                            <th>Date de Fin</th>
                            <th>Pénalisé par</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($etudiants_penalises as $penalise): 
                            $date_desactivation = $penalise['date_desactivation'] ?? null;
                            $date_penalisation = $penalise['date_penalisation'] ?? null;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($penalise['id_etudiant']); ?></td>
                                <td><?php echo htmlspecialchars($penalise['prenom'] . ' ' . $penalise['nom']); ?></td>
                                <td><?php echo htmlspecialchars($penalise['email']); ?></td>
                                <td>
                                    <?php 
                                    if ($date_penalisation) {
                                        echo date('d/m/Y', strtotime($date_penalisation));
                                    } else {
                                        echo '<span class="badge-secondary">N/A</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if (!$date_penalisation) {
                                        echo '<span class="badge-secondary">Sans pénalité</span>';
                                    } elseif (is_null($date_desactivation)) {
                                        echo '<span class="badge-permanent"><i class="fas fa-ban"></i> BLOQUÉ</span>';
                                    } else {
                                        $date_pen = new DateTime($date_penalisation);
                                        $date_fin = new DateTime($date_desactivation);
                                        $interval = $date_pen->diff($date_fin);
                                        
                                        if ($interval->days == 1) {
                                            echo '<span class="badge-temporary">1 jour</span>';
                                        } elseif ($interval->days < 30) {
                                            echo '<span class="badge-temporary">' . $interval->days . ' jours</span>';
                                        } elseif ($interval->days < 365) {
                                            $mois = round($interval->days / 30);
                                            echo '<span class="badge-temporary">' . $mois . ' mois</span>';
                                        } else {
                                            $ans = round($interval->days / 365);
                                            echo '<span class="badge-temporary">' . $ans . ' an' . ($ans > 1 ? 's' : '') . '</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (!$date_desactivation): ?>
                                        <span class="badge-permanent">
                                            <i class="fas fa-infinity"></i> PERMANENT
                                        </span>
                                    <?php elseif (strtotime($date_desactivation) < time()): ?>
                                        <span class="badge-expired">
                                            <i class="fas fa-clock"></i> Expiré
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-temporary">
                                            <i class="fas fa-calendar"></i> 
                                            <?php echo date('d/m/Y', strtotime($date_desactivation)); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $penalise['admin_nom'] ? htmlspecialchars($penalise['admin_prenom'] . ' ' . $penalise['admin_nom']) : '<span class="badge-secondary">N/A</span>'; ?>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Confirmer la réactivation de l\'étudiant #<?php echo $penalise['id_etudiant']; ?> ?')">
                                        <input type="hidden" name="action" value="reactiver_etudiant">
                                        <input type="hidden" name="id_etudiant" value="<?php echo $penalise['id_etudiant']; ?>">
                                        <button type="submit" class="btn btn-success-custom btn-sm">
                                            <i class="fas fa-check-circle"></i> Réactiver
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // fermer automatiquement les messages d'alerte après 5 secondes
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-custom');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
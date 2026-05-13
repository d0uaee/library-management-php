<?php
/**
 * GESTION DES EMPRUNTS*/

require_once '../../config/config.php';
require_once '../../utils/security.php';
require_once '../../views/admin/panel.php';

// Vérifier que l'utilisateur est un admin
requireAdmin();

// Variables pour les messages
$success = '';
$error = '';

// TRAITEMENT DES ACTIONS

// VALIDER UNE DEMANDE D'EMPRUNT
if (isset($_POST['action']) && $_POST['action'] === 'valider_demande') {
    $id_demande = (int)$_POST['id_demande'];
    
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT d.*, l.livre_ds_bib, l.titre
            FROM demande_emprunt d
            JOIN livre l ON d.id_livre = l.id_livre
            WHERE d.id_demande = ?
        ");
        $stmt->execute([$id_demande]);
        $demande = $stmt->fetch();
        
        if (!$demande) {
            $error = "Demande introuvable.";
            $pdo->rollBack();
        } elseif ($demande['livre_ds_bib'] <= 0) {
            $error = "Le livre '{$demande['titre']}' n'est plus disponible pour le moment.";
            $pdo->rollBack();
            
        } else {
            // Vérifier la limite d'emprunts en cours pour l'étudiant
            $maxEmprunts = defined('MAX_EMPRUNTS') ? MAX_EMPRUNTS : 3; // défaut 3
            $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM emprunt WHERE id_etudiant = ? AND statut IN ('en_cours','en_retard')");
            $stmt_count->execute([$demande['id_etudiant']]);
            $nb_en_cours = (int)$stmt_count->fetchColumn();

            if ($nb_en_cours >= $maxEmprunts) {
                $error = "Limite atteinte: l'étudiant possède déjà {$nb_en_cours}/{$maxEmprunts} emprunt(s) en cours. Validation impossible.";
                $pdo->rollBack();
                return; 
            }
            // Decrementer le nombre de livres disponibles
            $stmt_update_livre = $pdo->prepare("
                UPDATE livre 
                SET livre_ds_bib = livre_ds_bib - 1 
                WHERE id_livre = ? AND livre_ds_bib > 0
            ");
            $stmt_update_livre->execute([$demande['id_livre']]);

            if ($stmt_update_livre->rowCount() == 0) {
                throw new Exception("Erreur lors de la décrémentation des disponibilités. Annulation.");
            }

            // 2. Creer l'entree dans la table emprunt
            $date_emprunt = date('Y-m-d');
            $date_retour_prevue = date('Y-m-d', strtotime('+15 days'));
            $stmt_emprunt = $pdo->prepare("
                INSERT INTO emprunt (id_etudiant, id_livre,date_emprunt, date_retour_prevue, statut) 
                VALUES (?, ?, ?, ?, 'en_cours')
            ");
            $stmt_emprunt->execute([
                $demande['id_etudiant'], 
                $demande['id_livre'], 
                $date_emprunt, 
                $date_retour_prevue
            ]);

            // Supprimer la demande d emprunt valide
            $stmt_delete_demande = $pdo->prepare("
                DELETE FROM demande_emprunt WHERE id_demande = ?
            ");
            $stmt_delete_demande->execute([$id_demande]);
            
            //Notification a l'etudiant
            $message = "Votre demande d'emprunt pour '{$demande['titre']}' a été VALIDÉE. Date de retour prévue: " . date('d/m/Y', strtotime($date_retour_prevue));
            $stmt_notif = $pdo->prepare("
                INSERT INTO notification (id_etudiant, type, message) 
                VALUES (?, 'alerte', ?)
            ");
            $stmt_notif->execute([$demande['id_etudiant'], $message]);

            $success = "Emprunt validé et enregistré. Le livre '{$demande['titre']}' a été retiré de l'inventaire.";
            $pdo->commit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur lors de la validation de l'emprunt : " . $e->getMessage();
    }
}

// REFUSER UNE DEMANDE D'EMPRUNT
if (isset($_POST['action']) && $_POST['action'] === 'refuser_demande') {
    $id_demande = (int)$_POST['id_demande'];
    
    try {
        $pdo->beginTransaction();

        // Récupérer les infos de la demande pour la notification
        $stmt_select = $pdo->prepare("
            SELECT d.id_etudiant, d.id_livre, l.titre 
            FROM demande_emprunt d 
            JOIN livre l ON d.id_livre = l.id_livre 
            WHERE d.id_demande = ?
        ");
        $stmt_select->execute([$id_demande]);
        $demande = $stmt_select->fetch();

        if ($demande) {
            $id_livre = $demande['id_livre'];

            // Supprimer la demande
            $stmt_delete = $pdo->prepare("DELETE FROM demande_emprunt WHERE id_demande = ?");
            $stmt_delete->execute([$id_demande]);

            //(nombre_disponibles + 1)
            $stmt_release = $pdo->prepare("
                UPDATE livre
                SET nombre_disponibles = LEAST(nombre_disponibles + 1, nombre_exemplaires)
                WHERE id_livre = ?
            ");
            $stmt_release->execute([$id_livre]);

            // Notification à l'etudiant dont la demande est refusee
            $message = "Votre demande d'emprunt pour '{$demande['titre']}' a été REFUSÉE par l'administration.";
            $stmt_notif = $pdo->prepare("
                INSERT INTO notification (id_etudiant, type, message) 
                VALUES (?, 'alerte', ?)
            ");
            $stmt_notif->execute([$demande['id_etudiant'], $message]);

            // Verifier et traiter la prochaine reservation en attente pour ce livre
            $stmt_reservation = $pdo->prepare("
                SELECT r.id_reservation, r.id_etudiant, l.titre
                FROM reservation r
                JOIN livre l ON r.id_livre = l.id_livre
                WHERE r.id_livre = ? AND r.statut = 'en_attente'
                ORDER BY r.date_reservation ASC
                LIMIT 1
            ");

            $stmt_reservation->execute([$id_livre]); 
            $reservation = $stmt_reservation->fetch(PDO::FETCH_ASSOC);

            if ($reservation) {
                $id_etudiant_reserve = $reservation['id_etudiant'];
                $id_reservation = $reservation['id_reservation'];
                $titre_livre = $reservation['titre'];

                //Créer une nouvelle demande d'emprunt pour l'étudiant en tête de file
                $stmt_new_demande = $pdo->prepare("
                    INSERT INTO demande_emprunt (id_etudiant, id_livre, statut) 
                    VALUES (?, ?, 'en_attente')
                ");
                $stmt_new_demande->execute([$id_etudiant_reserve, $id_livre]);

                //Bloquer l'exemplaire pour la demande générée (nombre_disponibles - 1)
                $stmt_lock = $pdo->prepare("
                    UPDATE livre
                    SET nombre_disponibles = nombre_disponibles - 1
                    WHERE id_livre = ? AND nombre_disponibles > 0
                ");
                $stmt_lock->execute([$id_livre]);

                //Envoyer une notification a l etudiant
                $message_notif = "Le livre '{$titre_livre}' que vous avez réservé est maintenant disponible ! Une demande d'emprunt a été créée en votre nom.";
                $stmt_notif_reserve = $pdo->prepare("
                    INSERT INTO notification (id_etudiant, type, message) 
                    VALUES (?, 'alerte', ?)
                ");
                $stmt_notif_reserve->execute([$id_etudiant_reserve, $message_notif]);

                //Supprimer la réservation traite
                $stmt_delete_reservation = $pdo->prepare("
                    DELETE FROM reservation WHERE id_reservation = ?
                ");
                $stmt_delete_reservation->execute([$id_reservation]);

                // Message de succès adapté
                $success = "Demande d'emprunt refusée. La première réservation en attente pour '{$titre_livre}' a été promue en nouvelle demande d'emprunt.";

            } else {
                $success = "Demande d'emprunt refusée et supprimée. Le livre est de nouveau disponible dans le catalogue.";
            }

            $pdo->commit();
        } else {
            $pdo->rollBack();
            $error = "Demande introuvable.";
        }
    } catch (Exception $e) { 
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Erreur lors du refus de la demande : " . $e->getMessage();
    }
}

// ENREGISTRER LE RETOUR D'UN LIVRE 
if (isset($_POST['action']) && $_POST['action'] === 'enregistrer_retour') {
    $id_emprunt = (int)$_POST['id_emprunt'];
    $date_retour_effective = date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        //Recuperer l'emprunt et l'ID du livre
        $stmt = $pdo->prepare("SELECT id_livre, id_etudiant, date_retour_prevue FROM emprunt WHERE id_emprunt = ? AND statut IN ('en_cours', 'en_retard')");
        $stmt->execute([$id_emprunt]);
        $emprunt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$emprunt) {
            throw new Exception("Emprunt en cours introuvable.");
        }

        $id_livre = $emprunt['id_livre'];
        $id_etudiant_retour = $emprunt['id_etudiant'];
        $is_late = strtotime($date_retour_effective) > strtotime($emprunt['date_retour_prevue']);

        //Mettre à jour l emprunt
        $stmt_update_emprunt = $pdo->prepare("
            UPDATE emprunt 
            SET statut = 'retourne', date_retour_effective = ? 
            WHERE id_emprunt = ?
        ");
        $stmt_update_emprunt->execute([$date_retour_effective, $id_emprunt]);

        // Incrémenter le nombre de livres disponibles
        $stmt_update_livre = $pdo->prepare("
            UPDATE livre 
            SET nombre_disponibles = nombre_disponibles + 1, livre_ds_bib = livre_ds_bib + 1
            WHERE id_livre = ?
        ");
        $stmt_update_livre->execute([$id_livre]);

        //Verifier et traiter les réservations en attente
        $stmt_reservation = $pdo->prepare("
            SELECT r.id_reservation, r.id_etudiant, l.titre
            FROM reservation r
            JOIN livre l ON r.id_livre = l.id_livre
            WHERE r.id_livre = ? AND r.statut = 'en_attente'
            ORDER BY r.date_reservation ASC
            LIMIT 1
        ");
        $stmt_reservation->execute([$id_livre]);
        $reservation = $stmt_reservation->fetch(PDO::FETCH_ASSOC);

        if ($reservation) {
            $id_etudiant_reserve = $reservation['id_etudiant'];
            $id_reservation = $reservation['id_reservation'];
            $titre_livre = $reservation['titre'];

            // Creer une nouvelle demande d'emprunt pour l'etudiant
            $stmt_new_demande = $pdo->prepare("
                INSERT INTO demande_emprunt (id_etudiant, id_livre, statut) 
                VALUES (?, ?, 'en_attente')
            ");
            $stmt_new_demande->execute([$id_etudiant_reserve, $id_livre]);

            // Decrementer
            $stmt_lock = $pdo->prepare("
                UPDATE livre
                SET nombre_disponibles = nombre_disponibles - 1
                WHERE id_livre = ? AND nombre_disponibles > 0
            ");
            $stmt_lock->execute([$id_livre]);

            // Envoyer une notification à l'étudiant
            $message_notif = "Le livre '{$titre_livre}' que vous avez réservé est maintenant disponible ! Une demande d'emprunt a été créée en votre nom.";
            $stmt_notif = $pdo->prepare("
                INSERT INTO notification (id_etudiant, type, message) 
                VALUES (?, 'alerte', ?)
            ");
            $stmt_notif->execute([$id_etudiant_reserve, $message_notif]);

            //Supprimer la réservation traite
            $stmt_delete_reservation = $pdo->prepare("
                DELETE FROM reservation WHERE id_reservation = ?
            ");
            $stmt_delete_reservation->execute([$id_reservation]);

            $success = "Retour du livre enregistré et la réservation de l'étudiant #{$id_etudiant_reserve} a été transformée en demande d'emprunt.";

        } else {
        // Aucune reservation en attente
            $success = "Retour du livre enregistré. Le livre est de nouveau disponible dans le catalogue.";
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur lors de l'enregistrement du retour : " . $e->getMessage();
    }
}

// la recuperation des données:


//Demandes d'emprunt en attente
$stmt_demandes = $pdo->query("
    SELECT d.*, e.nom, e.prenom, l.titre, l.auteur 
    FROM demande_emprunt d
    JOIN etudiant e ON d.id_etudiant = e.id_etudiant
    JOIN livre l ON d.id_livre = l.id_livre
    WHERE d.statut = 'en_attente'
    ORDER BY d.date_demande ASC
");
$demandes = $stmt_demandes->fetchAll(PDO::FETCH_ASSOC);

// Emprunts en cours 
$stmt_all_emprunts = $pdo->query("
    SELECT em.*, e.nom, e.prenom, e.email, l.titre, l.auteur 
    FROM emprunt em
    JOIN etudiant e ON em.id_etudiant = e.id_etudiant
    JOIN livre l ON em.id_livre = l.id_livre
    WHERE em.statut IN ('en_cours', 'en_retard')
    ORDER BY em.date_retour_prevue ASC
");
$all_emprunts = $stmt_all_emprunts->fetchAll(PDO::FETCH_ASSOC);

$emprunts_a_jour = [];
$emprunts_en_retard = [];
$current_time = time();

foreach ($all_emprunts as $emprunt) {

    if (strtotime($emprunt['date_retour_prevue']) < $current_time) {
        $emprunt['is_late'] = true; 
        $emprunts_en_retard[] = $emprunt;
    } else {
        $emprunt['is_late'] = false; 
        $emprunts_a_jour[] = $emprunt;
    }
}

$emprunts_en_cours = $all_emprunts; 


//Historique des emprunts retournés
$stmt_historique = $pdo->query("
    SELECT em.*, e.nom, e.prenom, l.titre, l.auteur 
    FROM emprunt em
    JOIN etudiant e ON em.id_etudiant = e.id_etudiant
    JOIN livre l ON em.id_livre = l.id_livre
    WHERE em.statut = 'retourne'
    ORDER BY em.date_retour_effective DESC
");
$historique = $stmt_historique->fetchAll(PDO::FETCH_ASSOC);


//onglet actif
$active_tab = $_GET['tab'] ?? 'demandes';
$valid_tabs = ['demandes', 'en_cours', 'historique'];
if (!in_array($active_tab, $valid_tabs)) {
    $active_tab = 'demandes';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Emprunts - Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin/emprunts.css">
    
</head>
<body>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h1 style="margin:0; font-weight:700;">Gestion des emprunts</h1>
            <p style="margin:4px 0 0 0; color: var(--muted);">Suivi des demandes, prêts en cours et historique</p>
        </div>
        <div style="color: var(--muted); font-weight:600;">
            <i class="fas fa-user-shield"></i>
            <?php echo htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-icon"><i class="fas fa-inbox"></i></div>
            <div class="stat-value"><?php echo count($demandes); ?></div>
            <div class="stat-label">Demandes en attente</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
            <div class="stat-value"><?php echo count($emprunts_en_cours); ?></div>
            <div class="stat-label">Emprunts en cours</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-value"><?php echo count($emprunts_en_retard); ?></div>
            <div class="stat-label">Emprunts en retard</div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon"><i class="fas fa-history"></i></div>
            <div class="stat-value"><?php echo count($historique); ?></div>
            <div class="stat-label">Retours enregistrés</div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show alert-custom" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show alert-custom" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="tabs-container">
        <div class="tab-menu">
            <button class="btn tab-btn <?php echo $active_tab === 'demandes' ? 'active' : ''; ?>" onclick="showTab(event, 'demandes')">
                <i class="fas fa-list-alt"></i> Demandes (<?php echo count($demandes); ?>)
            </button>
            <button class="btn tab-btn <?php echo $active_tab === 'en_cours' ? 'active' : ''; ?>" onclick="showTab(event, 'en_cours')">
                <i class="fas fa-hand-holding-usd"></i> En cours (<?php echo count($emprunts_en_cours); ?>)
            </button>
            <button class="btn tab-btn <?php echo $active_tab === 'historique' ? 'active' : ''; ?>" onclick="showTab(event, 'historique')">
                <i class="fas fa-history"></i> Historique (<?php echo count($historique); ?>)
            </button>
        </div>

        <div id="tab-demandes" class="tab-content <?php echo $active_tab === 'demandes' ? 'active' : ''; ?>">
            <div class="section-title"><i class="fas fa-inbox"></i> Demandes en attente</div>
            <?php if (count($demandes) > 0): ?>
                <?php foreach ($demandes as $demande): ?>
                    <div class="list-card">
                        <div class="avatar"><i class="fas fa-user"></i></div>
                        <div style="flex:1;">
                            <h5><?php echo htmlspecialchars($demande['titre']); ?></h5>
                            <p>De <?php echo htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']); ?> — <?php echo htmlspecialchars($demande['auteur']); ?></p>
                            <div class="list-meta">
                                <span><i class="fas fa-clock"></i><?php echo date('d/m/Y H:i', strtotime($demande['date_demande'])); ?></span>
                                <span class="badge-soft info">En attente</span>
                            </div>
                            <div class="list-actions">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="valider_demande">
                                    <input type="hidden" name="id_demande" value="<?php echo $demande['id_demande']; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary-grad btn-pill">
                                        <i class="fas fa-check-circle"></i> Valider
                                    </button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="refuser_demande">
                                    <input type="hidden" name="id_demande" value="<?php echo $demande['id_demande']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger-soft btn-pill">
                                        <i class="fas fa-times"></i> Refuser
                                    </button>
                                </form>
                                <a href="export_pdf.php?action=demande&id=<?php echo $demande['id_demande']; ?>" target="_blank" class="btn btn-sm btn-info-soft btn-pill">
                                    <i class="fas fa-file-pdf"></i> Voir reçu
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-check"></i>
                    <p>Aucune demande d'emprunt en attente pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-en_cours" class="tab-content <?php echo $active_tab === 'en_cours' ? 'active' : ''; ?>">
            <div class="section-title" style="color:#c3263a;"><i class="fas fa-exclamation-triangle"></i> Emprunts en retard (<?php echo count($emprunts_en_retard); ?>)</div>
            <?php if (count($emprunts_en_retard) > 0): ?>
                <?php foreach ($emprunts_en_retard as $emprunt): ?>
                    <div class="list-card" style="border-left:4px solid #f5576c;">
                        <div class="avatar" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div style="flex:1;">
                            <h5 style="color:#c3263a;"><?php echo htmlspecialchars($emprunt['titre']); ?></h5>
                            <p><?php echo htmlspecialchars($emprunt['prenom'] . ' ' . $emprunt['nom']); ?> — <?php echo htmlspecialchars($emprunt['auteur']); ?></p>
                            <div class="list-meta">
                                <span><i class="fas fa-calendar-check"></i>Emprunté le <?php echo date('d/m/Y', strtotime($emprunt['date_emprunt'])); ?></span>
                                <span><i class="fas fa-calendar-times"></i>Retour prévu <?php echo date('d/m/Y', strtotime($emprunt['date_retour_prevue'])); ?></span>
                                <span class="badge-soft danger">En retard</span>
                            </div>
                            <div class="list-actions">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="enregistrer_retour">
                                    <input type="hidden" name="id_emprunt" value="<?php echo $emprunt['id_emprunt']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success-soft btn-pill" onclick="return confirm('Confirmez-vous le retour du livre ? Cette action est irréversible et peut déclencher une nouvelle demande d\'emprunt via réservation.')">
                                        <i class="fas fa-undo"></i> Enregistrer retour
                                    </button>
                                </form>
                                <a href="messagerie.php?id_etudiant=<?php echo $emprunt['id_etudiant']; ?>" class="btn btn-sm btn-warning-soft btn-pill">
                                    <i class="fas fa-bell"></i> Envoyer rappel
                                </a>
                                <form method="POST" action="traiter-prolongation.php" class="d-inline">
                                    <input type="hidden" name="id_emprunt" value="<?php echo $emprunt['id_emprunt']; ?>">
                                    <input type="date" name="nouvelle_date_retour" class="form-control form-control-sm d-inline w-auto" 
                                        value="<?php echo date('Y-m-d', strtotime('+30 days', strtotime($emprunt['date_retour_prevue']))); ?>"
                                        min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                        title="Choisissez la nouvelle date de retour prévue." required>
                                    <button type="submit" class="btn btn-sm btn-info-soft btn-pill">
                                        <i class="fas fa-history"></i> Prolonger
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>Aucun emprunt en retard.</p>
                </div>
            <?php endif; ?>

            <div class="section-title" style="margin-top:25px;"><i class="fas fa-book"></i> Emprunts à jour (<?php echo count($emprunts_a_jour); ?>)</div>
            <?php if (count($emprunts_a_jour) > 0): ?>
                <?php foreach ($emprunts_a_jour as $emprunt): ?>
                    <div class="list-card" style="border-left:4px solid #38ef7d;">
                        <div class="avatar" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                            <i class="fas fa-check"></i>
                        </div>
                        <div style="flex:1;">
                            <h5><?php echo htmlspecialchars($emprunt['titre']); ?></h5>
                            <p><?php echo htmlspecialchars($emprunt['prenom'] . ' ' . $emprunt['nom']); ?> — <?php echo htmlspecialchars($emprunt['auteur']); ?></p>
                            <div class="list-meta">
                                <span><i class="fas fa-calendar-check"></i>Emprunté le <?php echo date('d/m/Y', strtotime($emprunt['date_emprunt'])); ?></span>
                                <span><i class="fas fa-calendar-times"></i>Retour prévu <?php echo date('d/m/Y', strtotime($emprunt['date_retour_prevue'])); ?></span>
                                <span class="badge-soft success">En cours</span>
                            </div>
                            <div class="list-actions">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="enregistrer_retour">
                                    <input type="hidden" name="id_emprunt" value="<?php echo $emprunt['id_emprunt']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success-soft btn-pill" onclick="return confirm('Confirmez-vous le retour du livre ? Cette action est irréversible et peut déclencher une nouvelle demande d\'emprunt via réservation.')">
                                        <i class="fas fa-undo"></i> Enregistrer retour
                                    </button>
                                </form>
                                <form method="POST" action="traiter-prolongation.php" class="d-inline">
                                    <input type="hidden" name="id_emprunt" value="<?php echo $emprunt['id_emprunt']; ?>">
                                    <input type="date" name="nouvelle_date_retour" class="form-control form-control-sm d-inline w-auto" 
                                        value="<?php echo date('Y-m-d', strtotime('+30 days', strtotime($emprunt['date_retour_prevue']))); ?>"
                                        min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                        title="Choisissez la nouvelle date de retour prévue." required>
                                    <button type="submit" class="btn btn-sm btn-info-soft btn-pill">
                                        <i class="fas fa-history"></i> Prolonger
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>Aucun emprunt à jour en cours.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div id="tab-historique" class="tab-content <?php echo $active_tab === 'historique' ? 'active' : ''; ?>">
            <div class="section-title"><i class="fas fa-history"></i> Historique des retours</div>
            <?php if (count($historique) > 0): ?>
                <div class="table-responsive mt-3">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Livre</th>
                                <th>Étudiant</th>
                                <th>Emprunté le</th>
                                <th>Retour prévu</th>
                                <th>Retour effectif</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historique as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['titre']); ?></strong><br>
                                        <span style="color: var(--muted); font-size:0.85rem;">par <?php echo htmlspecialchars($item['auteur']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['prenom'] . ' ' . $item['nom']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($item['date_emprunt'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($item['date_retour_prevue'])); ?></td>
                                    <td>
                                        <?php 
                                            $return_date = strtotime($item['date_retour_effective']);
                                            $expected_date = strtotime($item['date_retour_prevue']);
                                            $is_late = $return_date > $expected_date;
                                        ?>
                                        <span class="<?php echo $is_late ? 'text-danger fw-bold' : 'text-success'; ?>">
                                            <?php echo date('d/m/Y', $return_date); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-soft <?php echo $is_late ? 'danger' : 'success'; ?>">Retourné</span>
                                        <?php if ($is_late): ?><span class="badge-soft warning">Retard</span><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <p>L'historique des retours est vide.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function showTab(event, tabName) {
        // Cacher tous les contenus
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Désactiver tous les boutons d'onglets
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Activer l'onglet et son contenu
        document.getElementById('tab-' + tabName).classList.add('active');
        event.target.classList.add('active');
        
        // Mettre à jour l'URL pour persister l'onglet
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
    }
</script>
</body>
</html>
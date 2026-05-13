<?php
/**
 * MES EMPRUNTS - ESPACE ÉTUDIANT
 * 
 * Cette page affiche :
 * - Les demandes d'emprunt en attente
 * - Les emprunts en cours
 * - L'historique des emprunts
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';

// Vérifier que l'utilisateur est un étudiant connecté
requireEtudiant();

$id_etudiant = $_SESSION['user_id'];

// TRAITEMENT DE L'ANNULATION D'UNE DEMANDE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_demande'])) {
    $id_demande = (int) cleanInput($_POST['id_demande']);
    
    try {
        $pdo->beginTransaction();

        // Vérifier que la demande appartient bien à l'étudiant et la récupérer
        $stmt = $pdo->prepare("SELECT * FROM demande_emprunt WHERE id_demande = ? AND id_etudiant = ?");
        $stmt->execute([$id_demande, $id_etudiant]);
        $demande = $stmt->fetch();
        
        if ($demande) {
            $id_livre = $demande['id_livre'];
            $titre_livre_annule = $demande['titre'] ?? 'Livre non spécifié'; // Assurez-vous que 'titre' est récupérable si nécessaire

            // Supprimer la demande
            $stmt_delete = $pdo->prepare("DELETE FROM demande_emprunt WHERE id_demande = ?");
            $stmt_delete->execute([$id_demande]);

            // 3. Restituer l'exemplaire réservé par la demande (nombre_disponibles + 1)
            $stmtLivre = $pdo->prepare("
                UPDATE livre
                SET nombre_disponibles = LEAST(nombre_disponibles + 1, nombre_exemplaires)
                WHERE id_livre = ?
            ");
            $stmtLivre->execute([$id_livre]);

            // --- Logique de promotion de la réservation suivante ---

            // 4. Vérifier et traiter la prochaine réservation en attente pour ce livre
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
                $titre_livre_reserve = $reservation['titre'];

                // 5.a. Créer une nouvelle demande d'emprunt pour l'étudiant en tête de file
                $stmt_new_demande = $pdo->prepare("
                    INSERT INTO demande_emprunt (id_etudiant, id_livre, statut) 
                    VALUES (?, ?, 'en_attente')
                ");
                $stmt_new_demande->execute([$id_etudiant_reserve, $id_livre]);

                // 5.b. Bloquer l'exemplaire pour la demande générée (nombre_disponibles - 1)
                $stmt_lock = $pdo->prepare("
                    UPDATE livre
                    SET nombre_disponibles = nombre_disponibles - 1
                    WHERE id_livre = ? AND nombre_disponibles > 0
                ");
                $stmt_lock->execute([$id_livre]);

                if ($stmt_lock->rowCount() === 0) {
                    // C'est une erreur critique, car nous venions de libérer un exemplaire.
                    throw new Exception("Erreur de cohérence: Impossible de réserver le livre pour l'étudiant #{$id_etudiant_reserve}.");
                }

                // 5.c. Envoyer une notification à l'étudiant réservataire
                $message_notif_reserve = "Le livre '{$titre_livre_reserve}' que vous avez réservé est maintenant disponible ! Une demande d'emprunt a été créée en votre nom.";
                $stmt_notif_reserve = $pdo->prepare("
                    INSERT INTO notification (id_etudiant, type, message) 
                    VALUES (?, 'alerte', ?)
                ");
                $stmt_notif_reserve->execute([$id_etudiant_reserve, $message_notif_reserve]);

                // 5.d. Supprimer la réservation traitée
                $stmt_delete_reservation = $pdo->prepare("
                    DELETE FROM reservation WHERE id_reservation = ?
                ");
                $stmt_delete_reservation->execute([$id_reservation]);

                $success_message = "Votre demande d'emprunt a été annulée. L'exemplaire a été immédiatement réaffecté à l'étudiant suivant.";
            } else {
                $success_message = "Votre demande d'emprunt a été annulée avec succès. L'exemplaire est de nouveau disponible dans le catalogue.";
            }

            // 6. Finalisation
            $pdo->commit();
            $_SESSION['success_message'] = $success_message;
        } else {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Demande introuvable ou non autorisée.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = "Erreur lors de l'annulation de la demande.";
    }
    
    header('Location: mes-emprunts.php');
    exit;
}

require_once '../../views/etudiant/nav-etudiant.php';


// Récupération des messages de session
$error = $_SESSION['error_message'] ?? '';
$success = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message'], $_SESSION['success_message']);

// DEMANDES EN ATTENTE
$stmt = $pdo->prepare("
    SELECT de.*, l.titre, l.auteur, l.image_couverture, c.nom as categorie_nom
    FROM demande_emprunt de
    JOIN livre l ON de.id_livre = l.id_livre
    LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
    WHERE de.id_etudiant = ? 
    AND de.statut = 'en_attente'
    ORDER BY de.date_demande DESC
");
$stmt->execute([$id_etudiant]);
$demandes_attente = $stmt->fetchAll();

// EMPRUNTS EN COURS
$stmt = $pdo->prepare("
    SELECT e.*, l.titre, l.auteur, l.image_couverture, c.nom as categorie_nom,
           DATEDIFF(e.date_retour_prevue, CURDATE()) as jours_restants
    FROM emprunt e
    JOIN livre l ON e.id_livre = l.id_livre
    LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
    WHERE e.id_etudiant = ? 
    AND e.statut IN ('en_cours', 'en_retard')
    ORDER BY e.date_retour_prevue ASC
");
$stmt->execute([$id_etudiant]);
$emprunts_cours = $stmt->fetchAll();

// HISTORIQUE
$stmt = $pdo->prepare("
    SELECT e.*, l.titre, l.auteur, c.nom as categorie_nom
    FROM emprunt e
    JOIN livre l ON e.id_livre = l.id_livre
    LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
    WHERE e.id_etudiant = ? 
    AND e.statut = 'retourne'
    ORDER BY e.date_retour_effective DESC
    LIMIT 10
");
$stmt->execute([$id_etudiant]);
$historique = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Emprunts - Bibliothèque ENSAM</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/etudiant/mes-emprunts.css">

</head>
<body>
    <div class="main-container">
        <!-- HEADER -->
        <div class="page-header">
            <h1><i class="fas fa-bookmark"></i> Mes emprunts</h1>
            <p>Gérez vos emprunts et consultez votre historique</p>
        </div>

        <!-- MESSAGES -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- DEMANDES EN ATTENTE -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-clock"></i>
                Demandes en attente (<?php echo count($demandes_attente); ?>)
            </h2>

            <?php if (count($demandes_attente) > 0): ?>
                <?php foreach ($demandes_attente as $demande): ?>
                    <div class="emprunt-item">
                        <div class="emprunt-header">
                            <div class="book-title-emprunt">
                                <?php echo htmlspecialchars($demande['titre']); ?>
                            </div>
                            <span class="badge-status badge-attente">
                                <i class="fas fa-hourglass-half"></i> En attente
                            </span>
                        </div>
                        <div class="emprunt-details">
                            <div class="detail-item">
                                <i class="fas fa-user-edit"></i>
                                <?php echo htmlspecialchars($demande['auteur']); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($demande['categorie_nom'] ?? 'Non catégorisé'); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-calendar"></i>
                                Demandé le <?php echo date('d/m/Y', strtotime($demande['date_demande'])); ?>
                            </div>
                        </div>

                        <!-- BOUTON ANNULER -->
                        <form method="POST" action="mes-emprunts.php" style="margin-top: 10px;">
                            <input type="hidden" name="id_demande" value="<?php echo $demande['id_demande']; ?>">
                            <button type="submit" class="btn-annuler" onclick="return confirm('Êtes-vous sûr de vouloir annuler cette demande d\'emprunt ?');">
                                <i class="fas fa-times-circle"></i> Annuler
                            </button>
                            <a href="../admin/export_pdf.php?action=demande&id=<?php echo $demande['id_demande']; ?>" target="_blank" class="btn-annuler" style="background: #17a2b8; display: inline-block; text-decoration: none; color: white; margin-left: 10px;">
                                <i class="fas fa-file-pdf"></i> Voir reçu
                            </a>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Aucune demande en attente</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- EMPRUNTS EN COURS -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-book-open"></i>
                Emprunts en cours (<?php echo count($emprunts_cours); ?>)
            </h2>

            <?php if (count($emprunts_cours) > 0): ?>
                <?php foreach ($emprunts_cours as $emprunt): ?>
                    <div class="emprunt-item">
                        <div class="emprunt-header">
                            <div class="book-title-emprunt">
                                <?php echo htmlspecialchars($emprunt['titre']); ?>
                            </div>
                            <span class="badge-status <?php echo $emprunt['statut'] === 'en_retard' ? 'badge-retard' : 'badge-cours'; ?>">
                                <i class="fas <?php echo $emprunt['statut'] === 'en_retard' ? 'fa-exclamation-triangle' : 'fa-book-reader'; ?>"></i>
                                <?php echo $emprunt['statut'] === 'en_retard' ? 'En retard' : 'En cours'; ?>
                            </span>
                        </div>
                        <div class="emprunt-details">
                            <div class="detail-item">
                                <i class="fas fa-user-edit"></i>
                                <?php echo htmlspecialchars($emprunt['auteur']); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-calendar-check"></i>
                                Emprunté le <?php echo date('d/m/Y', strtotime($emprunt['date_emprunt'])); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-calendar-times"></i>
                                Retour prévu le <?php echo date('d/m/Y', strtotime($emprunt['date_retour_prevue'])); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <?php 
                                if ($emprunt['jours_restants'] > 0) {
                                    echo "{$emprunt['jours_restants']} jour(s) restant(s)";
                                } elseif ($emprunt['jours_restants'] == 0) {
                                    echo "À retourner aujourd'hui";
                                } else {
                                    echo abs($emprunt['jours_restants']) . " jour(s) de retard";
                                }
                                ?>
                            </div>
                        </div>

                        <?php if ($emprunt['statut'] === 'en_retard'): ?>
                            <div class="alert-retard">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Attention :</strong> Ce livre est en retard de <?php echo abs($emprunt['jours_restants']); ?> jour(s).
                                Veuillez le retourner rapidement pour éviter le blocage de votre compte.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>Aucun emprunt en cours</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- HISTORIQUE -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Historique des retours (10 derniers)
            </h2>

            <?php if (count($historique) > 0): ?>
                <?php foreach ($historique as $emprunt): ?>
                    <div class="emprunt-item">
                        <div class="emprunt-header">
                            <div class="book-title-emprunt">
                                <?php echo htmlspecialchars($emprunt['titre']); ?>
                            </div>
                            <span class="badge-status badge-retourne">
                                <i class="fas fa-check"></i> Retourné
                            </span>
                        </div>
                        <div class="emprunt-details">
                            <div class="detail-item">
                                <i class="fas fa-user-edit"></i>
                                <?php echo htmlspecialchars($emprunt['auteur']); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-calendar"></i>
                                Retourné le <?php echo date('d/m/Y', strtotime($emprunt['date_retour_effective'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>Aucun historique d'emprunt</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    
</script>
</body>
</html>
<?php
/**
 * MES RÉSERVATIONS - ESPACE ÉTUDIANT
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';

requireEtudiant();

$id_etudiant = $_SESSION['user_id'];

// L'ANNULATION D'UNE RÉSERVATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_reservation'])) {
    // Nettoyer et typer l'identifiant reçu
    $id_reservation = (int) cleanInput($_POST['id_reservation']);
    
    try {
        // Vérifier que la réservation appartient bien à l'étudiant
        $stmt = $pdo->prepare("SELECT * FROM reservation WHERE id_reservation = ? AND id_etudiant = ?");
        $stmt->execute([$id_reservation, $id_etudiant]);
        $reservation = $stmt->fetch();
        
        if ($reservation) {
            // Supprimer la réservation 
            $stmt = $pdo->prepare("DELETE FROM reservation WHERE id_reservation = ?");
            $stmt->execute([$id_reservation]);

            $_SESSION['success_message'] = "Votre réservation a été annulée avec succès.";
        } else {
            $_SESSION['error_message'] = "Réservation introuvable ou non autorisée.";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur lors de l'annulation de la réservation.";
    }
    
    header('Location: reservation.php');
    exit;
}

// Récupération des messages de session
$error = $_SESSION['error_message'] ?? '';
$success = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message'], $_SESSION['success_message']);

// RÉCUPÉRATION DES RÉSERVATIONS EN COURS (statut ='en_attente')
$stmt = $pdo->prepare("
    SELECT r.*, l.titre, l.auteur, c.nom as categorie_nom
    FROM reservation r
    JOIN livre l ON r.id_livre = l.id_livre
    LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
    WHERE r.id_etudiant = ? 
    AND r.statut IN ('en_attente')
    ORDER BY r.date_reservation ASC
");
$stmt->execute([$id_etudiant]);
$reservations = $stmt->fetchAll();

require_once '../../views/etudiant/nav-etudiant.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Réservations - Bibliothèque ENSAM</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/etudiant/reservation.css">

</head>
<body>
    <div class="main-container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> Mes réservations</h1>
            <p>Consultez et gérez vos réservations en attente ou disponibles</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-calendar-check"></i>
                Réservations en cours (<?php echo count($reservations); ?>)
            </h2>

            <?php if (count($reservations) > 0): ?>
                <?php foreach ($reservations as $res): 
                    $is_disponible = $res['statut'] === 'disponible';
                ?>
                    <div class="emprunt-item">
                        <div class="emprunt-header">
                            <div class="book-title-emprunt">
                                <?php echo htmlspecialchars($res['titre']); ?>
                            </div>
                            <span class="badge-status <?php echo $is_disponible ? 'badge-disponible' : 'badge-attente'; ?>">
                                <i class="fas <?php echo $is_disponible ? 'fa-check-circle' : 'fa-hourglass-half'; ?>"></i> 
                                <?php echo $is_disponible ? 'Disponible pour vous' : 'En attente'; ?>
                            </span>
                        </div>
                        <div class="emprunt-details">
                            <div class="detail-item">
                                <i class="fas fa-user-edit"></i>
                                <?php echo htmlspecialchars($res['auteur']); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($res['categorie_nom'] ?? 'Non catégorisé'); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-calendar"></i>
                                Réservé le <?php echo date('d/m/Y', strtotime($res['date_reservation'])); ?>
                            </div>
                            <?php if ($is_disponible && isset($res['date_expiration_disponibilite'])): // Afficher l'alerte de récupération si le livre est disponible ?>
                            <div class="detail-item text-danger fw-bold">
                                <i class="fas fa-exclamation-circle text-danger"></i>
                                **À récupérer avant le <?php echo date('d/m/Y', strtotime($res['date_expiration_disponibilite'])); ?>**
                            </div>
                            <?php endif; ?>
                        </div>

                        <form method="POST" action="reservation.php" style="margin-top: 10px;">
                            <input type="hidden" name="id_reservation" value="<?php echo $res['id_reservation']; ?>">
                            <button type="submit" class="btn-annuler" onclick="return confirm('Êtes-vous sûr de vouloir annuler cette réservation ?');">
                                <i class="fas fa-times-circle"></i> Annuler la réservation
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-alt"></i>
                    <p>Aucune réservation en cours</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
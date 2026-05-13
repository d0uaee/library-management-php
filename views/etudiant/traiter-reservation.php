<?php
/**
 * TRAITER RÉSERVATION - Réserver un livre indisponible
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';

requireEtudiant();

// Récupérer l'ID du livre
$idLivre = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($idLivre <= 0) {
    $_SESSION['error_message'] = "Livre invalide";
    header('Location: catalogue.php');
    exit;
}

// Traitement du formulaire de confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Token CSRF invalide";
        header('Location: catalogue.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $idEtudiant = $_SESSION['user_id'];

        // l'étudiant n'est pas bloqué
        $stmt = $pdo->prepare("SELECT statut FROM etudiant WHERE id_etudiant = ?");
        $stmt->execute([$idEtudiant]);
        $etudiant = $stmt->fetch();

        if ($etudiant['statut'] === 'bloque') {
            throw new Exception("Votre compte est bloqué. Contactez l'administration.");
        }

        // le livre existe
        $stmt = $pdo->prepare("
            SELECT id_livre, titre, nombre_disponibles 
            FROM livre 
            WHERE id_livre = ?
        ");
        $stmt->execute([$idLivre]);
        $livre = $stmt->fetch();

        if (!$livre) {
            throw new Exception("Livre introuvable");
        }

        // il n'y a pas déjà une réservation active
        $stmt = $pdo->prepare("
            SELECT id_reservation 
            FROM reservation 
            WHERE id_etudiant = ? 
            AND id_livre = ? 
            AND statut IN ('en_attente', 'disponible')
        ");
        $stmt->execute([$idEtudiant, $idLivre]);
        
        if ($stmt->fetch()) {
            throw new Exception("Vous avez déjà une réservation active pour ce livre.");
        }

        //limite de réservations actives (max 3)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as nb_reservations
            FROM reservation 
            WHERE id_etudiant = ? 
            AND statut IN ('en_attente')
        ");
        $stmt->execute([$idEtudiant]);
        $count = $stmt->fetch();

        if ($count['nb_reservations'] >= 3) {
            throw new Exception("Vous ne pouvez pas avoir plus de 3 réservations actives simultanément.");
        }

        // Créer la réservation
        $stmt = $pdo->prepare("
            INSERT INTO reservation (id_etudiant, id_livre, date_reservation, statut)
            VALUES (?, ?, NOW(), 'en_attente')
        ");
        $stmt->execute([$idEtudiant, $idLivre]);

        // Créer une notification
        if (tableExists($pdo, 'notification')) {
            $message = "Votre réservation pour le livre \"{$livre['titre']}\" a été enregistrée. Vous serez notifié dès qu'un exemplaire sera disponible.";
            
            $stmt = $pdo->prepare("
                INSERT INTO notification (id_etudiant, type, message, date_envoi, lu)
                VALUES (?, 'info', ?, NOW(), FALSE)
            ");
            $stmt->execute([$idEtudiant, $message]);
        }

        $pdo->commit();

        $_SESSION['success_message'] = "Réservation enregistrée avec succès ! Vous serez notifié dès qu'un exemplaire sera disponible.";
        header('Location: reservation.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: catalogue.php');
        exit;
    }
}

// Afficher le formulaire de confirmation
try {
    // Récupérer les infos du livre
    $stmt = $pdo->prepare("
        SELECT l.*, c.nom as categorie_nom,
        (SELECT COUNT(*) FROM reservation WHERE id_livre = l.id_livre AND statut = 'en_attente') as nb_reservations_attente
        FROM livre l
        LEFT JOIN categorie c ON l.id_categorie = c.id_categorie
        WHERE l.id_livre = ?
    ");
    $stmt->execute([$idLivre]);
    $livre = $stmt->fetch();

    if (!$livre) {
        $_SESSION['error_message'] = "Livre introuvable";
        header('Location: catalogue.php');
        exit;
    }

    // Vérifier le statut de l'étudiant
    $stmt = $pdo->prepare("SELECT statut FROM etudiant WHERE id_etudiant = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $etudiant = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Erreur traiter-reservation.php: " . $e->getMessage());
    $_SESSION['error_message'] = "Erreur lors de la récupération des informations";
    header('Location: catalogue.php');
    exit;
}

// Générer un token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function tableExists($pdo, $table) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réserver un livre - Bibliothèque ENSAM</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/etudiant/traiter-reservation.css">

</head>
<body>
    <div class="confirmation-container">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-bookmark"></i> Réserver un livre</h2>
            </div>
            <div class="card-body p-4">
                <?php if ($etudiant['statut'] === 'bloque'): ?>
                    <div class="alert alert-danger alert-custom">
                        <i class="fas fa-ban"></i>
                        <strong>Compte bloqué:</strong><br>
                        Votre compte est temporairement bloqué. Veuillez contacter l'administration.
                    </div>
                    <a href="catalogue.php" class="btn btn-cancel w-100">
                        <i class="fas fa-arrow-left"></i> Retour au catalogue
                    </a>
                <?php elseif ($livre['nombre_disponibles'] > 0): ?>
                    <div class="alert alert-info alert-custom">
                        <i class="fas fa-info-circle"></i>
                        <strong>Livre disponible:</strong><br>
                        Ce livre est actuellement disponible. Vous pouvez l'emprunter directement.
                    </div>
                    <div class="text-center">
                        <a href="traiter-emprunt.php?id=<?php echo $livre['id_livre']; ?>" class="btn btn-reserve">
                            <i class="fas fa-hand-holding-heart"></i> Emprunter ce livre
                        </a>
                        <a href="catalogue.php" class="btn btn-cancel mt-2">
                            <i class="fas fa-arrow-left"></i> Retour au catalogue
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Informations du livre -->
                    <div class="livre-info">
                        <?php 
                            $imagePath = !empty($livre['image_couverture']) && file_exists('../../assets/img/covers/' . $livre['image_couverture'])
                                ? '../../assets/img/covers/' . $livre['image_couverture']
                                : 'https://via.placeholder.com/200x280?text=Pas+d\'image';
                        ?>
                        <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($livre['titre']); ?>" class="livre-image">
                        
                        <div class="livre-details">
                            <h3><?php echo htmlspecialchars($livre['titre']); ?></h3>

                            <div class="detail-row">
                                <span class="detail-label">Auteur:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($livre['auteur']); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Catégorie:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($livre['categorie_nom'] ?? 'Non catégorisé'); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Année:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($livre['annee_publication']); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">ISBN:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($livre['isbn']); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Disponibilité:</span>
                                <span class="detail-value">
                                    <span class="badge bg-danger">
                                        Indisponible (0/<?php echo $livre['nombre_exemplaires']; ?>)
                                    </span>
                                </span>
                            </div>

                        </div>
                    </div>

                    <!-- Informations sur la réservation -->
                    <div class="info-box">
                        <h5><i class="fas fa-info-circle"></i> Comment fonctionne la réservation ?</h5>
                        <ul>
                            <li>Vous serez placé(e) dans la <strong>file d'attente</strong></li>
                            <li>Vous recevrez une <strong>notification</strong> dès qu'un exemplaire sera disponible</li>
                            <li>Vous aurez <strong>48 heures</strong> pour venir emprunter le livre</li>
                            <li>Vous avez <strong>max 3</strong> réservations actives possibles</li>
                            <li>Passé ce délai, la réservation sera annulée</li>
                            <li>Les réservations sont traitées dans <strong>l'ordre d'arrivée</strong></li>
                        </ul>
                    </div>

                    <!-- Formulaire de confirmation -->
                    <form method="POST" action="" class="mt-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="acceptConditions" required>
                            <label class="form-check-label" for="acceptConditions">
                                Je comprends que je serai notifié(e) dès qu'un exemplaire sera disponible et que j'aurai 48h pour venir l'emprunter
                            </label>
                        </div>

                        <div class="d-flex gap-3 justify-content-center">
                            <button type="submit" class="btn btn-reserve">
                                <i class="fas fa-bookmark"></i> Confirmer la réservation
                            </button>
                            <a href="catalogue.php" class="btn btn-cancel">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Note d'information -->
        <div class="alert alert-info alert-custom mt-4">
            <i class="fas fa-lightbulb"></i>
            <strong>Astuce:</strong> Activez les notifications pour être alerté(e) dès qu'un livre que vous avez réservé devient disponible !
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
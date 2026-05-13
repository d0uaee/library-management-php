<?php
/**
 * TRAITER EMPRUNT - Demander un emprunt de livre
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';

requireEtudiant();

// Récupérer l'ID du livre en le nettoyant
$idLivre = isset($_GET['id']) ? (int) cleanInput($_GET['id']) : 0;

if ($idLivre <= 0) {
    $_SESSION['error_message'] = "Livre invalide";
    header('Location: catalogue.php');
    exit;
}

// Traitement du formulaire de confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        $pdo->beginTransaction();

        $idEtudiant = $_SESSION['user_id'];

        // 1. Vérifier que l'étudiant peut emprunter
        $canBorrowResult = canBorrow($pdo, $idEtudiant);
        if (!$canBorrowResult['can_borrow']) {
            throw new Exception($canBorrowResult['message']);
        }

        // 2. Vérifier que le livre existe et est disponible
        $stmt = $pdo->prepare("
            SELECT id_livre, titre, nombre_disponibles, nombre_exemplaires 
            FROM livre 
            WHERE id_livre = ?
        ");
        $stmt->execute([$idLivre]);
        $livre = $stmt->fetch();

        if (!$livre) {
            throw new Exception("Livre introuvable");
        }

        if ($livre['nombre_disponibles'] <= 0) {
            throw new Exception("Ce livre n'est plus disponible. Vous pouvez le réserver.");
        }

        // 3. Vérifier que l'étudiant n'a pas déjà emprunté ce livre (et non retourné)
        $stmt = $pdo->prepare("
            SELECT id_emprunt 
            FROM emprunt 
            WHERE id_etudiant = ? 
            AND id_livre = ? 
            AND date_retour_effective IS NULL
        ");
        $stmt->execute([$idEtudiant, $idLivre]);
        
        if ($stmt->fetch()) {
            throw new Exception("Vous avez déjà emprunté ce livre et ne l'avez pas encore retourné.");
        }

        // 4. Vérifier qu'il n'y a pas déjà une demande en attente
        $stmt = $pdo->prepare("
            SELECT id_demande 
            FROM demande_emprunt 
            WHERE id_etudiant = ? 
            AND id_livre = ? 
            AND statut = 'en_attente'
        ");
        $stmt->execute([$idEtudiant, $idLivre]);
        
        if ($stmt->fetch()) {
            throw new Exception("Vous avez déjà une demande d'emprunt en attente pour ce livre.");
        }

        // 5. Créer la demande d'emprunt
        $stmt = $pdo->prepare("
            INSERT INTO demande_emprunt (id_etudiant, id_livre, date_demande, statut)
            VALUES (?, ?, NOW(), 'en_attente')
        ");
        $stmt->execute([$idEtudiant, $idLivre]);
        $id_demande = $pdo->lastInsertId();

        // Réserver immédiatement un exemplaire pour éviter le sur-emprunt
        $stmt = $pdo->prepare("
            UPDATE livre
            SET nombre_disponibles = nombre_disponibles - 1
            WHERE id_livre = ? AND nombre_disponibles > 0
        ");
        $stmt->execute([$idLivre]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("Ce livre n'est plus disponible. Vous pouvez le réserver.");
        }

        // 6. Créer une notification pour l'étudiant
        try {
            $message = "Votre demande d'emprunt pour le livre \"{$livre['titre']}\" a été enregistrée. Elle sera traitée par l'administration.";
            
            $stmt = $pdo->prepare("
                INSERT INTO notification (id_etudiant, type, message, date_envoi, lu)
                VALUES (?, 'info', ?, NOW(), FALSE)
            ");
            $stmt->execute([$idEtudiant, $message]);
        } catch (PDOException $e) {
            // Notification échouée, mais on continue
        }

        $pdo->commit();

        $_SESSION['success_message'] = "Votre demande d'emprunt a été enregistrée avec succès ! Elle sera traitée par l'administration. <a href='../admin/export_pdf.php?action=demande&id={$id_demande}' target='_blank' style='color: #0056b3; font-weight: bold; text-decoration: underline;'><i class='fas fa-file-pdf'></i> Voir votre reçu</a>";
        header('Location: mes-emprunts.php');
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
        (SELECT AVG(note) FROM evaluation WHERE id_livre = l.id_livre) as note_moyenne,
        (SELECT COUNT(*) FROM evaluation WHERE id_livre = l.id_livre) as nb_evaluations
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

    // Vérifier la disponibilité
    $canBorrowResult = canBorrow($pdo, $_SESSION['user_id']);

} catch (PDOException $e) {
    error_log("Erreur traiter-emprunt.php: " . $e->getMessage());
        $_SESSION['error_message'] = "Erreur lors de la récupération des informations";
    header('Location: catalogue.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmer l'emprunt - Bibliothèque ENSAM</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/etudiant/traiter-emprunt.css">    

</head>
<body>
    <div class="confirmation-container">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-book-reader"></i> Confirmer la demande d'emprunt</h2>
            </div>
            <div class="card-body p-4">
                <?php if (!$canBorrowResult['can_borrow']): ?>
                    <div class="alert alert-danger alert-custom">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Impossible d'emprunter:</strong><br>
                        <?php echo htmlspecialchars($canBorrowResult['message']); ?>
                    </div>
                    <a href="catalogue.php" class="btn btn-cancel w-100">
                        <i class="fas fa-arrow-left"></i> Retour au catalogue
                    </a>
                <?php elseif ($livre['nombre_disponibles'] <= 0): ?>
                    <div class="alert alert-warning alert-custom">
                        <i class="fas fa-info-circle"></i>
                        <strong>Livre indisponible:</strong><br>
                        Ce livre n'est actuellement plus disponible. Vous pouvez le réserver.
                    </div>
                    <div class="text-center">
                        <a href="traiter-reservation.php?id=<?php echo $livre['id_livre']; ?>" class="btn btn-confirm">
                            <i class="fas fa-bookmark"></i> Réserver ce livre
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
                            
                            <?php if ($livre['note_moyenne'] > 0): ?>
                                <div class="mb-3">
                                    <span class="stars">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= round($livre['note_moyenne']) ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                    </span>
                                    <small class="text-muted">(<?php echo $livre['nb_evaluations']; ?> avis)</small>
                                </div>
                            <?php endif; ?>

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
                                    <span class="badge bg-success">
                                        <?php echo $livre['nombre_disponibles']; ?>/<?php echo $livre['nombre_exemplaires']; ?> exemplaires
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Conditions d'emprunt -->
                    <div class="conditions-box">
                        <h5><i class="fas fa-info-circle"></i> Conditions d'emprunt</h5>
                        <ul>
                            <li>Durée de l'emprunt : <strong>15 jours</strong></li>
                            <li>Prolongation : <strong>possible en contactant l’administration</strong></li>
                            <li>En cas de retard, vous pouvez être <strong>pénalisé par une suspension de 7 jours</strong></li>
                            <li>Après plusieurs retards, votre compte peut être <strong>temporairement bloqué</strong></li>
                            <li>Maximum de <strong>2 emprunts</strong> simultanés</li>
                            <li>Vous serez notifié <strong>1 jour avant la date de retour</strong></li>
                        </ul>
                    </div>

                    <!-- Formulaire de confirmation -->
                    <form method="POST" action="" class="mt-4">
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="acceptConditions" required>
                            <label class="form-check-label" for="acceptConditions">
                                J'accepte les conditions d'emprunt et m'engage à retourner le livre dans les délais
                            </label>
                        </div>

                        <div class="d-flex gap-3 justify-content-center">
                            <button type="submit" class="btn btn-confirm">
                                <i class="fas fa-check"></i> Confirmer la demande
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
            <strong>Note:</strong> Votre demande sera traitée par l'administration. Vous recevrez une notification une fois validée.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
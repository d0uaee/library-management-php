<?php
require_once '../../config/config.php';
require_once '../../utils/security.php';
require_once '../../views/etudiant/nav-etudiant.php';


requireEtudiant();

$id_etudiant = $_SESSION['user_id'];
$success = '';
$error = '';

// Marquer une notification comme lue
if (isset($_POST['action']) && $_POST['action'] === 'marquer_lu') {
    $id_notification = (int)$_POST['id_notification'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notification 
            SET lu = TRUE 
            WHERE id_notification = ? AND id_etudiant = ?
        ");
        $stmt->execute([$id_notification, $id_etudiant]);
        $success = "Notification marquée comme lue.";
    } catch (PDOException $e) {
        $error = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}

// Marquer toutes comme lues
if (isset($_POST['action']) && $_POST['action'] === 'marquer_tout_lu') {
    try {
        $stmt = $pdo->prepare("
            UPDATE notification 
            SET lu = TRUE 
            WHERE id_etudiant = ? AND lu = FALSE
        ");
        $stmt->execute([$id_etudiant]);
        $success = "Toutes les notifications ont été marquées comme lues.";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// RÉCUPÉRATION DES NOTIFICATIONS
$stmt_notifications = $pdo->prepare("
    SELECT * FROM notification 
    WHERE id_etudiant = ? 
    ORDER BY date_envoi DESC
");
$stmt_notifications->execute([$id_etudiant]);
$notifications = $stmt_notifications->fetchAll(PDO::FETCH_ASSOC);

// Compter les non lues
$stmt_count = $pdo->prepare("
    SELECT COUNT(*) as count FROM notification 
    WHERE id_etudiant = ? AND lu = FALSE
");
$stmt_count->execute([$id_etudiant]);
$count_non_lues = $stmt_count->fetch(PDO::FETCH_ASSOC)['count'];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Bibliothèque ENSAM</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/etudiant/notifications.css">

</head>

<body>
<!-- NOTIFICATIONS -->
<div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Notifications (<?php echo $count_non_lues; ?> non lues)</h2>
        <?php if ($count_non_lues > 0): ?>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="marquer_tout_lu">
                <button type="submit" class="btn-mark-all">
                    ✓ Tout marquer comme lu
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (count($notifications) > 0): ?>
        <?php foreach ($notifications as $notif): 
            $read_class = $notif['lu'] ? 'read' : '';
            
            // Déterminer l'icône selon le type
            $icon = '🔔';
            switch ($notif['type']) {
                case 'rappel_retour':
                    $icon = '⏰';
                    break;
                case 'alerte_retard':
                    $icon = '⚠️';
                    break;
                case 'livre_disponible':
                    $icon = '✅';
                    break;
                case 'compte_bloque':
                    $icon = '🚫';
                    break;
                case 'information':
                    $icon = 'ℹ️';
                    break;
            }
        ?>
            <div class="notification-card <?php echo $read_class; ?>">
                <div class="notif-header">
                    <span>
                        <span class="notif-icon"><?php echo $icon; ?></span>
                        <?php echo ucfirst(str_replace('_', ' ', $notif['type'])); ?>
                    </span>
                </div>
                <p class="notif-text"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                <span class="notif-time">
                    <?php 
                        $date = strtotime($notif['date_envoi']);
                        $now = time();
                        $diff = $now - $date;
                        
                        if ($diff < 3600) {
                            echo "Il y a " . floor($diff / 60) . " minutes";
                        } elseif ($diff < 86400) {
                            echo "Il y a " . floor($diff / 3600) . " heures";
                        } elseif ($diff < 172800) {
                            echo "Hier à " . date('H:i', $date);
                        } else {
                            echo date('d/m/Y à H:i', $date);
                        }
                    ?>
                </span>
                
                <?php if (!$notif['lu']): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="marquer_lu">
                        <input type="hidden" name="id_notification" value="<?php echo $notif['id_notification']; ?>">
                        <button type="submit" class="btn-read">✓ D'accord</button>
                    </form>
                <?php else: ?>
                    <button class="btn-read" disabled>Lu ✓</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <p>Aucune notification pour le moment.</p>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
/**
 * MESSAGERIE ADMIN - GESTION COMPLÈTE
 * Gestion des messages étudiants avec threads, réponses multiples et notifications
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';

requireAdmin();

require_once '../../views/admin/panel.php';

$id_admin = $_SESSION['user_id'];
$success = '';
$error = '';

// TRAITEMENT: ENVOYER UNE RÉPONSE

if (isset($_POST['action']) && $_POST['action'] === 'envoyer_reponse') {
    $id_etudiant = (int)$_POST['id_etudiant'];
    $interet = trim($_POST['interet']);
    $message_content = trim($_POST['message_content']);
    
    if (empty($message_content)) {
        $error = "Le message ne peut pas être vide.";
    } else {
        try {
            // Insérer la réponse de l'admin
            $stmt = $pdo->prepare("
                INSERT INTO message (id_etudiant, id_admin, role_expediteur, interet, message, lu)
                VALUES (?, ?, 'admin', ?, ?, FALSE)
            ");
            
            if ($stmt->execute([$id_etudiant, $id_admin, $interet, $message_content])) {
                // Créer une notification pour l'étudiant
                $stmt_notif = $pdo->prepare("
                    INSERT INTO notification (id_etudiant, type, message)
                    VALUES (?, 'information', ?)
                ");
                $notif_message = "Vous avez reçu une réponse de l'administration concernant: " . ucfirst(str_replace('_', ' ', $interet));
                $stmt_notif->execute([$id_etudiant, $notif_message]);
                
                $success = "Réponse envoyée avec succès et notification créée.";
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de l'envoi: " . $e->getMessage();
        }
    }
}

// TRAITEMENT: MARQUER COMME LU

if (isset($_POST['action']) && $_POST['action'] === 'marquer_lu') {
    $id_message = (int)$_POST['id_message'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE message 
            SET lu = TRUE 
            WHERE id_message = ? AND role_expediteur = 'etudiant'
        ");
        $stmt->execute([$id_message]);
        $success = "Message marqué comme lu.";
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// TRAITEMENT: MARQUER TOUT COMME LU
if (isset($_POST['action']) && $_POST['action'] === 'marquer_tout_lu') {
    try {
        $stmt = $pdo->prepare("
            UPDATE message 
            SET lu = TRUE 
            WHERE role_expediteur = 'etudiant' AND lu = FALSE
        ");
        $stmt->execute();
        $success = "Tous les messages ont été marqués comme lus.";
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// TRAITEMENT: SUPPRIMER UN MESSAGE

if (isset($_POST['action']) && $_POST['action'] === 'supprimer_message') {
    $id_message = (int)$_POST['id_message'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM message WHERE id_message = ?");
        $stmt->execute([$id_message]);
        $success = "Message supprimé avec succès.";
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// RÉCUPÉRATION DES CONVERSATIONS


// Récupérer tous les étudiants ayant des messages
$stmt = $pdo->query("
    SELECT DISTINCT
        e.id_etudiant,
        e.nom,
        e.prenom,
        e.email,
        e.numero_etudiant,
        (SELECT COUNT(*) FROM message m1 
         WHERE m1.id_etudiant = e.id_etudiant 
         AND m1.role_expediteur = 'etudiant' 
         AND m1.lu = FALSE) as messages_non_lus,
        (SELECT MAX(date_envoi) FROM message m3 
         WHERE m3.id_etudiant = e.id_etudiant) as derniere_activite,
        (SELECT interet FROM message m4 
         WHERE m4.id_etudiant = e.id_etudiant 
         ORDER BY date_envoi DESC LIMIT 1) as dernier_interet
    FROM etudiant e
    INNER JOIN message m ON e.id_etudiant = m.id_etudiant
    GROUP BY e.id_etudiant
    ORDER BY derniere_activite DESC
");
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la conversation sélectionnée
$conversation_active = null;
$messages_thread = [];
if (isset($_GET['id_etudiant'])) {
    $id_etudiant_actif = (int)$_GET['id_etudiant'];
    
    // Infos étudiant
    $stmt_etudiant = $pdo->prepare("
        SELECT * FROM etudiant WHERE id_etudiant = ?
    ");
    $stmt_etudiant->execute([$id_etudiant_actif]);
    $conversation_active = $stmt_etudiant->fetch(PDO::FETCH_ASSOC);
    
    // Messages du thread
    $stmt_thread = $pdo->prepare("
        SELECT m.*, a.nom as admin_nom, a.prenom as admin_prenom
        FROM message m
        LEFT JOIN admin a ON m.id_admin = a.id_admin
        WHERE m.id_etudiant = ?
        ORDER BY m.date_envoi ASC
    ");
    $stmt_thread->execute([$id_etudiant_actif]);
    $messages_thread = $stmt_thread->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie Admin - Bibliothèque ENSAM</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../../assets/css/admin/messagerie.css">

</head>
<body>

<div class="messagerie-container">
    <!-- SIDEBAR CONVERSATIONS -->
    <div class="conversations-sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-comments"></i> Messagerie Admin</h2>
        </div>

        <div class="conversations-list">
            <?php foreach ($conversations as $conv): ?>
            <a href="?id_etudiant=<?= $conv['id_etudiant'] ?>" 
               class="conversation-item <?= isset($_GET['id_etudiant']) && $_GET['id_etudiant'] == $conv['id_etudiant'] ? 'active' : '' ?>">
                <div class="conversation-header">
                    <div class="conversation-name"><?= htmlspecialchars($conv['prenom'] . ' ' . $conv['nom']) ?></div>
                    <?php if ($conv['messages_non_lus'] > 0): ?>
                    <span class="conversation-badge"><?= $conv['messages_non_lus'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="conversation-meta">
                    <span class="badge-interet"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $conv['dernier_interet']))) ?></span>
                    <span><?= date('d/m H:i', strtotime($conv['derniere_activite'])) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ZONE DE CONVERSATION -->
    <div class="conversation-zone">
        <?php if ($conversation_active): ?>
            <div class="conversation-header-main">
                <div class="conversation-info">
                    <h3><?= htmlspecialchars($conversation_active['prenom'] . ' ' . $conversation_active['nom']) ?></h3>
                    <p>
                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($conversation_active['email']) ?>
                        | <i class="fas fa-id-card"></i> <?= htmlspecialchars($conversation_active['numero_etudiant']) ?>
                    </p>
                </div>
                <div class="conversation-actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="marquer_tout_lu">
                        <button type="submit" class="btn-action">
                            <i class="fas fa-check-double"></i> Tout marquer lu
                        </button>
                    </form>
                    <a href="?" class="btn-action">
                        <i class="fas fa-times"></i> Fermer
                    </a>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-custom">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-custom">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="messages-container" id="messagesContainer">
                <?php foreach ($messages_thread as $msg): ?>
                    <div class="message-bubble <?= $msg['role_expediteur'] ?>">
                        <div class="message-header">
                            <span class="message-sender">
                                <?php if ($msg['role_expediteur'] === 'etudiant'): ?>
                                    <?= htmlspecialchars($conversation_active['prenom']) ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($msg['admin_prenom'] . ' ' . $msg['admin_nom']) ?> (Admin)
                                <?php endif; ?>
                            </span>
                            <span class="message-time">
                                <?= date('d/m/Y H:i', strtotime($msg['date_envoi'])) ?>
                            </span>
                        </div>
                        <div class="message-interet">
                            <i class="fas fa-tag"></i>
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $msg['interet']))) ?>
                        </div>
                        <div class="message-content">
                            <?= nl2br(htmlspecialchars($msg['message'])) ?>
                        </div>
                        <?php if ($msg['role_expediteur'] === 'etudiant'): ?>
                            <div class="message-status">
                                <?php if ($msg['lu']): ?>
                                    <i class="fas fa-check-double"></i> Lu
                                <?php else: ?>
                                    <i class="fas fa-check"></i> Envoyé
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="message-input-zone">
                <form method="POST" class="form-reply">
                    <input type="hidden" name="action" value="envoyer_reponse">
                    <input type="hidden" name="id_etudiant" value="<?= $conversation_active['id_etudiant'] ?>">
                    
                    <div class="input-group-custom">
                        <select name="interet" required>
                            <option value="">Sélectionner un sujet</option>
                            <option value="prolongation_emprunt">Prolongation d'emprunt</option>
                            <option value="reservation">Réservation</option>
                            <option value="retard">Retard</option>
                            <option value="autres">Autres</option>
                        </select>
                        
                        <textarea name="message_content" 
                                  rows="3" 
                                  placeholder="Écrire votre réponse..." 
                                  required></textarea>
                    </div>
                    
                    <button type="submit" class="btn-send">
                        <i class="fas fa-paper-plane"></i> Envoyer la réponse
                    </button>
                </form>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <h3>Sélectionnez une conversation</h3>
                <p>Choisissez un étudiant dans la liste pour voir les messages</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-scroll vers le bas des messages
    const container = document.getElementById('messagesContainer');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }

    // Auto-fermer les alertes
    setTimeout(() => {
        document.querySelectorAll('.alert-custom').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
</script>

</body>
</html>
<?php

/**
 * MESSAGES - ESPACE ÉTUDIANT
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';

requireEtudiant();
$id_etudiant = $_SESSION['user_id'];

// Envoyer un nouveau message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $interet = cleanInput($_POST['interet'] ?? '');
    $message = trim(cleanInput($_POST['message_content'] ?? ''));

    if (empty($interet) || empty($message)) {
        $_SESSION['error_message'] = "Veuillez remplir tous les champs.";
        header('Location: messages.php');
        exit;
    }

    try {
        $admin = $pdo->query("SELECT id_admin FROM admin LIMIT 1")->fetch();
        if (!$admin) {
            $_SESSION['error_message'] = "Aucun administrateur disponible.";
            header('Location: messages.php');
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO message (id_etudiant, id_admin, role_expediteur, interet, message, lu) VALUES (?, ?, 'etudiant', ?, ?, 0)");
        if ($stmt->execute([$id_etudiant, $admin['id_admin'], $interet, $message])) {
            $_SESSION['success_message'] = "Message envoyé avec succès.";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur : " . $e->getMessage();
    }
    
    header('Location: messages.php');
    exit;
}

// Marquer un message comme lu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_read'])) {
    $id_message = (int) ($_POST['id_message'] ?? 0);

    try {
        $stmt = $pdo->prepare("UPDATE message SET lu = 1 WHERE id_message = ? AND id_etudiant = ? AND role_expediteur = 'admin'");
        $stmt->execute([$id_message, $id_etudiant]);
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur : " . $e->getMessage();
    }
    
    header('Location: messages.php');
    exit;
}

// Récupérer les messages de session
$error = $_SESSION['error_message'] ?? '';
$success = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message'], $_SESSION['success_message']);

// Charger mes messages envoyés
$stmt = $pdo->prepare("SELECT m.*, a.prenom AS admin_prenom, a.nom AS admin_nom FROM message m JOIN admin a ON m.id_admin = a.id_admin WHERE m.id_etudiant = ? AND m.role_expediteur = 'etudiant' ORDER BY m.date_envoi DESC");
$stmt->execute([$id_etudiant]);
$messages_sent = $stmt->fetchAll();

// Charger mes messages reçus
$stmt = $pdo->prepare("SELECT m.*, a.prenom AS admin_prenom, a.nom AS admin_nom FROM message m JOIN admin a ON m.id_admin = a.id_admin WHERE m.id_etudiant = ? AND m.role_expediteur = 'admin' ORDER BY m.date_envoi DESC");
$stmt->execute([$id_etudiant]);
$messages_received = $stmt->fetchAll();

require_once '../../views/etudiant/nav-etudiant.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" >
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Bibliothèque ENSAM</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/etudiant/messages.css">

</head>
<body>
    <div class="main-container">
        <div class="page-header">
            <h1><i class="fas fa-envelope"></i> Messagerie</h1>
            <p>Communiquez avec l'administration de la bibliothèque.</p>
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
            <h2 class="section-title"><i class="fas fa-paper-plane"></i> Envoyer un nouveau message</h2>
            
            <form method="POST" action="messages.php">
                <input type="hidden" name="send_message" value="1">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Intérêt du message :</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="interet" id="interet_prolongation" value="prolongation_emprunt" required>
                        <label class="form-check-label" for="interet_prolongation">
                            Demande de prolongation de l'emprunt
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="interet" id="interet_autres" value="autres" required>
                        <label class="form-check-label" for="interet_autres">
                            Autres (Question, signalement, etc.)
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="message_content" class="form-label fw-bold">Votre message :</label>
                    <textarea class="form-control" id="message_content" name="message_content" rows="4" required minlength="10" placeholder="Décrivez votre demande en détail..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="background-color: #667eea; border-color: #667eea;">
                    <i class="fas fa-paper-plane"></i> Envoyer
                </button>
            </form>
        </div>

        <div class="messages-layout">
            
            <div class="section-card">
                <h2 class="section-title"><i class="fas fa-inbox"></i> Messages reçus (Admin)</h2>
                
                <?php if (count($messages_received) > 0): ?>
                    <?php foreach ($messages_received as $msg): 
                        $is_unread = !$msg['lu'];
                    ?>
                        <div class="message-item received <?php echo $is_unread ? 'unread' : ''; ?>">
                            <div class="message-header">
                                <span class="sender"><i class="fas fa-user-shield"></i> Administrateur</span>
                                <span><?php echo date('d/m/Y H:i', strtotime($msg['date_envoi'])); ?></span>
                            </div>
                            
                            <div class="message-body">
                                <p class="fw-bold">Intérêt : <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $msg['interet']))); ?></p>
                                <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                            </div>

                            <div class="message-footer">
                                <?php if ($is_unread): ?>
                                    <span class="text-danger fw-bold"><i class="fas fa-bell"></i> NON LU</span>
                                    <form method="POST" action="messages.php">
                                        <input type="hidden" name="mark_as_read" value="1">
                                        <input type="hidden" name="id_message" value="<?php echo $msg['id_message']; ?>">
                                        <button type="submit" class="btn-mark-read">Marquer comme lu</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge-read"><i class="fas fa-check"></i> Lu</span>
                                    <span></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-dots"></i>
                        <p>Aucun message reçu pour le moment.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section-card">
                <h2 class="section-title"><i class="fas fa-box-open"></i> Messages envoyés</h2>
                
                <?php if (count($messages_sent) > 0): ?>
                    <?php foreach ($messages_sent as $msg): ?>
                        <div class="message-item sent">
                            <div class="message-header">
                                <span class="sender"><i class="fas fa-user-tag"></i> Moi</span>
                                <span><?php echo date('d/m/Y H:i', strtotime($msg['date_envoi'])); ?></span>
                            </div>
                            
                            <div class="message-body">
                                <p class="fw-bold">Intérêt : 
                                    <span class="badge-interet <?php echo $msg['interet'] == 'prolongation_emprunt' ? 'badge-prolongation' : 'badge-autres'; ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $msg['interet']))); ?>
                                    </span>
                                </p>
                                <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                            </div>

                            <div class="message-footer">
                                <span class="text-muted">Destinataire : <?php echo htmlspecialchars($msg['admin_prenom'] . ' ' . $msg['admin_nom']); ?></span>
                                <span class="text-secondary"><i class="fas fa-clock"></i> En attente de réponse</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-paper-plane"></i>
                        <p>Vous n'avez envoyé aucun message.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
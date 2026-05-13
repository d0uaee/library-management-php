<?php
/**
 * GÉNÉRATION DE REÇUS DE DEMANDE D'EMPRUNT
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';

// Vérifier que l'utilisateur est connecté (admin ou étudiant)
requireLogin();

/**
 * Génère un reçu de demande d'emprunt
 */
function genererRecuDemande($id_demande) {
    global $pdo;
    
    // Récupérer les infos de la demande
    $stmt = $pdo->prepare("
        SELECT d.*, l.titre, l.auteur, l.isbn, et.nom, et.prenom, et.numero_etudiant, et.email
        FROM demande_emprunt d
        JOIN livre l ON d.id_livre = l.id_livre
        JOIN etudiant et ON d.id_etudiant = et.id_etudiant
        WHERE d.id_demande = ?
    ");
    $stmt->execute([$id_demande]);
    $demande = $stmt->fetch();
    
    if (!$demande) {
        return false;
    }
    
    // Vérifier que l'utilisateur a le droit de voir ce reçu
    // Admin peut tout voir, étudiant ne peut voir que ses propres demandes
    if (!isAdmin() && $_SESSION['user_id'] != $demande['id_etudiant']) {
        http_response_code(403);
        die("Accès refusé : vous ne pouvez consulter que vos propres reçus.");
    }
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Reçu de demande d'emprunt</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 0 auto;
                padding: 40px;
            }
            .recu-header {
                text-align: center;
                border-bottom: 3px solid #667eea;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            .recu-header h1 {
                color: #667eea;
                margin: 0;
                font-size: 28px;
            }
            .recu-header p {
                color: #666;
                margin: 5px 0;
            }
            .info-section {
                margin: 20px 0;
                padding: 20px;
                background: #f9f9f9;
                border-radius: 10px;
            }
            .info-section h3 {
                color: #667eea;
                margin-top: 0;
            }
            .info-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #e0e0e0;
            }
            .info-row:last-child {
                border-bottom: none;
            }
            .info-label {
                font-weight: bold;
                color: #333;
            }
            .info-value {
                color: #666;
            }
            .alert-box {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 20px 0;
                border-radius: 5px;
            }
            .footer {
                text-align: center;
                margin-top: 40px;
                padding-top: 20px;
                border-top: 2px solid #e0e0e0;
                color: #999;
                font-size: 12px;
            }
            .status-badge {
                display: inline-block;
                padding: 5px 15px;
                border-radius: 20px;
                background: #ffc107;
                color: #000;
                font-weight: bold;
                font-size: 14px;
            }
            @media print {
                body { padding: 20px; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="recu-header">
            <h1>📋 Reçu de Demande d'Emprunt</h1>
            <p>Bibliothèque ENSAM Meknès</p>
            <p>Date de demande : <?php echo date('d/m/Y à H:i', strtotime($demande['date_demande'])); ?></p>
        </div>

        <div class="info-section">
            <h3>Informations de l'étudiant</h3>
            <div class="info-row">
                <span class="info-label">Nom complet :</span>
                <span class="info-value"><?php echo htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Numéro étudiant :</span>
                <span class="info-value"><?php echo htmlspecialchars($demande['numero_etudiant']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email :</span>
                <span class="info-value"><?php echo htmlspecialchars($demande['email']); ?></span>
            </div>
        </div>

        <div class="info-section">
            <h3>Détails du livre demandé</h3>
            <div class="info-row">
                <span class="info-label">Titre :</span>
                <span class="info-value"><?php echo htmlspecialchars($demande['titre']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Auteur :</span>
                <span class="info-value"><?php echo htmlspecialchars($demande['auteur']); ?></span>
            </div>
            <?php if ($demande['isbn']): ?>
            <div class="info-row">
                <span class="info-label">ISBN :</span>
                <span class="info-value"><?php echo htmlspecialchars($demande['isbn']); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="info-section">
            <h3>Statut de la demande</h3>
            <div class="info-row">
                <span class="info-label">Statut actuel :</span>
                <span class="info-value"><span class="status-badge">⏳ <?php echo strtoupper($demande['statut']); ?></span></span>
            </div>
            <div class="info-row">
                <span class="info-label">Numéro de demande :</span>
                <span class="info-value">#<?php echo str_pad($demande['id_demande'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
        </div>

        <div class="alert-box">
            <strong>ℹ️ Information importante :</strong><br>
            Cette demande sera traitée par l'administration de la bibliothèque. Vous recevrez une notification dès que votre demande sera validée ou refusée. Conservez ce reçu comme preuve de votre demande.
        </div>

        <div class="footer">
            <p>Bibliothèque ENSAM Meknès - Email: bibliotheque@ensam.ma</p>
            <p>Numéro de demande : #<?php echo str_pad($demande['id_demande'], 6, '0', STR_PAD_LEFT); ?></p>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    
    return $html;
}

// Traitement des actions
if (isset($_GET['action']) && $_GET['action'] === 'demande' && isset($_GET['id'])) {
    $html = genererRecuDemande($_GET['id']);
    
    if ($html) {
        if (isset($_GET['download'])) {
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="recu_demande_' . $_GET['id'] . '.html"');
        }
        
        echo $html;
    } else {
        http_response_code(404);
        echo "Demande introuvable";
    }
    exit;
}

// Redirection si pas d'action
header('Location: ../../admin/dashboard.php');
exit;
?>

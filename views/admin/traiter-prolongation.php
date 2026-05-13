<?php
/**
 * TRAITER PROLONGATION
 * * Gère la mise à jour de la date de retour prévue d'un emprunt
 * Le fichier reçoit les données via POST depuis le formulaire de emprunts.php.
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';

requireAdmin();

$redirect_url = 'emprunts.php?tab=en_cours';
// Vérifier la méthode de la requête et les paramètres requis
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id_emprunt'], $_POST['nouvelle_date_retour'])) {
    $_SESSION['error'] = "Requête invalide pour la prolongation.";
    header("Location: $redirect_url");
    exit;
}
// Récupérer et valider les données
$id_emprunt = (int)$_POST['id_emprunt'];
$nouvelle_date = trim($_POST['nouvelle_date_retour']);
$date_valide = date('Y-m-d', strtotime($nouvelle_date));
$success = false;
$message = '';

// Vérification de la validité de la date (non vide et non dans le passé)
if (empty($id_emprunt)) {
    $message = "ID d'emprunt manquant.";
} elseif (empty($nouvelle_date) || $date_valide <= date('Y-m-d')) { // Doit être strictement supérieur à aujourd'hui
    $message = "Date de prolongation invalide. La date doit être postérieure à aujourd'hui.";
} else {
    try {
        // Mettre à jour la date de retour prévue
        // Le statut est remis à 'en_cours' si jamais il était 'en_retard'
        $stmt_update_emprunt = $pdo->prepare("
            UPDATE emprunt 
            SET date_retour_prevue = ?, statut = 'en_cours'
            WHERE id_emprunt = ? AND statut IN ('en_cours', 'en_retard')
        ");
        $stmt_update_emprunt->execute([$date_valide, $id_emprunt]);

        if ($stmt_update_emprunt->rowCount() > 0) {
            $success = true;
            $message = "Prolongation enregistrée. Nouvelle date de retour : " . date('d/m/Y', strtotime($date_valide));
            
            // Envoyer une notification à l'étudiant
            $stmt_etudiant = $pdo->prepare("SELECT id_etudiant FROM emprunt WHERE id_emprunt = ?");
            $stmt_etudiant->execute([$id_emprunt]);
            $etudiant = $stmt_etudiant->fetch();
            
            if ($etudiant) {
                $notif_message = "La date de retour de votre emprunt a été prolongée par l'administrateur. Nouvelle date prévue : " . date('d/m/Y', strtotime($date_valide));
                $stmt_notif = $pdo->prepare("
                    INSERT INTO notification (id_etudiant, type, message) 
                    VALUES (?, 'info', ?)
                ");
                $stmt_notif->execute([$etudiant['id_etudiant'], $notif_message]);
            }
        } else {
            $message = "Erreur : Emprunt introuvable ou déjà retourné.";
        }

    } catch (PDOException $e) {
        $message = "Erreur lors de la prolongation : " . $e->getMessage();
    }
}

if ($success) {
    $_SESSION['success'] = $message;
} else {
    $_SESSION['error'] = $message;
}

header("Location: $redirect_url");
exit;
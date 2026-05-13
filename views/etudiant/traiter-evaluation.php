<?php
/**
 * TRAITEMENT DES ÉVALUATIONS DE LIVRES
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';

requireEtudiant();

// Forcer le header JSON dès le début
header('Content-Type: application/json');

// Log pour debug
error_log("=== DEBUT traiter-evaluation.php ===");
error_log("POST data: " . print_r($_POST, true));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$id_livre = isset($_POST['id_livre']) ? (int)$_POST['id_livre'] : 0;
$note = isset($_POST['note']) ? (int)$_POST['note'] : 0;
$id_etudiant = (int)$_SESSION['user_id'];

error_log("id_livre: $id_livre, note: $note, id_etudiant: $id_etudiant");

// Validation
if ($id_livre <= 0) {
    error_log("Erreur: Livre invalide");
    echo json_encode(['success' => false, 'message' => 'Livre invalide']);
    exit;
}

if ($note < 0 || $note > 5) {
    error_log("Erreur: Note invalide");
    echo json_encode(['success' => false, 'message' => 'Note invalide (doit être entre 0 et 5)']);
    exit;
}

try {    
    // Vérifier si une évaluation existe déjà
    $stmt = $pdo->prepare("SELECT id_evaluation, note FROM evaluation WHERE id_livre = ? AND id_etudiant = ?");
    $stmt->execute([$id_livre, $id_etudiant]);
    $evaluation_existante = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Evaluation existante: " . ($evaluation_existante ? "Oui (note: {$evaluation_existante['note']})" : "Non"));

    // LOGIQUE UNIFIÉE
    $doit_supprimer = false;
    
    if ($note === 0) {
        // Cas 1 : L'utilisateur veut explicitement retirer sa note
        $doit_supprimer = true;
        $message = 'Votre évaluation a été retirée';
    } elseif ($evaluation_existante && $evaluation_existante['note'] == $note) {
        // Cas 2 : L'utilisateur clique sur l'étoile déjà sélectionnée
        $doit_supprimer = true;
        $message = 'Votre évaluation a été retirée';
    }
    
    if ($doit_supprimer) {
        if ($evaluation_existante) {
            $stmt = $pdo->prepare("DELETE FROM evaluation WHERE id_livre = ? AND id_etudiant = ?");
            $stmt->execute([$id_livre, $id_etudiant]);
            error_log("Evaluation supprimée");
        } else {
            error_log("Aucune évaluation à supprimer");
            $message = 'Aucune évaluation à retirer';
        }
    } else {
        // Ajouter ou mettre à jour l'évaluation
        if ($evaluation_existante) {
            // MISE À JOUR
            $stmt = $pdo->prepare("UPDATE evaluation SET note = ? WHERE id_livre = ? AND id_etudiant = ?");
            $result = $stmt->execute([$note, $id_livre, $id_etudiant]);
            error_log("Mise à jour - Résultat: " . ($result ? "OK" : "ECHEC"));
            $message = 'Votre note a été mise à jour';
        } else {
            // INSERTION
            $stmt = $pdo->prepare("INSERT INTO evaluation (id_livre, id_etudiant, note) VALUES (?, ?, ?)");
            $result = $stmt->execute([$id_livre, $id_etudiant, $note]);
            error_log("Insertion - Résultat: " . ($result ? "OK" : "ECHEC"));
            $message = 'Merci pour votre évaluation !';
        }
    }

    // Récupérer les nouvelles statistiques
    $stmt = $pdo->prepare("SELECT 
        COALESCE(AVG(note), 0) as moyenne, 
        COUNT(*) as nb 
        FROM evaluation 
        WHERE id_livre = ?");
    $stmt->execute([$id_livre]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer la note actuelle de l'utilisateur
    $stmt = $pdo->prepare("SELECT note FROM evaluation WHERE id_livre = ? AND id_etudiant = ?");
    $stmt->execute([$id_livre, $id_etudiant]);
    $note_finale = $stmt->fetch(PDO::FETCH_ASSOC);
    $note_utilisateur = $note_finale ? (int)$note_finale['note'] : 0;
    
    error_log("Note finale utilisateur: $note_utilisateur");

    $response = [
        'success' => true,
        'message' => $message,
        'nouvelle_moyenne' => round($stats['moyenne'], 1),
        'nb_evaluations' => (int)$stats['nb'],
        'note_utilisateur' => $note_utilisateur
    ];
    
    error_log("Réponse: " . json_encode($response));
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("ERREUR PDO: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors de l\'enregistrement'
    ]);
}
error_log("=== FIN traiter-evaluation.php ===");
?>
<?php
/**
 * SYSTÈME DE NOTIFICATIONS AUTOMATIQUES
*/

// Vérifier que l'étudiant est connecté
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    return; // Ne rien faire si pas connecté ou pas étudiant
}

$id_etudiant = $_SESSION['user_id'];
$today = date('Y-m-d');
$date_rappel = date('Y-m-d', strtotime('+3 days')); // 3 jours avant

try {
    // 1. VÉRIFIER LES EMPRUNTS EN RETARD
    $stmt_retard = $pdo->prepare("
        SELECT e.id_emprunt, e.id_livre, e.date_retour_prevue,
               l.titre, l.auteur
        FROM emprunt e
        JOIN livre l ON e.id_livre = l.id_livre
        WHERE e.id_etudiant = ?
        AND e.statut = 'en_cours' 
        AND e.date_retour_prevue < ?
    ");
    $stmt_retard->execute([$id_etudiant, $today]);
    $emprunts_retard = $stmt_retard->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($emprunts_retard as $emprunt) {
        // Mettre à jour le statut de l'emprunt en 'en_retard'
        $stmt_update = $pdo->prepare("
            UPDATE emprunt 
            SET statut = 'en_retard' 
            WHERE id_emprunt = ?
        ");
        $stmt_update->execute([$emprunt['id_emprunt']]);
        
        // Calculer le nombre de jours de retard
        $date_retour = new DateTime($emprunt['date_retour_prevue']);
        $date_aujourdhui = new DateTime($today);
        $jours_retard = $date_aujourdhui->diff($date_retour)->days;
        
        // Vérifier si une notification de retard n'a pas déjà été envoyée aujourd'hui
        $stmt_check = $pdo->prepare("
            SELECT id_notification 
            FROM notification 
            WHERE id_etudiant = ? 
            AND type = 'alerte_retard' 
            AND message LIKE ?
            AND DATE(date_envoi) = ?
        ");
        $message_pattern = "%{$emprunt['titre']}%";
        $stmt_check->execute([$id_etudiant, $message_pattern, $today]);
        
        if ($stmt_check->rowCount() == 0) {
            // Envoyer une notification d'alerte de retard
            $message = "⚠️ RETARD : Votre emprunt du livre '{$emprunt['titre']}' par {$emprunt['auteur']} est en retard de {$jours_retard} jour(s). Date de retour prévue : " . date('d/m/Y', strtotime($emprunt['date_retour_prevue'])) . ". Merci de le retourner au plus vite.";
            
            $stmt_notif = $pdo->prepare("
                INSERT INTO notification (id_etudiant, type, message, date_envoi) 
                VALUES (?, 'alerte_retard', ?, NOW())
            ");
            $stmt_notif->execute([$id_etudiant, $message]);
            
            // Incrémenter le compteur de retards de l'étudiant
            $stmt_inc_retard = $pdo->prepare("
                UPDATE etudiant 
                SET nombre_retards = nombre_retards + 1 
                WHERE id_etudiant = ?
            ");
            $stmt_inc_retard->execute([$id_etudiant]);
            
            // Insérer dans la table 'retard'
            $montant_amende = $jours_retard * 5; // 5 DH par jour de retard
            $stmt_retard_table = $pdo->prepare("
                INSERT INTO retard (id_emprunt, nb_jours_retard, montant_amende) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                nb_jours_retard = ?, 
                montant_amende = ?,
                date_notification = NOW()
            ");
            $stmt_retard_table->execute([
                $emprunt['id_emprunt'], 
                $jours_retard, 
                $montant_amende,
                $jours_retard,
                $montant_amende
            ]);
        }
    }
    
    // 2. VÉRIFIER LES RETOURS PROCHES (3 jours avant)
    $stmt_rappel = $pdo->prepare("
        SELECT e.id_emprunt, e.id_livre, e.date_retour_prevue,
               l.titre, l.auteur
        FROM emprunt e
        JOIN livre l ON e.id_livre = l.id_livre
        WHERE e.id_etudiant = ?
        AND e.statut = 'en_cours' 
        AND e.date_retour_prevue <= ?
        AND e.date_retour_prevue >= ?
    ");
    $stmt_rappel->execute([$id_etudiant, $date_rappel, $today]);
    $emprunts_rappel = $stmt_rappel->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($emprunts_rappel as $emprunt) {
        // Calculer le nombre de jours restants
        $date_retour = new DateTime($emprunt['date_retour_prevue']);
        $date_aujourdhui = new DateTime($today);
        $jours_restants = $date_aujourdhui->diff($date_retour)->days;
        
        // Vérifier si un rappel n'a pas déjà été envoyé aujourd'hui
        $stmt_check = $pdo->prepare("
            SELECT id_notification 
            FROM notification 
            WHERE id_etudiant = ? 
            AND type = 'rappel_retour' 
            AND message LIKE ?
            AND DATE(date_envoi) = ?
        ");
        $message_pattern = "%{$emprunt['titre']}%";
        $stmt_check->execute([$id_etudiant, $message_pattern, $today]);
        
        if ($stmt_check->rowCount() == 0) {
            // Envoyer une notification de rappel
            if ($jours_restants == 0) {
                $message = "⏰ RAPPEL : Le livre '{$emprunt['titre']}' par {$emprunt['auteur']} doit être retourné AUJOURD'HUI. Merci de le ramener à la bibliothèque.";
            } else {
                $message = "⏰ RAPPEL : Le livre '{$emprunt['titre']}' par {$emprunt['auteur']} doit être retourné dans {$jours_restants} jour(s) (le " . date('d/m/Y', strtotime($emprunt['date_retour_prevue'])) . "). N'oubliez pas de le ramener à temps.";
            }
            
            $stmt_notif = $pdo->prepare("
                INSERT INTO notification (id_etudiant, type, message, date_envoi) 
                VALUES (?, 'rappel_retour', ?, NOW())
            ");
            $stmt_notif->execute([$id_etudiant, $message]);
        }
    }
    
} catch (PDOException $e) {
    error_log("Erreur dans update.php pour étudiant {$id_etudiant} : " . $e->getMessage());
}
?>
<?php
/**
 * MISE À JOUR AUTOMATIQUE DES PÉNALISATIONS ET DES DEMANDES D'EMPRUNT EXPIRÉES
 * - Réactive les étudiants dont la pénalité a expiré
 * - Supprime les demandes d'emprunt 'en_attente' expirées (>24h)
 * - Gère les réservations en attente
 */

require_once '../../config/config.php';

$message = '';
$students_reactivated = 0;
$requests_deleted = 0;


try {
    $pdo->beginTransaction();

    // 1. Réactiver les étudiants dont la pénalité a expiré
    $sql_update_etudiant = "
        UPDATE etudiant e
        JOIN penalisation p ON e.id_etudiant = p.id_etudiant
        SET e.statut = 'actif'
        WHERE 
            p.date_desactivation IS NOT NULL 
            AND p.date_desactivation < DATE(NOW())
            AND e.statut = 'bloque'
    ";
    
    $result_update = $pdo->query($sql_update_etudiant);
    $students_reactivated = $result_update->rowCount(); 

    // 2. Supprimer les enregistrements de pénalisation expirés
    $sql_delete_penalisation = "
        DELETE FROM penalisation
        WHERE 
            date_desactivation IS NOT NULL 
            AND date_desactivation < DATE(NOW())
    ";
    
    $pdo->query($sql_delete_penalisation);
    
    // 3. Récupérer les demandes d'emprunt expirées (>24h)
    $stmt_expired_requests = $pdo->query("
        SELECT id_demande, id_livre
        FROM demande_emprunt
        WHERE 
            statut = 'en_attente'
            AND date_demande < DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    $expired_requests = $stmt_expired_requests->fetchAll(PDO::FETCH_ASSOC);

    $requests_deleted = count($expired_requests);
    $reservations_promoted = 0;
    
    if ($requests_deleted > 0) {
        $id_livres_to_update = [];
        $id_demandes_to_delete = [];

        foreach ($expired_requests as $request) {
            $id_livres_to_update[] = $request['id_livre'];
            $id_demandes_to_delete[] = $request['id_demande'];
        }

        $processed_book_ids = array_unique($id_livres_to_update);

        //  Augmenter la disponibilité pour les livres concernés
        $placeholders_livres = implode(',', array_fill(0, count($processed_book_ids), '?'));
        $stmt_update_disponibility = $pdo->prepare("
            UPDATE livre
            SET nombre_disponibles = nombre_disponibles + 1
            WHERE id_livre IN ({$placeholders_livres})
        ");
        $stmt_update_disponibility->execute($processed_book_ids);

        // 3.b. Supprimer les enregistrements de demande_emprunt expirés
        $placeholders_demandes = implode(',', array_fill(0, count($id_demandes_to_delete), '?'));
        $stmt_delete_demandes = $pdo->prepare("
            DELETE FROM demande_emprunt
            WHERE id_demande IN ({$placeholders_demandes})
        ");
        $stmt_delete_demandes->execute($id_demandes_to_delete);

        // Traiter les réservations en attente
        foreach ($processed_book_ids as $id_livre) {
            
            //  Vérifier et traiter la prochaine réservation en attente pour ce livre
            $stmt_reservation = $pdo->prepare("
                SELECT r.id_reservation, r.id_etudiant, l.titre
                FROM reservation r
                JOIN livre l ON r.id_livre = l.id_livre
                WHERE r.id_livre = ? AND r.statut = 'en_attente'
                ORDER BY r.date_reservation ASC
                LIMIT 1
            ");
            $stmt_reservation->execute([$id_livre]); 
            $reservation = $stmt_reservation->fetch(PDO::FETCH_ASSOC);

            if ($reservation) {
                $id_etudiant_reserve = $reservation['id_etudiant'];
                $id_reservation = $reservation['id_reservation'];
                $titre_livre = $reservation['titre'];

                // 4.a. Créer une demande d'emprunt pour l'étudiant
                $stmt_new_demande = $pdo->prepare("
                    INSERT INTO demande_emprunt (id_etudiant, id_livre, statut) 
                    VALUES (?, ?, 'en_attente')
                ");
                $stmt_new_demande->execute([$id_etudiant_reserve, $id_livre]);

                // Bloquer l'exemplaire
                $stmt_lock = $pdo->prepare("
                    UPDATE livre
                    SET nombre_disponibles = nombre_disponibles - 1
                    WHERE id_livre = ? AND nombre_disponibles > 0
                ");
                $stmt_lock->execute([$id_livre]);

                if ($stmt_lock->rowCount() === 0) {
                    error_log("ERREUR LOGIQUE - Réactivation de réservation: Impossible de bloquer l'exemplaire (ID Livre: {$id_livre}) pour l'étudiant #{$id_etudiant_reserve}.");
                    continue; 
                }

                // Notifier l'étudiant
                $message_notif = "Le livre '{$titre_livre}' que vous avez réservé est maintenant disponible ! Une demande d'emprunt a été créée en votre nom. Vous avez 24h pour la valider.";
                $stmt_notif_reserve = $pdo->prepare("
                    INSERT INTO notification (id_etudiant, type, message) 
                    VALUES (?, 'livre_disponible', ?)
                ");
                $stmt_notif_reserve->execute([$id_etudiant_reserve, $message_notif]);

                //  Supprimer la réservation traitée
                $stmt_delete_reservation = $pdo->prepare("
                    DELETE FROM reservation WHERE id_reservation = ?
                ");
                $stmt_delete_reservation->execute([$id_reservation]);
                
                $reservations_promoted++;
            }
        }
    }

    $pdo->commit();

    $message = "Succès : {$students_reactivated} étudiant(s) réactivé(s) automatiquement. Les enregistrements de pénalité expirés ont été supprimés. {$requests_deleted} demande(s) d'emprunt expirée(s) supprimée(s). {$reservations_promoted} réservation(s) promue(s) en demande d'emprunt.";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("ERREUR CRITIQUE - Update Penalisation: " . $e->getMessage());
    $message = "ERREUR SQL : Impossible de mettre à jour les pénalisations et/ou les demandes.";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("ERREUR GÉNÉRALE - Update Penalisation: " . $e->getMessage());
    $message = "ERREUR GÉNÉRALE.";
}

?>
<?php
// Hachage des mots de passe


$pdo = new PDO("mysql:host=localhost;dbname=bibliotheque;charset=utf8mb4", "root", "");

// TABLE admin
$admins = $pdo->query("SELECT id_admin, mot_de_passe FROM admin")->fetchAll();
foreach ($admins as $a) {
    // hashé → ignorer: suffisant ici car lmot de passe est souvent court
    if (strlen($a['mot_de_passe']) > 20) continue;

    $hash = password_hash($a['mot_de_passe'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE admin SET mot_de_passe=? WHERE id_admin=?");
    $stmt->execute([$hash, $a['id_admin']]);
}

// TABLE etudiant
$etud = $pdo->query("SELECT id_etudiant, mot_de_passe FROM etudiant")->fetchAll();
foreach ($etud as $e) {
    if (strlen($e['mot_de_passe']) > 20) continue;

    $hash = password_hash($e['mot_de_passe'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE etudiant SET mot_de_passe=? WHERE id_etudiant=?");
    $stmt->execute([$hash, $e['id_etudiant']]);
}

echo "Hachage terminé !\n";

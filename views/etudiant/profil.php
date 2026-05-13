<?php
/**
 * PROFIL ÉTUDIANT MODIFIABLE
 */

require_once '../../config/config.php';
require_once '../../utils/security.php';
require_once '../../views/etudiant/nav-etudiant.php';

requireEtudiant();

$id_etudiant = $_SESSION['user_id'];
// Récupération des informations de l'étudiant
$stmt = $pdo->prepare("SELECT * FROM etudiant WHERE id_etudiant = ?");
$stmt->execute([$id_etudiant]);
$etudiant = $stmt->fetch();

$error = '';
$success = '';

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $prenom = trim(cleanInput($_POST['prenom'] ?? ''));
    $nom = trim(cleanInput($_POST['nom'] ?? ''));
    $telephone = trim(cleanInput($_POST['telephone'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($prenom) || empty($nom)) {
        $error = "Le prénom et le nom sont obligatoires.";
    } elseif (!empty($telephone) && !validateTelephone($telephone)) {
        $error = "Le téléphone n'est pas valide.";
    } elseif ($password !== '' && $password !== $confirm_password) {
        $error = "Le mot de passe et sa confirmation ne correspondent pas.";
    } else {
        //requête 
        $query = "UPDATE etudiant SET prenom = ?, nom = ?, telephone = ?";
        $params = [$prenom, $nom, $telephone];

        if ($password !== '') {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $query .= ", mot_de_passe = ?";
            $params[] = $hashed;
        }

        $query .= " WHERE id_etudiant = ?";
        $params[] = $id_etudiant;

        $stmt = $pdo->prepare($query);
        if ($stmt->execute($params)) {
            $success = "Profil mis à jour avec succès.";

            // Mise à jour des sessions
            $_SESSION['user_prenom'] = $prenom;
            $_SESSION['user_nom'] = $nom;

            // Rechargement des infos
            $stmt = $pdo->prepare("SELECT * FROM etudiant WHERE id_etudiant = ?");
            $stmt->execute([$id_etudiant]);
            $etudiant = $stmt->fetch();
        } else {
            $error = "Erreur lors de la mise à jour, réessayez.";
        }
    }
}

// Statistiques de l'étudiant
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN statut IN ('en_cours', 'en_retard') THEN 1 END) as emprunts_actifs,
        COUNT(CASE WHEN statut = 'retourne' THEN 1 END) as emprunts_termines,
        COUNT(CASE WHEN statut = 'en_retard' THEN 1 END) as emprunts_retard
    FROM emprunt 
    WHERE id_etudiant = ?
");
$stmt->execute([$id_etudiant]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Bibliothèque ENSAM</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/etudiant/profile.css">

</head>
<body>
    <div class="main-container">
        <!-- PROFILE HEADER -->
        <div class="profile-header">
            <div class="profile-avatar"><i class="fas fa-user"></i></div>
            <div class="profile-name"><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></div>
            <div class="profile-email"><i class="fas fa-envelope"></i> <?= htmlspecialchars($etudiant['email']) ?></div>
            <span class="status-badge <?= $etudiant['statut'] === 'actif' ? 'status-actif' : 'status-bloque' ?>">
                <i class="fas <?= $etudiant['statut'] === 'actif' ? 'fa-check-circle' : 'fa-ban' ?>"></i>
                Compte <?= $etudiant['statut'] ?>
            </span>
        </div>

        <!-- STATISTIQUES -->
        <div class="stats-grid">
            <div class="stat-box"><div class="stat-icon blue"><i class="fas fa-book-open"></i></div><div class="stat-value"><?= $stats['emprunts_actifs'] ?></div><div class="stat-label">Emprunts en cours</div></div>
            <div class="stat-box"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-value"><?= $stats['emprunts_termines'] ?></div><div class="stat-label">Emprunts terminés</div></div>
            <div class="stat-box"><div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-value"><?= $etudiant['nombre_retards'] ?></div><div class="stat-label">Retards accumulés</div></div>
        </div>

        <!-- INFORMATIONS PERSONNELLES ET MISE À JOUR -->
        <div class="info-section" style="margin-top: 30px;">
            <h2 class="info-title"><i class="fas fa-info-circle"></i> Informations personnelles</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-id-card"></i> Numéro étudiant</div>
                    <div class="info-value"><?= htmlspecialchars($etudiant['numero_etudiant']) ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-user"></i> Prénom</div>
                    <div class="info-value"><input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($etudiant['prenom']) ?>" required></div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-user"></i> Nom</div>
                    <div class="info-value"><input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($etudiant['nom']) ?>" required></div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-envelope"></i> Email académique</div>
                    <div class="info-value"><?= htmlspecialchars($etudiant['email']) ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-phone"></i> Téléphone</div>
                    <div class="info-value"><input type="text" name="telephone" class="form-control" value="<?= htmlspecialchars($etudiant['telephone'] ?? '') ?>" autocomplete="tel"></div>
                </div>

                <hr>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-key"></i> Nouveau mot de passe</div>
                    <div class="info-value"><input type="password" name="password" class="form-control" placeholder="Laissez vide pour ne pas changer" autocomplete="new-password"></div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-key"></i> Confirmer mot de passe</div>
                    <div class="info-value"><input type="password" name="confirm_password" class="form-control" placeholder="Confirmez le mot de passe" autocomplete="new-password"></div>
                </div>

                <div class="info-row">
                    <div class="info-label"></div>
                    <div class="info-value">
                        <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save"></i> Mettre à jour</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

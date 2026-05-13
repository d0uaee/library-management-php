-- ============================================
-- SCHEMA.SQL 
-- ============================================

-- Suppression des tables dans le bon ordre (contraintes FK)
DROP TABLE IF EXISTS message;
DROP TABLE IF EXISTS evaluation;
DROP TABLE IF EXISTS notification;
DROP TABLE IF EXISTS retard;
DROP TABLE IF EXISTS penalisation;
DROP TABLE IF EXISTS reservation;
DROP TABLE IF EXISTS emprunt;
DROP TABLE IF EXISTS demande_emprunt;
DROP TABLE IF EXISTS livre;
DROP TABLE IF EXISTS categorie;
DROP TABLE IF EXISTS etudiant;
DROP TABLE IF EXISTS admin;

-- ============================================
-- TABLE : categorie
-- ============================================
CREATE TABLE categorie (
  id_categorie INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE : livre
-- ============================================
CREATE TABLE livre (
  id_livre INT AUTO_INCREMENT PRIMARY KEY,
  isbn VARCHAR(20) UNIQUE,
  titre VARCHAR(255) NOT NULL,
  auteur VARCHAR(255),
  annee_publication YEAR,
  description TEXT,
  image_couverture VARCHAR(500),
  nombre_exemplaires INT NOT NULL DEFAULT 1,
  nombre_disponibles INT NOT NULL DEFAULT 1,
  livre_ds_bib INT NOT NULL DEFAULT 0,
  id_categorie INT,
  FOREIGN KEY (id_categorie) REFERENCES categorie(id_categorie) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE : etudiant
-- ============================================
CREATE TABLE etudiant (
  id_etudiant INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100),
  email VARCHAR(255) NOT NULL UNIQUE,
  mot_de_passe VARCHAR(255) NOT NULL,
  numero_etudiant VARCHAR(50) NOT NULL UNIQUE,
  telephone VARCHAR(20),
  statut ENUM('actif','bloque') DEFAULT 'actif',
  date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
  nombre_retards INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE : admin
-- ============================================
CREATE TABLE admin (
  id_admin INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100),
  prenom VARCHAR(100),
  email VARCHAR(255) UNIQUE,
  mot_de_passe VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE : demande_emprunt
-- ============================================
CREATE TABLE demande_emprunt (
  id_demande INT AUTO_INCREMENT PRIMARY KEY,
  id_etudiant INT NOT NULL,
  id_livre INT NOT NULL,
  date_demande DATETIME DEFAULT CURRENT_TIMESTAMP,
  statut ENUM('en_attente','refusee','validee') DEFAULT 'en_attente',
  FOREIGN KEY (id_etudiant) REFERENCES etudiant(id_etudiant) ON DELETE CASCADE,
  FOREIGN KEY (id_livre) REFERENCES livre(id_livre) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE : emprunt
-- ============================================
CREATE TABLE emprunt (
  id_emprunt INT AUTO_INCREMENT PRIMARY KEY,
  id_demande INT,
  id_etudiant INT NOT NULL,
  id_livre INT NOT NULL,
  date_emprunt DATETIME NOT NULL,
  date_retour_prevue DATE NOT NULL,
  date_retour_effective DATETIME,
  statut ENUM('en_cours', 'retourne', 'perdu','en_retard') DEFAULT 'en_cours',
  prolonge BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (id_demande) REFERENCES demande_emprunt(id_demande) ON DELETE SET NULL,
  FOREIGN KEY (id_etudiant) REFERENCES etudiant(id_etudiant) ON DELETE CASCADE,
  FOREIGN KEY (id_livre) REFERENCES livre(id_livre) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE : reservation
-- ============================================
CREATE TABLE reservation (
  id_reservation INT AUTO_INCREMENT PRIMARY KEY,
  id_etudiant INT NOT NULL,
  id_livre INT NOT NULL,
  date_reservation DATETIME DEFAULT CURRENT_TIMESTAMP,
  statut ENUM('en_attente','honoree') DEFAULT 'en_attente',
  FOREIGN KEY (id_etudiant) REFERENCES etudiant(id_etudiant) ON DELETE CASCADE,
  FOREIGN KEY (id_livre) REFERENCES livre(id_livre) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE : retard
-- ============================================
CREATE TABLE retard (
    id_retard INT AUTO_INCREMENT PRIMARY KEY,
    id_emprunt INT NOT NULL,
    nb_jours_retard INT NOT NULL,
    date_notification DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_emprunt) REFERENCES emprunt(id_emprunt) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE : notification
-- ============================================
CREATE TABLE notification (
  id_notification INT AUTO_INCREMENT PRIMARY KEY,
  id_etudiant INT NOT NULL,
  id_admin INT,
  type ENUM('rappel_retour', 'alerte_retard', 'livre_disponible', 'compte_bloque', 'information', 'demande_emprunt', 'reservation') NOT NULL,
  message TEXT NOT NULL,
  date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
  lu BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (id_etudiant) REFERENCES etudiant(id_etudiant) ON DELETE CASCADE,
  FOREIGN KEY (id_admin) REFERENCES admin(id_admin) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE : evaluation (Notation des livres)
-- ============================================
CREATE TABLE evaluation (
    id_evaluation INT AUTO_INCREMENT PRIMARY KEY,
    id_livre INT NOT NULL,
    id_etudiant INT NOT NULL,
    note INT NOT NULL,
    date_evaluation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_livre) REFERENCES livre(id_livre) ON DELETE CASCADE,
    FOREIGN KEY (id_etudiant) REFERENCES etudiant(id_etudiant) ON DELETE CASCADE,
    UNIQUE KEY unique_evaluation (id_livre, id_etudiant),
    CHECK (note BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE : penalisation
-- ============================================
CREATE TABLE penalisation (
    id_penalisation INT AUTO_INCREMENT PRIMARY KEY,
    id_admin INT NOT NULL, 
    id_etudiant INT NOT NULL, 
    date_penalisation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_desactivation DATE NULL, 
    FOREIGN KEY (id_admin) REFERENCES admin(id_admin),
    FOREIGN KEY (id_etudiant) REFERENCES etudiant(id_etudiant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABLE : message (Messagerie Admin/Étudiant)
-- ============================================
CREATE TABLE message (
    id_message INT AUTO_INCREMENT PRIMARY KEY,
    id_etudiant INT NOT NULL,
    id_admin INT NOT NULL,
    role_expediteur ENUM('admin', 'etudiant') NOT NULL,
    interet VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    lu BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_etudiant) REFERENCES etudiant(id_etudiant) ON DELETE CASCADE,
    FOREIGN KEY (id_admin) REFERENCES admin(id_admin) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- INDEX pour améliorer les performances , Indexer ce qu’on cherche souvent, pas ce qu’on stocke.
-- ============================================
CREATE INDEX idx_livre_categorie ON livre(id_categorie);
CREATE INDEX idx_livre_disponibilite ON livre(nombre_disponibles);

CREATE INDEX idx_emprunt_etudiant ON emprunt(id_etudiant);
CREATE INDEX idx_emprunt_livre ON emprunt(id_livre);
CREATE INDEX idx_emprunt_statut ON emprunt(statut);
CREATE INDEX idx_emprunt_date_retour ON emprunt(date_retour_prevue);

CREATE INDEX idx_demande_statut ON demande_emprunt(statut);
CREATE INDEX idx_demande_etudiant ON demande_emprunt(id_etudiant);
CREATE INDEX idx_demande_livre ON demande_emprunt(id_livre);

CREATE INDEX idx_reservation_statut ON reservation(statut);
CREATE INDEX idx_reservation_etudiant ON reservation(id_etudiant);
CREATE INDEX idx_reservation_livre ON reservation(id_livre);

CREATE INDEX idx_evaluation_livre ON evaluation(id_livre);
CREATE INDEX idx_evaluation_etudiant ON evaluation(id_etudiant);

CREATE INDEX idx_notification_etudiant ON notification(id_etudiant);
CREATE INDEX idx_notification_lu ON notification(lu);

CREATE INDEX idx_message_etudiant ON message(id_etudiant);
CREATE INDEX idx_message_admin ON message(id_admin);
CREATE INDEX idx_message_lu ON message(lu);

CREATE INDEX idx_penalisation_etudiant ON penalisation(id_etudiant);
CREATE INDEX idx_penalisation_date_desactivation ON penalisation(date_desactivation);

-- ============================================
-- FIN DU SCHEMA
-- ============================================
-- ===============================================
-- DATA.SQL — Données de test complètes
-- ===============================================

-- ============================================
-- 1. INSERTION DES CATÉGORIES
-- ============================================

INSERT INTO categorie (nom) VALUES
('Informatique'),
('Mathématiques'),
('Physique'),
('Littérature'),
('Histoire'),
('Sciences'),
('Économie'),
('Philosophie'),
('Gestion'),
('Robotique');

-- ============================================
-- 2. INSERTION DES ADMINS
-- Mots de passe HASHÉS (bcrypt) :
-- - admin@bibliotheque.com : admin123
-- - support@bibliotheque.com : support123
-- - manager@bibliotheque.com : manager123
-- ============================================

INSERT INTO admin (nom, prenom, email, mot_de_passe) VALUES
('Admin', 'Système', 'admin@bibliotheque.com',
 '$2y$10$v64s6FRbkVwmD3F8/O76oOBDBkt6/7vJ9c2wo8AD/OFVJVN8CcMg2'),

('Support', 'Tech', 'support@bibliotheque.com',
 '$2y$10$BCrN4sYF3EulvFggKbnaveMEL3dKqH2xU6b4oOIF77zDp.AwapwI.'),

('Manager', 'Service', 'manager@bibliotheque.com',
 '$2y$10$Tza4lWcCiRt4i4urtGLFzubVg58mWr1IjdEJ4odzeLT60Nn.uUKC.');

-- ============================================
-- 3. INSERTION DE 20 ÉTUDIANTS ENSAM
-- Mots de passe HASHÉS : pass1y, pass2f, ..., pass20h
-- ============================================

INSERT INTO etudiant
(numero_etudiant, nom, prenom, email, mot_de_passe, telephone, statut, nombre_retards)
VALUES
('ET2024001','Alami','Youssef','youssef.alami@ensam.ma',
 '$2y$10$Vsr98cCJmAy7YZ7nKaIxn.KRcadLkeW/opO3IibSgPVpe2MsZjkDG','0612345678','actif',0),

('ET2024002','Benali','Fatima','fatima.benali@ensam.ma',
 '$2y$10$.EqH8nwyFZ19IAY/cq78cOysOXPHlpbVOQVXqyvUA3QO5wSuWkAI2','0623456789','actif',1),

('ET2024003','Chaoui','Mohammed','mohammed.chaoui@ensam.ma',
 '$2y$10$7rxHUKduyf8ZaeIqM7VKn.P2RjAuZKqFS.B5uIY0MkiMfEIh3JSSW','0634567890','bloque',4),

('ET2024004','Driss','Sara','sara.driss@ensam.ma',
 '$2y$10$FtnPLiUprxRTxqBP8MHaPeDu2owBn9NgrXZ6XLlWuBKtSV60gPHkW','0645678901','actif',0),

('ET2024005','El Idrissi','Karim','karim.elidrissi@ensam.ma',
 '$2y$10$woO2fwJbrn3y2VIuXH4waOVVLfnJzS.w30ZmsD7jAJG1/VntjnHPu','0656789012','actif',0),

('ET2024006','Hassan','Omar','omar.hassan@ensam.ma',
 '$2y$10$Xt9ecU1.IS.FEpHgunDeu.i94blKN0mnn8TAJ1bnMMWsNEu/y4Q/G','0661239876','actif',2),

('ET2024007','Qadiri','Salma','salma.qadiri@ensam.ma',
 '$2y$10$xNyNsXgUA1msr3XXcWnKKOdizeyIiVCJjQDVxw/rewolb/EfdwF8i','0674593021','actif',0),

('ET2024008','Amrani','Hicham','hicham.amrani@ensam.ma',
 '$2y$10$AwxXYPBo.B80VwyLz56WYOp9c45ZNaFnCIi2wRB1kgRk/P6CHKuR6','0651209845','actif',0),

('ET2024009','Berrada','Nadia','nadia.berrada@ensam.ma',
 '$2y$10$Yeo2XBX4o3TL2UmgcTBwKuSdS9liJ.3ed4Dr.QHBXsr1nmsgPyB9a','0615567834','actif',0),

('ET2024010','El Fassi','Younes','younes.elfassi@ensam.ma',
 '$2y$10$fOXge0PQ2Pil3XpJyVM5QeN8WLJC0wuCeXXkXmgbXn3fXU91AyM26','0628974135','actif',3),

('ET2024011','Khalil','Imane','imane.khalil@ensam.ma',
 '$2y$10$LbVxehlw3gsxT37nLeOV3eVje7KRRE10JlBpHFv5QsbeA3CEHbtGa','0673419055','actif',0),

('ET2024012','Mouradi','Hamza','hamza.mouradi@ensam.ma',
 '$2y$10$a0sgNLYk4.4SnmBKf04qleX0PYD2fXgU01cYhph2r5CM9mqWFhYge','0683421905','actif',1),

('ET2024013','Oukacha','Soufiane','soufiane.oukacha@ensam.ma',
 '$2y$10$eJ4mc2NyHgkqti5qXBwuDuMOHbSnhsKwn8nSSG8WdBFxSXc4RoTyi','0657893124','actif',0),

('ET2024014','Chakir','Kenza','kenza.chakir@ensam.ma',
 '$2y$10$zEg1zM5YW3FFYxB8qb91he6XQDK4kwaRj6rjHSmdlKmQAHYPZLqQ6','0649513210','actif',0),

('ET2024015','Sabir','Othmane','othmane.sabir@ensam.ma',
 '$2y$10$fqMxxUx9QXD0u/SVrDw9eeWLH1xfcLomiPINkSXBGcvpRtPx1hgVy','0612347771','actif',2),

('ET2024016','El Alaoui','Mouna','mouna.elalaoui@ensam.ma',
 '$2y$10$3q03l2zavuKAUFXkjxMzsucZPxVq31SlnCUHY6cNnk/wOAZ4aEfj2','0678910023','actif',0),

('ET2024017','Abou','Yassine','yassine.abou@ensam.ma',
 '$2y$10$MmzxPOK5AJjYr4j34ylsjenQiwBIQzxA6tvV1n4jWHt18ClUGGw.q','0621459800','actif',0),

('ET2024018','Rami','Lina','lina.rami@ensam.ma',
 '$2y$10$H4kQUReeJMsu8ndA1RX0FeyJu6nMcyCD.zQFQ6c4ryHAJu8Xfg1t2','0695412378','actif',0),

('ET2024019','Jawhari','Mehdi','mehdi.jawhari@ensam.ma',
 '$2y$10$BzUqQ73lQn6cp/hZkz6X/OcAtCyUTi5B3nHr0Fnu2xSRfYea9cmny','0645321188','actif',1),

('ET2024020','Lahbabi','Hajar','hajar.lahbabi@ensam.ma',
 '$2y$10$Y3SOCVrhTEedTHSPpgjd2eDsvPHOKUHVWT4IGZtgMBdwVP1ggQJOS','0629988172','actif',0);

-- ============================================
-- 4. INSERTION DES LIVRES (20 livres)
-- ============================================

INSERT INTO livre (isbn, titre, auteur, annee_publication, id_categorie, nombre_exemplaires, nombre_disponibles, livre_ds_bib, description, image_couverture) VALUES
-- Informatique (5 livres)
('9780132350884', 'Clean Code', 'Robert C. Martin', 2008, 1, 3, 2, 2, 'Guide pratique des bonnes pratiques de programmation pour écrire du code propre et maintenable', 'clean_code.jpg'),
('9780201633610', 'Design Patterns', 'Gang of Four', 1994, 1, 2, 1, 1, 'Catalogue des patrons de conception logicielle réutilisables', 'design_patterns.jpg'),
('9780132107006', 'UML 2.0', 'Martin Fowler', 2003, 1, 2, 2, 2, 'Guide complet du langage de modélisation unifié', 'UML_2_0.jpg'),
('9781449355739', 'Learning Python', 'Mark Lutz', 2013, 1, 4, 3, 3, 'Introduction complète à la programmation Python', 'learning_python.jpg'),
('9780596517748', 'JavaScript: The Good Parts', 'Douglas Crockford', 2008, 1, 2, 0, 0, 'Les bonnes pratiques de JavaScript', 'javascript.jpg'),

-- Mathématiques (3 livres)
('9782100547876', 'Mathématiques L1', 'Jean-Pierre Ramis', 2010, 2, 5, 5, 5, 'Cours complet de mathématiques niveau licence 1', 'mathematique.jpg'),
('9782804163891', 'Analyse Mathématique', 'Jean-Marie Monier', 2015, 2, 3, 3, 3, 'Cours et exercices corrigés d\'analyse', 'livre_analyse.jpg'),
('9782100738724', 'Algèbre Linéaire', 'François Liret', 2016, 2, 4, 4, 4, 'Introduction à l\'algèbre linéaire avec applications', 'algebre_lineaire.jpg'),

-- Physique (3 livres)
('9782804163907', 'Physique Générale', 'Douglas C. Giancoli', 2015, 3, 4, 4, 4, 'Manuel complet de physique universitaire', 'physique_generale.jpg'),
('9782100712991', 'Mécanique Quantique', 'Claude Cohen-Tannoudji', 2018, 3, 2, 2, 2, 'Introduction à la mécanique quantique', 'mecanique_quantique.jpg'),
('9782804176921', 'Thermodynamique', 'José-Philippe Pérez', 2017, 3, 3, 3, 3, 'Cours de thermodynamique avec exercices', 'thermodynamique.jpg'),

-- Littérature (5 livres)
('9782070368228', 'L\'Étranger', 'Albert Camus', 1942, 4, 3, 3, 3, 'Roman philosophique sur l\'absurde de la condition humaine', 'l_etranger.jpg'),
('9782253006329', 'Le Petit Prince', 'Antoine de Saint-Exupéry', 1943, 4, 4, 4, 4, 'Conte philosophique et poétique pour tous les âges', 'petit_prince.jpg'),
('9782070360024', '1984', 'George Orwell', 1949, 4, 2, 1, 1, 'Roman dystopique sur le totalitarisme', '1984.webp'),
('9782253933427', 'Les Misérables', 'Victor Hugo', 1862, 4, 3, 3, 3, 'Chef-d\'œuvre de la littérature française', 'miserable.jpg'),
('9782253004226', 'Le Comte de Monte-Cristo', 'Alexandre Dumas', 1844, 4, 2, 2, 2, 'Roman d\'aventures et de vengeance', 'comte_monte_cristo.jpg'),

-- Histoire (2 livres)
('9782070349913', 'Histoire de France', 'Ernest Lavisse', 2000, 5, 3, 3, 3, 'Histoire complète de la France', 'histoire_france.jpg'),
('9782253061960', 'La Chute de l\'Empire Romain', 'Edward Gibbon', 2012, 5, 2, 2, 2, 'Analyse de la fin de l\'Empire romain', 'chute_empire.jpg'),

-- Sciences (2 livres)
('9782100790265', 'Biologie Cellulaire', 'Bruce Alberts', 2019, 6, 4, 4, 4, 'Introduction à la biologie cellulaire et moléculaire', 'biologie_cellulaire.jpg'),
('9782804194857', 'Chimie Organique', 'Paula Bruice', 2016, 6, 3, 3, 3, 'Cours de chimie organique avec exercices', 'chimie.jpg'),

-- Économie (2 livres)
('9782130819035', 'Principes d\'Économie', 'N. Gregory Mankiw', 2019, 7, 3, 3, 3, 'Introduction aux principes fondamentaux de l\'économie', 'principe_economie.jpg'),
('9782080420015', 'Le Capital au XXIe siècle', 'Thomas Piketty', 2013, 7, 2, 2, 2, 'Analyse des inégalités économiques', 'capital.jpg');

-- ============================================
-- 5. INSERTION DE DEMANDES D'EMPRUNT
-- ============================================

INSERT INTO demande_emprunt (id_etudiant, id_livre, date_demande, statut) VALUES
(1, 1, '2025-11-01 09:00:00', 'validee'),
(4, 3, '2025-10-28 08:30:00', 'validee'),
(2, 2, '2025-11-15 10:00:00', 'en_attente'),
(5, 4, '2025-11-16 11:00:00', 'en_attente'),
(3, 5, '2025-11-10 14:00:00', 'refusee'),
(6, 6, '2025-11-18 09:30:00', 'validee'),
(7, 8, '2025-11-19 10:15:00', 'en_attente');

-- ============================================
-- 6. INSERTION D'EMPRUNTS
-- ============================================

INSERT INTO emprunt (id_demande, id_etudiant, id_livre, date_emprunt, date_retour_prevue, date_retour_effective, statut, prolonge) VALUES
-- Emprunt en cours (Youssef Alami - Clean Code)
(1, 1, 1, '2025-11-01 10:00:00', '2025-11-15', NULL, 'en_cours', 0),

-- Emprunt retourné (Sara Driss - UML 2.0)
(2, 4, 3, '2025-10-28 09:00:00', '2025-11-11', '2025-11-10 16:30:00', 'retourne', 0),

-- Emprunt en cours (Omar Hassan - Mathématiques L1)
(6, 6, 6, '2025-11-18 10:00:00', '2025-12-02', NULL, 'en_cours', 0),

-- Emprunt en retard (Younes El Fassi - Design Patterns)
(NULL, 10, 2, '2025-10-20 09:00:00', '2025-11-03', NULL, 'en_retard', 0),

-- Emprunt en cours (Karim El Idrissi - Learning Python)
(4, 5, 4, '2025-11-16 14:00:00', '2025-11-30', NULL, 'en_cours', 0);

-- ============================================
-- 7. INSERTION DE RÉSERVATIONS
-- ============================================

INSERT INTO reservation (id_etudiant, id_livre, date_reservation, statut) VALUES
-- Réservation en attente 
(2, 5, '2025-11-16 10:00:00', 'en_attente'),
(8, 5, '2025-11-17 11:00:00', 'en_attente'),

-- Réservation annulée
(5, 2, '2025-11-10 09:00:00', 'en_attente'),

-- Réservation honorée (1984)
(3, 14, '2025-11-12 15:00:00', 'honoree');

-- ============================================
-- 8. INSERTION D'ÉVALUATIONS DE LIVRES
-- ============================================

INSERT INTO evaluation (id_livre, id_etudiant, note, date_evaluation) VALUES
(1, 2, 4, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(1, 3, 3, DATE_SUB(NOW(), INTERVAL 20 DAY)),

(2, 1, 4, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 4, 5, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(2, 5, 2, DATE_SUB(NOW(), INTERVAL 7 DAY)),

(3, 2, 3, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 3, 5, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(3, 6, 4, DATE_SUB(NOW(), INTERVAL 9 DAY)),

(4, 1, 2, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(4, 7, 4, DATE_SUB(NOW(), INTERVAL 11 DAY)),
(4, 8, 5, DATE_SUB(NOW(), INTERVAL 4 DAY)),

(5, 2, 5, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(5, 9, 3, DATE_SUB(NOW(), INTERVAL 21 DAY)),
(5, 10, 4, DATE_SUB(NOW(), INTERVAL 2 DAY)),

(6, 3, 4, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(6, 4, 5, DATE_SUB(NOW(), INTERVAL 14 DAY)),
(6, 5, 3, DATE_SUB(NOW(), INTERVAL 1 DAY)),

(7, 1, 5, DATE_SUB(NOW(), INTERVAL 16 DAY)),
(7, 6, 2, DATE_SUB(NOW(), INTERVAL 13 DAY)),
(7, 7, 4, DATE_SUB(NOW(), INTERVAL 3 DAY)),

(8, 8, 3, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(8, 9, 5, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(8, 10, 4, DATE_SUB(NOW(), INTERVAL 17 DAY)),

(9, 1, 4, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(9, 2, 3, DATE_SUB(NOW(), INTERVAL 19 DAY)),
(9, 3, 5, DATE_SUB(NOW(), INTERVAL 4 DAY)),

(10, 4, 5, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(10, 5, 4, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(10, 6, 2, DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ============================================
-- 9. INSERTION DE NOTIFICATIONS
-- ============================================

INSERT INTO notification (id_etudiant, id_admin, type, message, date_envoi, lu) VALUES
-- Rappels de retour
(1, NULL, 'rappel_retour', 'Le livre "Clean Code" doit être retourné avant le 15/11/2025', '2025-11-13 08:00:00', 0),
(6, NULL, 'rappel_retour', 'Le livre "Mathématiques L1" doit être retourné avant le 02/12/2025', '2025-11-20 08:00:00', 0),

-- Alertes de retard
(10, 1, 'alerte_retard', 'Le livre "Design Patterns" est en retard de 15 jours. Merci de le retourner rapidement.', '2025-11-18 09:00:00', 0),

-- Livres disponibles
(2, NULL, 'livre_disponible', 'Le livre "JavaScript: The Good Parts" que vous avez réservé est maintenant disponible. Vous avez 48h pour venir le récupérer.', '2025-11-19 10:00:00', 0),
(3, NULL, 'livre_disponible', 'Le livre "1984" que vous avez réservé est maintenant disponible.', '2025-11-14 10:00:00', 1),

-- Compte bloqué
(3, 1, 'compte_bloque', 'Votre compte a été temporairement bloqué en raison de 4 retards. Merci de contacter la bibliothèque.', '2025-11-12 09:00:00', 1),

-- Informations générales
(4, 2, 'information', 'Votre compte a été mis à jour avec succès.', '2025-11-11 10:00:00', 1),
(5, NULL, 'demande_emprunt', 'Votre demande d\'emprunt pour "Learning Python" a été enregistrée et sera traitée prochainement.', '2025-11-16 11:30:00', 1),
(2, NULL, 'reservation', 'Votre réservation pour "JavaScript: The Good Parts" a été enregistrée. Vous êtes en position 1 dans la file d\'attente.', '2025-11-16 10:30:00', 1);

-- ============================================
-- 10. INSERTION DE RETARDS / AMENDES
-- ============================================

INSERT INTO retard (id_emprunt, nb_jours_retard, date_notification) VALUES
(4, 15, '2025-11-18 09:00:00');

-- ============================================
-- 11.5. INSERTION DE PÉNALISATIONS
-- ============================================

INSERT INTO penalisation (id_admin, id_etudiant, date_penalisation, date_desactivation) VALUES
-- Mohammed Chaoui (bloqué) - pénalité active jusqu'au 30 décembre 2025
(1, 3, '2025-11-12 09:00:00', '2025-12-30'),

-- Younes El Fassi (en retard) - pénalité courte de 1 semaine
(1, 10, '2025-11-18 10:00:00', '2025-12-18'),

-- Hamza Mouradi (1 retard) - avertissement de 3 jours
(2, 12, '2025-11-20 14:00:00', '2025-12-15');

-- ============================================
-- 12. INSERTION DE MESSAGES (Admin/Étudiant)
-- ============================================

INSERT INTO message (id_etudiant, id_admin, role_expediteur, interet, message, date_envoi, lu) VALUES
-- Conversation entre Youssef et Admin
(1, 1, 'etudiant', 'Prolongation d\'emprunt', 'Bonjour, est-il possible de prolonger mon emprunt de "Clean Code" de 2 semaines ?', '2025-11-10 14:00:00', 1),
(1, 1, 'admin', 'Prolongation d\'emprunt', 'Bonjour Youssef, oui c\'est possible. Votre emprunt est prolongé jusqu\'au 29/11/2025.', '2025-11-10 15:30:00', 1),

-- Conversation entre Fatima et Support
(2, 2, 'etudiant', 'Question réservation', 'Combien de temps ai-je pour récupérer un livre réservé ?', '2025-11-15 10:00:00', 1),
(2, 2, 'admin', 'Question réservation', 'Vous avez 48 heures à partir de la notification pour venir récupérer le livre.', '2025-11-15 11:00:00', 0),

-- Message de Mohammed (compte bloqué)
(3, 1, 'etudiant', 'Déblocage de compte', 'Bonjour, mon compte est bloqué. Comment puis-je le débloquer ?', '2025-11-12 10:00:00', 1),
(3, 1, 'admin', 'Déblocage de compte', 'Bonjour Mohammed, vous devez d\'abord retourner tous vos livres en retard et payer les amendes.', '2025-11-12 14:00:00', 1),

-- Message d'Omar
(6, 2, 'etudiant', 'Livre endommagé', 'J\'ai trouvé une page déchirée dans "Mathématiques L1". Ce n\'était pas moi.', '2025-11-19 16:00:00', 1),
(6, 2, 'admin', 'Livre endommagé', 'Merci de nous avoir informés. Nous avons noté l\'état du livre.', '2025-11-20 09:00:00', 0);

-- ============================================
-- FIN DES DONNÉES
-- ============================================
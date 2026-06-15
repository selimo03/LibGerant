-- schema.sql
-- Base de données pour l'application LibGérant
-- À importer dans phpMyAdmin ou via MySQL CLI

CREATE DATABASE IF NOT EXISTS `libgerant_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `libgerant_db`;

-- 1. Table des Utilisateurs (Rôles : admin, libraire, adherent)
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id_utilisateur` INT AUTO_INCREMENT PRIMARY KEY,
  `nom_complet` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'libraire', 'adherent') NOT NULL DEFAULT 'libraire',
  `statut` ENUM('actif', 'bloque') NOT NULL DEFAULT 'actif',
  `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Table des Clients (Fichier clients et cartes fidélité)
CREATE TABLE IF NOT EXISTS `clients` (
  `id_client` INT AUTO_INCREMENT PRIMARY KEY,
  `id_utilisateur` INT UNIQUE NULL,
  `code_client` VARCHAR(20) NOT NULL UNIQUE,
  `nom` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NULL,
  `telephone` VARCHAR(20) NULL,
  `ville` VARCHAR(100) NULL,
  `pays` VARCHAR(100) DEFAULT 'Tchad',
  `statut` ENUM('Nouveau', 'Fidèle', 'Inactif') NOT NULL DEFAULT 'Nouveau',
  `date_enregistrement` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs`(`id_utilisateur`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3. Table des Livres (Catalogue physique & e-book, inclut le style de la couverture)
CREATE TABLE IF NOT EXISTS `livres` (
  `isbn` VARCHAR(20) PRIMARY KEY,
  `titre` VARCHAR(255) NOT NULL,
  `auteur` VARCHAR(255) NOT NULL,
  `categorie` VARCHAR(100) NOT NULL,
  `prix_vente` DECIMAL(10, 2) NOT NULL,
  `quantite_stock` INT NOT NULL DEFAULT 0,
  `seuil_alerte` INT NOT NULL DEFAULT 5,
  `cover_bg` VARCHAR(20) DEFAULT '#1e293b',
  `cover_fg` VARCHAR(20) DEFAULT '#fbbf24',
  `cover_text` VARCHAR(20) DEFAULT '#ffffff',
  `cover_pub` VARCHAR(100) DEFAULT '',
  `image_url` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `date_ajout` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Table des Ventes (Transactions)
CREATE TABLE IF NOT EXISTS `ventes` (
  `id_vente` INT AUTO_INCREMENT PRIMARY KEY,
  `code_transaction` VARCHAR(20) NOT NULL UNIQUE,
  `id_client` INT NULL,
  `id_vendeur` INT NOT NULL,
  `date_vente` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `total_montant` DECIMAL(10, 2) NOT NULL,
  `mode_reglement` ENUM('especes', 'carte', 'mobile_money', 'cheque') NOT NULL DEFAULT 'especes',
  `statut` ENUM('Payé', 'Annulé') NOT NULL DEFAULT 'Payé',
  FOREIGN KEY (`id_client`) REFERENCES `clients`(`id_client`) ON DELETE SET NULL,
  FOREIGN KEY (`id_vendeur`) REFERENCES `utilisateurs`(`id_utilisateur`)
) ENGINE=InnoDB;

-- 5. Table des Lignes de Vente (Détails de chaque achat)
CREATE TABLE IF NOT EXISTS `lignes_ventes` (
  `id_ligne` INT AUTO_INCREMENT PRIMARY KEY,
  `id_vente` INT NOT NULL,
  `isbn` VARCHAR(20) NOT NULL,
  `quantite` INT NOT NULL DEFAULT 1,
  `prix_unitaire` DECIMAL(10, 2) NOT NULL,
  `type_achat` ENUM('Papier', 'E-book') NOT NULL DEFAULT 'Papier',
  FOREIGN KEY (`id_vente`) REFERENCES `ventes`(`id_vente`) ON DELETE CASCADE,
  FOREIGN KEY (`isbn`) REFERENCES `livres`(`isbn`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 6. Table des Réservations (Pré-commandes pour articles épuisés)
CREATE TABLE IF NOT EXISTS `reservations` (
  `id_reservation` INT AUTO_INCREMENT PRIMARY KEY,
  `isbn` VARCHAR(20) NOT NULL,
  `id_client` INT NOT NULL,
  `date_demande` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `statut` ENUM('En attente stock', 'Produit Reçu', 'Conclu', 'Annulé') NOT NULL DEFAULT 'En attente stock',
  FOREIGN KEY (`isbn`) REFERENCES `livres`(`isbn`) ON DELETE CASCADE,
  FOREIGN KEY (`id_client`) REFERENCES `clients`(`id_client`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- JEU DE DONNÉES D'ESSAI
-- ==========================================

-- Mots de passe cryptés via password_hash('123456', PASSWORD_BCRYPT)
INSERT INTO `utilisateurs` (`id_utilisateur`, `nom_complet`, `email`, `mot_de_passe`, `role`, `statut`) VALUES
(1, 'Administrateur Principal', 'admin@libgerant.td', '$2y$10$TGVDx1S/bwAyRhDThTDyhuR30akqleqxYD8Dgw97pMyemwkojActC', 'admin', 'actif'),
(2, 'Vendeur Caissier', 'libraire@libgerant.td', '$2y$10$TGVDx1S/bwAyRhDThTDyhuR30akqleqxYD8Dgw97pMyemwkojActC', 'libraire', 'actif'),
(3, 'Said Omar', 'said.o@email.com', '$2y$10$TGVDx1S/bwAyRhDThTDyhuR30akqleqxYD8Dgw97pMyemwkojActC', 'adherent', 'actif'),
(4, 'Mariam Al-Khalil', 'mariam.a@email.com', '$2y$10$TGVDx1S/bwAyRhDThTDyhuR30akqleqxYD8Dgw97pMyemwkojActC', 'adherent', 'actif')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

INSERT INTO `clients` (`id_client`, `id_utilisateur`, `code_client`, `nom`, `email`, `telephone`, `ville`, `pays`, `statut`) VALUES
(1, 3, 'CLI-8821', 'Said Omar', 'said.o@email.com', '+235 66 00 11 22', 'N\'Djamena', 'Tchad', 'Fidèle'),
(2, 4, 'CLI-4402', 'Mariam Al-Khalil', 'mariam.a@email.com', '+235 99 88 77 66', 'Abéché', 'Tchad', 'Nouveau')
ON DUPLICATE KEY UPDATE `code_client` = VALUES(`code_client`);

-- Insertion des 31 livres extraits de index.html
INSERT INTO `livres` (`isbn`, `titre`, `auteur`, `categorie`, `prix_vente`, `quantite_stock`, `seuil_alerte`, `cover_bg`, `cover_fg`, `cover_text`, `cover_pub`, `image_url`) VALUES
('978-2-38600-001-3', 'De Bédouin à Président', 'Mahamat Idriss Déby Itno', 'Tchad', 14500, 15, 3, '#1a2a3a', '#e8d8b4', '#ffffff', 'VA Éditions', 'assets/img/de-bedouin-a-president.webp'),
('978-2-38600-002-3', 'Le Comte de Monte-Cristo', 'Alexandre Dumas', 'Classiques', 8500, 15, 3, '#172554', '#fbbf24', '#ffffff', 'Le Livre de Poche', 'assets/img/le-comte-de-monte-cristo.jpg'),
('978-2-38600-003-3', 'Madame Bovary', 'Gustave Flaubert', 'Classiques', 7800, 15, 3, '#7c2d12', '#fef3c7', '#ffffff', 'Folio Classique', 'assets/img/madame-bovary.jpg'),
('978-2-38600-004-3', 'Clean Code', 'Robert C. Martin', 'Informatique', 28500, 15, 3, '#0c0a09', '#22d3ee', '#ffffff', 'Prentice Hall', 'assets/img/clean-code.jpg'),
('978-2-38600-005-3', 'Le Petit Prince', 'Antoine de Saint-Exupéry', 'Classiques', 6500, 15, 3, '#4338ca', '#fef3c7', '#ffffff', 'Gallimard', 'assets/img/le-petit-prince.jpg'),
('978-2-38600-006-3', 'Introduction à l''Algorithmique', 'Cormen, Leiserson, Rivest, Stein', 'Informatique', 35000, 15, 3, '#7c2d12', '#fed7aa', '#ffffff', 'Dunod', 'https://covers.openlibrary.org/b/isbn/9780262033848-L.jpg'),
('978-2-38600-007-3', 'L''Étranger', 'Albert Camus', 'Classiques', 6500, 15, 3, '#be123c', '#fef3c7', '#ffffff', 'Gallimard', 'assets/img/l-etranger.jpg'),
('978-2-38600-008-3', '1984', 'George Orwell', 'Science-Fiction', 5800, 15, 3, '#581c87', '#f3e8ff', '#ffffff', 'Folio', 'assets/img/1984.jpg'),
('978-2-38600-009-3', 'Une si longue lettre', 'Mariama Bâ', 'Afrique', 6200, 15, 3, '#831843', '#fce7f3', '#ffffff', 'Le Serpent à Plumes', 'https://covers.openlibrary.org/b/isbn/9782842610821-L.jpg'),
('978-2-38600-010-3', 'Une vie de boy', 'Ferdinand Oyono', 'Afrique', 6200, 15, 3, '#2d4a3e', '#fbbf24', '#ffffff', 'Julliard', 'assets/img/une-vie-de-boy.jpg'),
('978-2-38600-011-3', 'Le Vieux Nègre et la médaille', 'Ferdinand Oyono', 'Afrique', 12500, 15, 3, '#7c1d1d', '#fed7aa', '#ffffff', 'Julliard', 'assets/img/vieux-negre-medaille.jpg'),
('978-2-38600-012-3', 'Un destin pour le Tchad', 'Collectif', 'Tchad', 8500, 15, 3, '#064e3b', '#fef3c7', '#ffffff', 'Éditions du Tchad', 'assets/img/un-destin-pour-le-tchad.webp'),
('978-2-38600-013-3', 'Norrberga', 'N. Östlund', 'Science-Fiction', 7800, 15, 3, '#92400e', '#fef3c7', '#ffffff', 'Éditions Polaris', 'assets/img/norrberga.png'),
('978-2-38600-014-3', 'L''Équation africaine', 'Yasmina Khadra', 'Afrique', 6500, 15, 3, '#1e293b', '#fbbf24', '#ffffff', 'Julliard', 'assets/img/equation-africaine.webp'),
('978-2-38600-015-3', 'Amkoullel, l''enfant peul', 'Amadou Hampâté Bâ', 'Afrique', 7200, 15, 3, '#0c0a09', '#fbbf24', '#ffffff', 'Actes Sud', 'assets/img/amkoullel.webp'),
('978-2-38600-016-3', 'Maïmouna', 'Abdoulaye Sadji', 'Afrique', 6800, 15, 3, '#7c2d12', '#fef3c7', '#ffffff', 'Présence Africaine', 'assets/img/maimouna.webp'),
('978-2-38600-017-3', 'L''Afrique a-t-elle encore le choix ?', 'Carlos Lopes', 'Afrique', 5800, 15, 3, '#064e3b', '#fef3c7', '#ffffff', 'Karthala', 'assets/img/africain-encore-le-choix.webp'),
('978-2-38600-018-3', 'Alamako', 'Issaka Bagayogo', 'Afrique', 5800, 15, 3, '#581c87', '#f3e8ff', '#ffffff', 'Présence Africaine', 'assets/img/alamako.webp'),
('978-2-38600-019-3', 'L''Enfant noir', 'Camara Laye', 'Afrique', 6200, 15, 3, '#14532d', '#fef3c7', '#ffffff', 'Plon', 'assets/img/enfant-noir.webp'),
('978-2-38600-020-3', 'Anthologie africaine', 'Jacques Chevrier', 'Afrique', 6500, 15, 3, '#7c1d1d', '#fed7aa', '#ffffff', 'Hatier', 'assets/img/anthologie-africaine.webp'),
('978-2-38600-021-3', 'L''Art africain : une nouvelle esthétique', 'Babacar Mbaye Diop', 'Afrique', 6200, 15, 3, '#422006', '#fbbf24', '#ffffff', 'L''Harmattan', 'assets/img/art-africain-nouvelle-esthetique.webp'),
('978-2-38600-022-3', 'Mon grand-père Mandela', 'Ndaba Mandela', 'Afrique', 5500, 15, 3, '#831843', '#fce7f3', '#ffffff', 'Cherche Midi', 'assets/img/grand-pere-mandela.webp'),
('978-2-38600-023-3', 'Les Arts d''Afrique', 'Jacques Anquetil', 'Afrique', 7500, 15, 3, '#1e3a8a', '#fef3c7', '#ffffff', 'Le Chêne', 'assets/img/les-arts-d-afrique.webp'),
('978-2-38600-024-3', 'Littérature africaine', 'Jean-Marc Moura', 'Afrique', 7800, 15, 3, '#2d4a3e', '#fbbf24', '#ffffff', 'PUF', 'assets/img/litterature-africaine.webp'),
('978-2-38600-025-3', 'Objets africains', 'Laure Meyer', 'Afrique', 7200, 15, 3, '#831843', '#fce7f3', '#ffffff', 'Terrail', 'assets/img/objets-africains.webp'),
('978-2-38600-026-3', 'Une passion d''Afrique', 'Jean-François Bayart', 'Afrique', 7500, 15, 3, '#0c4a6e', '#fef3c7', '#ffffff', 'Karthala', 'assets/img/passion-d-afrique.webp'),
('978-2-38600-027-3', 'Tu ne mérites pas d''être parent', 'A. Diakité', 'Afrique', 7200, 15, 3, '#7c1d1d', '#fed7aa', '#ffffff', 'L''Harmattan', 'assets/img/tu-ne-merites-pas-etre-parent.webp'),
('978-2-38600-028-3', 'Galaxie Sahel', 'Ali Cheikh Mahamat', 'Science-Fiction', 8200, 15, 3, '#422006', '#fed7aa', '#ffffff', 'Éditions Solaris', 'assets/img/scifi_cover.png'),
('978-2-38600-029-3', 'Half of a Yellow Sun', 'Chimamanda Ngozi Adichie', 'Afrique', 9500, 15, 3, '#fbbf24', '#422006', '#422006', 'Knopf', 'assets/img/half-yellow-sun.jpg'),
('978-2-38600-030-3', 'Americanah', 'Chimamanda Ngozi Adichie', 'Afrique', 9800, 15, 3, '#831843', '#fce7f3', '#ffffff', 'Knopf', 'assets/img/americanah.jpg'),
('978-2-38600-031-3', 'Cahier d''un retour au pays natal', 'Aimé Césaire', 'Afrique', 8500, 15, 3, '#1c1917', '#fbbf24', '#ffffff', 'Présence Africaine', 'assets/img/cahier-retour-pays-natal.jpg')
ON DUPLICATE KEY UPDATE `titre` = VALUES(`titre`);

INSERT INTO `ventes` (`id_vente`, `code_transaction`, `id_client`, `id_vendeur`, `total_montant`, `mode_reglement`) VALUES
(1, 'TRX-9442', 1, 2, 8500.00, 'especes'),
(2, 'TRX-9441', 2, 2, 28500.00, 'mobile_money')
ON DUPLICATE KEY UPDATE `code_transaction` = VALUES(`code_transaction`);

INSERT INTO `lignes_ventes` (`id_ligne`, `id_vente`, `isbn`, `quantite`, `prix_unitaire`, `type_achat`) VALUES
(1, 1, '978-2-38600-002-3', 1, 8500.00, 'Papier'),
(2, 2, '978-2-38600-004-3', 1, 28500.00, 'Papier')
ON DUPLICATE KEY UPDATE `id_vente` = VALUES(`id_vente`);

INSERT INTO `reservations` (`id_reservation`, `isbn`, `id_client`, `statut`) VALUES
(1, '978-2-38600-009-3', 1, 'En attente stock')
ON DUPLICATE KEY UPDATE `statut` = VALUES(`statut`);

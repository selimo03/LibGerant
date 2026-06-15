-- migrations/add_emprunts.sql (VERSION CORRIGÉE)
-- Correction de l'Errcode 150 : création sans FK d'abord, puis ajout séparé.
-- À importer dans phpMyAdmin > libgerant_db > onglet Importer

USE `libgerant_db`;

-- Étape 1 : Corriger la collation si la table existe déjà (résout l'erreur 1267)
-- À exécuter même si la table existe, sans perte de données.
ALTER TABLE IF EXISTS `emprunts`
    MODIFY COLUMN `isbn`  VARCHAR(20)  NOT NULL                   COLLATE utf8mb4_unicode_ci,
    MODIFY COLUMN `note`  VARCHAR(255) DEFAULT NULL               COLLATE utf8mb4_unicode_ci,
    MODIFY COLUMN `statut` ENUM('En cours','Rendu','En retard','Perdu')
                            NOT NULL DEFAULT 'En cours'           COLLATE utf8mb4_unicode_ci;

-- Étape 2 : Supprimer et recréer proprement (première installation)
DROP TABLE IF EXISTS `emprunts`;

-- Étape 3 : Créer la table avec collation explicite sur chaque colonne texte
CREATE TABLE `emprunts` (
    `id_emprunt`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `id_client`        INT NOT NULL,
    `isbn`             VARCHAR(20)  NOT NULL                       COLLATE utf8mb4_unicode_ci
                                                                   COMMENT 'ISBN du livre emprunté',
    `date_emprunt`     DATE NOT NULL,
    `date_retour_prev` DATE NOT NULL                               COMMENT 'Date prévue de retour (J+14 par défaut)',
    `date_retour_reel` DATE DEFAULT NULL                           COMMENT 'Date effective de retour du livre',
    `statut`           ENUM('En cours','Rendu','En retard','Perdu')
                        NOT NULL DEFAULT 'En cours'                COLLATE utf8mb4_unicode_ci,
    `amende_fcfa`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `note`             VARCHAR(255) DEFAULT NULL                   COLLATE utf8mb4_unicode_ci,
    `cree_par`         INT NOT NULL,
    `date_creation`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_client` (`id_client`),
    INDEX `idx_isbn`   (`isbn`),
    INDEX `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Étape 3 : Données de démonstration
INSERT INTO `emprunts`
    (`id_client`, `isbn`, `date_emprunt`, `date_retour_prev`, `statut`, `amende_fcfa`, `cree_par`)
SELECT
    c.id_client,
    '978-2-38600-007-3',
    DATE_SUB(CURDATE(), INTERVAL 5 DAY),
    DATE_ADD(CURDATE(), INTERVAL 9 DAY),
    'En cours',
    0.00,
    2
FROM clients c LIMIT 1;

INSERT INTO `emprunts`
    (`id_client`, `isbn`, `date_emprunt`, `date_retour_prev`, `statut`, `amende_fcfa`, `cree_par`)
SELECT
    c.id_client,
    '978-2-38600-005-3',
    DATE_SUB(CURDATE(), INTERVAL 20 DAY),
    DATE_SUB(CURDATE(), INTERVAL 6 DAY),
    'En retard',
    600.00,
    2
FROM clients c LIMIT 1;

-- Message de confirmation
SELECT 'Table emprunts créée avec succès !' AS Résultat;

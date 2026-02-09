-- Création de la base de données si elle n'existe pas
CREATE DATABASE IF NOT EXISTS l3_info CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE l3_info;

-- Table Formation
CREATE TABLE IF NOT EXISTS formation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    ects INT NOT NULL DEFAULT 60
) ENGINE=InnoDB;

-- Table Bloc (Semestres ou blocs de compétences)
CREATE TABLE IF NOT EXISTS bloc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    ects INT NOT NULL,
    formation_id INT NOT NULL,
    FOREIGN KEY (formation_id) REFERENCES formation(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table UE (Unités d'Enseignement)
CREATE TABLE IF NOT EXISTS ue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    coef_cc FLOAT DEFAULT 0,
    coef_partiel FLOAT DEFAULT 0,
    coef_examen FLOAT DEFAULT 0,
    ects INT NOT NULL,
    bloc_id INT NOT NULL,
    FOREIGN KEY (bloc_id) REFERENCES bloc(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table Utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table Notes
CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    ue_id INT NOT NULL,
    cc FLOAT,
    partiel FLOAT,
    examen FLOAT,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (ue_id) REFERENCES ue(id) ON DELETE CASCADE,
    UNIQUE KEY unique_note (utilisateur_id, ue_id) -- Une seule entrée de note par UE par utilisateur
) ENGINE=InnoDB;

-- Table Tracking (Journalisation des actions)
CREATE TABLE IF NOT EXISTS tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NULL,
    action TEXT NOT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insertion de données de test (L3 Informatique Paris-Saclay - Exemple simplifié)
-- Suppose que nous insérons pour la formation ID 1
INSERT INTO formation (nom, ects) VALUES ('L3 Informatique', 60);

-- Semestre 5
INSERT INTO bloc (nom, ects, formation_id) VALUES ('Semestre 5', 30, 1);
SET @s5_id = LAST_INSERT_ID();

INSERT INTO ue (nom, coef_cc, coef_partiel, coef_examen, ects, bloc_id) VALUES 
('Algorithmique Avancée', 0.3, 0.3, 0.4, 6, @s5_id),
('Bases de Données', 0.4, 0, 0.6, 6, @s5_id),
('Systèmes d\'Exploitation', 0.3, 0.3, 0.4, 6, @s5_id),
('Réseaux', 0.3, 0.2, 0.5, 6, @s5_id),
('Probabilités et Statistiques', 0.4, 0, 0.6, 6, @s5_id);

-- Semestre 6
INSERT INTO bloc (nom, ects, formation_id) VALUES ('Semestre 6', 30, 1);
SET @s6_id = LAST_INSERT_ID();

INSERT INTO ue (nom, coef_cc, coef_partiel, coef_examen, ects, bloc_id) VALUES 
('Programmation Web', 0.5, 0, 0.5, 6, @s6_id),
('Logique', 0.3, 0.3, 0.4, 6, @s6_id),
('Compilation', 0.3, 0.3, 0.4, 6, @s6_id),
('Projet', 1.0, 0, 0, 6, @s6_id),
('Anglais', 1.0, 0, 0, 6, @s6_id);

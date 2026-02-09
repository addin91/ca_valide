-- Ajout de la colonne parent_id
ALTER TABLE ue ADD COLUMN ue_parent_id INT NULL DEFAULT NULL AFTER id;
ALTER TABLE ue ADD CONSTRAINT fk_ue_parent FOREIGN KEY (ue_parent_id) REFERENCES ue(id) ON DELETE CASCADE;

-- Insertion d'une UE Parent "Projet Semestriel" (Semestre 6)
-- Pas de coefficients car ce sont les enfants qui porteront les notes
-- On récupère l'ID du bloc Semestre 6 (supposé existant, id récupéré dynamiquement ou fixe ici pour l'exemple)
-- Pour être sûr, on utilise une variable
SELECT @s6_id := id FROM bloc WHERE nom = 'Semestre 6' LIMIT 1;

-- Création du Parent
INSERT INTO ue (nom, coef_cc, coef_partiel, coef_examen, ects, bloc_id, ue_parent_id) VALUES 
('Projet Semestriel (Choix)', 0, 0, 0, 6, @s6_id, NULL);

SET @parent_id = LAST_INSERT_ID();

-- Création des Enfants
-- Note : Les enfants doivent avoir le même bloc_id que le parent pour simplifier, ou on l'ignore.
INSERT INTO ue (nom, coef_cc, coef_partiel, coef_examen, ects, bloc_id, ue_parent_id) VALUES 
('Projet : Développement Web', 0.5, 0, 0.5, 6, @s6_id, @parent_id),
('Projet : Intelligence Artificielle', 0.4, 0.2, 0.4, 6, @s6_id, @parent_id),
('Projet : Réseaux & Télécoms', 1.0, 0, 0, 6, @s6_id, @parent_id);

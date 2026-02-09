-- Mise à jour de la table tracking pour le mode invité
-- Permettre à utilisateur_id d'être NULL
ALTER TABLE tracking MODIFY utilisateur_id INT NULL;

-- Ajouter les colonnes IP et User Agent
ALTER TABLE tracking ADD COLUMN ip VARCHAR(45) NULL AFTER action;
ALTER TABLE tracking ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip;

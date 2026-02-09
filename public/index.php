<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
// Pas de require_login() ici pour permettre le mode invité
$user_id = is_logged_in() ? $_SESSION['user_id'] : null;
$user_name = is_logged_in() ? $_SESSION['user_name'] : 'Invité';
$formation_id = 1; // ID 1 = L3 Info (fixe pour l'instant)
// Récupérer les données (structure + notes existantes si connecté)
$blocs = get_formation_data($pdo, $formation_id, $user_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Notes - L3 Info Calc</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1><a href="/">L3 Info Calc</a></h1>
        <div style="display: flex; align-items: center;">
            <span style="margin-right: 1rem; font-weight: 500;">
                <?php if (is_logged_in()): ?>
                    Bonjour, <?php echo htmlspecialchars($user_name); ?>
                <?php else: ?>
                    Mode Invité
                <?php endif; ?>
            </span>
            <nav style="display: flex; align-items: center; gap: 1rem;">
                <?php if (is_logged_in()): ?>
                    <a href="logout.php" style="color: var(--danger-color);">Déconnexion</a>
                <?php else: ?>
                    <a href="login.php" style="color: var(--primary-color);">Connexion</a>
                    <a href="register.php">Inscription</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <div class="container">
        <form action="resultat.php" method="POST">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Saisie des notes</h2>
                <!-- Toggle Simulation -->
                <div class="toggle-container" style="display: flex; align-items: center; gap: 0.5rem;">
                    <label class="switch" style="position: relative; display: inline-block; width: 50px; height: 24px;">
                        <input type="checkbox" name="enable_simulation" value="1"
                            style="opacity: 0; width: 0; height: 0;">
                        <span class="slider round"
                            style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px;"></span>
                        <style>
                            .switch input:checked+.slider {
                                background-color: var(--primary-color);
                            }
                            .switch input:focus+.slider {
                                box-shadow: 0 0 1px var(--primary-color);
                            }
                            .slider:before {
                                position: absolute;
                                content: "";
                                height: 18px;
                                width: 18px;
                                left: 3px;
                                bottom: 3px;
                                background-color: white;
                                transition: .4s;
                                border-radius: 50%;
                            }
                            .switch input:checked+.slider:before {
                                transform: translateX(26px);
                            }
                        </style>
                    </label>
                    <span style="font-weight: 500; font-size: 0.9rem;">Activer la simulation</span>
                </div>
                <!-- Toggle Partial UE -->
                <div class="toggle-container" style="display: flex; align-items: center; gap: 0.5rem;">
                    <label class="switch" style="position: relative; display: inline-block; width: 50px; height: 24px;">
                        <input type="checkbox" name="enable_partial_ects" value="1"
                            style="opacity: 0; width: 0; height: 0;">
                        <span class="slider round"
                            style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px;"></span>
                        <style>
                            .switch input:checked+.slider {
                                background-color: var(--primary-color);
                            }
                            .switch input:focus+.slider {
                                box-shadow: 0 0 1px var(--primary-color);
                            }
                            .slider:before {
                                position: absolute;
                                content: "";
                                height: 18px;
                                width: 18px;
                                left: 3px;
                                bottom: 3px;
                                background-color: white;
                                transition: .4s;
                                border-radius: 50%;
                            }
                            .switch input:checked+.slider:before {
                                transform: translateX(26px);
                            }
                        </style>
                    </label>
                    <span style="font-weight: 500; font-size: 0.9rem;">Mode calcul UE partielles</span>
                </div>
            </div>
            <p style="margin-bottom: 2rem; color: var(--text-muted);">Saisissez vos notes ci-dessous. Les champs laissés
                vides ne seront pas comptabilisés dans la moyenne actuelle si non sauvegardés, mais peuvent impacter la
                validation.</p>
            <?php foreach ($blocs as &$bloc): ?>
                <div class="bloc-section">
                    <div class="bloc-title">
                        <?php echo htmlspecialchars($bloc['nom']); ?>
                        <span
                            style="font-size: 0.8em; font-weight: normal; margin-left: 0.5rem; background-color: #e5e7eb; padding: 0.2rem 0.5rem; border-radius: 1rem;">
                            <?php echo $bloc['ects']; ?> ECTS
                        </span>
                    </div>
                    <div class="ue-grid">
                        <?php foreach ($bloc['ues'] as $ue): ?>
                            <div class="ue-card" data-ue-id="<?php echo $ue['id']; ?>">
                                <?php if (!empty($ue['children'])): ?>
                                    <!-- UE PARENT (Select) -->
                                    <h3><?php echo htmlspecialchars($ue['nom']); ?></h3>
                                    <div class="ue-details">
                                        UE à choix (Sélectionnez une option)
                                    </div>
                                    <div class="form-group">
                                        <select class="ue-selector" name="ue_selection[<?php echo $ue['id']; ?>]">
                                            <option value="">-- Choisir une option --</option>
                                            <?php foreach ($ue['children'] as $child): ?>
                                                <?php
                                                // Auto-select if child has valid grades
                                                $selected = ($child['cc'] !== null || $child['partiel'] !== null || $child['examen'] !== null) ? 'selected' : '';
                                                ?>
                                                <option value="<?php echo $child['id']; ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($child['nom']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <!-- HIDDEN CHILDREN INPUTS -->
                                    <?php foreach ($ue['children'] as $child): ?>
                                        <div class="child-inputs child-of-<?php echo $ue['id']; ?>"
                                            id="child-<?php echo $child['id']; ?>"
                                            style=" margin-top: 1rem; border-top: 1px solid #eee; padding-top: 0.5rem;">
                                            <h4><?php echo htmlspecialchars($child['nom']); ?></h4>
                                            <div class="ue-details">
                                                ECTS: <?php echo $child['ects']; ?> |
                                                Coeffs: CC(<?php echo $child['coef_cc']; ?>)
                                                P(<?php echo $child['coef_partiel']; ?>)
                                                E(<?php echo $child['coef_examen']; ?>)
                                            </div>
                                            <div class="grade-inputs">
                                                <div class="grade-input-group">
                                                    <label>CC</label>
                                                    <input type="number" step="0.01" min="0" max="20"
                                                        name="notes[<?php echo $child['id']; ?>][cc]"
                                                        value="<?php echo $child['cc'] !== null ? htmlspecialchars($child['cc']) : ''; ?>"
                                                        placeholder="-">
                                                </div>
                                                <div class="grade-input-group">
                                                    <label>Partiel</label>
                                                    <input type="number" step="0.01" min="0" max="20"
                                                        name="notes[<?php echo $child['id']; ?>][partiel]"
                                                        value="<?php echo $child['partiel'] !== null ? htmlspecialchars($child['partiel']) : ''; ?>"
                                                        placeholder="-">
                                                </div>
                                                <div class="grade-input-group">
                                                    <label>Examen</label>
                                                    <input type="number" step="0.01" min="0" max="20"
                                                        name="notes[<?php echo $child['id']; ?>][examen]"
                                                        value="<?php echo $child['examen'] !== null ? htmlspecialchars($child['examen']) : ''; ?>"
                                                        placeholder="-">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- UE NORMALE (Standard) -->
                                    <h3><?php echo htmlspecialchars($ue['nom']); ?></h3>
                                    <div class="ue-details">
                                        ECTS: <?php echo $ue['ects']; ?> |
                                        Coeffs: CC(<?php echo $ue['coef_cc']; ?>)
                                        P(<?php echo $ue['coef_partiel']; ?>)
                                        E(<?php echo $ue['coef_examen']; ?>)
                                    </div>
                                    <div class="grade-inputs">
                                        <div class="grade-input-group">
                                            <label>CC</label>
                                            <input type="number" step="0.01" min="0" max="20"
                                                name="notes[<?php echo $ue['id']; ?>][cc]"
                                                value="<?php echo $ue['cc'] !== null ? htmlspecialchars($ue['cc']) : ''; ?>"
                                                placeholder="-">
                                        </div>
                                        <div class="grade-input-group">
                                            <label>Partiel</label>
                                            <input type="number" step="0.01" min="0" max="20"
                                                name="notes[<?php echo $ue['id']; ?>][partiel]"
                                                value="<?php echo $ue['partiel'] !== null ? htmlspecialchars($ue['partiel']) : ''; ?>"
                                                placeholder="-">
                                        </div>
                                        <div class="grade-input-group">
                                            <label>Examen</label>
                                            <input type="number" step="0.01" min="0" max="20"
                                                name="notes[<?php echo $ue['id']; ?>][examen]"
                                                value="<?php echo $ue['examen'] !== null ? htmlspecialchars($ue['examen']) : ''; ?>"
                                                placeholder="-">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="action-bar">
                <button type="submit" class="btn-primary" style="width: auto; padding-left: 2rem; padding-right: 2rem;">
                    Enregistrer et Calculer
                </button>
            </div>
        </form>
    </div>
    <script src="assets/js/script.js"></script>
</body>
</html>
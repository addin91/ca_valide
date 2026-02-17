<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
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
    <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body>
    <header>
        <h1><a href="/">Ca Valide</a></h1>
        <div style="display: flex; align-items: center;">
            <span style="margin-right: 1rem; font-weight: 500;">
                <?php if (is_logged_in()): ?>
                    Bonjour, <?php echo htmlspecialchars($user_name); ?>
                <?php else: ?>
                    Mode Invité (Notes non sauvegardées)
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
          <!-- TOP SECTION: HEADER GRID with TOGGLES (Hybrid Style) -->
        <div style="display: flex; align-items: center; margin-bottom: 2rem; background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h2 style="margin:0; flex:1; font-size: 1.5rem; color: var(--primary-color);">Mes Notes</h2>
            
            <div class="toggle-wrapper">
                <label class="switch">
                    <input type="checkbox" name="enable_simulation" value="1" id="simToggle"
                           onchange="toggleLabel(this, 'simLabel')">
                    <span class="slider"></span>
                </label>

                <span id="simLabel" class="toggle-label">
                    Activer Simulation
                    <span class="info-icon">ℹ️
                        <span class="tooltip">
                            Active le mode simulation permet d'avoir les notes minimal afin de validé l'année.
                        </span>
                    </span>
                </span>
            </div>

            
            <div class="toggle-wrapper">
                <label class="switch">
                    <input type="checkbox" name="enable_partial_ects" value="1" id="partToggle" onchange="toggleLabel(this, 'partLabel')">
                    <span class="slider"></span>
                </label>
                <span id="partLabel" class="toggle-label">Calcul UE Partielles
                 <span class="info-icon">ℹ️
                        <span class="tooltip">
                            Active le mode calcul UE partielles permet d'avoir sa moyenne que avec les notes saisies
                        </span>
                    </span>
                </span>
                </span>
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
                                            <input type="<?php echo $ue['coef_cc'] == 0 ? "text" : "number"; ?>" step="1" min="0" max="20"
                                                name="notes[<?php echo $ue['id']; ?>][cc]"
                                                value="<?php echo $ue['cc'] !== null ? htmlspecialchars($ue['cc']) : ''; ?>"
                                                placeholder="-"
                                                <?php echo $ue['coef_cc'] == 0 ? "disabled" : ''; ?>   >
                                        </div>
                                        <div class="grade-input-group">
                                            <label>Partiel</label>
                                            <input type="<?php echo $ue['coef_partiel'] == 0 ? "text" : "number"; ?>" step="1" min="0" max="20"
                                                name="notes[<?php echo $ue['id']; ?>][partiel]"
                                                value="<?php echo $ue['partiel'] !== null ? htmlspecialchars($ue['partiel']) : ''; ?>"
                                                placeholder="-"
                                                <?php echo $ue['coef_partiel'] == 0 ? "disabled" : ''; ?>   >
                                        </div>
                                        <div class="grade-input-group">
                                            <label>Examen</label>
                                            <input type="<?php echo $ue['coef_examen'] == 0 ? "text" : "number"; ?>" step="1" min="0" max="20"
                                                name="notes[<?php echo $ue['id']; ?>][examen]"
                                                value="<?php echo $ue['examen'] !== null ? htmlspecialchars($ue['examen']) : ''; ?>"
                                                placeholder="-"
                                                <?php echo $ue['coef_examen'] == 0 ? "disabled" : ''; ?>   >
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
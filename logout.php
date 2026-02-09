<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
$user_id = is_logged_in() ? $_SESSION['user_id'] : null;
$blocs = get_formation_data($pdo, 1, $user_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Saisie - Ca Valide</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        window.toggleLabel = function(chk, labelId) {
            const lbl = document.getElementById(labelId);
            if(chk.checked) lbl.classList.add('text-green-active');
            else lbl.classList.remove('text-green-active');
        };

        // UE Child Selection Logic
        document.querySelectorAll("select.ue-selector").forEach(sel => {
            sel.addEventListener("change", (e) => {
                const parentId = e.target.name.match(/\[(\d+)\]/)[1];
                document.querySelectorAll(`.child-of-${parentId}`).forEach(el => el.style.display = "none");
                const val = e.target.value;
                if(val) {
                    const child = document.getElementById(`child-${val}`);
                    if(child) child.style.display = "block";
                }
            });
        });
    });
    </script>
</head>
<body>
<header>
    <h1>Ca Valide</h1>
    <nav>
        <?php if ($user_id): ?>
            <span style="margin-right:1rem; font-size:0.9rem;">Bonjour <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="logout.php" style="color:var(--danger-color);">Déco</a>
        <?php else: ?>
            <a href="login.php">Co</a>
        <?php endif; ?>
    </nav>
</header>
<div class="container">
    <form action="resultat.php" method="POST">
        <!-- TOP SECTION: HEADER GRID with TOGGLES (Hybrid Style) -->
        <div style="display: flex; align-items: center; margin-bottom: 2rem; background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <h2 style="margin:0; flex:1; font-size: 1.5rem; color: var(--primary-color);">Mes Notes</h2>
            
            <div class="toggle-wrapper">
                <label class="switch">
                    <input type="checkbox" name="enable_simulation" value="1" id="simToggle" onchange="toggleLabel(this, 'simLabel')">
                    <span class="slider"></span>
                </label>
                <span id="simLabel" class="toggle-label">Activer Simulation</span>
            </div>
            
            <div class="toggle-wrapper">
                <label class="switch">
                    <input type="checkbox" name="enable_partial_ects" value="1" id="partToggle" onchange="toggleLabel(this, 'partLabel')">
                    <span class="slider"></span>
                </label>
                <span id="partLabel" class="toggle-label">Calcul UE Partielles</span>
            </div>
        </div>

        <!-- FORM CONTENT: BLOCS & UES (Classic Grid) -->
        <?php foreach ($blocs as $b): ?>
            <div class="bloc-section-legacy">
                <div class="bloc-title-legacy">
                    <?php echo htmlspecialchars($b['nom']); ?>
                    <span style="font-size: 0.8em; font-weight: normal; margin-left: 0.5rem; background-color: #e5e7eb; padding: 0.2rem 0.5rem; border-radius: 1rem;">
                        <?php echo $b['ects']; ?> ECTS
                    </span>
                </div>
                
                <div class="ue-grid-legacy">
                    <?php foreach ($b['ues'] as $ue): ?>
                        <div class="ue-card-legacy">
                            <?php if (!empty($ue['children'])): ?>
                                <!-- PARENT SELECT -->
                                <h3><?php echo htmlspecialchars($ue['nom']); ?></h3>
                                <div class="ue-details">UE à choix</div>
                                <div class="form-group" style="margin-bottom:1rem;">
                                    <select class="ue-selector" name="ue_selection[<?php echo $ue['id']; ?>]">
                                        <option value="">-- Choisir --</option>
                                        <?php foreach ($ue['children'] as $child): ?>
                                            <?php $sel = ($child['cc']!==null||$child['partiel']!==null||$child['examen']!==null)?'selected':''; ?>
                                            <option value="<?php echo $child['id']; ?>" <?php echo $sel; ?>>
                                                <?php echo htmlspecialchars($child['nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- CHILDREN INPUTS -->
                                <?php foreach ($ue['children'] as $child): ?>
                                    <?php 
                                    $visible = ($child['cc']!==null||$child['partiel']!==null||$child['examen']!==null) ? 'block' : 'none'; 
                                    ?>
                                    <div class="child-inputs child-of-<?php echo $ue['id']; ?>" id="child-<?php echo $child['id']; ?>" style="display: <?php echo $visible; ?>;">
                                        <h4><?php echo htmlspecialchars($child['nom']); ?></h4>
                                        <div class="ue-details">
                                            C<?php echo $child['coef_cc']; ?> P<?php echo $child['coef_partiel']; ?> E<?php echo $child['coef_examen']; ?>
                                        </div>
                                        <div class="grade-inputs-legacy">
                                            <div class="grade-input-group">
                                                <label>CC</label>
                                                <input type="number" step="0.01" name="notes[<?php echo $child['id']; ?>][cc]" value="<?php echo $child['cc']; ?>" placeholder="-">
                                            </div>
                                            <div class="grade-input-group">
                                                <label>Partiel</label>
                                                <input type="number" step="0.01" name="notes[<?php echo $child['id']; ?>][partiel]" value="<?php echo $child['partiel']; ?>" placeholder="-">
                                            </div>
                                            <div class="grade-input-group">
                                                <label>Exam</label>
                                                <input type="number" step="0.01" name="notes[<?php echo $child['id']; ?>][examen]" value="<?php echo $child['examen']; ?>" placeholder="-">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                            <?php else: ?>
                                <!-- STANDARD UE -->
                                <h3><?php echo htmlspecialchars($ue['nom']); ?></h3>
                                <div class="ue-details">
                                    ECTS: <?php echo $ue['ects']; ?> | 
                                    C<?php echo $ue['coef_cc']; ?> P<?php echo $ue['coef_partiel']; ?> E<?php echo $ue['coef_examen']; ?>
                                </div>
                                <div class="grade-inputs-legacy">
                                    <div class="grade-input-group">
                                        <label>CC</label>
                                        <input type="number" step="0.01" name="notes[<?php echo $ue['id']; ?>][cc]" value="<?php echo $ue['cc']; ?>" placeholder="-">
                                    </div>
                                    <div class="grade-input-group">
                                        <label>Partiel</label>
                                        <input type="number" step="0.01" name="notes[<?php echo $ue['id']; ?>][partiel]" value="<?php echo $ue['partiel']; ?>" placeholder="-">
                                    </div>
                                    <div class="grade-input-group">
                                        <label>Exam</label>
                                        <input type="number" step="0.01" name="notes[<?php echo $ue['id']; ?>][examen]" value="<?php echo $ue['examen']; ?>" placeholder="-">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="action-bar">
            <button type="submit" class="btn-submit">Calculer</button>
        </div>
    </form>
</div>
</body>
</html>

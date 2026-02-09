<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
$user_id = is_logged_in() ? $_SESSION['user_id'] : null;
$formation_id = 1;
// 1. Sauvegarde des notes SI connecté
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notes'])) {
    if ($user_id) {
        save_notes($pdo, $user_id, $_POST['notes']);
        track_action($pdo, $user_id, "Mise à jour des notes et calcul");
    } else {
        track_action($pdo, null, "Calcul simulation (Invité)");
    }
} else {
    // Si accès direct sans POST
    track_action($pdo, $user_id, "Consultation des résultats");
}
// 2. Récupération des données de base (DB)
// 2. Récupération des données de base (DB)
$blocs = get_formation_data($pdo, $formation_id, $user_id);
// 3. Injection des données POST si disponibles
if (isset($_POST['notes'])) {
    $posted_notes = $_POST['notes'];
    foreach ($blocs as &$bloc) {
        foreach ($bloc['ues'] as &$ue) {
            if (isset($posted_notes[$ue['id']])) {
                $n = $posted_notes[$ue['id']];
                if (isset($n['cc']) && $n['cc'] !== '')
                    $ue['cc'] = floatval($n['cc']);
                if (isset($n['partiel']) && $n['partiel'] !== '')
                    $ue['partiel'] = floatval($n['partiel']);
                if (isset($n['examen']) && $n['examen'] !== '')
                    $ue['examen'] = floatval($n['examen']);
            }
        }
    }
}
// 3bis. Calculs
$total_ects_formation = 0;
$weighted_sum_formation = 0;
$total_ects_non_transverse = 0;
$weighted_sum_non_transverse = 0;
$bloc_transverse_present = false;
$bloc_validation_flags = [];
foreach ($blocs as &$bloc) {
    // Calcul du bloc
    $bloc_sum = 0;
    $bloc_ects = 0;
    foreach ($bloc['ues'] as &$ue) {
        $ue['children'] = $ue['children'] ?? [];
        if (!empty($ue['children'])) {
            // Processing Parent UE: Iterate over children
            foreach ($ue['children'] as &$child) {
                $child_avg = calculate_ue_average($child);
                $child['moyenne'] = $child_avg;
                $bloc_sum += $child_avg * $child['ects'];
                $bloc_ects += $child['ects'];
                // Global ECTS Sums
                $total_ects_formation += $child['ects'];
                $weighted_sum_formation += $child_avg * $child['ects'];
            }
            $ue['moyenne'] = 0;
        } else {
            // Standard UE
            $ue_avg = calculate_ue_average($ue);
            $ue['moyenne'] = $ue_avg;
            $bloc_sum += $ue_avg * $ue['ects'];
            $bloc_ects += $ue['ects'];
            $total_ects_formation += $ue['ects'];
            $weighted_sum_formation += $ue_avg * $ue['ects'];
        }
    }
    $bloc_avg = ($bloc_ects > 0) ? $bloc_sum / $bloc_ects : 0;
    $bloc['moyenne'] = $bloc_avg;
    // Status Logic Immediate (for verification loop)
    $is_transverse = ($bloc['nom'] === "Bloc Transverse");
    if ($is_transverse) {
        $bloc['is_valid'] = ($bloc_avg >= 10);
    } else {
        $bloc['is_valid'] = ($bloc_avg >= 7);
        $weighted_sum_non_transverse += $bloc_sum;
        $total_ects_non_transverse += $bloc_ects;
    }
}
// Global Averages
$moyenne_generale = ($total_ects_formation > 0) ? $weighted_sum_formation / $total_ects_formation : 0;
$moyenne_non_transverse = ($total_ects_non_transverse > 0) ? $weighted_sum_non_transverse / $total_ects_non_transverse : 0;
// Determination du Statut Final STRICT (Binaire + Règles)
$admis = true;
foreach ($blocs as $b) {
    if (!$b['is_valid']) {
        $admis = false;
        break;
    }
}
if ($moyenne_non_transverse < 10)
    $admis = false;
if ($moyenne_generale < 10)
    $admis = false;
$statut_final = $admis ? "ADMIS" : "AJOURNÉ";
$class_final = $admis ? "status-valid" : "status-invalid";
$details_final = $admis ? ["Félicitations, vous validez l'année !"] : ["Les conditions de validation ne sont pas atteintes."];
// --- SIMULATION (Optionnelle) ---
// La simulation doit être indépendante du mode partiel.
// Si Partial Mode est actif, la simulation se base-t-elle sur le mode partiel ou standard ?
// "La simulation doit uniquement empêcher la sauvegarde... MAIS ne jamais empêcher l'affichage".
// On assume que la simulation utilise les règles standards pour dire "ce qu'il manque pour valider le Vrai Diplôme".
$simulation_active = isset($_POST['enable_simulation']) && $_POST['enable_simulation'] == '1';
$simulation_results = [];
if ($simulation_active && !$admis) {
    // On itère sur les UEs manquantes
    // Note: On utilise les fonctions helper déplacées dans functions.php
    // Reuse logic structure
    foreach ($blocs as $b_idx => $bloc) {
        foreach ($bloc['ues'] as $u_idx => $ue) {
            $targets = !empty($ue['children']) ? $ue['children'] : [$ue];
            foreach ($targets as $t) {
                if (($t['coef_cc'] > 0 && $t['cc'] === null) || ($t['coef_partiel'] > 0 && $t['partiel'] === null) || (($t['coef_examen'] > 0 && $t['examen'] === null))) {
                    // Try Year Validation
                    $g_year = find_uniform_grade_for_year($blocs, 7, 10, 10, 10);
                    if ($g_year !== false && $g_year <= 20) {
                        $simulation_results[$t['id']] = ['type' => 'success', 'msg' => "Validation Année possible", 'grades' => fill_grades($t, $g_year)];
                    } else {
                        // Try Block Validation
                        $target_bloc = ($bloc['nom'] === "Bloc Transverse") ? 10 : 7;
                        $g_bloc = find_uniform_grade_for_bloc($bloc, $target_bloc);
                        if ($g_bloc !== false && $g_bloc <= 20) {
                            $simulation_results[$t['id']] = ['type' => 'warning', 'msg' => "Validation Bloc possible", 'grades' => fill_grades($t, $g_bloc)];
                        } else {
                            // Try UE Validation
                            $g_ue = find_uniform_grade_for_ue($t, 10);
                            if ($g_ue !== false && $g_ue <= 20) {
                                $simulation_results[$t['id']] = ['type' => 'warning', 'msg' => "Moyenne UE possible", 'grades' => fill_grades($t, $g_ue)];
                            } else {
                                $simulation_results[$t['id']] = ['type' => 'error', 'msg' => "Impossible"];
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultats - L3 Info Calc</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .ue-result-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        .ue-result-row:last-child {
            border-bottom: none;
        }
        .warning-message {
            color: var(--warning-color);
            background-color: #fffbeb;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
            border: 1px solid #fcd34d;
        }
    </style>
</head>
<body>
    <header>
        <h1><a href="/">L3 Info Calc</a></h1>
        <nav>
            <a href="index.php">Changer les notes</a>
            <?php if (is_logged_in()): ?>
                <a href="logout.php" style="color: var(--danger-color); margin-left: 1rem;">Déconnexion</a>
            <?php else: ?>
                <a href="login.php" style="color: var(--primary-color); margin-left: 1rem;">Se connecter (Sauvegarder)</a>
            <?php endif; ?>
        </nav>
    </header>
    <style>
        .status-warning {
            color: #d97706;
            font-weight: bold;
        }
        .result-details-list {
            text-align: left;
            list-style: none;
            padding: 0;
            margin-top: 1rem;
            color: #555;
            font-size: 0.9rem;
        }
        .result-details-list li {
            margin-bottom: 0.5rem;
        }
    </style>
    <div class="container">
        <h2>Vos Résultats</h2>
        <div class="results-summary">
            <div class="result-card">
                <div class="result-label">DÉCISION DU JURY (Simulation)</div>
            <div class="result-value <?php echo $class_final; ?>">
                <?php echo $statut_final; ?>
            </div>
            <ul class="result-details-list">
                <?php foreach ($details_final as $msg): ?>
                    <li>- <?php echo $msg; ?></li>
                <?php endforeach; ?>
            </ul>
            </div>
        </div>
        <!-- Partial UE Mode Toggle in Result Page -->
        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
            <div class="result-card" style="flex: 1;">
                <div class="result-label">
                    Moyenne Générale
                </div>
                <div class="result-value">
                    <?php echo number_format($moyenne_generale, 2); ?>
                </div>
            </div>
            <div class="result-card" style="flex: 1;">
                <div class="result-label">Moyenne Hors Transverse</div>
                <div class="result-value">
                    <?php echo number_format($moyenne_non_transverse, 2); ?>
                </div>
            </div>
        </div>
    </div>
    <div
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-top: 2rem;">
        <?php foreach ($blocs as &$bloc): ?>
            <div class="bloc-section" style="margin-bottom: 0;">
                <div class="bloc-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; flex-direction: column;">
                        <span><?php echo htmlspecialchars($bloc['nom']); ?></span>
                        <span style="font-size: 0.8rem; font-weight: normal; color: #666;">
                            <?php
                            // Status Logic Per Bloc Display
                            if ($bloc['nom'] === "Bloc Transverse") {
                                echo ($bloc['moyenne'] >= 10) ? "Validé (>10)" : (($bloc['moyenne'] >= 8) ? "Rattrapable (>8)" : "Non Validé (<8)");
                            } else {
                                echo ($bloc['moyenne'] >= 7) ? "Validé (>7)" : "Non Validé (<7)";
                            }
                            ?>
                        </span>
                    </div>
                    <span
                        class="<?php echo ($bloc['moyenne'] >= 10) ? 'status-valid' : (($bloc['moyenne'] >= (($bloc['nom'] === "Bloc Transverse") ? 8 : 7)) ? 'status-warning' : 'status-invalid'); ?>">
                        <?php echo number_format($bloc['moyenne'], 2); ?>
                    </span>
                </div>
                <?php foreach ($bloc['ues'] as &$ue): ?>
                    <?php if (!empty($ue['children'])): ?>
                        <!-- Parent Row (Just a header) -->
                        <div class="ue-result-row" style="background-color: #f9fafb; font-weight: bold;">
                            <span><?php echo htmlspecialchars($ue['nom']); ?></span>
                            <span>-</span>
                        </div>
                        <!-- Children Rows -->
                        <?php foreach ($ue['children'] as $child): ?>
                            <!-- On n'affiche que ceux qui ont été notés ou qui ont des coefficients (ie tous les enfants théoriques) -->
                            <div class="ue-result-row" style="padding-left: 2rem;">
                                <span><?php echo htmlspecialchars($child['nom']); ?> <small
                                        style="color: grey;">(x<?php echo $child['ects']; ?>)</small></span>
                                <div style="text-align: right;">
                                    <span
                                        style="font-weight: 500; <?php echo ($child['moyenne'] === null && $enable_partial_ects) ? 'color: #999;' : (($child['moyenne'] >= 10) ? 'color: var(--success-color);' : 'color: var(--danger-color);'); ?>">
                                        <?php echo ($child['moyenne'] === null && $enable_partial_ects) ? '-' : number_format($child['moyenne'], 2); ?>
                                    </span>
                                    <!-- SIMULATION DISPLAY -->
                                    <?php if ($simulation_active && isset($simulation_results[$child['id']])): ?>
                                        <div style="font-size: 0.8rem; margin-top: 0.2rem;">
                                            <?php
                                            $sim = $simulation_results[$child['id']];
                                            if ($sim['type'] === 'error') {
                                                echo '<span style="color: var(--danger-color); font-weight: bold;">' . htmlspecialchars($sim['msg']) . '</span>';
                                            } else {
                                                $color = ($sim['type'] === 'success') ? 'var(--success-color)' : 'var(--warning-color)';
                                                $txts = [];
                                                foreach ($sim['grades'] as $f => $v)
                                                    $txts[] = "$f > " . number_format($v, 2);
                                                echo '<span style="color: ' . $color . '; font-weight: bold;">';
                                                echo htmlspecialchars($sim['msg']) . ' : ' . implode(', ', $txts);
                                                echo '</span>';
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Standard Row -->
                        <div class="ue-result-row">
                            <span><?php echo htmlspecialchars($ue['nom']); ?> <small
                                    style="color: grey;">(x<?php echo $ue['ects']; ?>)</small></span>
                            <div style="text-align: right;">
                                <span
                                    style="font-weight: 500; <?php echo ($ue['moyenne'] === null && $smart_calc_active) ? 'color: #999;' : (($ue['moyenne'] >= 10) ? 'color: var(--success-color);' : 'color: var(--danger-color);'); ?>">
                                    <?php echo ($ue['moyenne'] === null && $smart_calc_active) ? '-' : number_format($ue['moyenne'], 2); ?>
                                </span>
                                <!-- SIMULATION DISPLAY -->
                                <?php if ($simulation_active && isset($simulation_results[$ue['id']])): ?>
                                    <div style="font-size: 0.8rem; margin-top: 0.2rem;">
                                        <?php
                                        $sim = $simulation_results[$ue['id']];
                                        if ($sim['type'] === 'error') {
                                            echo '<span style="color: var(--danger-color); font-weight: bold;">' . htmlspecialchars($sim['msg']) . '</span>';
                                        } else {
                                            $color = ($sim['type'] === 'success') ? 'var(--success-color)' : 'var(--warning-color)';
                                            $txts = [];
                                            foreach ($sim['grades'] as $f => $v)
                                                $txts[] = "$f > " . number_format($v, 2);
                                            echo '<span style="color: ' . $color . '; font-weight: bold;">';
                                            echo htmlspecialchars($sim['msg']) . ' : ' . implode(', ', $txts);
                                            echo '</span>';
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div style="margin-top: 2rem; text-align: center;">
        <a href="index.php" class="btn-primary"
            style="text-decoration: none; padding: 0.75rem 2rem; display: inline-block;">Retour à la saisie</a>
    </div>
    </div>
</body>
</html>
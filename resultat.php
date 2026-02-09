<?php
// resultat.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$user_id = is_logged_in() ? $_SESSION['user_id'] : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notes'])) {
    if ($user_id)
        save_notes($pdo, $user_id, $_POST['notes']);
}
$blocs = get_formation_data($pdo, 1, $user_id);
$enable_partial = isset($_POST['enable_partial_ects']);
$enable_sim = isset($_POST['enable_simulation']);

// --- INJECTION ---
if (isset($_POST['notes'])) {
    $posted = $_POST['notes'];
    foreach ($blocs as $k => $b) {
        foreach ($b['ues'] as $uk => $ue) {
            $kids = !empty($ue['children']) ? $ue['children'] : [['id' => $ue['id']]];
            foreach ($kids as $t_info) {
                if (isset($posted[$t_info['id']])) {
                    $p = $posted[$t_info['id']];
                    $cc = ($p['cc'] !== '') ? $p['cc'] : null;
                    $pa = ($p['partiel'] !== '') ? $p['partiel'] : null;
                    $ex = ($p['examen'] !== '') ? $p['examen'] : null;
                    if (!empty($ue['children'])) {
                        foreach ($blocs[$k]['ues'][$uk]['children'] as $ck => $cv) {
                            if ($cv['id'] == $t_info['id']) {
                                $blocs[$k]['ues'][$uk]['children'][$ck]['cc'] = $cc;
                                $blocs[$k]['ues'][$uk]['children'][$ck]['partiel'] = $pa;
                                $blocs[$k]['ues'][$uk]['children'][$ck]['examen'] = $ex;
                            }
                        }
                    } else {
                        $blocs[$k]['ues'][$uk]['cc'] = $cc;
                        $blocs[$k]['ues'][$uk]['partiel'] = $pa;
                        $blocs[$k]['ues'][$uk]['examen'] = $ex;
                    }
                }
            }
        }
    }
}

// --- CALC ---
$te = 0;
$ws = 0;
$te_nt = 0;
$ws_nt = 0;
foreach ($blocs as $k => $b) {
    $bs = 0;
    $be = 0;
    foreach ($b['ues'] as $uk => $ue) {
        $kids = !empty($ue['children']) ? $ue['children'] : [$ue];
        foreach ($kids as $idx => $t) {
            $m = $enable_partial ? calculate_partial_ue_average($t) : calculate_ue_average($t);
            if (!empty($ue['children']))
                $blocs[$k]['ues'][$uk]['children'][$idx]['moyenne'] = $m;
            else
                $blocs[$k]['ues'][$uk]['moyenne'] = $m;
            if ($m !== null) {
                $bs += $m * $t['ects'];
                $be += $t['ects'];
            }
        }
    }
    $bavg = ($be > 0) ? $bs / $be : 0;
    $blocs[$k]['moyenne'] = $bavg;
    $is_tr = ($b['nom'] === "Bloc Transverse");
    $blocs[$k]['is_valid'] = ($bavg >= ($is_tr ? 10 : 7));
    $te += $be;
    $ws += $bs;
    if (!$is_tr) {
        $te_nt += $be;
        $ws_nt += $bs;
    }
}
$gen = ($te > 0) ? $ws / $te : 0;
$nt = ($te_nt > 0) ? $ws_nt / $te_nt : 0;
$admis = ($gen >= 10 && $nt >= 10);
foreach ($blocs as $b)
    if (!$b['is_valid'])
        $admis = false;

// --- SIMULATION (Delta) ---
$sim_res = [];
if ($enable_sim && !$admis) {
    $bases = [];
    foreach ($blocs as $b) {
        $tr = ($b['nom'] === "Bloc Transverse");
        $loc = simulate_target_bloc($b, $tr ? 10 : 7);
        if ($loc === false)
            $loc = 21;
        if ($loc === null)
            $loc = 0;
        foreach ($b['ues'] as $ue) {
            $kids = !empty($ue['children']) ? $ue['children'] : [$ue];
            foreach ($kids as $t)
                $bases[$t['id']] = $loc;
        }
    }
    $blocs_n = array_filter($blocs, function ($b) {
        return $b['nom'] !== "Bloc Transverse";
    });
    $d_nt = simulate_delta_for_collection($blocs_n, $bases, 10);
    $d_gen = simulate_delta_for_collection($blocs, $bases, 10);

    foreach ($blocs as $b) {
        $tr = ($b['nom'] === "Bloc Transverse");
        $delta = $tr ? $d_gen : max($d_gen, $d_nt);
        foreach ($b['ues'] as $ue) {
            $kids = !empty($ue['children']) ? $ue['children'] : [$ue];
            foreach ($kids as $t) {
                $base = $bases[$t['id']] ?? 0;
                $final = ($base > 20) ? 999 : $base + $delta;
                if ($final <= 20) {
                    $miss = ($t['coef_cc'] > 0 && $t['cc'] === null) || ($t['coef_partiel'] > 0 && $t['partiel'] === null) || ($t['coef_examen'] > 0 && $t['examen'] === null);
                    if ($miss)
                        $sim_res[$t['id']] = fill_grades($t, $final);
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
    <title>Résultats</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <header>
        <h1><a href="index.php">Ca Valide</a></h1>
        <nav>
            <a href="index.php">Retour</a>
            <?php if ($user_id): ?>
                <a href="logout.php"
                    style="margin-left: 1rem; color: var(--danger-color); font-weight: bold;">Déconnexion</a>
            <?php endif; ?>
        </nav>
    </header>
    <div class="container">

        <!-- TOP STATUS -->
        <div class="status-block">
            <div class="status-text <?php echo $admis ? 'text-green' : 'text-red'; ?>">
                <?php echo $admis ? "ADMIS" : "AJOURNÉ"; ?>
            </div>
        </div>

        <!-- AVERAGES -->
        <div class="averages-row">
            <div class="avg-card">
                <div class="avg-label">Moyenne Générale</div>
                <div class="avg-value"><?php echo number_format($gen, 2); ?></div>
            </div>
            <div class="avg-card">
                <div class="avg-label">Moyenne Blocs</div>
                <div class="avg-value"><?php echo number_format($nt, 2); ?></div>
            </div>
        </div>

        <!-- BLOCS GRID -->
        <div class="blocs-grid">
            <?php foreach ($blocs as $b): ?>
                <div class="bloc-card">
                    <div class="bloc-header">
                        <div>
                            <div class="bloc-name"><?php echo htmlspecialchars($b['nom']); ?></div>
                            <div class="bloc-status">
                                <?php echo $b['is_valid'] ? "Validé" : "Non Validé"; ?>
                            </div>
                        </div>
                        <div class="bloc-grade <?php echo ($b['moyenne'] >= 10) ? 'text-green' : 'text-red'; ?>">
                            <?php echo number_format($b['moyenne'], 2); ?>
                        </div>
                    </div>

                    <!-- UEs -->
                    <?php foreach ($b['ues'] as $ue): ?>
                        <?php
                        $kids = !empty($ue['children']) ? $ue['children'] : [$ue];
                        $kids_with_grades = [];
                        foreach ($kids as $k) {
                            if ($k['cc'] !== null || $k['partiel'] !== null || $k['examen'] !== null) {
                                $kids_with_grades[] = $k;
                            }
                        }
                        // Only filter if at least one had a grade (to hide empty siblings)
                        // If none had grades, show all so user sees options exist? Or show none?
                        // User requested "only the child UE *that has a grade*". 
                        // Assuming if none have grades, they haven't picked yet, so maybe show all.
                        if (!empty($kids_with_grades)) {
                            $kids = $kids_with_grades;
                        }

                        if (!empty($ue['children'])): ?>
                            <div style="font-weight:bold; font-size:0.9rem; margin-top:0.5rem;">
                                <?php echo htmlspecialchars($ue['nom']); ?></div>
                        <?php endif; ?>

                        <?php foreach ($kids as $k): ?>
                            <div class="ue-row">
                                <div class="ue-name">
                                    <?php echo htmlspecialchars($k['nom']); ?>
                                    <span style="font-size:0.8rem; color:#888;">(<?php echo $k['ects']; ?>)</span>
                                </div>
                                <div
                                    class="ue-grade <?php echo ($k['moyenne'] !== null && $k['moyenne'] >= 10) ? 'text-green' : 'text-red'; ?>">
                                    <?php echo ($k['moyenne'] !== null) ? number_format($k['moyenne'], 2) : '-'; ?>
                                </div>
                            </div>
                            <?php if (isset($sim_res[$k['id']])): ?>
                                <div class="sim-row">
                                    <?php
                                    $p = [];
                                    foreach ($sim_res[$k['id']] as $n => $v)
                                        $p[] = "$n > " . number_format($v, 2);
                                    echo implode(", ", $p);
                                    ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>

</html>
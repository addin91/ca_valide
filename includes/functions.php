<?php
// includes/functions.php

function track_action($pdo, $user_id, $action)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO tracking (utilisateur_id, action, ip) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $action, $ip]);
}

function get_formation_data($pdo, $formation_id, $user_id = null)
{
    $stmt = $pdo->prepare("SELECT * FROM bloc WHERE formation_id = ?");
    $stmt->execute([$formation_id]);
    $blocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($blocs as &$bloc) {
        $sql = "SELECT ue.*, " . ($user_id ? "n.cc, n.partiel, n.examen" : "NULL as cc, NULL as partiel, NULL as examen") . " 
                FROM ue " . ($user_id ? "LEFT JOIN notes n ON ue.id = n.ue_id AND n.utilisateur_id = ?" : "") . " 
                WHERE ue.bloc_id = ? ORDER BY ue.nom";
        $params = $user_id ? [$user_id, $bloc['id']] : [$bloc['id']];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $ues_by_id = [];
        $root = [];
        foreach ($all as $ue) {
            $ue['children'] = [];
            $ues_by_id[$ue['id']] = $ue;
        }
        foreach ($all as $ue) {
            // Support both naming conventions just in case DB schema varies
            $pid = $ue['parent_ue_id'] ?? ($ue['ue_parent_id'] ?? null);
            if ($pid && isset($ues_by_id[$pid]))
                $ues_by_id[$pid]['children'][] = $ues_by_id[$ue['id']];
        }
        foreach ($ues_by_id as $ue) {
            $pid = $ue['parent_ue_id'] ?? ($ue['ue_parent_id'] ?? null);
            if (!$pid)
                $root[] = $ue;
        }
        $bloc['ues'] = $root;
    }
    return $blocs;
}

function calculate_ue_average($ue)
{
    $total_coef = $ue['coef_cc'] + $ue['coef_partiel'] + $ue['coef_examen'];
    if ($total_coef == 0)
        return 0;
    $s = 0;
    if ($ue['coef_cc'] > 0 && $ue['cc'] !== null)
        $s += $ue['cc'] * $ue['coef_cc'];
    if ($ue['coef_partiel'] > 0 && $ue['partiel'] !== null)
        $s += $ue['partiel'] * $ue['coef_partiel'];
    if ($ue['coef_examen'] > 0 && $ue['examen'] !== null)
        $s += $ue['examen'] * $ue['coef_examen'];
    return $s / $total_coef;
}

function calculate_partial_ue_average($ue)
{
    $s = 0;
    $tc = 0;
    if ($ue['coef_cc'] > 0 && $ue['cc'] !== null) {
        $s += $ue['cc'] * $ue['coef_cc'];
        $tc += $ue['coef_cc'];
    }
    if ($ue['coef_partiel'] > 0 && $ue['partiel'] !== null) {
        $s += $ue['partiel'] * $ue['coef_partiel'];
        $tc += $ue['coef_partiel'];
    }
    if ($ue['coef_examen'] > 0 && $ue['examen'] !== null) {
        $s += $ue['examen'] * $ue['coef_examen'];
        $tc += $ue['coef_examen'];
    }
    if ($tc == 0)
        return null;
    return $s / $tc;
}

function save_notes($pdo, $user_id, $notes)
{
    foreach ($notes as $uid => $n) {
        $stmt = $pdo->prepare("SELECT id FROM notes WHERE utilisateur_id=? AND ue_id=?");
        $stmt->execute([$user_id, $uid]);
        $exists = $stmt->fetch();
        $cc = ($n['cc'] !== '') ? $n['cc'] : null;
        $pa = ($n['partiel'] !== '') ? $n['partiel'] : null;
        $ex = ($n['examen'] !== '') ? $n['examen'] : null;
        if ($exists) {
            $pdo->prepare("UPDATE notes SET cc=?, partiel=?, examen=? WHERE id=?")->execute([$cc, $pa, $ex, $exists['id']]);
        } else if ($cc !== null || $pa !== null || $ex !== null) {
            $pdo->prepare("INSERT INTO notes (utilisateur_id, ue_id, cc, partiel, examen) VALUES (?,?,?,?,?)")->execute([$user_id, $uid, $cc, $pa, $ex]);
        }
    }
}

// --- LOGIQUE DIFFERENTIELLE ---
function simulate_target_bloc($bloc, $target)
{
    $A = 0;
    $B = 0;
    $te = 0;
    foreach ($bloc['ues'] as $ue) {
        $kids = !empty($ue['children']) ? $ue['children'] : [$ue];
        foreach ($kids as $t) {
            $tc = $t['coef_cc'] + $t['coef_partiel'] + $t['coef_examen'];
            if ($tc == 0)
                continue;
            $acq = 0;
            $miss = 0;
            if ($t['coef_cc'] > 0) {
                if ($t['cc'] !== null)
                    $acq += $t['cc'] * $t['coef_cc'];
                else
                    $miss += $t['coef_cc'];
            }
            if ($t['coef_partiel'] > 0) {
                if ($t['partiel'] !== null)
                    $acq += $t['partiel'] * $t['coef_partiel'];
                else
                    $miss += $t['coef_partiel'];
            }
            if ($t['coef_examen'] > 0) {
                if ($t['examen'] !== null)
                    $acq += $t['examen'] * $t['coef_examen'];
                else
                    $miss += $t['coef_examen'];
            }

            $A += ($acq / $tc) * $t['ects'];
            $B += ($miss / $tc) * $t['ects'];
            $te += $t['ects'];
        }
    }
    if ($te == 0)
        return 0;
    if ($B == 0)
        return null;
    $C = $target * $te;
    $need = ($C - $A) / $B;
    return ($need < 0) ? 0 : $need;
}

function simulate_delta_for_collection($blocs, $bases, $target)
{
    $S_Const = 0;
    $S_Var = 0;
    $TE = 0;
    foreach ($blocs as $b) {
        foreach ($b['ues'] as $ue) {
            $kids = !empty($ue['children']) ? $ue['children'] : [$ue];
            foreach ($kids as $t) {
                $base = $bases[$t['id']] ?? 0;
                $tc = $t['coef_cc'] + $t['coef_partiel'] + $t['coef_examen'];
                if ($tc == 0)
                    continue;
                $acq = 0;
                $miss = 0;
                if ($t['coef_cc'] > 0) {
                    if ($t['cc'] !== null)
                        $acq += $t['cc'] * $t['coef_cc'];
                    else
                        $miss += $t['coef_cc'];
                }
                if ($t['coef_partiel'] > 0) {
                    if ($t['partiel'] !== null)
                        $acq += $t['partiel'] * $t['coef_partiel'];
                    else
                        $miss += $t['coef_partiel'];
                }
                if ($t['coef_examen'] > 0) {
                    if ($t['examen'] !== null)
                        $acq += $t['examen'] * $t['coef_examen'];
                    else
                        $miss += $t['coef_examen'];
                }

                $const = ($acq + $base * $miss) / $tc;
                $var = $miss / $tc;
                $S_Const += $const * $t['ects'];
                $S_Var += $var * $t['ects'];
                $TE += $t['ects'];
            }
        }
    }
    if ($TE == 0 || $S_Var == 0)
        return 0;
    $T_Sum = $target * $TE;
    $Delta = ($T_Sum - $S_Const) / $S_Var;
    return ($Delta < 0) ? 0 : $Delta;
}

function fill_grades($ue, $val)
{
    if ($val > 20)
        return [];
    $r = [];
    if ($ue['coef_cc'] > 0 && $ue['cc'] === null)
        $r['CC'] = $val;
    if ($ue['coef_partiel'] > 0 && $ue['partiel'] === null)
        $r['Partiel'] = $val;
    if ($ue['coef_examen'] > 0 && $ue['examen'] === null)
        $r['Examen'] = $val;
    return $r;
}
?>
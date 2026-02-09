<?php
// includes/functions.php
/**
 * Journalise une action utilisateur (Invité ou Connecté).
 */
function track_action($pdo, $user_id, $action)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO tracking (utilisateur_id, action, ip, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $ip, $user_agent]);
}
/**
 * Récupère la structure de la formation (Blocs -> UEs) avec les notes de l'utilisateur (si connecté).
 */
/**
 * Récupère la structure de la formation (Blocs -> UEs) avec les notes de l'utilisateur (si connecté).
 * Supporte le mode hiérarchique (UE Parent -> UE Enfants).
 */
function get_formation_data($pdo, $formation_id, $user_id = null)
{
    // Récupérer les blocs
    $stmt = $pdo->prepare("SELECT * FROM bloc WHERE formation_id = ?");
    $stmt->execute([$formation_id]);
    $blocs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($blocs as &$bloc) {
        $sql = "";
        $params = [];
        if ($user_id !== null) {
            // Mode Connecté
            $sql = "
                SELECT ue.*, n.cc, n.partiel, n.examen 
                FROM ue 
                LEFT JOIN notes n ON ue.id = n.ue_id AND n.utilisateur_id = ? 
                WHERE ue.bloc_id = ? 
                ORDER BY ue.nom
            ";
            $params = [$user_id, $bloc['id']];
        } else {
            // Mode Invité
            $sql = "
                SELECT ue.*, NULL as cc, NULL as partiel, NULL as examen 
                FROM ue 
                WHERE ue.bloc_id = ? 
                ORDER BY ue.nom
            ";
            $params = [$bloc['id']];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $all_ues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Organisation hiérarchique
        $ues_by_id = [];
        $root_ues = [];
        // 1. Indexer et préparer
        foreach ($all_ues as $ue) {
            $ue['children'] = [];
            $ues_by_id[$ue['id']] = $ue;
        }
        // 2. Lier les enfants aux parents
        foreach ($all_ues as $ue) {
            if ($ue['parent_ue_id'] !== null && isset($ues_by_id[$ue['parent_ue_id']])) {
                $ues_by_id[$ue['parent_ue_id']]['children'][] = $ues_by_id[$ue['id']];
            }
        }
        // 3. Filtrer les racines
        foreach ($ues_by_id as $id => $ue) {
            if ($ue['parent_ue_id'] === null) {
                $root_ues[] = $ue;
            }
        }
        $bloc['ues'] = $root_ues;
    }
    return $blocs;
}
/**
 * Calcule la moyenne d'une UE.
 */
function calculate_ue_average($ue, $cc = null, $partiel = null, $examen = null)
{
    // Utiliser les notes fournies ou celles de l'UE (DB) si null
    $cc = $cc !== null ? $cc : $ue['cc'];
    $partiel = $partiel !== null ? $partiel : $ue['partiel'];
    $examen = $examen !== null ? $examen : $ue['examen'];
    $total_coef = $ue['coef_cc'] + $ue['coef_partiel'] + $ue['coef_examen'];
    if ($total_coef == 0)
        return 0;
    $score = 0;
    if ($ue['coef_cc'] > 0 && $cc !== null)
        $score += $cc * $ue['coef_cc'];
    if ($ue['coef_partiel'] > 0 && $partiel !== null)
        $score += $partiel * $ue['coef_partiel'];
    if ($ue['coef_examen'] > 0 && $examen !== null)
        $score += $examen * $ue['coef_examen'];
    // Note : Si une note est manquante mais a un coeff, on considère 0 ou on ignore ? 
    // Pour une simulation stricte, une note manquante est un 0 si on veut voir la moyenne actuelle,
    // mais ici on veut souvent voir la moyenne "potentielle". 
    // Simplification : On divise par le total des coefs seulement si les notes sont présentes ? 
    // Non, l'usage académique est : note manquante = 0 dans le calcul final.
    // Cependant pour la "simulation", on peut vouloir voir "moyenne provisoire".
    // Le sujet demande "prévoir les notes nécessaires", donc on suppose 0 pour les manquantes dans le calcul "actuel".
    // Pour gérer proprement les inputs vides convertis en 0 par PHP parfois :
    // On va considérer que null = 0 pour le calcul final.
    return $score / $total_coef;
}
/**
 * Sauvegarde les notes d'un utilisateur.
 */
function save_notes($pdo, $user_id, $notes_data)
{
    foreach ($notes_data as $ue_id => $notes) {
        // Vérifier si une entrée existe
        $stmt = $pdo->prepare("SELECT id FROM notes WHERE utilisateur_id = ? AND ue_id = ?");
        $stmt->execute([$user_id, $ue_id]);
        $exists = $stmt->fetch();
        $cc = is_numeric($notes['cc']) ? $notes['cc'] : null;
        $partiel = is_numeric($notes['partiel']) ? $notes['partiel'] : null;
        $examen = is_numeric($notes['examen']) ? $notes['examen'] : null;
        if ($exists) {
            $stmt = $pdo->prepare("UPDATE notes SET cc = ?, partiel = ?, examen = ? WHERE id = ?");
            $stmt->execute([$cc, $partiel, $examen, $exists['id']]);
        } else {
            if ($cc !== null || $partiel !== null || $examen !== null) {
                $stmt = $pdo->prepare("INSERT INTO notes (utilisateur_id, ue_id, cc, partiel, examen) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $ue_id, $cc, $partiel, $examen]);
            }
        }
    }
}
/**
 * Partial UE Calculation Helper (Mode "Calcul UE partielles")
 * Rules:
 * - Only CC -> CC
 * - CC + Partiel -> Avg(CC, Partiel)
 * - Empty -> Ignore (return null)
 * - Exam ignored if empty.
 */
function calculate_smart_ue_average($ue)
{
    $grades = [];
    if ($ue['cc'] !== null && $ue['cc'] !== '')
        $grades[] = $ue['cc'];
    if ($ue['partiel'] !== null && $ue['partiel'] !== '')
        $grades[] = $ue['partiel'];
    // Exam is ignored if empty, included if present?
    // User says: "L’examen n’est jamais pris en compte si non renseigné" -> Implies taken if renseigné?
    // "Si seulement CC est renseigné -> note UE = CC"
    // "Si CC + partiel sont renseignés -> moyenne des deux"
    // It doesn't explicitly say what happens if Exam IS renseigné in this mode.
    // However, usually Exam overrides everything or counts for X%. 
    // Given the previous requirement "Si une note n’est pas renseignée, elle est ignorée", implies we just average what is there.
    if ($ue['examen'] !== null && $ue['examen'] !== '')
        $grades[] = $ue['examen'];
    if (empty($grades))
        return null;
    return array_sum($grades) / count($grades);
}
// --- SIMULATION HELPERS ---
function fill_grades($ue, $val)
{
    $res = [];
    if ($ue['coef_cc'] > 0 && $ue['cc'] === null)
        $res['CC'] = $val;
    if ($ue['coef_partiel'] > 0 && $ue['partiel'] === null)
        $res['Partiel'] = $val;
    if ($ue['coef_examen'] > 0 && $ue['examen'] === null)
        $res['Examen'] = $val;
    return $res;
}
function find_uniform_grade_for_ue($ue, $target)
{
    $acquis = 0;
    $miss = 0;
    $total = 0;
    if ($ue['coef_cc'] > 0) {
        $total += $ue['coef_cc'];
        if ($ue['cc'] !== null)
            $acquis += $ue['cc'] * $ue['coef_cc'];
        else
            $miss += $ue['coef_cc'];
    }
    if ($ue['coef_partiel'] > 0) {
        $total += $ue['coef_partiel'];
        if ($ue['partiel'] !== null)
            $acquis += $ue['partiel'] * $ue['coef_partiel'];
        else
            $miss += $ue['coef_partiel'];
    }
    if ($ue['coef_examen'] > 0) {
        $total += $ue['coef_examen'];
        if ($ue['examen'] !== null)
            $acquis += $ue['examen'] * $ue['coef_examen'];
        else
            $miss += $ue['coef_examen'];
    }
    if ($miss == 0)
        return ($acquis / $total >= $target) ? 0 : false;
    $g = ($target * $total - $acquis) / $miss;
    return max(0, $g);
}
// ... other simulation helpers moved here to clean up resultat.php ...
// For brevity I will keep some complex ones in resultat.php IF they rely on global scope, but they don't seem to.
// Actually, moving them all is safer.
function get_bloc_ects($bloc)
{
    $e = 0;
    foreach ($bloc['ues'] as $ue) {
        if (!empty($ue['children'])) {
            foreach ($ue['children'] as $c)
                $e += $c['ects'];
        } else {
            $e += $ue['ects'];
        }
    }
    return $e;
}
function sim_ue_avg($ue, $g)
{
    $acquis = 0;
    $total = 0;
    if ($ue['coef_cc'] > 0) {
        $total += $ue['coef_cc'];
        $acquis += (($ue['cc'] !== null) ? $ue['cc'] : $g) * $ue['coef_cc'];
    }
    if ($ue['coef_partiel'] > 0) {
        $total += $ue['coef_partiel'];
        $acquis += (($ue['partiel'] !== null) ? $ue['partiel'] : $g) * $ue['coef_partiel'];
    }
    if ($ue['coef_examen'] > 0) {
        $total += $ue['coef_examen'];
        $acquis += (($ue['examen'] !== null) ? $ue['examen'] : $g) * $ue['coef_examen'];
    }
    return ($total > 0) ? $acquis / $total : 0;
}
function get_bloc_sim_avg($bloc, $g)
{
    $sum = 0;
    $ects = 0;
    foreach ($bloc['ues'] as $ue) {
        $targets = !empty($ue['children']) ? $ue['children'] : [$ue];
        foreach ($targets as $t) {
            $sim_mean = sim_ue_avg($t, $g);
            $sum += $sim_mean * $t['ects'];
            $ects += $t['ects'];
        }
    }
    return ($ects > 0) ? $sum / $ects : 0;
}
function check_bloc_sim($bloc, $g, $target)
{
    return get_bloc_sim_avg($bloc, $g) >= $target;
}
function find_uniform_grade_for_bloc($bloc, $target)
{
    for ($g = 0; $g <= 20.1; $g += 0.1) {
        if (check_bloc_sim($bloc, $g, $target))
            return $g;
    }
    return false;
}
function find_uniform_grade_for_year($blocs, $thresh_bloc, $thresh_trans, $thresh_non_trans, $thresh_gen)
{
    for ($g = 0; $g <= 20.1; $g += 0.1) {
        $blocks_ok = true;
        $sum_nt = 0;
        $ects_nt = 0;
        $sum_gen = 0;
        $ects_gen = 0;
        foreach ($blocs as $b) {
            $is_tr = ($b['nom'] === "Bloc Transverse");
            $local_val = get_bloc_sim_avg($b, $g);
            if ($local_val < ($is_tr ? $thresh_trans : $thresh_bloc)) {
                $blocks_ok = false;
                break;
            }
            $e = get_bloc_ects($b);
            $sum_gen += $local_val * $e;
            $ects_gen += $e;
            if (!$is_tr) {
                $sum_nt += $local_val * $e;
                $ects_nt += $e;
            }
        }
        if (!$blocks_ok)
            continue;
        $Gen = ($ects_gen > 0) ? $sum_gen / $ects_gen : 0;
        $Nt = ($ects_nt > 0) ? $sum_nt / $ects_nt : 0;
        if ($Gen >= $thresh_gen && $Nt >= $thresh_non_trans)
            return $g;
    }
    return false;
}
/**
 * Calcule la moyenne 'Partielle' d'une UE.
 * Règles:
 * - Si CC seul => NOTE = CC
 * - Si CC + Partiel => NOTE = (CC + Partiel) / 2
 * - Si Examen => Pris en compte ou pas ? "Examen ignoré tant qu'aucune note n'est saisie" -> Si saisi, il est pris en compte ?
 *   L'utilisateur dit "Examen ignoré tant qu'aucune note n'est saisie".
 *   Mais le calcul standard est (CC*Coef + ...).
 *   Sujet: "Calcul du bloc = Somme(NoteUE * Ects) / Somme(Ects des UE renseignées)".
 *   Pour l'UE elle-même : "Si seule CC -> CC", "Si CC+Partiel -> Moyenne". 
 *   On va assumer une moyenne simple des notes présentes pour l'UE dans ce mode.
 */
function calculate_partial_ue_average($ue) {
    // Récupérer les notes non nulles et non vides
    $grades = [];
    if (isset($ue['cc']) && $ue['cc'] !== null && $ue['cc'] !== '') $grades[] = $ue['cc'];
    if (isset($ue['partiel']) && $ue['partiel'] !== null && $ue['partiel'] !== '') $grades[] = $ue['partiel'];
    if (isset($ue['examen']) && $ue['examen'] !== null && $ue['examen'] !== '') $grades[] = $ue['examen'];
    if (empty($grades)) return null;
    return array_sum($grades) / count($grades);
}
?>
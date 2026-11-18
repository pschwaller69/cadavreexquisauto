<?php
/*
 * Tâche planifiée (toutes les 5 minutes) : relances, passage automatique au suivant, alertes de retard,
 * placement des inscrits en attente / ouverture de cadavres, file d'envoi des mails « nouvelle photo ».
 * cPanel o2switch > Tâches Cron :
 *   0-59/5 * * * * /usr/local/bin/php /home/VOTRE_COMPTE/cadavreexquisauto.schwaller.art/cron.php >/dev/null 2>&1
 * ou par URL : https://cadavreexquisauto.schwaller.art/cron.php?key=VOTRE_CLE
 */
require __DIR__ . '/inc/bootstrap.php';

if (PHP_SAPI !== 'cli' && !hash_equals((string) cfg('cron_key'), get('key'))) {
    http_response_code(403);
    exit('Accès refusé');
}
header('Content-Type: text/plain; charset=utf-8');
@file_put_contents(DATA_DIR . '/cron_dernier.txt', now());

$log = [];
$retards = [];
$n = now();
$rel = (int) cfg('relance_jours', 10);
$auto = (int) cfg('auto_passer_jours', 2);

foreach (q("SELECT * FROM maillons WHERE statut = 'en_cours' ORDER BY date_limite")->fetchAll() as $m) {
    $p = participant((int) $m['participant_id']);
    $ch = chaine((int) $m['chaine_id']);
    $qui = "{$p['prenom']} {$p['nom']} (cadavre n°{$ch['numero']})";

    // 1. Passage automatique au suivant
    if ($auto > 0 && plus_jours($m['date_limite'], $auto) < $n) {
        passer_maillon($m, "Passé automatiquement ($auto j après la date limite)");
        $log[] = "Passé automatiquement : $qui";
        continue;
    }
    // 2. Relance à J+N
    if (!$m['relance_envoyee'] && plus_jours($m['date_envoi'], $rel) <= $n && $m['date_limite'] > $n) {
        envoyer_modele('relance', $p['mail'], vars_mail($p, $m), $p['prenom'] . ' ' . $p['nom']);
        update('maillons', (int) $m['id'], ['relance_envoyee' => 1]);
        $log[] = "Relance : $qui";
    }
    // 3. Date limite dépassée : alerte à l'admin seulement si rien n'est automatique
    if ($auto <= 0 && !$m['alerte_envoyee'] && $m['date_limite'] < $n) {
        update('maillons', (int) $m['id'], ['alerte_envoyee' => 1]);
        $retards[] = "- $qui, limite le " . fdate($m['date_limite']) . " : " . url('admin/chaine.php?id=' . $ch['id']);
    }
}
if ($retards) {
    mail_admin(count($retards) . ' photo(s) en retard', "Date limite dépassée pour :\n" . implode("\n", $retards));
    $log[] = count($retards) . ' alerte(s) de retard';
}

// 4. Rappels « recontacte-moi dans… » arrivés à échéance
foreach (q('SELECT * FROM participants WHERE rappel_le IS NOT NULL AND rappel_le <= ?', [$n])->fetchAll() as $p) {
    q('UPDATE participants SET rappel_le = NULL WHERE id = ?', [$p['id']]);
    $libre = !$p['en_attente_depuis'] && !qv("SELECT COUNT(*) FROM maillons WHERE participant_id = ? AND statut = 'en_cours'", [$p['id']]);
    if ($libre && mettre_en_file('rappel', $p['mail'], vars_mail($p), $p['prenom'] . ' ' . $p['nom'])) $log[] = "Rappel : {$p['prenom']} {$p['nom']}";
}

// 5. Inscrits en attente : placement, ouverture de nouveaux cadavres
if ($k = placer_attente()) $log[] = "$k inscrit(s) placé(s)";

// 6. File d'envoi des mails groupés
if ($k = traiter_file((int) cfg('cron_mails_par_passage', 3))) $log[] = "$k mail(s) de la file envoyé(s), " . file_en_attente() . ' restant(s)';

echo date('d/m/Y H:i') . ' – ' . ($log ? implode("\n", $log) : 'rien à faire') . "\n";

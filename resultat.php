<?php
/*
 * Le cadavre d'un participant, vu par lui (lien reçu après le dépôt de sa photo : resultat.php?t=<jeton du maillon>).
 * Visible uniquement une fois sa photo rendue ; se complète à chaque nouvelle photo.
 */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/vue.php';
header('X-Robots-Tag: noindex, nofollow');

$t = get('t');
$m = $t !== '' ? q1('SELECT * FROM maillons WHERE token = ?', [$t]) : null;

if (!$m || $m['statut'] !== 'rendu') {
    http_response_code($m ? 403 : 404); ?>
<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cadavre Exquis Automatique</title><link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
<body class="public"><main class="carte"><h1>Cadavre Exquis <span>Automatique</span></h1>
<?php if ($m && $m['statut'] === 'en_cours'): ?>
  <p>Le cadavre se dévoile une fois ta photo déposée. <a href="depot.php?t=<?= h($t) ?>">Déposer ma photo →</a></p>
<?php else: ?>
  <p>Ce lien n'est pas valide.</p>
<?php endif ?>
</main></body></html>
<?php exit;
}

$p = participant((int) $m['participant_id']);
$ch = chaine((int) $m['chaine_id']);
$tq = 'img.php?t=' . urlencode($t) . '&';
afficher_cadavre($ch, fn(string $type, ?int $id) => $tq . ($type === 'depart' ? 'q=depart' : 'm=' . $id), [
    'moi' => (int) $m['id'],
    'retour' => 'moi.php?u=' . $p['token'], 'retour_lib' => 'Mon espace',
    'head' => '<meta name="robots" content="noindex"><link rel="icon" type="image/png" href="assets/icons/favicon.png">',
]);

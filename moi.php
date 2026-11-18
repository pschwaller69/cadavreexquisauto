<?php
/* Espace personnel d'un participant (lien moi.php?u=<jeton>) : ses cadavres, sa place, rejouer, mails de suivi. */
require __DIR__ . '/inc/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');

$u = get('u');
$p = $u !== '' ? q1('SELECT * FROM participants WHERE token = ?', [$u]) : null;

if ($p && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $en_jeu = qv("SELECT COUNT(*) FROM maillons WHERE participant_id = ? AND statut = 'en_cours'", [$p['id']]);
    switch (post('action')) {
        case 'rejouer':
            rejouer($p);
            break;
        case 'annuler_rappel':
            q('UPDATE participants SET rappel_le = NULL WHERE id = ?', [$p['id']]);
            break;
        case 'quitter_file':
            q('UPDATE participants SET en_attente_depuis = NULL WHERE id = ?', [$p['id']]);
            break;
        case 'notif':
            update('participants', (int) $p['id'], ['notif' => post('notif') ? 1 : 0]);
            break;
    }
    redirect(url('moi.php?u=' . urlencode($u) . '&ok=1'));
}

if ($p) {
    $ms = q("SELECT m.*, c.numero, c.statut AS cstatut,
               (SELECT COUNT(*) FROM maillons x WHERE x.chaine_id = m.chaine_id AND x.statut = 'rendu') AS nb
             FROM maillons m JOIN chaines c ON c.id = m.chaine_id WHERE m.participant_id = ? ORDER BY m.id DESC", [$p['id']])->fetchAll();
    $cours = array_values(array_filter($ms, fn($m) => $m['statut'] === 'en_cours'))[0] ?? null;
    $rang = rang_attente($p);
}
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mon espace – Cadavre Exquis Automatique</title><link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
<link rel="icon" type="image/png" href="assets/icons/favicon.png">
<body class="public"><main class="carte">
<h1>Cadavre Exquis <span>Automatique</span></h1>
<?php if (!$p): ?>
  <p class="alerte ko">Ce lien n'est pas valide. Pour le recevoir à nouveau, inscris-toi avec la même adresse sur <a href="./">la page d'accueil</a>.</p>
<?php else: ?>
  <h2>Bonjour <?= h($p['prenom']) ?></h2>
  <?php if (get('ok')): ?><p class="alerte ok">C'est enregistré.</p><?php endif ?>

  <?php if ($cours): ?>
    <p class="alerte info">Une photo t'attend dans le cadavre n°<?= (int) $cours['numero'] ?>, à déposer avant le <?= fdate($cours['date_limite']) ?>.<br>
      <a class="btn" href="depot.php?t=<?= h($cours['token']) ?>" style="margin-top:8px">Voir la photo et déposer la mienne</a></p>
  <?php elseif ($rang): ?>
    <p class="alerte info">Tu es en file d'attente : <?= h(phrase_rang($rang)) ?> Ton tour devrait arriver
      <?= h(texte_estimation(duree_moyenne_maillon() * $rang / max(1, cadavres_ouverts()))) ?>.</p>
    <form method="post" onsubmit="return confirm('Quitter la file d\'attente ?')"><?= csrf_field() ?><input type="hidden" name="action" value="quitter_file">
      <button class="lien">Quitter la file d'attente</button></form>
  <?php else: ?>
    <?php if (get('rejouer')): ?><p class="alerte info">Content de te revoir ! Un clic et tu es dans la partie :</p><?php endif ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="rejouer">
      <button class="btn">Rejouer dans un nouveau cadavre</button></form>
    <?php if ($p['rappel_le']): ?>
      <p class="petit" style="margin-top:10px">Nous te recontacterons le <?= fdate($p['rappel_le']) ?>.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="annuler_rappel"><button class="lien">Annuler ce rappel</button></form>
    <?php endif ?>
  <?php endif ?>

  <h2>Mes cadavres</h2>
  <?php $faits = array_filter($ms, fn($m) => $m['statut'] === 'rendu'); ?>
  <?php if (!$faits): ?><p class="petit">Tes cadavres apparaîtront ici dès ta première photo déposée.</p><?php endif ?>
  <ul class="mes-cadavres">
  <?php foreach ($faits as $m): ?>
    <li><a href="resultat.php?t=<?= h($m['token']) ?>"><img src="img.php?t=<?= h($m['token']) ?>&amp;q=moi" alt="" loading="lazy">
      <span><strong>Cadavre n°<?= (int) $m['numero'] ?></strong><br><?= (int) $m['nb'] ?>/<?= total_photos() ?> photos
      · <?= $m['cstatut'] === 'terminee' ? 'terminé' : 'en cours' ?><?= $m['titre'] ? '<br><em>« ' . h($m['titre']) . ' »</em>' : '' ?></span></a></li>
  <?php endforeach ?>
  </ul>

  <form method="post" class="form" style="margin-top:20px"><?= csrf_field() ?><input type="hidden" name="action" value="notif">
    <label class="case"><input type="checkbox" name="notif" value="1" <?= $p['notif'] ? 'checked' : '' ?> onchange="this.form.submit()">
      Me prévenir par mail à chaque nouvelle photo dans mes cadavres (le mail de fin est toujours envoyé).</label>
    <noscript><button class="btn sec">Enregistrer</button></noscript>
  </form>
<?php endif ?>
</main></body></html>

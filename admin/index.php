<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    switch (post('action')) {
        case 'placer':
            $n = placer_attente();
            flash($n ? "$n inscrit(s) placé(s)." : 'Personne n\'a pu être placé (aucun cadavre libre compatible et pas de nouveau cadavre nécessaire).', $n ? 'ok' : 'info');
            break;
        case 'nouveau':
            $ch = creer_chaine();
            if ($ch) { placer_attente(); flash("Cadavre n°{$ch['numero']} ouvert."); }
            else flash('Impossible : aucune photo de départ disponible.', 'ko');
            break;
    }
    redirect('index.php');
}

$st = stats_publiques();
$st['en_jeu'] = (int) qv("SELECT COUNT(*) FROM maillons WHERE statut = 'en_cours'");
$st['retard'] = (int) qv("SELECT COUNT(*) FROM maillons WHERE statut = 'en_cours' AND date_limite < ?", [now()]);
$st['inscrits'] = (int) qv('SELECT COUNT(*) FROM participants');
$chaines = q("SELECT * FROM chaines WHERE statut <> 'terminee' ORDER BY numero")->fetchAll();
$attente = file_attente();
$pool = count(pool_depart());
$cron = dernier_cron();
$cron_ok = $cron && strtotime($cron) > time() - 3 * 60 * max(5, (int) cfg('cron_minutes', 5));
$duree = duree_moyenne_maillon();

entete('Tableau de bord', 'index');
?>
<h1>Cadavre exquis automatique</h1>
<?php if (!$pool): ?><p class="alerte ko">Aucune photo de départ : aucun cadavre ne peut s'ouvrir. <a href="depart.php">Ajoutez des photos de départ</a>.</p><?php endif ?>
<?php if (!$cron_ok): ?><p class="alerte ko">La tâche cron ne tourne pas (dernier passage : <?= $cron ? fdate($cron, true) : 'jamais' ?>) : relances, passages automatiques et mails « nouvelle photo » sont bloqués. Voir le README.</p><?php endif ?>
<?php if ($nf = file_en_attente()): ?><p class="alerte info"><?= $nf ?> mail(s) « nouvelle photo / cadavre terminé » en file d'envoi, fin estimée <?= h(fin_estimee_file()) ?> – <a href="mails.php">suivi</a>.</p><?php endif ?>

<div class="actions">
  <form method="post"><?= csrf_field() ?><button class="btn sec" name="action" value="placer">↻ Placer les inscrits en attente</button></form>
  <form method="post" onsubmit="return confirm('Ouvrir un cadavre de plus, avec une photo de départ tirée au sort ?')"><?= csrf_field() ?><button class="btn sec" name="action" value="nouveau">＋ Ouvrir un cadavre</button></form>
  <a class="btn sec" href="<?= h(url()) ?>" target="_blank">Page d'inscription ↗</a>
</div>

<div class="stats">
  <div class="stat"><b><?= $st['en_cours'] ?></b><span>cadavres en cours</span></div>
  <div class="stat"><b><?= $st['termines'] ?></b><span>terminés</span></div>
  <div class="stat"><b><?= $st['en_jeu'] ?></b><span>photos attendues</span></div>
  <div class="stat"><b style="color:<?= $st['retard'] ? 'var(--rouge)' : 'inherit' ?>"><?= $st['retard'] ?></b><span>en retard</span></div>
  <div class="stat"><b><?= $st['attente'] ?></b><span>en file d'attente</span></div>
  <div class="stat"><b><?= $st['photos'] ?></b><span>photos reçues</span></div>
  <div class="stat"><b><?= $st['inscrits'] ?></b><span>inscrits</span></div>
</div>
<p class="petit">Règles : <?= total_photos() ?> photos par cadavre · <?= (int) cfg('delai_jours') ?> j pour rendre, relance à J+<?= (int) cfg('relance_jours') ?>,
  <?= (int) cfg('auto_passer_jours') > 0 ? 'passage automatique ' . (int) cfg('auto_passer_jours') . ' j après la limite' : 'pas de passage automatique' ?> ·
  nouveau cadavre si plus de <?= (int) cfg('attente_par_cadavre') ?> inscrit(s) en attente par cadavre en cours ou attente &gt; <?= (int) cfg('attente_max_jours') ?> j
  (max <?= (int) cfg('cadavres_max') ?>) · <?= $pool ?> photo(s) de départ possibles · durée moyenne d'une photo : <?= number_format($duree, 1, ',', '') ?> j.</p>

<h2>Cadavres en cours (<?= count($chaines) ?>)</h2>
<?php if (!$chaines): ?><p>Aucun cadavre en cours : le premier s'ouvrira à la première inscription.</p><?php endif ?>
<div class="grille-chaines">
<?php foreach ($chaines as $ch):
    $ms = maillons_chaine((int) $ch['id']);
    $cours = array_values(array_filter($ms, fn($m) => $m['statut'] === 'en_cours'))[0] ?? null;
    $rendus = array_filter($ms, fn($m) => $m['statut'] === 'rendu'); ?>
  <div class="ch">
    <h3><a href="chaine.php?id=<?= $ch['id'] ?>">Cadavre n°<?= (int) $ch['numero'] ?></a> <?= badge($ch['statut']) ?> <span class="petit"><?= count($rendus) ?>/<?= total_photos() ?></span></h3>
    <div class="vignettes">
      <img src="../img.php?d=<?= $ch['id'] ?>" title="Départ : <?= h($ch['depart_credit']) ?>" style="outline:2px solid var(--violet)">
      <?php foreach ($rendus as $m): ?><img src="../img.php?m=<?= $m['id'] ?>" title="<?= h($m['prenom'] . ' ' . $m['nom']) ?>" loading="lazy"><?php endforeach ?>
      <?php if ($cours): ?><div class="vide">?</div><?php endif ?>
    </div>
    <div class="encours">
      <?php if ($cours): ?>Chez <strong><?= h($cours['prenom'] . ' ' . $cours['nom']) ?></strong> jusqu'au <?= fdate($cours['date_limite']) ?> <?= en_retard($cours) ? badge('retard') : '' ?>
      <?php else: ?>Attend un photographe depuis le <?= fdate($ch['attente_depuis'], true) ?><?php endif ?>
    </div>
  </div>
<?php endforeach ?>
</div>

<h2>File d'attente (<?= count($attente) ?>)</h2>
<?php if (!$attente): ?><p class="petit">Personne en attente : chaque nouvel inscrit est placé tout de suite (ou un cadavre s'ouvre).</p><?php else: ?>
<table class="liste"><tr><th>#</th><th>Participant</th><th>Club</th><th>En attente depuis</th><th>Cadavres joués</th></tr>
<?php foreach ($attente as $i => $p): ?>
  <tr><td><?= $i + 1 ?></td><td><a href="participants.php?id=<?= $p['id'] ?>"><?= h($p['prenom'] . ' ' . $p['nom']) ?></a></td><td><?= h($p['club']) ?></td>
    <td><?= fdate($p['en_attente_depuis'], true) ?></td><td><?= (int) qv("SELECT COUNT(*) FROM maillons WHERE participant_id = ? AND statut = 'rendu'", [$p['id']]) ?></td></tr>
<?php endforeach ?></table>
<?php endif ?>
<?php pied();

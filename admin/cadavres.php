<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
require_admin();

$f = get('f', 'tous');
$where = ['en_cours' => "WHERE c.statut <> 'terminee'", 'termines' => "WHERE c.statut = 'terminee'"][$f] ?? '';
$chaines = q("SELECT c.*, (SELECT COUNT(*) FROM maillons m WHERE m.chaine_id = c.id AND m.statut = 'rendu') AS nb,
              (SELECT COUNT(*) FROM maillons m WHERE m.chaine_id = c.id AND m.statut = 'passe') AS passes
              FROM chaines c $where ORDER BY c.numero DESC")->fetchAll();

entete('Cadavres', 'cadavres');
?>
<h1>Cadavres</h1>
<p class="filtre"><?php foreach (['tous' => 'Tous', 'en_cours' => 'En cours', 'termines' => 'Terminés'] as $k => $lib): ?>
  <a class="btn petit <?= $f === $k ? '' : 'sec' ?>" href="?f=<?= $k ?>"><?= $lib ?></a> <?php endforeach ?></p>
<table class="liste"><tr><th>N°</th><th>Départ</th><th>Photos</th><th>État</th><th>Ouvert le</th><th>Terminé le</th><th>Passés</th><th></th></tr>
<?php foreach ($chaines as $ch): ?>
<tr><td><a href="chaine.php?id=<?= $ch['id'] ?>"><strong><?= (int) $ch['numero'] ?></strong></a></td>
  <td><img class="mini" src="../img.php?d=<?= $ch['id'] ?>" alt="" loading="lazy"> <span class="petit"><?= h($ch['depart_credit']) ?></span></td>
  <td><?= (int) $ch['nb'] ?>/<?= total_photos() ?></td><td><?= badge($ch['statut']) ?></td>
  <td><?= fdate($ch['date_creation']) ?></td><td><?= fdate($ch['date_fin']) ?></td><td><?= (int) $ch['passes'] ?: '' ?></td>
  <td><a class="btn petit sec" href="voir.php?id=<?= $ch['id'] ?>" target="_blank">Voir ↗</a></td></tr>
<?php endforeach ?>
<?php if (!$chaines): ?><tr><td colspan="8">Aucun cadavre.</td></tr><?php endif ?>
</table>
<?php pied();

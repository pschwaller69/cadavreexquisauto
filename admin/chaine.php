<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
require_admin();

$ch = chaine((int) get('id'));
if (!$ch) redirect('cadavres.php');
$self = 'chaine.php?id=' . $ch['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $m = post('maillon') ? maillon((int) post('maillon')) : null;
    if ($m && $m['chaine_id'] != $ch['id']) $m = null;
    $p = $m ? participant((int) $m['participant_id']) : null;
    try {
        switch (post('action')) {
            case 'relancer':
                envoyer_modele('relance', $p['mail'], vars_mail($p, $m), $p['prenom'] . ' ' . $p['nom']);
                update('maillons', (int) $m['id'], ['relance_envoyee' => 1]);
                flash('Relance envoyée à ' . $p['prenom'] . ' ' . $p['nom'] . '.');
                break;
            case 'renvoyer':
                envoyer_modele('a_toi', $p['mail'], vars_mail($p, $m), $p['prenom'] . ' ' . $p['nom']);
                flash('Mail « c\'est ton tour » renvoyé.');
                break;
            case 'prolonger':
                update('maillons', (int) $m['id'], ['date_limite' => plus_jours($m['date_limite'], (int) post('jours', 7)),
                    'relance_envoyee' => 0, 'alerte_envoyee' => 0]);
                flash('Délai prolongé.');
                break;
            case 'passer':
                passer_maillon($m, post('note') ?: 'Passé par l\'administrateur');
                flash($p['prenom'] . ' ' . $p['nom'] . ' passé(e) ; le cadavre repart avec le prochain inscrit.');
                break;
            case 'deposer': // photo reçue par mail : l'admin la dépose à la place du participant
                deposer_photo($m, $_FILES['photo'] ?? []);
                flash('Photo déposée pour ' . $p['prenom'] . ' ' . $p['nom'] . '.');
                break;
            case 'remplacer':
                [$o, $r] = enregistrer_image($_FILES['photo'] ?? [], dossier_chaine($ch),
                    sprintf('%02d %s %s', $m['position'], $p['prenom'], $p['nom']) . ' v' . date('His'));
                update('maillons', (int) $m['id'], ['photo' => $o, 'photo_reduite' => $r]);
                flash('Photo remplacée.');
                break;
            case 'textes':
                update('maillons', (int) $m['id'], ['titre' => mb_substr(post('titre'), 0, 200) ?: null,
                    'demarche' => mb_substr(str_replace("\r\n", "\n", post('demarche')), 0, 600) ?: null]);
                flash('Titre et démarche enregistrés.');
                break;
            case 'retirer': // photo inappropriée : retirée du cadavre, le participant est considéré comme passé
                if ($m['statut'] !== 'rendu') throw new RuntimeException('Seule une photo rendue peut être retirée.');
                if (qv("SELECT COUNT(*) FROM maillons WHERE chaine_id = ? AND position > ? AND statut <> 'passe'", [$ch['id'], $m['position']]))
                    throw new RuntimeException('Impossible : d\'autres photos ont déjà été faites à partir de celle-ci.');
                update('maillons', (int) $m['id'], ['statut' => 'passe', 'note' => 'Photo retirée par l\'administrateur']);
                q("DELETE FROM file_envoi WHERE statut = 'attente' AND cle = ?", ['c' . $ch['id']]); // annonces de cette photo pas encore parties
                if ($ch['statut'] !== 'terminee') { update('chaines', (int) $ch['id'], ['statut' => 'attente', 'attente_depuis' => now()]); placer_attente(); }
                flash('Photo retirée du cadavre.');
                break;
            case 'retirer_depart':
                if (nb_rendus((int) $ch['id']) || maillon_en_cours((int) $ch['id'])) throw new RuntimeException('Le cadavre a déjà commencé : la photo de départ ne peut plus changer.');
                $photo = tirer_photo_depart((int) $ch['numero']);
                if (!$photo) throw new RuntimeException('Aucune autre photo de départ disponible.');
                poser_depart($ch, $photo);
                flash('Nouvelle photo de départ tirée au sort.');
                break;
            case 'terminer':
                update('chaines', (int) $ch['id'], ['statut' => 'terminee', 'date_fin' => now(), 'attente_depuis' => null]);
                flash('Cadavre terminé (le participant en cours peut encore déposer sa photo).');
                break;
            case 'rouvrir':
                if (nb_rendus((int) $ch['id']) >= total_photos()) throw new RuntimeException('Ce cadavre a déjà ses ' . total_photos() . ' photos.');
                update('chaines', (int) $ch['id'], ['statut' => maillon_en_cours((int) $ch['id']) ? 'active' : 'attente', 'date_fin' => null, 'attente_depuis' => now()]);
                placer_attente();
                flash('Cadavre rouvert.');
                break;
            case 'supprimer':
                supprimer_chaine($ch);
                flash('Cadavre n°' . $ch['numero'] . ' supprimé.');
                redirect('cadavres.php');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'ko');
    }
    redirect($self);
}

$ms = maillons_chaine((int) $ch['id']);
$nb = nb_rendus((int) $ch['id']);
$k = 0;

entete('Cadavre n°' . $ch['numero'], 'cadavres');
?>
<h1>Cadavre n°<?= (int) $ch['numero'] ?> <?= badge($ch['statut']) ?> <span class="petit"><?= $nb ?>/<?= total_photos() ?> photos</span></h1>
<div class="actions">
  <a class="btn sec" href="cadavres.php">← Cadavres</a>
  <a class="btn sec" href="voir.php?id=<?= $ch['id'] ?>" target="_blank">▣ Voir / diaporama ↗</a>
  <?php if ($ch['statut'] !== 'terminee'): ?>
    <form method="post" onsubmit="return confirm('Terminer ce cadavre maintenant (avant <?= total_photos() ?> photos) ?')"><?= csrf_field() ?><button class="btn sec" name="action" value="terminer">■ Terminer</button></form>
  <?php elseif ($nb < total_photos()): ?>
    <form method="post"><?= csrf_field() ?><button class="btn sec" name="action" value="rouvrir">↺ Rouvrir</button></form>
  <?php endif ?>
  <form method="post" onsubmit="return confirm('Supprimer définitivement le cadavre n°<?= (int) $ch['numero'] ?> et ses photos ?')"><?= csrf_field() ?>
    <button class="btn danger" name="action" value="supprimer">✕ Supprimer</button></form>
</div>

<table class="liste">
<tr><th>#</th><th>Photo</th><th>Participant</th><th>Club</th><th>Envoi</th><th>Limite</th><th>Retour</th><th>État</th><th>Actions</th></tr>
<tr><td>0</td><td><a href="../img.php?d=<?= $ch['id'] ?>" target="_blank"><img class="mini" src="../img.php?d=<?= $ch['id'] ?>"></a></td>
  <td colspan="6"><em>Photo de départ tirée au sort</em><br><span class="petit"><?= h($ch['depart_credit']) ?></span></td>
  <td><?php if (!$ms): ?><form method="post"><?= csrf_field() ?><button class="btn petit sec" name="action" value="retirer_depart">Tirer une autre</button></form><?php endif ?></td></tr>
<?php foreach ($ms as $m): if ($m['statut'] === 'rendu') $k++; ?>
<tr>
  <td><?= $m['statut'] === 'rendu' ? $k : '' ?></td>
  <td><?php if ($m['photo_reduite']): ?><a href="../img.php?m=<?= $m['id'] ?>&amp;v=o" target="_blank"><img class="mini" src="../img.php?m=<?= $m['id'] ?>" loading="lazy"></a><?php endif ?></td>
  <td><a href="participants.php?id=<?= $m['participant_id'] ?>"><strong><?= h($m['nom']) ?></strong> <?= h($m['prenom']) ?></a><br><span class="petit"><?= h($m['mail']) ?></span><?= $m['titre'] ? '<br><em>« ' . h($m['titre']) . ' »</em>' : '' ?><?= !empty($m['demarche']) ? '<br><span class="petit" title="' . h($m['demarche']) . '">Démarche : ' . h(mb_strimwidth($m['demarche'], 0, 90, '…')) . '</span>' : '' ?></td>
  <td><?= h($m['club']) ?></td>
  <td><?= fdate($m['date_envoi']) ?></td>
  <td><?= fdate($m['date_limite']) ?><?= $m['relance_envoyee'] ? '<br><span class="petit">relancé</span>' : '' ?></td>
  <td><?= fdate($m['date_retour']) ?></td>
  <td><?= en_retard($m) ? badge('retard') : badge($m['statut']) ?><?= $m['note'] ? '<br><span class="petit">' . h($m['note']) . '</span>' : '' ?></td>
  <td>
  <?php if ($m['statut'] === 'en_cours'): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="maillon" value="<?= $m['id'] ?>"><button class="btn petit sec" name="action" value="relancer">Relancer</button></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="maillon" value="<?= $m['id'] ?>"><input type="hidden" name="jours" value="7"><button class="btn petit sec" name="action" value="prolonger">+7 j</button></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="maillon" value="<?= $m['id'] ?>"><button class="btn petit sec" name="action" value="renvoyer" title="Renvoyer le mail avec le lien">Renvoyer lien</button></form>
    <form method="post" onsubmit="this.note.value = prompt('Raison (facultatif) :', 'Pas de réponse') ?? ''; return confirm('Passer ce participant ? Le cadavre repart avec le prochain inscrit.')"><?= csrf_field() ?>
      <input type="hidden" name="maillon" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="passer"><input type="hidden" name="note"><button class="btn petit danger">Passer</button></form>
    <form method="post" enctype="multipart/form-data" style="display:block;margin-top:6px"><?= csrf_field() ?>
      <input type="hidden" name="maillon" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="deposer">
      <input type="file" name="photo" accept="image/*" required style="width:200px;padding:3px"> <button class="btn petit">Déposer pour lui</button></form>
    <span class="petit">Lien : <a href="<?= h(url('depot.php?t=' . $m['token'])) ?>" target="_blank">page de dépôt</a></span>
  <?php elseif ($m['statut'] === 'rendu'): ?>
    <a class="btn petit sec" href="../img.php?m=<?= $m['id'] ?>&amp;v=o&amp;dl=1">Original</a>
    <a class="btn petit sec" href="<?= h(url('resultat.php?t=' . $m['token'])) ?>" target="_blank" title="La page que voit ce participant">Sa vue ↗</a>
    <details style="margin-top:6px"><summary class="petit" style="cursor:pointer">Titre / démarche / remplacer</summary>
      <form method="post" class="form" style="margin-top:6px;min-width:260px"><?= csrf_field() ?><input type="hidden" name="maillon" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="textes">
        <input name="titre" maxlength="200" placeholder="Titre" value="<?= h($m['titre']) ?>">
        <textarea name="demarche" class="court" maxlength="600" placeholder="Démarche"><?= h($m['demarche'] ?? '') ?></textarea>
        <button class="btn petit">Enregistrer</button></form>
      <form method="post" enctype="multipart/form-data" style="display:block;margin-top:6px"><?= csrf_field() ?>
        <input type="hidden" name="maillon" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="remplacer">
        <input type="file" name="photo" accept="image/*" required style="width:200px;padding:3px"> <button class="btn petit sec">Remplacer</button></form>
      <form method="post" style="margin-top:6px" onsubmit="return confirm('Retirer cette photo du cadavre ? (possible seulement si c\'est la dernière)')"><?= csrf_field() ?>
        <input type="hidden" name="maillon" value="<?= $m['id'] ?>"><button class="btn petit danger" name="action" value="retirer">Retirer la photo</button></form>
    </details>
  <?php endif ?>
  </td>
</tr>
<?php endforeach ?>
</table>
<?php pied();

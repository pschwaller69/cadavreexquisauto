<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $p = participant((int) post('id'));
    try {
        switch (post('action')) {
            case 'ajouter':
                if (!filter_var(post('mail'), FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Adresse mail invalide.');
                $r = inscrire(post('nom'), post('prenom'), post('mail'), post('club'));
                flash($r['etat'] === 'deja' ? 'Déjà inscrit(e) et en jeu ou en attente (lien de son espace renvoyé).'
                    : $r['participant']['prenom'] . ' ' . $r['participant']['nom'] . (!empty($r['maillon']) ? ' inscrit(e) et placé(e) dans un cadavre.' : ' inscrit(e), en file d\'attente.'));
                redirect('participants.php');
            case 'modifier':
                update('participants', (int) $p['id'], ['nom' => nom_propre(post('nom')), 'prenom' => prenom_propre(post('prenom')),
                    'mail' => mb_strtolower(post('mail')), 'club' => club_inscription(post('club')), 'remarque' => post('remarque') ?: null,
                    'notif' => post('notif') ? 1 : 0]);
                flash('Participant enregistré.');
                break;
            case 'file':
                if (post('mettre')) {
                    if (qv("SELECT COUNT(*) FROM maillons WHERE participant_id = ? AND statut = 'en_cours'", [$p['id']])) throw new RuntimeException('Il a déjà une photo en cours.');
                    update('participants', (int) $p['id'], ['en_attente_depuis' => now()]);
                    placer_attente();
                    flash('Mis(e) en file d\'attente.');
                } else {
                    q('UPDATE participants SET en_attente_depuis = NULL WHERE id = ?', [$p['id']]);
                    flash('Retiré(e) de la file d\'attente.');
                }
                break;
            case 'espace':
                envoyer_modele('espace', $p['mail'], vars_mail($p), $p['prenom'] . ' ' . $p['nom']);
                flash('Lien de son espace envoyé.');
                break;
            case 'supprimer':
                if (qv("SELECT COUNT(*) FROM maillons WHERE participant_id = ? AND statut IN ('rendu','en_cours')", [$p['id']]))
                    throw new RuntimeException('Impossible : ce participant a des photos dans des cadavres (ou une photo en cours). Passez-la d\'abord.');
                q('DELETE FROM participants WHERE id = ?', [$p['id']]);
                flash('Participant supprimé.');
                redirect('participants.php');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'ko');
    }
    redirect('participants.php' . ($p ? '?id=' . $p['id'] : ''));
}

$fiche = get('id') !== '' ? participant((int) get('id')) : null;
$cherche = get('q');
$sql = "SELECT p.*, (SELECT COUNT(*) FROM maillons m WHERE m.participant_id = p.id AND m.statut = 'rendu') AS nb_rendus,
          (SELECT COUNT(*) FROM maillons m WHERE m.participant_id = p.id AND m.statut = 'passe') AS nb_passes,
          (SELECT m.chaine_id FROM maillons m WHERE m.participant_id = p.id AND m.statut = 'en_cours' LIMIT 1) AS en_jeu
        FROM participants p";
$par = [];
if ($cherche !== '') { $sql .= ' WHERE p.nom LIKE ? OR p.prenom LIKE ? OR p.mail LIKE ? OR p.club LIKE ?'; $par = array_fill(0, 4, "%$cherche%"); }
$liste = q($sql . ' ORDER BY p.nom, p.prenom', $par)->fetchAll();

entete('Participants', 'participants');
?>
<h1>Participants (<?= count($liste) ?>)</h1>

<?php if ($fiche):
    $ms = q('SELECT m.*, c.numero, c.statut AS cstatut FROM maillons m JOIN chaines c ON c.id = m.chaine_id WHERE m.participant_id = ? ORDER BY m.id DESC', [$fiche['id']])->fetchAll();
    $en_jeu = (bool) array_filter($ms, fn($m) => $m['statut'] === 'en_cours'); ?>
<div class="deux-col">
  <form method="post" class="form panneau"><?= csrf_field() ?><input type="hidden" name="action" value="modifier"><input type="hidden" name="id" value="<?= $fiche['id'] ?>">
    <h2 style="margin:0"><?= h($fiche['prenom'] . ' ' . $fiche['nom']) ?></h2>
    <div class="ligne"><label>Prénom<input name="prenom" value="<?= h($fiche['prenom']) ?>"></label><label>Nom<input name="nom" value="<?= h($fiche['nom']) ?>"></label></div>
    <label>Mail<input name="mail" type="email" value="<?= h($fiche['mail']) ?>"></label>
    <label>Club<input name="club" value="<?= h($fiche['club']) ?>"></label>
    <label>Remarque<input name="remarque" value="<?= h($fiche['remarque']) ?>"></label>
    <label class="case"><input type="checkbox" name="notif" value="1" <?= $fiche['notif'] ? 'checked' : '' ?>> Reçoit les mails « nouvelle photo »</label>
    <div class="actions"><button class="btn">Enregistrer</button>
      <a class="btn sec" href="<?= h(url('moi.php?u=' . $fiche['token'])) ?>" target="_blank">Son espace ↗</a></div>
    <p class="petit">Inscrit le <?= fdate($fiche['date_inscription'], true) ?><?= $fiche['en_attente_depuis'] ? ' · en file d\'attente depuis le ' . fdate($fiche['en_attente_depuis'], true) . ' (rang ' . rang_attente($fiche) . ')' : '' ?></p>
  </form>
  <div class="panneau">
    <h2 style="margin-top:0">Cadavres</h2>
    <?php if (!$ms): ?><p class="petit">Aucun pour l'instant.</p><?php endif ?>
    <table class="liste"><?php foreach ($ms as $m): ?>
      <tr><td><a href="chaine.php?id=<?= $m['chaine_id'] ?>">n°<?= (int) $m['numero'] ?></a></td>
        <td><?php if ($m['photo_reduite'] && $m['statut'] === 'rendu'): ?><img class="mini" src="../img.php?m=<?= $m['id'] ?>" loading="lazy"><?php endif ?></td>
        <td><?= en_retard($m) ? badge('retard') : badge($m['statut']) ?></td><td><?= fdate($m['date_envoi']) ?></td></tr>
    <?php endforeach ?></table>
    <div class="actions" style="margin-top:12px">
      <?php if (!$en_jeu): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="file"><input type="hidden" name="id" value="<?= $fiche['id'] ?>">
        <input type="hidden" name="mettre" value="<?= $fiche['en_attente_depuis'] ? 0 : 1 ?>"><button class="btn sec"><?= $fiche['en_attente_depuis'] ? 'Retirer de la file d\'attente' : 'Mettre en file d\'attente' ?></button></form><?php endif ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="espace"><input type="hidden" name="id" value="<?= $fiche['id'] ?>"><button class="btn sec">✉ Envoyer le lien de son espace</button></form>
      <form method="post" onsubmit="return confirm('Supprimer ce participant ?')"><?= csrf_field() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="id" value="<?= $fiche['id'] ?>"><button class="btn danger">✕ Supprimer</button></form>
    </div>
  </div>
</div>
<?php endif ?>

<form method="get" class="actions filtre"><input name="q" value="<?= h($cherche) ?>" placeholder="Rechercher (nom, mail, club)" style="max-width:320px"><button class="btn sec">Rechercher</button></form>
<table class="liste"><tr><th>Nom</th><th>Mail</th><th>Club</th><th>Photos</th><th>Passés</th><th>État</th><th>Inscrit le</th></tr>
<?php foreach ($liste as $p): ?>
<tr><td><a href="?id=<?= $p['id'] ?>"><strong><?= h($p['nom']) ?></strong> <?= h($p['prenom']) ?></a></td><td><?= h($p['mail']) ?></td><td><?= h($p['club']) ?></td>
  <td><?= (int) $p['nb_rendus'] ?></td><td><?= (int) $p['nb_passes'] ?: '' ?></td>
  <td><?= $p['en_jeu'] ? '<a href="chaine.php?id=' . (int) $p['en_jeu'] . '">' . badge('en_cours', 'en jeu') . '</a>' : ($p['en_attente_depuis'] ? badge('attente') : '') ?><?= $p['notif'] ? '' : ' <span class="petit" title="Ne reçoit pas les mails « nouvelle photo »">🔕</span>' ?></td>
  <td><?= fdate($p['date_inscription']) ?></td></tr>
<?php endforeach ?></table>

<form method="post" class="form panneau" style="margin-top:20px;max-width:640px"><?= csrf_field() ?><input type="hidden" name="action" value="ajouter">
  <h2 style="margin:0">Inscrire quelqu'un</h2>
  <div class="ligne"><label>Prénom<input name="prenom" required></label><label>Nom<input name="nom" required></label></div>
  <div class="ligne"><label>Mail<input name="mail" type="email" required></label><label>Club<input name="club"></label></div>
  <button class="btn">Inscrire (il reçoit les mails comme une inscription normale)</button>
</form>
<?php pied();

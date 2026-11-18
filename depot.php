<?php
require __DIR__ . '/inc/bootstrap.php';

$t = get('t');
$m = $t !== '' ? q1('SELECT * FROM maillons WHERE token = ?', [$t]) : null;
$err = '';

if ($m && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fichier trop gros pour PHP : $_POST est vide
    if (empty($_POST)) {
        $err = 'Fichier trop volumineux pour le serveur.';
    } else {
        check_csrf();
        try {
            if (post('action') === 'reporter') {
                reporter_maillon($m, (int) post('mois'));
                redirect(url('depot.php?t=' . urlencode($t)));
            }
            if (post('action') === 'desister') {
                passer_maillon($m, 'Désistement du participant');
                redirect(url('depot.php?t=' . urlencode($t)));
            }
            if (!post('accord')) throw new RuntimeException('Merci de cocher l\'accord de diffusion.');
            deposer_photo($m, $_FILES['photo'] ?? [], (bool) post('rejouer'));
            redirect(url('depot.php?t=' . urlencode($t) . '&ok=1'));
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
    $m = maillon((int) $m['id']);
}

$p = $m ? participant((int) $m['participant_id']) : null;
$ch = $m ? chaine((int) $m['chaine_id']) : null;
$prec = $m ? photo_precedente($m) : null;
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cadavre Exquis – Dépôt</title><link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
<link rel="icon" type="image/png" href="assets/icons/favicon.png">
<body class="public"><main class="carte large">
<h1>Cadavre Exquis <span>Automatique</span></h1>

<?php if (!$m): ?>
  <p class="alerte ko">Ce lien n'est pas valide. Vérifie qu'il a été copié en entier depuis ton mail.</p>

<?php elseif ($m['statut'] === 'passe' && $p['rappel_le'] && str_starts_with((string) $m['note'], 'Pas le temps')): ?>
  <p class="alerte ok">C'est noté <?= h($p['prenom']) ?> : ta place a été laissée au participant suivant et nous te recontacterons
    le <strong><?= fdate($p['rappel_le']) ?></strong> pour te proposer un nouveau cadavre.</p>
  <p class="petit">Tu peux revenir plus tôt (ou changer d'avis) depuis ton espace.</p>
  <p><a class="btn" href="moi.php?u=<?= h($p['token']) ?>">Mon espace</a></p>

<?php elseif ($m['statut'] === 'passe'): ?>
  <p>Bonjour <?= h($p['prenom']) ?>, ce lien n'est plus actif : le cadavre n°<?= (int) $ch['numero'] ?> a continué avec un autre participant.</p>
  <p><a class="btn" href="moi.php?u=<?= h($p['token']) ?>">Mon espace / rejouer</a></p>

<?php elseif ($m['statut'] === 'rendu'): ?>
  <p class="alerte ok"><?= get('ok') ? 'Merci ' . h($p['prenom']) . ', ta photo est bien arrivée et vient d\'être transmise au participant suivant !' : 'Ta photo a bien été reçue le ' . fdate($m['date_retour'], true) . '. Merci !' ?></p>
  <div class="duo">
    <figure><img src="img.php?t=<?= h($t) ?>&amp;q=prec" alt=""><figcaption>La photo reçue</figcaption></figure>
    <figure><img src="img.php?t=<?= h($t) ?>&amp;q=moi" alt=""><figcaption>Ta photo<?= $m['titre'] ? ' – « ' . h($m['titre']) . ' »' : '' ?></figcaption></figure>
  </div>
  <p style="text-align:center"><a class="btn" href="resultat.php?t=<?= h($t) ?>">Voir tout le cadavre n°<?= (int) $ch['numero'] ?> →</a></p>
  <p class="petit" style="text-align:center">Tu recevras un mail à chaque nouvelle photo. <a href="moi.php?u=<?= h($p['token']) ?>">Mon espace</a></p>

<?php else: ?>
  <p>Bonjour <strong><?= h($p['prenom']) ?></strong>, c'est ton tour : tu réalises la <strong>photo n°<?= rang_photo($m) ?>/<?= total_photos() ?></strong>
     du <strong>cadavre n°<?= (int) $ch['numero'] ?></strong>. Voici la photo qui te précède<?= $prec['type'] === 'depart' ? ' (la photo de départ, tirée au sort)' : '' ?> :</p>
  <figure class="unique"><a href="img.php?t=<?= h($t) ?>&amp;q=prec&amp;dl=1" title="Télécharger"><img src="img.php?t=<?= h($t) ?>&amp;q=prec" alt="Photo précédente"></a>
    <figcaption><a href="img.php?t=<?= h($t) ?>&amp;q=prec&amp;dl=1">Télécharger la photo</a></figcaption></figure>

  <p class="delai">À déposer avant le <strong><?= fdate($m['date_limite']) ?></strong>
    <?php $j = (int) ceil((strtotime($m['date_limite']) - time()) / 86400); ?>
    <?= $j >= 0 ? "(encore $j jour" . ($j > 1 ? 's' : '') . ')' : '(délai dépassé, mais tu peux encore la déposer)' ?></p>

  <?php if ($err): ?><p class="alerte ko"><?= h($err) ?></p><?php endif ?>
  <form method="post" enctype="multipart/form-data" class="form" id="f">
    <?= csrf_field() ?>
    <label>Ta photo (JPEG, pleine résolution, <?= (int) cfg('upload_max_mo') ?> Mo max)
      <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required id="photo"></label>
    <img id="apercu" alt="" hidden>
    <label>Titre (facultatif)<input name="titre" maxlength="200" value="<?= h(post('titre')) ?>"></label>
    <label>Ta démarche en quelques mots (facultatif)
      <textarea name="demarche" class="court" maxlength="600" placeholder="Ce que tu as retenu de la photo précédente, ton idée, ta technique…"><?= h(post('demarche')) ?></textarea>
      <span class="petit">Affichée avec ta photo dans le cadavre. 600 caractères maximum.</span></label>
    <label class="case"><input type="checkbox" name="rejouer" value="1" <?= post('rejouer') ? 'checked' : '' ?>>
      Je veux rejouer : inscris-moi pour un autre cadavre.</label>
    <label class="case"><input type="checkbox" name="accord" value="1" required>
      Je certifie être l'auteur de cette photo et j'accepte qu'elle soit transmise au participant suivant,
      montrée aux participants du cadavre et utilisée comme photo de départ d'un autre cadavre.</label>
    <button class="btn" id="go">Envoyer ma photo</button>
  </form>
  <div class="pause">
    <p><strong>Pas le temps en ce moment ?</strong> Laisse ta place au suivant, on te recontacte plus tard pour un autre cadavre.</p>
    <form method="post" class="actions" onsubmit="return confirm('Laisser ta place dans ce cadavre ? Tu recevras un mail à la date choisie pour rejouer.')">
      <?= csrf_field() ?><input type="hidden" name="action" value="reporter">
      <label class="inline">Recontacte-moi dans
        <select name="mois"><?php foreach (delais_report() as $k => $lib): ?><option value="<?= $k ?>" <?= $k === 2 ? 'selected' : '' ?>><?= $lib ?></option><?php endforeach ?></select></label>
      <button class="btn sec">Je laisse ma place pour l'instant</button>
    </form>
    <form method="post" class="desister" onsubmit="return confirm('Confirmer : tu ne participes pas et le cadavre passe à quelqu\'un d\'autre ?')">
      <?= csrf_field() ?><input type="hidden" name="action" value="desister">
      <button class="lien">Je ne peux pas participer du tout</button>
    </form>
  </div>
  <script>
    const f = document.getElementById('photo'), ap = document.getElementById('apercu'), max = <?= (int) cfg('upload_max_mo') ?> * 1048576;
    f.addEventListener('change', () => {
      const x = f.files[0]; if (!x) return;
      if (x.size > max) { alert('Fichier trop lourd (' + (x.size / 1048576).toFixed(1) + ' Mo).'); f.value = ''; ap.hidden = true; return; }
      ap.src = URL.createObjectURL(x); ap.hidden = false;
    });
    document.getElementById('f').addEventListener('submit', () => { const b = document.getElementById('go'); b.disabled = true; b.textContent = 'Envoi en cours…'; });
  </script>
<?php endif ?>
</main></body></html>

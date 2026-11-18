<?php
require __DIR__ . '/inc/bootstrap.php';

$res = null;
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (post('site_web') !== '') { // pot de miel anti-robots
        $res = ['etat' => 'nouveau', 'participant' => ['prenom' => ''], 'maillon' => null];
    } elseif (!filter_var(post('mail'), FILTER_VALIDATE_EMAIL)) {
        $err = 'Adresse mail invalide.';
    } elseif (post('nom') === '' || post('prenom') === '') {
        $err = 'Nom et prénom obligatoires.';
    } elseif (!post('accord')) {
        $err = 'Merci d\'accepter le règlement.';
    } else {
        $res = inscrire(post('nom'), post('prenom'), post('mail'), post('club'));
    }
}

$st = stats_publiques();
$clubs = clubs_connus();
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cadavre Exquis Photographique Automatique</title><link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
<link rel="icon" type="image/png" href="assets/icons/favicon.png">
<body class="public"><main class="carte">
<h1>Cadavre Exquis <span>Automatique</span></h1>

<div class="stats-pub">
  <div><b><?= $st['en_cours'] ?></b><span>cadavre<?= $st['en_cours'] > 1 ? 's' : '' ?> en cours</span></div>
  <div><b><?= $st['termines'] ?></b><span>terminé<?= $st['termines'] > 1 ? 's' : '' ?></span></div>
  <div><b><?= $st['photos'] ?></b><span>photos</span></div>
  <div><b><?= $st['photographes'] ?></b><span>photographes</span></div>
</div>

<?php if ($res): $p = $res['participant']; ?>
  <?php if ($res['etat'] === 'deja'): ?>
    <p class="alerte ok">Tu es déjà inscrit(e) avec cette adresse<?= q1("SELECT id FROM maillons WHERE participant_id = ? AND statut = 'en_cours'", [$p['id'] ?? 0]) ? ' et une photo t\'attend' : '' ?>.
      Nous venons de te renvoyer par mail le lien de ton espace personnel.</p>
  <?php else: ?>
    <p class="alerte ok"><?= $res['etat'] === 'retour' ? 'Te revoilà' : 'Merci' ?> <?= h($p['prenom']) ?> ! Ton inscription est enregistrée.<br>
      <?php if (!empty($res['maillon'])): ?><strong>C'est déjà ton tour : regarde tes mails, ta photo de départ t'attend !</strong>
      <?php else: ?>Tous les cadavres sont occupés pour l'instant : tu recevras un mail dès que ce sera ton tour.<?php endif ?></p>
    <p class="petit">Pense à vérifier tes courriers indésirables et à ajouter <?= h(cfg('mail.from')) ?> à tes contacts.</p>
  <?php endif ?>

<?php else: ?>
  <div class="regles">
    <p>Le principe : tu reçois la dernière photo d'un cadavre en cours et tu as <?= (int) cfg('delai_jours') ?> jours pour réaliser
      une photo qui s'en inspire (forme, couleur, sujet, idée…). Tu ne vois que cette photo-là.</p>
    <p>Chaque cadavre compte <strong><?= total_photos() ?> photos</strong> et démarre d'une photo tirée au sort parmi celles des autres cadavres.
      Dès que ta photo est déposée, tu reçois le lien de ton cadavre et un mail à chaque nouvelle photo ajoutée, jusqu'à la <?= total_photos() ?>e.</p>
    <p>Tout est automatique : plus il y a d'inscrits, plus il y a de cadavres en cours. Tu pourras rejouer autant que tu veux.</p>
  </div>

  <?php if ($err): ?><p class="alerte ko"><?= h($err) ?></p><?php endif ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <div class="ligne">
      <label>Prénom<input name="prenom" required value="<?= h(post('prenom')) ?>" autocomplete="given-name"></label>
      <label>Nom<input name="nom" required value="<?= h(post('nom')) ?>" autocomplete="family-name"></label>
    </div>
    <label>Adresse mail<input name="mail" type="email" required value="<?= h(post('mail')) ?>" autocomplete="email"></label>
    <label>Club photo (facultatif)
      <input name="club" list="clubs" value="<?= h(post('club')) ?>" placeholder="Laisser vide si pas de club">
      <datalist id="clubs"><?php foreach ($clubs as $c): ?><option value="<?= h($c) ?>"><?php endforeach ?></datalist>
      <span class="petit">Deux photos du même club ne se suivent pas dans un cadavre.</span></label>
    <input name="site_web" class="pot" tabindex="-1" autocomplete="off">
    <label class="case"><input type="checkbox" name="accord" value="1" required>
      J'accepte que mes photos soient transmises au participant suivant, montrées aux participants de mon cadavre
      et utilisées comme photo de départ d'autres cadavres, avec mon nom et mon club. Mes coordonnées ne servent qu'à l'organisation du jeu.</label>
    <button class="btn">Je m'inscris</button>
  </form>
  <p class="petit">Déjà inscrit(e) ? Saisis la même adresse : tu recevras le lien de ton espace personnel.</p>
<?php endif ?>
</main></body></html>

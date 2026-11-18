<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/schema.php';

$checks = [
    'PHP 8.1 ou plus' => version_compare(PHP_VERSION, '8.1', '>='),
    'Extension GD (images)' => extension_loaded('gd'),
    'Dossier data/ inscriptible' => is_writable(DATA_DIR),
];
$msg = '';
$deja = false;
try {
    assurer_schema();
    $deja = (int) qv('SELECT COUNT(*) FROM admins') > 0;
    $checks['Connexion base de données'] = true;
} catch (Throwable $e) {
    $checks['Connexion base de données'] = false;
    $msg = $e->getMessage();
}

if (!$deja && $_SERVER['REQUEST_METHOD'] === 'POST' && $checks['Connexion base de données']) {
    if (strlen(post('pass')) < 8) {
        $msg = 'Mot de passe : 8 caractères minimum.';
    } else {
        insert('admins', ['login' => post('login'), 'nom' => post('nom'), 'mail' => post('mail'),
            'pass_hash' => password_hash(post('pass'), PASSWORD_DEFAULT)]);
        redirect(url('admin/login.php?installe=1'));
    }
}
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Installation – Cadavre Auto</title><link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>"><?= pwa_head() ?>
<body class="public"><main class="carte">
<h1>Installation du Cadavre <span>Automatique</span></h1>
<ul class="checks"><?php foreach ($checks as $k => $ok): ?><li class="<?= $ok ? 'ok' : 'ko' ?>"><?= $ok ? '✔' : '✘' ?> <?= h($k) ?></li><?php endforeach ?></ul>
<?= alerte_base_url() ?>
<?php if ($msg): ?><p class="alerte ko"><?= h($msg) ?></p><?php endif ?>
<?php if ($deja): ?>
  <p class="alerte ok">L'application est installée (tables à jour). <strong>Supprimez install.php du serveur.</strong></p>
  <p>Avant d'ouvrir les inscriptions, ajoutez quelques <strong>photos de départ</strong> dans l'admin
     (ou renseignez <code>photos_depart_externes</code> dans config.php pour piocher dans le cadavre classique).</p>
  <p><a class="btn" href="admin/">Aller à l'administration</a></p>
<?php elseif ($checks['Connexion base de données']): ?>
  <h2>Premier compte administrateur</h2>
  <form method="post" class="form">
    <label>Identifiant<input name="login" required value="patrick"></label>
    <label>Nom affiché<input name="nom" value="Patrick Schwaller"></label>
    <label>Mail<input name="mail" type="email" value="<?= h(cfg('mail.admin')) ?>"></label>
    <label>Mot de passe<input name="pass" type="password" required minlength="8"></label>
    <button class="btn">Créer le compte</button>
  </form>
<?php endif ?>
</main></body></html>

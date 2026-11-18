<?php
require __DIR__ . '/../inc/bootstrap.php';
start_session();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = q1('SELECT * FROM admins WHERE login = ?', [post('login')]);
    if ($a && password_verify(post('pass'), $a['pass_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $a['id'];
        update('admins', (int) $a['id'], ['derniere_connexion' => now()]);
        redirect('index.php');
    }
    usleep(800000);
    $err = 'Identifiant ou mot de passe incorrect.';
}
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connexion – Cadavre Auto</title><link rel="stylesheet" href="../assets/style.css?v=<?= APP_VERSION ?>"><?= pwa_head() ?>
<body class="public"><main class="carte" style="max-width:380px">
<h1>Cadavre <span>Auto</span></h1>
<?= alerte_base_url() ?>
<?php if (get('installe')): ?><p class="alerte ok">Compte créé. Pensez à supprimer install.php.</p><?php endif ?>
<?php if ($err): ?><p class="alerte ko"><?= h($err) ?></p><?php endif ?>
<form method="post" class="form"><?= csrf_field() ?>
  <label>Identifiant<input name="login" required autofocus></label>
  <label>Mot de passe<input name="pass" type="password" required></label>
  <button class="btn">Se connecter</button>
</form>
<p class="petit" style="text-align:center;margin:18px 0 0"><?= h(version_texte()) ?></p></main></body></html>

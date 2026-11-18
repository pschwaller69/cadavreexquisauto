<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
$moi = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        switch (post('action')) {
            case 'ajouter':
                if (strlen(post('pass')) < 8) throw new RuntimeException('Mot de passe : 8 caractères minimum.');
                if (qv('SELECT COUNT(*) FROM admins WHERE login = ?', [post('login')])) throw new RuntimeException('Identifiant déjà utilisé.');
                insert('admins', ['login' => post('login'), 'nom' => post('nom'), 'mail' => post('mail'), 'pass_hash' => password_hash(post('pass'), PASSWORD_DEFAULT)]);
                flash('Administrateur ajouté.');
                break;
            case 'mdp':
                if (strlen(post('pass')) < 8) throw new RuntimeException('Mot de passe : 8 caractères minimum.');
                update('admins', (int) $moi['id'], ['pass_hash' => password_hash(post('pass'), PASSWORD_DEFAULT)]);
                flash('Mot de passe modifié.');
                break;
            case 'supprimer':
                if ((int) post('id') === (int) $moi['id']) throw new RuntimeException('Vous ne pouvez pas supprimer votre propre compte.');
                q('DELETE FROM admins WHERE id = ?', [(int) post('id')]);
                flash('Administrateur supprimé.');
                break;
        }
    } catch (Throwable $e) { flash($e->getMessage(), 'ko'); }
    redirect('admins.php');
}
$admins = q('SELECT * FROM admins ORDER BY login')->fetchAll();
entete('Administrateurs', 'admins');
?>
<h1>Administrateurs</h1>
<table class="liste" style="max-width:800px"><tr><th>Identifiant</th><th>Nom</th><th>Mail</th><th>Dernière connexion</th><th></th></tr>
<?php foreach ($admins as $x): ?><tr><td><?= h($x['login']) ?></td><td><?= h($x['nom']) ?></td><td><?= h($x['mail']) ?></td><td><?= fdate($x['derniere_connexion'], true) ?></td>
<td><?php if ($x['id'] != $moi['id']): ?><form method="post" onsubmit="return confirm('Supprimer ?')"><?= csrf_field() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="btn petit danger">✕</button></form><?php endif ?></td></tr><?php endforeach ?>
</table>
<div class="deux-col" style="margin-top:20px">
  <form method="post" class="form panneau"><?= csrf_field() ?><input type="hidden" name="action" value="ajouter">
    <h2 style="margin:0">Ajouter un administrateur</h2>
    <div class="ligne"><label>Identifiant<input name="login" required></label><label>Nom<input name="nom"></label></div>
    <label>Mail<input name="mail" type="email"></label>
    <label>Mot de passe<input name="pass" type="password" required minlength="8"></label>
    <button class="btn">Ajouter</button>
  </form>
  <form method="post" class="form panneau"><?= csrf_field() ?><input type="hidden" name="action" value="mdp">
    <h2 style="margin:0">Changer mon mot de passe</h2>
    <label>Nouveau mot de passe<input name="pass" type="password" required minlength="8"></label>
    <button class="btn sec">Modifier</button>
  </form>
</div>
<?php pied();

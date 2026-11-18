<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        switch (post('action')) {
            case 'ajouter':
                $f = $_FILES['photos'] ?? null;
                $n = 0; $err = [];
                foreach ((array) ($f['name'] ?? []) as $i => $nom) {
                    $un = ['name' => $nom, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
                    try {
                        $id = insert('photos_depart', ['fichier' => '', 'credit' => post('credit') ?: null, 'actif' => 1, 'date_ajout' => now()]);
                        [, $red] = enregistrer_image($un, 'depart', sprintf('%04d', $id) . ' ' . pathinfo($nom, PATHINFO_FILENAME));
                        update('photos_depart', $id, ['fichier' => $red]);
                        $n++;
                    } catch (Throwable $e) {
                        if (!empty($id)) q('DELETE FROM photos_depart WHERE id = ?', [$id]);
                        $err[] = "$nom : " . $e->getMessage();
                    }
                    $id = null;
                }
                if ($n) { flash("$n photo(s) de départ ajoutée(s)."); placer_attente(); }
                foreach ($err as $e) flash($e, 'ko');
                break;
            case 'credit':
                update('photos_depart', (int) post('id'), ['credit' => post('credit') ?: null]);
                flash('Crédit enregistré.');
                break;
            case 'actif':
                update('photos_depart', (int) post('id'), ['actif' => post('actif') ? 1 : 0]);
                break;
            case 'supprimer':
                $d = q1('SELECT * FROM photos_depart WHERE id = ?', [(int) post('id')]);
                if ($d) {
                    foreach (glob(PHOTO_DIR . '/depart/' . sprintf('%04d', $d['id']) . ' *') ?: [] as $fic) @unlink($fic);
                    q('DELETE FROM photos_depart WHERE id = ?', [$d['id']]);
                    flash('Photo supprimée (les cadavres déjà partis de cette photo en gardent une copie).');
                }
                break;
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'ko');
    }
    redirect('depart.php');
}

$seeds = q('SELECT * FROM photos_depart ORDER BY id DESC')->fetchAll();
$utilisees = array_count_values(q('SELECT depart_cle FROM chaines WHERE depart_cle IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN));
$nb_cadavres = (int) qv("SELECT COUNT(*) FROM maillons WHERE statut = 'rendu'");
$pool = pool_depart();
$neuves = count(array_diff_key($pool, $utilisees));

entete('Photos de départ', 'depart');
?>
<h1>Photos de départ</h1>
<p>Chaque nouveau cadavre démarre d'une photo <strong>tirée au sort</strong> parmi : les photos déjà déposées dans les autres cadavres,
  et les photos ajoutées ici. Les photos jamais utilisées comme départ sont tirées en priorité.</p>
<div class="stats">
  <div class="stat"><b><?= count($pool) ?></b><span>photos possibles</span></div>
  <div class="stat"><b><?= $neuves ?></b><span>jamais tirées</span></div>
  <div class="stat"><b><?= $nb_cadavres ?></b><span>des cadavres</span></div>
  <div class="stat"><b><?= count(array_filter($seeds, fn($s) => $s['actif'])) ?></b><span>ajoutées ici</span></div>
</div>

<form method="post" enctype="multipart/form-data" class="form panneau" style="max-width:640px;margin-bottom:18px"><?= csrf_field() ?><input type="hidden" name="action" value="ajouter">
  <h2 style="margin:0">Ajouter des photos de départ</h2>
  <label>Photos (plusieurs possibles)<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
  <label>Crédit affiché (facultatif)<input name="credit" placeholder="ex. Photo de Patrick Schwaller"></label>
  <button class="btn">Ajouter</button>
</form>

<?php if ($seeds): ?>
<table class="liste"><tr><th>Photo</th><th>Crédit</th><th>Tirée</th><th>Active</th><th></th></tr>
<?php foreach ($seeds as $s): ?>
<tr><td><a href="../img.php?s=<?= $s['id'] ?>" target="_blank"><img class="mini" src="../img.php?s=<?= $s['id'] ?>" loading="lazy"></a></td>
  <td><form method="post" class="actions" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="credit"><input type="hidden" name="id" value="<?= $s['id'] ?>">
    <input name="credit" value="<?= h($s['credit']) ?>" style="max-width:280px"><button class="btn petit sec">OK</button></form></td>
  <td><?= (int) ($utilisees['d:' . $s['id']] ?? 0) ?> fois</td>
  <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="actif"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="actif" value="<?= $s['actif'] ? 0 : 1 ?>">
    <button class="btn petit <?= $s['actif'] ? '' : 'sec' ?>"><?= $s['actif'] ? 'oui' : 'non' ?></button></form></td>
  <td><form method="post" onsubmit="return confirm('Supprimer cette photo de départ ?')"><?= csrf_field() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn petit danger">✕</button></form></td></tr>
<?php endforeach ?></table>
<?php endif ?>
<?php pied();

<?php
/**
 * Affiche un cadavre complet (photo de départ + photos rendues + emplacements restants), avec diaporama.
 * Utilisée par la page résultat des participants (resultat.php) et par l'admin (admin/voir.php).
 * $src(string $type, ?int $id) : URL d'une image ('depart' ou 'm' = photo d'un maillon).
 * $opt : moi (id du maillon du visiteur), retour (URL), entete (HTML), head (HTML).
 */
function afficher_cadavre(array $ch, callable $src, array $opt = []): void
{
    $ms = maillons_chaine((int) $ch['id']);
    $rendus = array_values(array_filter($ms, fn($m) => $m['statut'] === 'rendu'));
    $cours = array_values(array_filter($ms, fn($m) => $m['statut'] === 'en_cours'))[0] ?? null;
    $total = total_photos();
    $nb = count($rendus);
    $fini = $ch['statut'] === 'terminee';
    $diapos = [['img' => $src('depart', null), 'leg' => 'Photo de départ' . ($ch['depart_credit'] ? ' · ' . $ch['depart_credit'] : '')]];
    foreach ($rendus as $i => $m) {
        $diapos[] = ['img' => $src('m', (int) $m['id']), 'leg' => ($i + 1) . '/' . $total . ' · ' . $m['prenom'] . ' ' . $m['nom']
            . ($m['club'] && !est_independant($m['club']) ? ' · ' . $m['club'] : '') . ($m['titre'] ? ' · « ' . $m['titre'] . ' »' : '')];
    }
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?= $opt['head'] ?? '' ?>
<title>Cadavre n°<?= (int) $ch['numero'] ?> – Cadavre Exquis Automatique</title>
<style>
  * { box-sizing: border-box; }
  html, body { margin: 0; background: #0d0d12; color: #eee; font-family: "Segoe UI", system-ui, sans-serif; }
  a { color: #b98cff; }
  header { padding: 22px 4vw 10px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px 22px; }
  header h1 { margin: 0; font-weight: 300; font-size: 30px; letter-spacing: 1px; }
  header h1 b { color: #a774ff; font-weight: 700; }
  .etat { color: #aaa; font-size: 15px; }
  .barre-prog { flex: 1 1 220px; max-width: 360px; height: 8px; background: #26262f; border-radius: 4px; overflow: hidden; }
  .barre-prog i { display: block; height: 100%; background: linear-gradient(90deg, #7c3aed, #b98cff); }
  .outils { margin-left: auto; display: flex; gap: 8px; }
  .outils a, .outils button { background: #8b45ff; color: #fff; border: 0; border-radius: 6px; padding: 9px 14px; font: 14px sans-serif; cursor: pointer; text-decoration: none; }
  .outils a.sec { background: #2a2a35; }
  .intro { padding: 0 4vw; color: #bbb; max-width: 900px; }
  .grille { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 18px; padding: 18px 4vw 50px; }
  .carte { background: #17171f; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; }
  .carte .img { aspect-ratio: 1; background: #000; display: flex; align-items: center; justify-content: center; cursor: zoom-in; }
  .carte .img img { max-width: 100%; max-height: 100%; display: block; }
  .carte .txt { padding: 10px 12px 12px; font-size: 14px; line-height: 1.4; }
  .carte .n { color: #a774ff; font-weight: 700; margin-right: 6px; }
  .carte .club { color: #999; }
  .carte .titre { font-style: italic; color: #ddd; }
  .carte .dem { color: #bbb; font-style: italic; font-size: 13px; border-left: 2px solid #8b45ff; padding-left: 8px; margin-top: 6px; white-space: pre-line; }
  .carte.depart { outline: 2px solid #8b45ff; }
  .carte.moi { outline: 2px solid #3ecf8e; }
  .carte.moi .n::after { content: " · ta photo"; color: #3ecf8e; font-weight: 400; }
  .carte.vide .img { cursor: default; color: #444; font-size: 54px; background: repeating-linear-gradient(45deg, #121218, #121218 10px, #15151c 10px, #15151c 20px); }
  .carte.vide.cours .img { color: #a774ff; }
  .carte.vide .txt { color: #666; }
  #diapo { position: fixed; inset: 0; background: #000; z-index: 50; display: none; flex-direction: column; align-items: center; justify-content: center; }
  #diapo.on { display: flex; }
  #diapo img { max-width: 96vw; max-height: 86vh; border: 2px solid #fff; }
  #diapo p { color: #ddd; font-size: 18px; margin: 14px 10px 0; text-align: center; }
  #diapo .ferme { position: absolute; top: 12px; right: 18px; font-size: 30px; color: #aaa; cursor: pointer; background: none; border: 0; }
  #diapo .nav { position: absolute; top: 0; bottom: 0; width: 30%; cursor: pointer; }
  #diapo .nav.g { left: 0; } #diapo .nav.d { right: 0; }
</style>
<body>
<header>
  <h1>Cadavre <b>n°<?= (int) $ch['numero'] ?></b></h1>
  <span class="etat"><?= $nb ?>/<?= $total ?> photos · <?= $fini ? 'terminé le ' . fdate($ch['date_fin']) : ($cours ? 'photo n°' . ($nb + 1) . ' en cours de création' : 'en attente du prochain photographe') ?></span>
  <div class="barre-prog"><i style="width:<?= round(100 * $nb / $total) ?>%"></i></div>
  <div class="outils">
    <?php if (!empty($opt['retour'])): ?><a class="sec" href="<?= h($opt['retour']) ?>"><?= h($opt['retour_lib'] ?? '← Retour') ?></a><?php endif ?>
    <button onclick="ouvre(0)">▶ Diaporama</button>
  </div>
</header>
<?= $opt['entete'] ?? '' ?>
<div class="grille">
  <div class="carte depart"><div class="img" onclick="ouvre(0)"><img src="<?= h($src('depart', null)) ?>" alt="Photo de départ"></div>
    <div class="txt"><span class="n">Départ</span> tirée au sort<?= $ch['depart_credit'] ? '<br><span class="club">' . h($ch['depart_credit']) . '</span>' : '' ?></div></div>
  <?php foreach ($rendus as $i => $m): ?>
  <div class="carte <?= ($opt['moi'] ?? 0) == $m['id'] ? 'moi' : '' ?>"><div class="img" onclick="ouvre(<?= $i + 1 ?>)"><img src="<?= h($src('m', (int) $m['id'])) ?>" alt="" loading="lazy"></div>
    <div class="txt"><span class="n"><?= $i + 1 ?></span><?= h($m['prenom'] . ' ' . $m['nom']) ?>
      <?php if ($m['club'] && !est_independant($m['club'])): ?><br><span class="club"><?= h($m['club']) ?></span><?php endif ?>
      <?php if ($m['titre']): ?><br><span class="titre">« <?= h($m['titre']) ?> »</span><?php endif ?>
      <?php if ($m['demarche']): ?><div class="dem"><?= h($m['demarche']) ?></div><?php endif ?>
    </div></div>
  <?php endforeach ?>
  <?php for ($k = $nb + 1; !$fini && $k <= $total; $k++): ?>
  <div class="carte vide <?= $k === $nb + 1 && $cours ? 'cours' : '' ?>"><div class="img">?</div>
    <div class="txt"><span class="n"><?= $k ?></span><?= $k === $nb + 1 && $cours ? 'en cours de création…' : 'à venir' ?></div></div>
  <?php endfor ?>
</div>
<div id="diapo"><button class="ferme" onclick="ferme()">✕</button><div class="nav g" onclick="va(-1)"></div><div class="nav d" onclick="va(1)"></div><img alt=""><p></p></div>
<script>
  const D = <?= json_encode($diapos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>, el = document.getElementById('diapo');
  let k = 0;
  function montre() { el.querySelector('img').src = D[k].img; el.querySelector('p').textContent = D[k].leg + '   (' + (k + 1) + '/' + D.length + ')'; }
  function ouvre(i) { k = i; montre(); el.classList.add('on'); el.requestFullscreen?.().catch(() => {}); }
  function ferme() { el.classList.remove('on'); if (document.fullscreenElement) document.exitFullscreen(); }
  function va(d) { k = Math.max(0, Math.min(D.length - 1, k + d)); montre(); }
  document.addEventListener('keydown', e => {
    if (!el.classList.contains('on')) return;
    if (['ArrowRight', 'PageDown', ' ', 'Enter'].includes(e.key)) { e.preventDefault(); va(1); }
    if (['ArrowLeft', 'PageUp', 'Backspace'].includes(e.key)) { e.preventDefault(); va(-1); }
    if (e.key === 'Escape') ferme();
  });
  document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) el.classList.remove('on'); });
</script>
</body></html>
<?php
}

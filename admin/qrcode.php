<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
require_admin();

$lien = url();
$st = stats_publiques();
entete('QR code', 'qrcode');
?>
<style>
  .qr-zone { display: grid; grid-template-columns: minmax(260px, 420px) 1fr; gap: 28px; align-items: start; }
  .qr-boite { background: #fff; border-radius: 12px; padding: 18px; }
  .qr-boite svg { display: block; width: 100%; height: auto; }
  #evenement { position: fixed; inset: 0; z-index: 100; background: radial-gradient(ellipse at top, #0b2a3a 0%, #0d0d12 65%); color: #fff;
    display: none; flex-direction: column; align-items: center; justify-content: center; gap: 2.5vh; text-align: center; padding: 3vh; cursor: pointer; }
  #evenement.on { display: flex; }
  #evenement h1 { margin: 0; font-size: 6vh; font-weight: 300; letter-spacing: 1px; }
  #evenement h1 b { color: #4cc6f0; font-weight: 700; }
  #evenement .qr-boite { padding: 2.5vh; width: min(62vh, 82vw); }
  #evenement .appel { font-size: 4vh; margin: 0; }
  #evenement .url { font-size: 2.6vh; color: #bbb; margin: 0; }
  #evenement .chiffres { font-size: 2.2vh; color: #999; margin: 0; }
  #evenement .ferme { position: absolute; top: 14px; right: 20px; font-size: 14px; color: #777; }
  @media (max-width: 760px) { .qr-zone { grid-template-columns: 1fr; } }
  @media print {
    @page { size: A4 portrait; margin: 12mm; }
    body.admin header, .pied-app, .no-print, .alerte { display: none !important; }
    body { background: #fff !important; color: #000 !important; }
    .admin main { padding: 0 !important; }
    #affiche { display: flex !important; }
  }
  #affiche { display: none; flex-direction: column; align-items: center; text-align: center; gap: 8mm; color: #000; padding-top: 10mm; }
  #affiche h1 { font-size: 30pt; margin: 0; color: #000; } #affiche h1 b { color: #0b7fb0; }
  #affiche .qr-boite { width: 130mm; padding: 0; }
  #affiche p { margin: 0; font-size: 16pt; } #affiche .url { font-size: 13pt; color: #444; }
</style>

<div class="no-print">
<h1>QR code d'inscription</h1>
<p>À montrer lors d'un événement photo ou à imprimer : il ouvre directement la page d'inscription.</p>
<div class="qr-zone">
  <div class="qr-boite" id="qr-ecran"></div>
  <div>
    <p><strong>Lien :</strong> <a href="<?= h($lien) ?>" target="_blank"><?= h($lien) ?></a></p>
    <div class="actions">
      <button class="btn" onclick="evenement()">⛶ Mode événement (plein écran)</button>
      <button class="btn sec" onclick="print()">🖶 Imprimer une affiche A4</button>
      <button class="btn sec" onclick="telechargerPng()">⇩ PNG (impression, réseaux)</button>
      <button class="btn sec" onclick="telechargerSvg()">⇩ SVG (vectoriel)</button>
      <button class="btn sec" onclick="navigator.clipboard.writeText(LIEN).then(() => this.textContent = '✓ Lien copié')">Copier le lien</button>
    </div>
    <p class="petit">Mode événement : le QR code s'affiche en grand sur fond sombre (idéal sur une tablette ou un vidéoprojecteur).
      Cliquez ou appuyez sur Échap pour revenir. Le PNG fait 2000 px avec une marge blanche, prêt pour un flyer ou un kakémono.</p>
  </div>
</div>
</div>

<div id="affiche">
  <h1>Cadavre Exquis <b>Automatique</b></h1>
  <p>Scanne et rejoins un cadavre exquis photographique !</p>
  <div class="qr-boite" id="qr-affiche"></div>
  <p class="url"><?= h(preg_replace('#^https?://#', '', $lien)) ?></p>
</div>

<div id="evenement" onclick="fermer()">
  <span class="ferme">Échap ou clic pour quitter</span>
  <h1>Cadavre Exquis <b>Automatique</b></h1>
  <p class="appel">Scanne et rejoins un cadavre !</p>
  <div class="qr-boite" id="qr-event"></div>
  <p class="url"><?= h(preg_replace('#^https?://#', '', $lien)) ?></p>
  <p class="chiffres"><?= $st['en_cours'] ?> cadavre<?= $st['en_cours'] > 1 ? 's' : '' ?> en cours · <?= $st['photos'] ?> photos · <?= $st['photographes'] ?> photographes</p>
</div>

<script src="../assets/qrcode.js?v=<?= APP_VERSION ?>"></script>
<script>
  const LIEN = <?= json_encode($lien) ?>;
  const qr = qrcode(0, 'M'); qr.addData(LIEN); qr.make();
  const n = qr.getModuleCount();
  function svg(marge = 0) {
    const t = n + 2 * marge; let d = '';
    for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) if (qr.isDark(r, c)) d += `M${c + marge} ${r + marge}h1v1h-1z`;
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${t} ${t}" shape-rendering="crispEdges"><rect width="${t}" height="${t}" fill="#fff"/><path d="${d}" fill="#000"/></svg>`;
  }
  for (const id of ['qr-ecran', 'qr-affiche', 'qr-event']) document.getElementById(id).innerHTML = svg();
  function telecharger(blob, nom) { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = nom; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 1000); }
  function telechargerSvg() { telecharger(new Blob([svg(4)], {type: 'image/svg+xml'}), 'qrcode-cadavre-exquis-auto.svg'); }
  function telechargerPng() {
    const taille = 2000, img = new Image();
    img.onload = () => { const cv = document.createElement('canvas'); cv.width = cv.height = taille; const x = cv.getContext('2d');
      x.imageSmoothingEnabled = false; x.drawImage(img, 0, 0, taille, taille); cv.toBlob(b => telecharger(b, 'qrcode-cadavre-exquis-auto.png')); };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg(4));
  }
  const ev = document.getElementById('evenement');
  function evenement() { ev.classList.add('on'); document.documentElement.requestFullscreen?.().catch(() => {}); navigator.wakeLock?.request('screen').catch(() => {}); }
  function fermer() { ev.classList.remove('on'); if (document.fullscreenElement) document.exitFullscreen(); }
  document.addEventListener('keydown', e => { if (e.key === 'Escape') fermer(); });
  document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) ev.classList.remove('on'); });
  if (location.hash === '#evenement') evenement();
</script>
<?php pied();

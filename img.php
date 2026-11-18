<?php
require __DIR__ . '/inc/bootstrap.php';

$rel = null;
$abs = null;
$nom = 'photo.jpg';

if (($t = get('t')) !== '') {
    // Accès participant par jeton de maillon
    $m = q1('SELECT * FROM maillons WHERE token = ?', [$t]);
    if ($m && $m['statut'] !== 'passe') {
        if (get('q') === 'moi') {
            $rel = $m['photo_reduite'];
        } elseif (get('q') === 'prec') {
            $rel = photo_precedente($m)['fichier'];
            $nom = 'cadavre-exquis-photo-precedente.jpg';
        } elseif ($m['statut'] === 'rendu') {
            // Photo rendue : il voit tout son cadavre (départ + photos rendues)
            if (get('q') === 'depart') {
                $rel = chaine((int) $m['chaine_id'])['depart_fichier'] ?? null;
            } elseif (get('m') !== '') {
                $x = q1("SELECT * FROM maillons WHERE id = ? AND chaine_id = ? AND statut = 'rendu'", [(int) get('m'), $m['chaine_id']]);
                $rel = $x['photo_reduite'] ?? null;
            }
        }
    }
} elseif (admin()) {
    if (get('d') !== '') {
        $rel = chaine((int) get('d'))['depart_fichier'] ?? null;
    } elseif (get('m') !== '') {
        $m = maillon((int) get('m'));
        $rel = get('v') === 'o' ? ($m['photo'] ?? null) : ($m['photo_reduite'] ?? null);
    } elseif (get('s') !== '') {
        $rel = qv('SELECT fichier FROM photos_depart WHERE id = ?', [(int) get('s')]) ?: null;
    } elseif (get('x') !== '') {
        // Aperçu d'une photo externe (cadavre classique) dans l'admin
        foreach (photos_externes() as $f) if (md5($f) === get('x')) { $abs = $f; break; }
    }
}

$path = $abs ?: chemin_photo($rel);
if (!$path) { http_response_code(404); exit; }

$mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
if (get('dl')) {
    $nom = get('t') ? $nom : basename($path);
    header('Content-Disposition: attachment; filename="' . addslashes($nom) . '"');
}
readfile($path);

<?php
/*
 * Logique du cadavre exquis automatique.
 *
 * Pas d'édition : le jeu tourne en continu.
 * Cadavre (table chaines) : numéro 1, 2, 3… ; photo de départ tirée au sort parmi les photos des autres cadavres ;
 *   statut 'active' (un participant a la main) | 'attente' (attend un participant) | 'terminee' (15 photos).
 * Maillon : un participant placé dans un cadavre ; statut 'en_cours' | 'rendu' | 'passe'.
 * Participant : une adresse mail. « En attente » quand en_attente_depuis est renseigné.
 *
 * Le nombre de cadavres en cours s'adapte aux inscrits :
 *  - un inscrit en attente est placé dans un cadavre libre (le plus avancé d'abord, pour les terminer) ;
 *  - un nouveau cadavre s'ouvre quand il y a plus de N inscrits en attente par cadavre en cours,
 *    ou quand quelqu'un attend depuis trop longtemps ; les cadavres terminés libèrent la place.
 */

function chaine(int $id): ?array { return q1('SELECT * FROM chaines WHERE id = ?', [$id]); }
function maillon(int $id): ?array { return q1('SELECT * FROM maillons WHERE id = ?', [$id]); }
function participant(int $id): ?array { return q1('SELECT * FROM participants WHERE id = ?', [$id]); }
function total_photos(): int { return max(2, (int) cfg('photos_par_cadavre', 15)); }

function maillons_chaine(int $chaine_id): array
{
    return q('SELECT m.*, p.nom, p.prenom, p.mail, p.club FROM maillons m JOIN participants p ON p.id = m.participant_id
              WHERE m.chaine_id = ? ORDER BY m.position', [$chaine_id])->fetchAll();
}
function nb_rendus(int $chaine_id): int
{
    return (int) qv("SELECT COUNT(*) FROM maillons WHERE chaine_id = ? AND statut = 'rendu'", [$chaine_id]);
}
/** Numéro de la photo dans le cadavre (les participants passés ne comptent pas). */
function rang_photo(array $m): int
{
    if ($m['statut'] === 'rendu') return (int) qv("SELECT COUNT(*) FROM maillons WHERE chaine_id = ? AND statut = 'rendu' AND position <= ?", [$m['chaine_id'], $m['position']]);
    return (int) qv("SELECT COUNT(*) FROM maillons WHERE chaine_id = ? AND statut = 'rendu' AND position < ?", [$m['chaine_id'], $m['position']]) + 1;
}
function maillon_en_cours(int $chaine_id): ?array
{
    return q1("SELECT * FROM maillons WHERE chaine_id = ? AND statut = 'en_cours' LIMIT 1", [$chaine_id]);
}

// ---------------------------------------------------------------- photos
/** Photo que voit le participant de ce maillon : dernière photo rendue avant lui, sinon photo de départ du cadavre. */
function photo_precedente(array $maillon): array
{
    $prev = q1("SELECT m.*, p.nom, p.prenom, p.club FROM maillons m JOIN participants p ON p.id = m.participant_id
                WHERE m.chaine_id = ? AND m.position < ? AND m.statut = 'rendu' ORDER BY m.position DESC LIMIT 1",
               [$maillon['chaine_id'], $maillon['position']]);
    if ($prev) return ['type' => 'maillon', 'maillon' => $prev, 'fichier' => $prev['photo_reduite']];
    $ch = chaine((int) $maillon['chaine_id']);
    return ['type' => 'depart', 'maillon' => null, 'fichier' => $ch['depart_fichier']];
}

function chemin_photo(?string $rel): ?string
{
    if (!$rel) return null;
    $p = PHOTO_DIR . '/' . $rel;
    return is_file($p) ? $p : null;
}

function supprimer_dossier(string $dir): void
{
    if (!is_dir($dir) || !str_starts_with(realpath($dir), realpath(PHOTO_DIR))) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

function nom_fichier(string $s): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    return trim(preg_replace('/[^A-Za-z0-9 _\-]+/', '', $s));
}

/** Image GD depuis un fichier, orientation EXIF corrigée. */
function charger_image(string $chemin): GdImage
{
    @ini_set('memory_limit', '1024M');
    $src = @imagecreatefromstring((string) file_get_contents($chemin));
    if (!$src) throw new RuntimeException('Image illisible.');
    if (function_exists('exif_read_data') && (@getimagesize($chemin)[2] ?? 0) === IMAGETYPE_JPEG) {
        $exif = @exif_read_data($chemin);
        $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rot) $src = imagerotate($src, $rot, 0);
    }
    return $src;
}

/** Écrit une version réduite JPEG d'une image GD. */
function ecrire_reduite(GdImage $src, string $dest_abs): void
{
    $w = imagesx($src); $hgt = imagesy($src);
    $r = min(1, (int) cfg('reduite_px', 2000) / max($w, $hgt));
    $nw = (int) round($w * $r); $nh = (int) round($hgt * $r);
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $hgt);
    imagejpeg($dst, $dest_abs, 88);
    imagedestroy($dst);
}

/**
 * Vérifie et enregistre une image envoyée ($_FILES[...]) : original + version réduite JPEG.
 * Retourne [chemin_original_relatif, chemin_reduite_relatif].
 */
function enregistrer_image(array $file, string $dossier_rel, string $base_nom): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = [
            UPLOAD_ERR_INI_SIZE => 'fichier trop lourd pour le serveur',
            UPLOAD_ERR_FORM_SIZE => 'fichier trop lourd',
            UPLOAD_ERR_PARTIAL => 'envoi interrompu, réessayez',
            UPLOAD_ERR_NO_FILE => 'aucun fichier choisi',
        ][$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'erreur d\'envoi';
        throw new RuntimeException('Photo non reçue : ' . $msg . '.');
    }
    if ($file['size'] > cfg('upload_max_mo', 30) * 1024 * 1024) {
        throw new RuntimeException('La photo dépasse ' . cfg('upload_max_mo', 30) . ' Mo.');
    }
    $info = @getimagesize($file['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext) throw new RuntimeException('Format non accepté : envoyez un JPEG (ou PNG/WebP).');
    $src = charger_image($file['tmp_name']);

    $dir = PHOTO_DIR . '/' . $dossier_rel;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $base = nom_fichier($base_nom) ?: 'photo';
    $orig_rel = $dossier_rel . '/' . $base . '.' . $ext;
    $red_rel = $dossier_rel . '/' . $base . ' reduite.jpg';
    if (!move_uploaded_file($file['tmp_name'], PHOTO_DIR . '/' . $orig_rel)) {
        if (!copy($file['tmp_name'], PHOTO_DIR . '/' . $orig_rel)) throw new RuntimeException('Impossible d\'enregistrer la photo.');
    }
    ecrire_reduite($src, PHOTO_DIR . '/' . $red_rel);
    imagedestroy($src);
    return [$orig_rel, $red_rel];
}

function dossier_chaine(array $ch): string { return 'cadavres/' . sprintf('%04d', (int) $ch['numero']); }

// ---------------------------------------------------------------- photos de départ (tirage au sort)
/**
 * Toutes les photos pouvant servir de départ : photos rendues des cadavres de cette appli,
 * photos ajoutées par l'admin, et photos du cadavre classique (config photos_depart_externes).
 * Chaque entrée : cle (unique), abs (chemin du fichier), credit.
 */
function pool_depart(bool $avec_externes = true): array
{
    $pool = [];
    foreach (q("SELECT m.id, m.photo_reduite, m.titre, c.numero, p.prenom, p.nom FROM maillons m
                JOIN chaines c ON c.id = m.chaine_id JOIN participants p ON p.id = m.participant_id
                WHERE m.statut = 'rendu' AND m.photo_reduite IS NOT NULL")->fetchAll() as $m) {
        if ($abs = chemin_photo($m['photo_reduite'])) {
            $pool['m:' . $m['id']] = ['cle' => 'm:' . $m['id'], 'abs' => $abs, 'chaine' => (int) $m['numero'],
                'credit' => "Photo de {$m['prenom']} {$m['nom']}, cadavre n°{$m['numero']}"];
        }
    }
    foreach (q('SELECT * FROM photos_depart WHERE actif = 1')->fetchAll() as $d) {
        if ($abs = chemin_photo($d['fichier'])) {
            $pool['d:' . $d['id']] = ['cle' => 'd:' . $d['id'], 'abs' => $abs, 'chaine' => 0, 'credit' => $d['credit'] ?: 'Photo de départ'];
        }
    }
    if ($avec_externes) foreach (photos_externes() as $abs) {
        $pool['x:' . md5($abs)] = ['cle' => 'x:' . md5($abs), 'abs' => $abs, 'chaine' => 0, 'credit' => credit_externe($abs)];
    }
    return $pool;
}

/** Versions réduites du cadavre exquis classique (fichiers « … reduite.jpg »). Liste mise en cache 1 h. */
function photos_externes(): array
{
    $dir = trim((string) cfg('photos_depart_externes', ''));
    if ($dir === '' || !is_dir($dir)) return [];
    $cache = DATA_DIR . '/externes.json';
    if (is_file($cache) && filemtime($cache) > time() - 3600) {
        $l = json_decode((string) file_get_contents($cache), true);
        if (is_array($l)) return array_values(array_filter($l, 'is_file'));
    }
    $l = [];
    try {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && preg_match('/ reduite\.jpe?g$/i', $f->getFilename())) $l[] = $f->getPathname();
        }
    } catch (Throwable $e) { return []; }
    @file_put_contents($cache, json_encode($l));
    return $l;
}

/** « 03 Marie DUPONT reduite.jpg » -> « Photo de Marie DUPONT (cadavre exquis classique) » */
function credit_externe(string $abs): string
{
    $n = preg_replace('/ reduite\.jpe?g$/i', '', basename($abs));
    $n = trim(preg_replace('/^\d+\s+/', '', $n));
    return $n !== '' && stripos($n, 'base') === false ? "Photo de $n (cadavre exquis classique)" : 'Photo du cadavre exquis classique';
}

/** Tire au sort une photo de départ : de préférence jamais utilisée comme départ. */
function tirer_photo_depart(?int $exclure_chaine = null): ?array
{
    $pool = array_filter(pool_depart(), fn($p) => !$exclure_chaine || $p['chaine'] !== $exclure_chaine);
    if (!$pool) return null;
    $utilisees = array_flip(q('SELECT depart_cle FROM chaines WHERE depart_cle IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN));
    $neuves = array_diff_key($pool, $utilisees);
    $choix = $neuves ?: $pool;
    return $choix[array_rand($choix)];
}

/** Copie la photo tirée au sort dans le dossier du cadavre et l'enregistre comme départ. */
function poser_depart(array $ch, array $photo): void
{
    $dir = PHOTO_DIR . '/' . dossier_chaine($ch);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $rel = dossier_chaine($ch) . '/00 depart ' . date('His') . '.jpg';
    $src = charger_image($photo['abs']);
    ecrire_reduite($src, PHOTO_DIR . '/' . $rel);
    imagedestroy($src);
    if ($ch['depart_fichier'] && ($ancien = chemin_photo($ch['depart_fichier']))) @unlink($ancien);
    update('chaines', (int) $ch['id'], ['depart_fichier' => $rel, 'depart_cle' => $photo['cle'], 'depart_credit' => mb_substr($photo['credit'], 0, 255)]);
}

/** Ouvre un nouveau cadavre avec une photo de départ tirée au sort. */
function creer_chaine(): ?array
{
    $photo = tirer_photo_depart();
    if (!$photo) {
        // Une alerte par jour au maximum
        $f = DATA_DIR . '/alerte_depart.txt';
        if (!is_file($f) || filemtime($f) < time() - 86400) {
            @touch($f);
            mail_admin('Aucune photo de départ', "Des inscrits attendent mais aucun cadavre ne peut être ouvert : il n'y a aucune photo de départ.\n"
                . "Ajoutez des photos dans l'admin (Photos de départ) ou renseignez photos_depart_externes dans config.php.\n\n" . url('admin/depart.php'));
        }
        return null;
    }
    $numero = (int) qv('SELECT COALESCE(MAX(numero), 0) FROM chaines') + 1;
    $id = insert('chaines', ['numero' => $numero, 'statut' => 'attente', 'date_creation' => now(), 'attente_depuis' => now()]);
    poser_depart(chaine($id), $photo);
    return chaine($id);
}

// ---------------------------------------------------------------- variables de mail
function vars_mail(array $p, ?array $m = null): array
{
    $v = [
        'prenom' => $p['prenom'], 'nom' => $p['nom'], 'club' => $p['club'], 'mail' => $p['mail'],
        'delai' => cfg('delai_jours', 14), 'total' => total_photos(), 'site' => url(),
        'espace' => url('moi.php?u=' . $p['token']),
    ];
    if ($m) {
        $ch = chaine((int) $m['chaine_id']);
        $v += [
            'numero' => $ch['numero'], 'lien' => url('depot.php?t=' . $m['token']),
            'resultat' => url('resultat.php?t=' . $m['token']),
            'date_limite' => fdate($m['date_limite']), 'position' => rang_photo($m),
            'nb_photos' => nb_rendus((int) $ch['id']),
        ];
    }
    return $v;
}

// ---------------------------------------------------------------- clubs
function est_independant(string $club): bool
{
    return in_array(mb_strtolower(trim($club)), ['', 'indépendant', 'independant', 'individuel', 'aucun'], true);
}

/** Club tel qu'enregistré : même écriture qu'un club déjà connu (sans tenir compte des majuscules). */
function club_inscription(string $club): string
{
    $club = club_propre($club);
    if (est_independant($club)) return 'Indépendant';
    foreach (clubs_connus() as $c) if (mb_strtolower($c) === mb_strtolower($club)) return $c;
    return $club;
}
function clubs_connus(): array
{
    return q("SELECT DISTINCT club FROM participants WHERE club IS NOT NULL AND club <> '' AND club <> 'Indépendant' ORDER BY club")->fetchAll(PDO::FETCH_COLUMN);
}

/** Clubs des 2 dernières photos rendues d'un cadavre (du plus récent au plus ancien). « Indépendant » est ignoré. */
function clubs_voisins(int $chaine_id, int $n = 2): array
{
    $clubs = q("SELECT p.club FROM maillons m JOIN participants p ON p.id = m.participant_id
                WHERE m.chaine_id = ? AND m.statut = 'rendu' ORDER BY m.position DESC LIMIT " . (int) $n, [$chaine_id])->fetchAll(PDO::FETCH_COLUMN);
    return array_map(fn($c) => est_independant((string) $c) ? '' : mb_strtolower((string) $c), $clubs);
}

/** 0 = aucun conflit, 1 = même club que l'avant-dernière photo, 2 = même club que la dernière. */
function conflit_club(?string $club, array $voisins): int
{
    $c = est_independant((string) $club) ? '' : mb_strtolower((string) $club);
    if ($c === '') return 0;
    if (($voisins[0] ?? null) === $c) return 2;
    if (($voisins[1] ?? null) === $c) return 1;
    return 0;
}

// ---------------------------------------------------------------- moteur
function file_attente(): array
{
    return q('SELECT * FROM participants WHERE en_attente_depuis IS NOT NULL ORDER BY en_attente_depuis, id')->fetchAll();
}

function cadavres_ouverts(): int { return (int) qv("SELECT COUNT(*) FROM chaines WHERE statut <> 'terminee'"); }

/** Place un participant dans un cadavre et lui envoie son lien de dépôt. */
function lancer_maillon(array $ch, array $p): array
{
    $pos = (int) qv('SELECT COALESCE(MAX(position),0) FROM maillons WHERE chaine_id = ?', [$ch['id']]) + 1;
    $n = now();
    $id = insert('maillons', [
        'chaine_id' => $ch['id'], 'participant_id' => $p['id'], 'position' => $pos,
        'token' => token(), 'statut' => 'en_cours', 'date_envoi' => $n,
        'date_limite' => plus_jours($n, (int) cfg('delai_jours', 14)),
        'relance_envoyee' => 0, 'alerte_envoyee' => 0,
    ]);
    q('UPDATE participants SET en_attente_depuis = NULL WHERE id = ?', [$p['id']]);
    update('chaines', (int) $ch['id'], ['statut' => 'active', 'attente_depuis' => null]);
    $m = maillon($id);
    envoyer_modele('a_toi', $p['mail'], vars_mail($p, $m), $p['prenom'] . ' ' . $p['nom']);
    return $m;
}

/**
 * Le cœur : place les inscrits en attente dans les cadavres libres et ouvre de nouveaux cadavres si besoin.
 * Appelé à chaque inscription, dépôt, désistement et à chaque passage du cron. Retourne le nombre de placements.
 */
function placer_attente(): int
{
    $places = 0;
    $verrou = fopen(DATA_DIR . '/placement.lock', 'c');
    if ($verrou) flock($verrou, LOCK_EX);
    try {
        for ($tour = 0; $tour < 500; $tour++) {
            $file = file_attente();
            if (!$file) break;
            // Cadavres libres : le plus avancé d'abord (pour les terminer), puis celui qui attend depuis le plus longtemps
            $libres = q("SELECT c.*, (SELECT COUNT(*) FROM maillons m WHERE m.chaine_id = c.id AND m.statut = 'rendu') AS rendus
                         FROM chaines c WHERE c.statut = 'attente' ORDER BY rendus DESC, c.attente_depuis, c.numero")->fetchAll();
            $place = false;
            if ($libres) {
                $deja = [];
                foreach (q("SELECT chaine_id, participant_id FROM maillons")->fetchAll() as $r) $deja[$r['chaine_id'] . '-' . $r['participant_id']] = 1;
                $voisins = [];
                foreach ($libres as $ch) $voisins[$ch['id']] = clubs_voisins((int) $ch['id']);
                $limite_tol = plus_jours(now(), -(int) cfg('club_tolerance_jours', 3));
                // Tolérance 0 : pas le club des 2 dernières photos ; 1 : pas celui de la dernière ; 2 : après N jours d'attente
                foreach ([0, 1, 2] as $tol) {
                    foreach ($file as $p) {
                        if ($tol === 2 && $p['en_attente_depuis'] > $limite_tol) continue;
                        foreach ($libres as $ch) {
                            if (isset($deja[$ch['id'] . '-' . $p['id']])) continue; // jamais deux fois dans le même cadavre
                            if (conflit_club($p['club'], $voisins[$ch['id']]) > $tol) continue;
                            lancer_maillon($ch, $p);
                            $places++;
                            $place = true;
                            break 3;
                        }
                    }
                }
            }
            if ($place) continue;

            // Personne ne peut être placé : faut-il ouvrir un nouveau cadavre ?
            $ouverts = cadavres_ouverts();
            $trop = count($file) > $ouverts * max(0, (int) cfg('attente_par_cadavre', 1));
            $vieux = $file[0]['en_attente_depuis'] <= plus_jours(now(), -(int) cfg('attente_max_jours', 7));
            if ($ouverts < (int) cfg('cadavres_max', 40) && ($ouverts === 0 || $trop || $vieux)) {
                if (creer_chaine()) continue;
            }
            break;
        }
    } finally {
        if ($verrou) { flock($verrou, LOCK_UN); fclose($verrou); }
    }
    return $places;
}

/** Inscription (formulaire public ou admin). Un ancien participant libre est remis en file d'attente. */
function inscrire(string $nom, string $prenom, string $mail, string $club): array
{
    $mail = mb_strtolower(trim($mail));
    $p = q1('SELECT * FROM participants WHERE mail = ?', [$mail]);
    if ($p) {
        $en_jeu = (bool) qv("SELECT COUNT(*) FROM maillons WHERE participant_id = ? AND statut = 'en_cours'", [$p['id']]);
        if ($en_jeu || $p['en_attente_depuis']) {
            envoyer_modele('espace', $p['mail'], vars_mail($p), $p['prenom'] . ' ' . $p['nom']);
            return ['etat' => 'deja', 'participant' => $p];
        }
        update('participants', (int) $p['id'], ['nom' => nom_propre($nom), 'prenom' => prenom_propre($prenom),
            'club' => club_inscription($club), 'en_attente_depuis' => now()]);
        $etat = 'retour';
    } else {
        $id = insert('participants', [
            'nom' => nom_propre($nom), 'prenom' => prenom_propre($prenom), 'mail' => $mail,
            'club' => club_inscription($club), 'token' => token(), 'notif' => 1,
            'date_inscription' => now(), 'en_attente_depuis' => now(),
        ]);
        $p = participant($id);
        $etat = 'nouveau';
    }
    placer_attente();
    $p = participant((int) $p['id']);
    $m = q1("SELECT * FROM maillons WHERE participant_id = ? AND statut = 'en_cours'", [$p['id']]);
    if (!$m) envoyer_bienvenue($p);
    return ['etat' => $etat, 'participant' => $p, 'maillon' => $m];
}

function envoyer_bienvenue(array $p): void
{
    $r = rang_attente($p);
    envoyer_modele('bienvenue', $p['mail'], vars_mail($p) + ['rang' => $r, 'phrase_rang' => phrase_rang($r)], $p['prenom'] . ' ' . $p['nom']);
}

/** Rang dans la file d'attente (1 = prochain), 0 si pas en attente. */
function rang_attente(array $p): int
{
    if (!$p['en_attente_depuis']) return 0;
    return 1 + (int) qv('SELECT COUNT(*) FROM participants WHERE en_attente_depuis IS NOT NULL AND (en_attente_depuis < ? OR (en_attente_depuis = ? AND id < ?))',
        [$p['en_attente_depuis'], $p['en_attente_depuis'], $p['id']]);
}
function phrase_rang(int $rang): string
{
    if ($rang <= 1) return 'tu es le prochain sur la liste.';
    if ($rang === 2) return 'il n\'y a qu\'une personne avant toi.';
    return 'il y a ' . ($rang - 1) . ' personnes avant toi.';
}

/** Le participant dépose sa photo. */
function deposer_photo(array $m, array $file, bool $rejouer = false): void
{
    if ($m['statut'] !== 'en_cours') throw new RuntimeException('Ce lien n\'attend plus de photo.');
    $p = participant((int) $m['participant_id']);
    $ch = chaine((int) $m['chaine_id']);
    [$orig, $red] = enregistrer_image($file, dossier_chaine($ch), sprintf('%02d %s %s', $m['position'], $p['prenom'], $p['nom']));
    update('maillons', (int) $m['id'], [
        'photo' => $orig, 'photo_reduite' => $red, 'statut' => 'rendu', 'date_retour' => now(),
        'titre' => mb_substr((string) post('titre'), 0, 200) ?: null,
        'demarche' => mb_substr(trim(str_replace("\r\n", "\n", (string) post('demarche'))), 0, 600) ?: null,
    ]);
    $m = maillon((int) $m['id']);
    $nb = nb_rendus((int) $ch['id']);
    $fini = $nb >= total_photos() || $ch['statut'] === 'terminee'; // terminé à la main entre-temps
    update('chaines', (int) $ch['id'], $fini ? ['statut' => 'terminee', 'date_fin' => $ch['date_fin'] ?: now(), 'attente_depuis' => null]
                                             : ['statut' => 'attente', 'attente_depuis' => now()]);
    if ($rejouer) q('UPDATE participants SET en_attente_depuis = ? WHERE id = ?', [now(), $p['id']]);

    envoyer_modele('merci', $p['mail'], vars_mail($p, $m), $p['prenom'] . ' ' . $p['nom']);
    prevenir_chaine($ch, $m, $p, $fini);
    if ($fini) mail_admin("Cadavre n°{$ch['numero']} terminé", "Le cadavre n°{$ch['numero']} a reçu sa " . total_photos() . "e photo.\n" . url('admin/chaine.php?id=' . $ch['id']));
    placer_attente();
}

/** Prévient les participants précédents du cadavre (nouvelle photo, ou cadavre terminé). Mails mis en file. */
function prevenir_chaine(array $ch, array $nouveau, array $auteur, bool $fini): int
{
    $n = 0;
    $nb = nb_rendus((int) $ch['id']);
    foreach (q("SELECT m.*, p.nom, p.prenom, p.mail, p.token AS ptoken, p.notif, p.club FROM maillons m JOIN participants p ON p.id = m.participant_id
                WHERE m.chaine_id = ? AND m.statut = 'rendu' AND m.id <> ? ORDER BY m.position", [$ch['id'], $nouveau['id']])->fetchAll() as $r) {
        if (!$fini && !$r['notif']) continue;
        $p = ['prenom' => $r['prenom'], 'nom' => $r['nom'], 'club' => $r['club'], 'mail' => $r['mail'], 'token' => $r['ptoken']];
        $vars = vars_mail($p, $r) + ['auteur' => $auteur['prenom'] . ' ' . $auteur['nom'], 'nb_photos' => $nb];
        if (mettre_en_file($fini ? 'termine' : 'nouvelle_photo', $r['mail'], $vars, $r['prenom'] . ' ' . $r['nom'], 'c' . $ch['id'])) $n++;
    }
    return $n;
}

/** Abandon / dépassement : le participant est passé, le cadavre repart avec un autre inscrit. */
function passer_maillon(array $m, string $raison = ''): void
{
    if ($m['statut'] !== 'en_cours') return;
    update('maillons', (int) $m['id'], ['statut' => 'passe', 'date_retour' => now(), 'note' => $raison ?: null]);
    if (chaine((int) $m['chaine_id'])['statut'] !== 'terminee') update('chaines', (int) $m['chaine_id'], ['statut' => 'attente', 'attente_depuis' => now()]);
    placer_attente();
}

/** Supprime un cadavre, ses maillons et ses photos (les participants restent). */
function supprimer_chaine(array $ch): void
{
    q('DELETE FROM maillons WHERE chaine_id = ?', [$ch['id']]);
    q('DELETE FROM chaines WHERE id = ?', [$ch['id']]);
    supprimer_dossier(PHOTO_DIR . '/' . dossier_chaine($ch));
}

// ---------------------------------------------------------------- estimations
/** Durée moyenne (jours) entre l'envoi et le rendu d'une photo (7 j par défaut). */
function duree_moyenne_maillon(): float
{
    $ms = q("SELECT date_envoi, date_retour FROM maillons WHERE statut IN ('rendu','passe') AND date_retour IS NOT NULL ORDER BY id DESC LIMIT 200")->fetchAll();
    if (count($ms) < 3) return 7.0;
    $t = 0;
    foreach ($ms as $m) $t += max(0, strtotime($m['date_retour']) - strtotime($m['date_envoi']));
    return max(0.5, $t / count($ms) / 86400);
}

function texte_estimation(float $jours): string
{
    if ($jours <= 3) return 'dans les prochains jours';
    if ($jours <= 7) return 'dans la semaine';
    $sem = (int) round($jours / 7);
    if ($sem <= 8) return "dans $sem semaines environ";
    return 'dans ' . (int) round($jours / 30) . ' mois environ';
}

function stats_publiques(): array
{
    return [
        'en_cours' => cadavres_ouverts(),
        'termines' => (int) qv("SELECT COUNT(*) FROM chaines WHERE statut = 'terminee'"),
        'photos' => (int) qv("SELECT COUNT(*) FROM maillons WHERE statut = 'rendu'"),
        'photographes' => (int) qv("SELECT COUNT(DISTINCT participant_id) FROM maillons WHERE statut = 'rendu'"),
        'attente' => (int) qv('SELECT COUNT(*) FROM participants WHERE en_attente_depuis IS NOT NULL'),
    ];
}

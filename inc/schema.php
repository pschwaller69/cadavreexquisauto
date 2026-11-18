<?php
/** Schéma compatible MySQL et SQLite. */
function schema_sql(string $driver): array
{
    $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $eng = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $txt = $driver === 'sqlite' ? 'TEXT' : 'MEDIUMTEXT';
    return [
        "CREATE TABLE IF NOT EXISTS admins (
            id $pk, login VARCHAR(60) NOT NULL UNIQUE, nom VARCHAR(120), mail VARCHAR(190),
            pass_hash VARCHAR(255) NOT NULL, derniere_connexion DATETIME NULL)$eng",
        // Un participant = une adresse mail. Il peut jouer dans plusieurs cadavres (jamais deux fois dans le même).
        "CREATE TABLE IF NOT EXISTS participants (
            id $pk, nom VARCHAR(120) NOT NULL, prenom VARCHAR(120) NOT NULL, mail VARCHAR(190) NOT NULL UNIQUE,
            club VARCHAR(190), token VARCHAR(64) NOT NULL UNIQUE, notif INT NOT NULL DEFAULT 1,
            date_inscription DATETIME, en_attente_depuis DATETIME NULL, rappel_le DATETIME NULL, remarque VARCHAR(255))$eng",
        // Cadavre : statut 'active' (un participant joue) | 'attente' (attend un participant) | 'terminee'
        "CREATE TABLE IF NOT EXISTS chaines (
            id $pk, numero INT NOT NULL, statut VARCHAR(20) NOT NULL DEFAULT 'attente',
            date_creation DATETIME, date_fin DATETIME NULL, attente_depuis DATETIME NULL,
            depart_fichier VARCHAR(255), depart_cle VARCHAR(80), depart_credit VARCHAR(255))$eng",
        "CREATE TABLE IF NOT EXISTS maillons (
            id $pk, chaine_id INT NOT NULL, participant_id INT NOT NULL, position INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE, statut VARCHAR(20) NOT NULL DEFAULT 'en_cours',
            date_envoi DATETIME, date_limite DATETIME, date_retour DATETIME NULL,
            relance_envoyee INT NOT NULL DEFAULT 0, alerte_envoyee INT NOT NULL DEFAULT 0,
            photo VARCHAR(255) NULL, photo_reduite VARCHAR(255) NULL, titre VARCHAR(200) NULL,
            demarche TEXT NULL, note VARCHAR(255) NULL,
            FOREIGN KEY (chaine_id) REFERENCES chaines(id) ON DELETE CASCADE,
            FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE)$eng",
        // Photos ajoutées par l'admin pour démarrer des cadavres
        "CREATE TABLE IF NOT EXISTS photos_depart (
            id $pk, fichier VARCHAR(255) NOT NULL, credit VARCHAR(255), actif INT NOT NULL DEFAULT 1, date_ajout DATETIME)$eng",
        "CREATE TABLE IF NOT EXISTS modeles_mail (
            code VARCHAR(30) PRIMARY KEY, libelle VARCHAR(120), sujet VARCHAR(255) NOT NULL, corps $txt NOT NULL)$eng",
        "CREATE TABLE IF NOT EXISTS file_envoi (
            id $pk, code VARCHAR(30), cle VARCHAR(40) NULL, destinataire VARCHAR(190) NOT NULL, nom VARCHAR(190), sujet VARCHAR(255) NOT NULL,
            corps $txt NOT NULL, desinscription INT NOT NULL DEFAULT 0, statut VARCHAR(20) NOT NULL DEFAULT 'attente',
            tentatives INT NOT NULL DEFAULT 0, erreur VARCHAR(500), date_creation DATETIME, date_envoi DATETIME NULL)$eng",
        "CREATE TABLE IF NOT EXISTS journal_mails (
            id $pk, date_envoi DATETIME, destinataire VARCHAR(190), sujet VARCHAR(255), corps $txt,
            statut VARCHAR(20), erreur VARCHAR(500))$eng",
    ];
}

function modeles_defaut(): array
{
    return [
        'bienvenue' => ['Bienvenue (inscrit en attente)', 'Bienvenue dans le cadavre exquis automatique !',
"Bonjour {prenom},

Merci pour ton inscription au cadavre exquis photographique automatique !

Le principe : tu reçois la dernière photo d'un cadavre en cours. Tu as {delai} jours pour réaliser une photo qui s'en inspire (une forme, une couleur, un sujet, une idée… à toi de voir), puis tu la déposes grâce au lien reçu.
Chaque cadavre compte {total} photos.

Tous les cadavres sont occupés pour l'instant : {phrase_rang}
Tu recevras un mail dès que ce sera ton tour.

Ton espace personnel (tes cadavres, ta place dans la file) :
{espace}

À bientôt,
Patrick"],
        'a_toi' => ['C\'est ton tour (lien de dépôt)', 'Cadavre exquis : c\'est ton tour ! (cadavre n°{numero})',
"Bonjour {prenom},

C'est à toi de jouer : tu es le maillon n°{position} sur {total} du cadavre n°{numero} !

Découvre la photo qui te précède et dépose la tienne ici :
{lien}

Tu as jusqu'au {date_limite} pour déposer ta photo (JPEG, la plus grande résolution possible).
Le même lien te permet de revoir la photo à tout moment.

Pas le temps en ce moment ? En bas de la page, tu peux laisser ta place au suivant et choisir quand on te recontacte (1 mois, 2 mois, 6 mois…).

Bonne inspiration !
Patrick"],
        'relance' => ['Relance', 'Rappel : ta photo pour le cadavre exquis n°{numero}',
"Bonjour {prenom},

Petit rappel : il te reste jusqu'au {date_limite} pour déposer ta photo dans le cadavre n°{numero}.

Ton lien : {lien}

Passé ce délai, le cadavre continuera automatiquement avec le participant suivant.

Merci !
Patrick"],
        'merci' => ['Merci + lien du résultat', 'Photo bien reçue, merci {prenom} ! (cadavre n°{numero})',
"Bonjour {prenom},

Ta photo est bien arrivée : c'est la n°{position} sur {total} du cadavre n°{numero}. Merci !
Elle vient d'être transmise au participant suivant.

Tu peux maintenant voir tout le cadavre, depuis la photo de départ :
{resultat}

Tu recevras un mail à chaque nouvelle photo ajoutée, jusqu'à la {total}e.

Ton espace personnel (pour rejouer ou arrêter les mails) :
{espace}

Patrick"],
        'nouvelle_photo' => ['Nouvelle photo dans ton cadavre', 'Cadavre n°{numero} : nouvelle photo ({nb_photos}/{total})',
"Bonjour {prenom},

{auteur} vient d'ajouter la photo n°{nb_photos} sur {total} au cadavre n°{numero}, dont tu fais partie.

Découvre-la ici :
{resultat}

Patrick

(Pour ne plus recevoir ces nouvelles : {espace})"],
        'termine' => ['Cadavre terminé', 'Le cadavre n°{numero} est terminé ! ({total} photos)',
"Bonjour {prenom},

Le cadavre n°{numero}, dont tu fais partie, vient de recevoir sa {total}e et dernière photo !

Le voici en entier, avec le diaporama :
{resultat}

Merci d'avoir joué le jeu. Tu peux rejouer dans un nouveau cadavre depuis ton espace :
{espace}

Patrick"],
        'rappel' => ['Rappel après une pause', 'On t\'avait promis de te recontacter : prêt(e) pour un cadavre exquis ?',
"Bonjour {prenom},

Il y a quelque temps, tu as laissé ta place dans un cadavre exquis faute de temps, en nous demandant de te recontacter. C'est le moment !

Pour rejouer, un clic suffit : tu recevras la dernière photo d'un cadavre en cours et tu auras {delai} jours pour réaliser la tienne.
{rejouer}

Pas encore le bon moment ? Ignore simplement ce mail, tu pourras revenir quand tu veux depuis ton espace :
{espace}

Patrick"],
        'espace' => ['Lien de l\'espace personnel', 'Ton lien personnel – Cadavre exquis automatique',
"Bonjour {prenom},

Voici ton espace personnel du cadavre exquis automatique : tes cadavres, ta place dans la file d'attente, et le bouton pour rejouer.
{espace}

Patrick"],
    ];
}

/** Crée les tables / modèles manquants (mises à jour sans relancer install.php). */
function assurer_schema(): void
{
    foreach (schema_sql(cfg('db.driver')) as $sql) db()->exec($sql);
    // Colonnes ajoutées après la première version
    ajouter_colonne('participants', 'rappel_le', 'DATETIME NULL');
    // 1.2.0 : le mail « c'est ton tour » présente l'option « recontacte-moi dans… » (seulement si la phrase d'origine n'a pas été modifiée)
    q("UPDATE modeles_mail SET corps = REPLACE(corps, ?, ?) WHERE code = 'a_toi'", [
        "Si tu ne peux finalement pas participer, clique sur « Je ne peux pas participer » en bas de la page : le cadavre continuera sans attendre.",
        "Pas le temps en ce moment ? En bas de la page, tu peux laisser ta place au suivant et choisir quand on te recontacte (1 mois, 2 mois, 6 mois…).",
    ]);
    foreach (modeles_defaut() as $code => [$lib, $suj, $corps]) {
        if (!qv('SELECT COUNT(*) FROM modeles_mail WHERE code = ?', [$code])) {
            insert('modeles_mail', ['code' => $code, 'libelle' => $lib, 'sujet' => $suj, 'corps' => $corps]);
        }
    }
}

/** Ajoute une colonne si elle n'existe pas encore (MySQL et SQLite). */
function ajouter_colonne(string $table, string $col, string $def): void
{
    try {
        db()->query("SELECT $col FROM $table LIMIT 0");
    } catch (Throwable $e) {
        db()->exec("ALTER TABLE $table ADD COLUMN $col $def");
    }
}

/** Met la base à jour quand schema.php change (appelé à chaque chargement, coût : un filemtime). */
function maj_schema_si_besoin(): void
{
    $v = (string) @filemtime(__FILE__);
    $f = DATA_DIR . '/schema_v.txt';
    if (@file_get_contents($f) === $v) return;
    try {
        assurer_schema();
        @file_put_contents($f, $v);
    } catch (Throwable $e) {
        // base pas encore installée : install.php s'en charge
    }
}

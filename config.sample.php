<?php
// Copier ce fichier en config.php et l'adapter.
return [
    // URL publique de l'application, sans / final
    'base_url' => 'https://cadavreexquisauto.schwaller.art',

    // Base de données : 'mysql' (o2switch) ou 'sqlite' (tests). Utiliser une base DIFFÉRENTE de celle du cadavre classique.
    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'name'     => 'xxxx_cadavreauto',
        'user'     => 'xxxx_cadavreauto',
        'pass'     => 'MOT_DE_PASSE_MYSQL',
        'sqlite'   => __DIR__ . '/data/cadavre.sqlite',
    ],

    // Envoi des mails via Gmail (SMTP) : même compte et même mot de passe d'application que le cadavre classique possible.
    'mail' => [
        'mode'      => 'smtp',              // 'smtp' ou 'log' (n'envoie rien, journalise seulement)
        'host'      => 'smtp.gmail.com',
        'port'      => 587,
        'secure'    => 'tls',               // 'tls' (587) ou 'ssl' (465)
        'user'      => 'cadavre.exquis@gmail.com',
        'pass'      => 'xxxx xxxx xxxx xxxx',
        'from'      => 'cadavre.exquis@gmail.com', // Gmail impose l'adresse du compte
        'from_name' => 'Cadavre Exquis Automatique',
        'admin'     => 'patrick@schwaller.fr', // reçoit les alertes
    ],
    // Mails « nouvelle photo » et « cadavre terminé » : mis en file et envoyés par la tâche cron
    'cron_minutes'           => 5, // fréquence de la tâche cron (pour l'estimation affichée)
    'cron_mails_par_passage' => 3, // mails de la file envoyés à chaque passage (3 toutes les 5 min = 864/jour max ; Gmail ≈ 500/jour)
    'mail_pause_sec'         => 4, // pause entre deux mails d'un même passage

    // Règles du jeu
    'photos_par_cadavre' => 15, // un cadavre est terminé quand il contient 15 photos (hors photo de départ)
    'delai_jours'        => 14, // délai pour rendre sa photo
    'relance_jours'      => 10, // relance automatique
    'auto_passer_jours'  => 2,  // passe automatiquement au suivant N jours après la date limite (0 = jamais, l'admin décide)

    // Nombre de cadavres en cours : il s'adapte tout seul au nombre d'inscrits
    'attente_par_cadavre' => 1,  // un nouveau cadavre s'ouvre dès qu'il y a plus de N inscrits en attente par cadavre en cours
    'attente_max_jours'   => 7,  // ... ou dès qu'un inscrit attend depuis plus de N jours sans cadavre possible
    'cadavres_max'        => 40, // plafond de cadavres en cours en même temps
    'club_tolerance_jours' => 3, // au-delà de N jours d'attente, on accepte deux photos du même club à la suite

    // Photos
    'upload_max_mo'      => 30,
    'reduite_px'         => 2000, // plus grand côté de la version réduite (envoyée au suivant / affichée)

    // Clé pour lancer cron.php par URL si pas de cron en ligne de commande
    'cron_key'           => 'changer-cette-cle-' . 'aleatoire',

    'timezone'           => 'Europe/Paris',
];

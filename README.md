# Cadavre Exquis Photographique Automatique – application web

Version entièrement automatique du cadavre exquis, sur `https://cadavreexquisauto.schwaller.art`.
Même technique que le cadavre classique (`cadavreexquis.schwaller.art`) : PHP/MySQL chez o2switch, mails par Gmail,
administration installable sur le téléphone. Les deux applis sont indépendantes (base et dossier séparés).

## Comment ça marche

- **Pas d'édition, pas de date** : le jeu tourne en continu. On s'inscrit quand on veut sur la page d'accueil.
- **Cadavres de 15 photos** (`photos_par_cadavre`). Un cadavre est terminé à sa 15e photo.
- **Photo de départ tirée au sort** parmi les photos des autres cadavres (en priorité celles jamais tirées),
  et les photos ajoutées dans l'admin (*Photos de départ*).
- **Nombre de cadavres automatique** :
  - un inscrit est placé tout de suite dans un cadavre libre, le plus avancé d'abord (pour les terminer) ;
  - jamais deux fois dans le même cadavre, jamais deux photos du même club à la suite
    (sauf si la personne attend depuis plus de `club_tolerance_jours`) ;
  - un **nouveau cadavre s'ouvre** dès qu'il y a plus de `attente_par_cadavre` inscrit(s) en attente par cadavre en cours,
    ou quand quelqu'un attend depuis plus de `attente_max_jours` ; plafond `cadavres_max` ;
  - beaucoup d'inscrits = beaucoup de cadavres en parallèle ; peu d'inscrits = les cadavres existants avancent.
- **Résultat immédiat** : dès sa photo déposée, le participant reçoit le lien de son cadavre (`resultat.php`),
  qu'il voit en entier depuis la photo de départ, avec diaporama.
  Il reçoit ensuite **un mail à chaque nouvelle photo** de ce cadavre, puis un mail quand il est terminé.
  (Celui qui doit faire la photo suivante ne voit toujours que la photo précédente.)
- **Rejouer** : case « Je veux rejouer » au dépôt, ou bouton dans l'espace personnel (`moi.php`).
  L'espace personnel montre ses cadavres, sa place dans la file et permet de couper les mails « nouvelle photo ».
- **Retards** : relance à J+10, passage automatique au suivant 2 jours après la date limite (`auto_passer_jours`).
  Le participant peut aussi se désister en un clic.

## Mails automatiques (modifiables dans Admin > Mails)

| Mail | Quand |
|------|-------|
| Bienvenue | inscription quand tous les cadavres sont occupés (avec sa place dans la file) |
| C'est ton tour | placé dans un cadavre : lien de dépôt avec la photo précédente |
| Relance | J+10 |
| Merci + lien du résultat | dès le dépôt |
| Nouvelle photo | aux participants précédents du cadavre, à chaque photo ajoutée (file d'envoi) |
| Cadavre terminé | à tous les participants du cadavre, à la 15e photo (file d'envoi) |
| Lien de l'espace | si quelqu'un se réinscrit avec la même adresse alors qu'il joue déjà |

Les mails « nouvelle photo » et « terminé » partent par la tâche cron, 3 par passage (toutes les 5 min).
Si une 2e photo arrive avant le départ du mail, le mail en attente est remplacé (un seul mail, à jour).
Gmail limite à environ 500 mails par jour : largement suffisant pour plusieurs dizaines de cadavres en parallèle.

## Installation sur o2switch

### 1. Sous-domaine et base
1. cPanel > **Domaines** : créer `cadavreexquisauto.schwaller.art` (dossier `cadavreexquisauto.schwaller.art`). Vérifier le SSL (AutoSSL).
2. cPanel > **Bases de données MySQL** : créer une **nouvelle** base (ex. `xxxx_cadavreauto`) et un utilisateur avec tous les privilèges.

### 2. Fichiers
- **Avec Git (recommandé, comme le cadavre classique)** : créer un dépôt GitHub `CadavreExquisAuto`, y mettre le contenu de ce dossier,
  puis cPanel > Git Version Control > cloner, « Update from Remote » et « Deploy HEAD Commit » (`.cpanel.yml` copie vers le sous-domaine).
- **Sans Git** : cPanel > Gestionnaire de fichiers > dossier `cadavreexquisauto.schwaller.art` > Charger `cadavreexquisauto.zip` > Extraire.

### 3. Configuration
1. Copier `config.sample.php` en `config.php` et remplir : la base MySQL, le bloc `mail` (même Gmail et même mot de passe d'application
   que le cadavre classique si vous voulez), `mail.admin`, et changer `cron_key`.
2. Ouvrir `https://cadavreexquisauto.schwaller.art/install.php` : contrôles, création des tables et du compte admin.
3. **Supprimer `install.php`.**
4. cPanel > **Sélectionner une version de PHP** > Options : `upload_max_filesize` = 32M, `post_max_size` = 128M
   (o2switch ignore `.user.ini` : sans ça, les photos de plus de 2 Mo sont refusées).
5. Vérifier que `https://cadavreexquisauto.schwaller.art/config.php` et `/data/photos/` répondent « 403 Forbidden ».

### 4. Tâche cron (indispensable)
cPanel > **Tâches Cron**, toutes les 5 minutes :
```
*/5 * * * * /usr/local/bin/php /home/VOTRE_COMPTE/cadavreexquisauto.schwaller.art/cron.php >/dev/null 2>&1
```
Elle envoie les relances, passe les retardataires, place les inscrits en attente et envoie les mails « nouvelle photo ».

### 5. Avant d'ouvrir
1. Admin > **Photos de départ** : ajouter quelques photos. Sans photo de départ, aucun cadavre ne peut s'ouvrir.
2. Admin > **Mails** > « M'envoyer un test ».
3. Diffuser `https://cadavreexquisauto.schwaller.art`.

## Réglages (config.php)
| Réglage | Défaut | Rôle |
|---|---|---|
| `photos_par_cadavre` | 15 | photos par cadavre (hors photo de départ) |
| `delai_jours` / `relance_jours` | 14 / 10 | délai pour rendre sa photo, relance |
| `auto_passer_jours` | 2 | passage automatique N jours après la limite (0 = jamais) |
| `attente_par_cadavre` | 1 | nouveau cadavre dès qu'il y a plus de N inscrits en attente par cadavre en cours |
| `attente_max_jours` | 7 | nouveau cadavre si quelqu'un attend depuis plus de N jours |
| `cadavres_max` | 40 | nombre maximum de cadavres en cours |
| `club_tolerance_jours` | 3 | au-delà, deux photos du même club peuvent se suivre |
| `cron_mails_par_passage` | 3 | mails de la file envoyés toutes les 5 min |

## Administration
- **Tableau de bord** : cadavres en cours (vignettes, qui a la main, retards), file d'attente, boutons « Placer » et « Ouvrir un cadavre ».
- **Cadavres** : tous les cadavres ; page d'un cadavre : relancer, +7 j, renvoyer le lien, passer, déposer une photo reçue par mail,
  remplacer / retirer une photo, tirer une autre photo de départ (avant le 1er participant), terminer, rouvrir, supprimer, diaporama.
- **Participants** : recherche, fiche, mise en file / retrait, lien de son espace, inscription manuelle.
- **QR code** : pour les événements photo (plein écran, affiche A4, PNG/SVG).
- **Photos de départ**, **Mails** (modèles, file d'envoi, journal), **Administrateurs**.

## Structure
```
index.php      inscription publique          depot.php    dépôt de la photo (lien unique)
resultat.php   le cadavre du participant     moi.php      espace personnel (rejouer, mails)
img.php        photos protégées              cron.php     tâche planifiée
admin/         administration                inc/         code (logic.php = le moteur) – accès web interdit
data/photos/   photos – accès web interdit
```
Sauvegarde : la base MySQL et `data/photos/` (couverts par JetBackup chez o2switch).

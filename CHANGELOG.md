# Nouveautés

La version en ligne s'affiche en haut à droite de l'admin et sur https://cadavreexquisauto.schwaller.art/version.php

## 1.2.0 – 18/11/2026
- Page de dépôt : « Pas le temps en ce moment ? » — le participant laisse sa place au suivant et choisit quand être recontacté
  (1, 2, 3, 6 mois ou 1 an) ; à la date prévue, il reçoit un mail avec un lien pour rejouer en un clic (nouveau modèle « Rappel après une pause »)
- Espace personnel : date du rappel prévu et bouton pour l'annuler ; admin Participants : ⏰ date de rappel
- Mail « c'est ton tour » : présente cette option
- La base se met à jour toute seule quand une nouvelle version ajoute une colonne (plus besoin de passer par l'admin)

## 1.1.1 – 18/11/2026
- Suppression de l'option « photos du cadavre classique » comme photos de départ (photos_depart_externes) :
  les départs viennent uniquement des photos ajoutées dans l'admin et des photos des cadavres automatiques

## 1.1.0 – 18/11/2026
- Admin > **QR code** : QR code de la page d'inscription, mode événement plein écran (tablette, vidéoprojecteur),
  affiche A4 à imprimer, téléchargement PNG 2000 px et SVG ; raccourci « QR code (événement) » dans l'appli admin installée

## 1.0.2 – 18/11/2026
- Limites d'envoi réglées dans cPanel (PHP Selector > Options : 32 Mo par photo, 128 Mo par envoi) ; .user.ini et php_value sont ignorés chez o2switch

## 1.0.1 – 18/11/2026
- (essai de limites dans .htaccess, sans effet chez o2switch)

## 1.0.0 – 18/11/2026
Première version du cadavre exquis automatique (construit à partir du cadavre exquis classique v1.13.0).
- Jeu continu, sans édition : inscription à tout moment, cadavres de 15 photos
- Photo de départ tirée au sort parmi les photos des autres cadavres (+ photos ajoutées dans l'admin)
- Nombre de cadavres en cours ajusté automatiquement selon les inscrits en attente
- Lien du cadavre envoyé dès le dépôt, mail à chaque nouvelle photo puis à la fin du cadavre
- Espace personnel : ses cadavres, sa place dans la file, rejouer, couper les mails
- Relance, passage automatique des retardataires, désistement en un clic
- Admin : tableau de bord, cadavres, participants, photos de départ, mails, administrateurs

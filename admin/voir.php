<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/vue.php';
require_admin();

$ch = chaine((int) get('id'));
if (!$ch) redirect('cadavres.php');
afficher_cadavre($ch, fn(string $type, ?int $id) => '../img.php?' . ($type === 'depart' ? 'd=' . $ch['id'] : 'm=' . $id), [
    'retour' => 'chaine.php?id=' . $ch['id'], 'retour_lib' => '← Admin',
    'head' => '<link rel="icon" type="image/png" href="../assets/icons/favicon.png">',
]);

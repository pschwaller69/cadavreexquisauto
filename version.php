<?php
// Vérification rapide sans connexion : https://cadavreexquisauto.schwaller.art/version.php
require __DIR__ . '/inc/version.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(version_info(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

<?php
// Numéro de version de l'application : à augmenter à chaque mise à jour (voir CHANGELOG.md).
const APP_VERSION = '1.4.0';
const APP_DATE    = '2026-11-18';

/**
 * Infos de version : numéro + commit et date du déploiement.
 * inc/deploy.txt est écrit par .cpanel.yml à chaque « Deploy HEAD Commit ».
 */
function version_info(): array
{
    $v = ['version' => APP_VERSION, 'date' => APP_DATE, 'commit' => null, 'deploye' => null];
    $f = __DIR__ . '/deploy.txt';
    if (is_file($f) && preg_match('/^(\w+)\s+(.+)$/', trim((string) file_get_contents($f)), $m)) {
        $v['commit'] = $m[1];
        $v['deploye'] = trim($m[2]);
    }
    return $v;
}

function version_texte(): string
{
    $v = version_info();
    $t = 'v' . $v['version'];
    if ($v['commit']) $t .= ' · ' . $v['commit'];
    if ($v['deploye']) $t .= ' · déployée le ' . date('d/m/Y à H:i', strtotime($v['deploye']));
    return $t;
}

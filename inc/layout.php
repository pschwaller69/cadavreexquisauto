<?php
function entete(string $titre, string $actif = ''): void
{
    $a = admin();
    $menu = ['index' => 'Tableau de bord', 'cadavres' => 'Cadavres', 'participants' => 'Participants', 'depart' => 'Photos de départ',
             'mails' => 'Mails', 'admins' => 'Administrateurs'];
    ?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($titre) ?> – Cadavre Auto</title><link rel="stylesheet" href="../assets/style.css?v=<?= APP_VERSION ?>"><?= pwa_head() ?>
<body class="admin">
<header class="barre">
  <a class="logo" href="index.php">Cadavre <span>Auto</span></a>
  <nav><?php foreach ($menu as $k => $lib): ?><a href="<?= $k ?>.php" class="<?= $actif === $k ? 'actif' : '' ?>"><?= $lib ?></a><?php endforeach ?></nav>
  <div class="droite">
    <span class="version" title="<?= h(version_texte()) ?>">v<?= APP_VERSION ?></span>
    <span><?= h($a['nom'] ?: $a['login']) ?></span><a href="logout.php">Déconnexion</a>
  </div>
</header>
<main>
<?= alerte_base_url() ?>
<?php foreach (flash() as [$type, $msg]): ?><p class="alerte <?= $type ?>"><?= h($msg) ?></p><?php endforeach;
}

function pied(): void
{
    echo '<footer class="pied-app">Cadavre Exquis Automatique ' . h(version_texte())
        . '</footer></main></body></html>';
}

function badge(string $statut, ?string $lib = null): string
{
    $libs = ['active' => 'en jeu', 'en_cours' => 'en cours', 'rendu' => 'rendu', 'attente' => 'en attente',
             'close' => 'terminée', 'passe' => 'passé', 'abandon' => 'abandon', 'affecte' => 'placé',
             'preparation' => 'préparation', 'terminee' => 'terminé', 'retard' => 'en retard'];
    return '<span class="badge ' . h($statut) . '">' . h($lib ?? ($libs[$statut] ?? $statut)) . '</span>';
}

function en_retard(array $m): bool { return $m['statut'] === 'en_cours' && $m['date_limite'] < now(); }

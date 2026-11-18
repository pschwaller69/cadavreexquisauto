<?php
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/** PHPMailer sans encodage de l'en-tête List-Unsubscribe (il doit rester lisible tel quel). */
class MailerCadavre extends PHPMailer
{
    public function encodeHeader($str, $position = 'text')
    {
        if (str_starts_with((string) $str, '<mailto:')) return $str;
        return parent::encodeHeader($str, $position);
    }
}

/** Remplace {variables} dans un texte. */
function remplir(string $txt, array $vars): string
{
    return preg_replace_callback('/\{(\w+)\}/', fn($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0], $txt);
}

/** Texte brut -> HTML simple (liens cliquables, paragraphes). */
function texte_vers_html(string $txt): string
{
    $html = h($txt);
    $html = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#0b7fb0">$1</a>', $html);
    $html = nl2br($html);
    return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#222;max-width:640px">' . $html . '</div>';
}

/**
 * Envoie un mail et le journalise. Retourne true si OK.
 */
function envoyer_mail(string $to, string $sujet, string $corps, ?string $nom = null, array $opts = []): bool
{
    $statut = 'envoye';
    $erreur = null;
    if (cfg('mail.mode') === 'smtp') {
        $m = new MailerCadavre(true);
        try {
            $m->isSMTP();
            $m->Host = cfg('mail.host');
            $m->Port = (int) cfg('mail.port');
            $m->SMTPAuth = true;
            $m->Username = cfg('mail.user');
            $m->Password = cfg('mail.pass');
            $m->SMTPSecure = cfg('mail.secure') === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $m->CharSet = 'UTF-8';
            $m->setFrom(cfg('mail.from'), cfg('mail.from_name'));
            $m->addReplyTo(cfg('mail.from'), cfg('mail.from_name'));
            if (!empty($opts['desinscription'])) {
                // Mails envoyés à plusieurs personnes : lien de désinscription standard (moins de risque de spam)
                $m->addCustomHeader('List-Unsubscribe', '<mailto:' . cfg('mail.from') . '?subject=STOP>');
            }
            $m->addAddress($to, $nom ?? '');
            $m->Subject = $sujet;
            $m->isHTML(true);
            $m->Body = texte_vers_html($corps);
            $m->AltBody = $corps;
            $m->send();
        } catch (Throwable $e) {
            $statut = 'erreur';
            $erreur = $m->ErrorInfo ?: $e->getMessage();
        }
    } else {
        $statut = 'simule';
    }
    insert('journal_mails', [
        'date_envoi' => now(), 'destinataire' => $to, 'sujet' => $sujet,
        'corps' => $corps, 'statut' => $statut, 'erreur' => $erreur,
    ]);
    return $statut !== 'erreur';
}

/** Envoie un mail à partir d'un modèle (table modeles_mail). */
function envoyer_modele(string $code, string $to, array $vars, ?string $nom = null): bool
{
    $mod = q1('SELECT * FROM modeles_mail WHERE code = ?', [$code]);
    if (!$mod) return false;
    $groupes = ['nouvelle_photo', 'termine']; // mails envoyés à beaucoup de monde d'un coup
    return envoyer_mail($to, remplir($mod['sujet'], $vars), remplir($mod['corps'], $vars), $nom,
        ['desinscription' => in_array($code, $groupes, true)]);
}

/** Pause entre deux mails d'un envoi groupé (évite le classement en spam). */
function pause_envoi(): void
{
    if (cfg('mail.mode') === 'smtp') sleep(max(0, (int) cfg('mail_pause_sec', 4)));
}

function mail_admin(string $sujet, string $corps): void
{
    if (cfg('mail.admin')) envoyer_mail(cfg('mail.admin'), '[Cadavre Auto] ' . $sujet, $corps);
}

// ---------------------------------------------------------------- file d'envoi
/*
 * Les mails groupés (nouvelle photo, cadavre terminé) ne partent pas tous d'un coup :
 * ils sont mis en file et la tâche cron en envoie quelques-uns à chaque passage (moins de risque de spam).
 */
function mettre_en_file(string $code, string $to, array $vars, ?string $nom = null, ?string $cle = null): bool
{
    $mod = q1('SELECT * FROM modeles_mail WHERE code = ?', [$code]);
    if (!$mod) return false;
    if ($cle !== null) {
        // Un mail plus récent sur le même sujet (ex. même cadavre) remplace celui qui n'est pas encore parti
        q("DELETE FROM file_envoi WHERE statut = 'attente' AND cle = ? AND destinataire = ?", [$cle, $to]);
    } elseif (qv("SELECT COUNT(*) FROM file_envoi WHERE statut = 'attente' AND code = ? AND destinataire = ?", [$code, $to])) {
        return true; // déjà en file
    }
    insert('file_envoi', [
        'code' => $code, 'cle' => $cle, 'destinataire' => $to, 'nom' => $nom, 'sujet' => remplir($mod['sujet'], $vars),
        'corps' => remplir($mod['corps'], $vars), 'desinscription' => 1, 'statut' => 'attente', 'tentatives' => 0,
        'date_creation' => now(),
    ]);
    return true;
}

/** Envoie les $max plus anciens mails de la file. Retourne le nombre envoyés. */
function traiter_file(int $max = 1): int
{
    $n = 0;
    foreach (q("SELECT * FROM file_envoi WHERE statut = 'attente' ORDER BY id LIMIT " . max(1, $max))->fetchAll() as $i => $f) {
        if ($i > 0) pause_envoi();
        $ok = envoyer_mail($f['destinataire'], $f['sujet'], $f['corps'], $f['nom'], ['desinscription' => (bool) $f['desinscription']]);
        if ($ok) {
            update('file_envoi', (int) $f['id'], ['statut' => 'envoye', 'date_envoi' => now(), 'tentatives' => $f['tentatives'] + 1]);
            $n++;
        } else {
            $err = qv('SELECT erreur FROM journal_mails ORDER BY id DESC LIMIT 1');
            $t = $f['tentatives'] + 1;
            update('file_envoi', (int) $f['id'], ['tentatives' => $t, 'erreur' => mb_substr((string) $err, 0, 500), 'statut' => $t >= 3 ? 'erreur' : 'attente']);
            break; // problème d'envoi : on réessaiera au prochain passage
        }
    }
    return $n;
}

function file_en_attente(): int { return (int) qv("SELECT COUNT(*) FROM file_envoi WHERE statut = 'attente'"); }

/** Heure estimée de fin de la file, d'après la fréquence du cron. */
function fin_estimee_file(): string
{
    $n = file_en_attente();
    if (!$n) return '';
    $par = max(1, (int) cfg('cron_mails_par_passage', 1));
    $min = (int) ceil($n / $par) * max(1, (int) cfg('cron_minutes', 5));
    return $min < 60 ? "dans $min min environ" : 'vers ' . date('H:i', time() + $min * 60) . ($min >= 1440 - (int) date('G') * 60 ? ' (' . date('d/m', time() + $min * 60) . ')' : '');
}

/** Dernier passage de la tâche cron (enregistré par cron.php). */
function dernier_cron(): ?string
{
    $f = DATA_DIR . '/cron_dernier.txt';
    return is_file($f) ? trim((string) file_get_contents($f)) : null;
}

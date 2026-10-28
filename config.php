<?php
// Réglages du site de présentation de DevLauncher.
// Les valeurs propres à l'hébergement et les secrets (clés WhatsApp, jeton du cron) sont dans un fichier .env :
//   1. ../devlauncher.env  (dossier parent du site : hors d'atteinte du web, recommandé sur un hébergement mutualisé)
//   2. ./.env              (dans le dossier du site : protégé par .htaccess)
// Modèle à copier : .env.example
$env = [];
foreach ([dirname(__DIR__) . '/devlauncher.env', __DIR__ . '/.env'] as $file) {
    if (!is_file($file)) continue;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/', $line, $m) && $line[0] !== '#') {
            $env[$m[1]] = trim($m[2], "\"'");
        }
    }
    break;
}
$get = fn (string $k, $default = '') => array_key_exists($k, $env) && $env[$k] !== '' ? $env[$k] : $default;
$bool = fn (string $k, bool $default = false) => in_array(strtolower((string) $get($k, $default ? 'true' : 'false')), ['1', 'true', 'oui', 'yes', 'on'], true);

return [
    'version'       => $get('APP_VERSION', '1.5.0'),
    'installer'     => $get('INSTALLER', 'downloads/DevLauncher-Setup-1.5.0.exe'), // chemin relatif à ce dossier
    'portable'      => $get('PORTABLE', 'downloads/DevLauncher-1.5.0-portable.zip'), // version sans installation
    'site_url'      => rtrim($get('SITE_URL', ''), '/'),                            // adresse publique (liens du rapport)
    'contact_email' => $get('CONTACT_EMAIL', 'contact@novatechbenin.com'),          // destinataire des avis et des rapports
    'from_email'    => $get('FROM_EMAIL', 'no-reply@novatechbenin.com'),            // expéditeur : une adresse du domaine qui héberge le site
    'whatsapp'      => $get('WHATSAPP_NUMBER', '22966185595'),                      // numéro public de contact, sans « + »
    'community'     => $get('COMMUNITY_URL', 'https://chat.whatsapp.com/Ea1u7p4KD5261ZBGuOzCrw'),
    'signed'        => $bool('INSTALLER_SIGNED'),                                   // true une fois l'installateur signé
    'publisher'     => $get('PUBLISHER', 'Novatech Solution'),
    // Compteur public : affiché à partir de ce nombre de téléchargements (avant : « Nouveau »). Le chiffre affiché est toujours le vrai.
    'counter_min'   => (int) $get('COUNTER_MIN_DISPLAY', '0'),
    // Compte à rebours de lancement (vide = aucun) ; LAUNCH_LOCK=true bloque le téléchargement jusqu'à cette date.
    'launch_at'     => $get('LAUNCH_AT', ''),                                       // ex. 2026-12-01 10:00
    'launch_lock'   => $bool('LAUNCH_LOCK'),
    'timezone'      => $get('TIMEZONE', 'Africa/Porto-Novo'),
    // Rapport WhatsApp (cron/rapport.php)
    'report' => [
        'provider'      => $get('REPORT_PROVIDER', 'greenapi'),        // greenapi | meta | callmebot | email
        'phone'         => $get('REPORT_PHONE', '22966185595'),        // numéro qui reçoit le rapport, sans « + »
        // Green-API (WhatsApp relié par QR code, offre « Developer » gratuite)
        'greenapi_url'      => rtrim($get('GREENAPI_URL', 'https://api.green-api.com'), '/'),
        'greenapi_instance' => $get('GREENAPI_INSTANCE', ''),
        'greenapi_token'    => $get('GREENAPI_TOKEN', ''),
        // API WhatsApp Cloud officielle (Meta)
        'meta_token'    => $get('META_WA_TOKEN', ''),
        'meta_phone_id' => $get('META_WA_PHONE_ID', ''),
        'meta_template' => $get('META_WA_TEMPLATE', ''),                // modèle approuvé à 4 variables (voir .env.example)
        'meta_lang'     => $get('META_WA_TEMPLATE_LANG', 'fr'),
        'callmebot_key' => $get('CALLMEBOT_APIKEY', ''),
        'email_copy'    => $bool('REPORT_EMAIL_COPY', true),            // copie du rapport par e-mail
        'cron_token'    => $get('CRON_TOKEN', ''),                      // pour un cron « par URL » (sinon : ligne de commande seulement)
    ],
    // Serveur d'envoi des e-mails (avis, rapports). Vide : fonction mail() de PHP.
    'smtp' => [
        'host'      => $get('SMTP_HOST', ''),
        'port'      => (int) $get('SMTP_PORT', '465'),
        'secure'    => $get('SMTP_SECURE', 'ssl'),             // ssl (port 465) | tls (port 587) | none (tests locaux)
        'user'      => $get('SMTP_USER', ''),
        'pass'      => $get('SMTP_PASS', ''),
        'verify'    => $bool('SMTP_VERIFY_CERT', true),          // false seulement si l'hébergeur a un certificat invalide
        'from_name' => $get('SMTP_FROM_NAME', 'DevLauncher · Novatech Solution'),
    ],
    'data_dir'      => __DIR__ . '/data',                               // compteur et copies des avis (protégé par .htaccess)
];

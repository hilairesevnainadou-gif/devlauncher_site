<?php
// Rapport des téléchargements envoyé sur WhatsApp (et par e-mail), lancé par une tâche planifiée (cron).
//
//   php rapport.php              rapport complet (à planifier une fois par jour)
//   php rapport.php --paliers    message seulement quand un palier est franchi (10, 50, 100...) : à planifier toutes les heures
//   php rapport.php --essai      affiche le message sans l'envoyer
//
// Par URL (services de cron externes) : rapport.php?token=CRON_TOKEN[&paliers=1][&essai=1]
require dirname(__DIR__) . '/lib.php';
date_default_timezone_set($CONFIG['timezone']);
$R = $CONFIG['report'];

// --- Accès : ligne de commande, ou URL avec le jeton secret
$cli = PHP_SAPI === 'cli';
if (!$cli) {
    $token = (string) ($_GET['token'] ?? '');
    if ($R['cron_token'] === '' || !hash_equals($R['cron_token'], $token)) { http_response_code(403); exit("Accès refusé.\n"); }
    header('Content-Type: text/plain; charset=utf-8');
}
$args = $cli ? array_slice($argv, 1) : array_keys(array_filter(['--paliers' => isset($_GET['paliers']), '--essai' => isset($_GET['essai'])]));
$milestonesOnly = in_array('--paliers', $args, true);
$dryRun = in_array('--essai', $args, true);

// --- Données
$counter = is_file(data_path('compteur.json')) ? (json_decode((string) file_get_contents(data_path('compteur.json')), true) ?: []) : [];
$total = (int) ($counter['total'] ?? 0);
$days = $counter['days'] ?? [];
$day = fn (int $ago) => (int) ($days[date('Y-m-d', strtotime("-$ago day"))] ?? 0);
$sumDays = function (int $n) use ($day) { $s = 0; for ($i = 0; $i < $n; $i++) $s += $day($i); return $s; };
arsort($days);
$bestDay = $days ? array_key_first($days) : null;

$reviews = [];
if (is_file(data_path('avis.jsonl'))) {
    foreach (file(data_path('avis.jsonl'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) { $r = json_decode($l, true); if ($r) $reviews[] = $r; }
}
$rated = array_filter(array_column($reviews, 'note'));
$average = $rated ? round(array_sum($rated) / count($rated), 1) : null;

$state = is_file(data_path('rapport.json')) ? (json_decode((string) file_get_contents(data_path('rapport.json')), true) ?: []) : [];
$sinceTotal = $total - (int) ($state['total'] ?? 0);
$sinceReviews = count($reviews) - (int) ($state['reviews'] ?? 0);

$n = fn (int $v) => number_format($v, 0, ',', ' ');
$fr = fn (?string $d) => $d ? date('d/m/Y', strtotime($d)) : '—';

// --- Paliers : message court, seulement quand un nouveau palier est atteint
$steps = [10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10000, 25000, 50000, 100000];
$reached = max(array_merge([0], array_filter($steps, fn ($s) => $total >= $s)));
if ($milestonesOnly) {
    if ($reached <= (int) ($state['milestone'] ?? 0)) { echo "Aucun nouveau palier ($n($total) téléchargements).\n"; exit; }
    $message = "🎉 DevLauncher vient de dépasser {$n($reached)} téléchargements !\n"
        . "Total actuel : {$n($total)} · aujourd'hui : {$n($day(0))}"
        . ($CONFIG['site_url'] ? "\n{$CONFIG['site_url']}" : '');
} else {
    $last = $reviews ? end($reviews) : null;
    $message = "📊 DevLauncher — rapport du " . date('d/m/Y à H:i') . "\n\n"
        . "⬇️ Téléchargements : *{$n($total)}* au total"
        . (!empty($counter['types']) ? ' (installateur : ' . $n((int) ($counter['types']['exe'] ?? 0)) . ', portable : ' . $n((int) ($counter['types']['zip'] ?? 0)) . ')' : '') . "\n"
        . "• Aujourd'hui : {$n($day(0))} (hier : {$n($day(1))})\n"
        . "• 7 derniers jours : {$n($sumDays(7))} · 30 jours : {$n($sumDays(30))}\n"
        . "• Depuis le dernier rapport : " . ($sinceTotal >= 0 ? '+' : '') . $n($sinceTotal) . "\n"
        . ($bestDay ? "• Meilleur jour : {$n((int) $counter['days'][$bestDay])} le {$fr($bestDay)}\n" : '')
        . "\n⭐ Avis : {$n(count($reviews))}" . ($average !== null ? ' (moyenne ' . str_replace('.', ',', (string) $average) . '/5)' : '')
        . ($sinceReviews > 0 ? " · {$n($sinceReviews)} nouveau" . ($sinceReviews > 1 ? 'x' : '') : '') . "\n"
        . ($last ? '💬 Dernier : « ' . mb_strimwidth((string) $last['message'], 0, 140, '…') . ' » — ' . $last['nom'] . "\n" : '')
        . ($CONFIG['site_url'] ? "\n🔗 {$CONFIG['site_url']}" : '');
}

echo $message . "\n\n";
if ($dryRun) { echo "(essai : rien n'a été envoyé)\n"; exit; }

// --- Envoi
function http_call(string $url, ?array $json = null, array $headers = []): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers]);
        if ($json !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json)); }
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        if (PHP_VERSION_ID < 80000) curl_close($ch); // sans effet depuis PHP 8
        return [$code, $body ?: $err];
    }
    $ctx = stream_context_create(['http' => ['method' => $json !== null ? 'POST' : 'GET', 'timeout' => 30, 'ignore_errors' => true,
        'header' => implode("\r\n", $headers), 'content' => $json !== null ? json_encode($json) : null]]);
    $body = (string) @file_get_contents($url, false, $ctx);
    $code = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) ? (int) $m[1] : 0;
    return [$code, $body];
}

$sent = false;
$phone = preg_replace('/\D/', '', $R['phone']);
if ($R['provider'] === 'greenapi') {
    if ($R['greenapi_instance'] === '' || $R['greenapi_token'] === '') echo "WhatsApp : GREENAPI_INSTANCE ou GREENAPI_TOKEN manquant dans le .env.\n";
    else {
        [$code, $body] = http_call($R['greenapi_url'] . '/waInstance' . rawurlencode($R['greenapi_instance']) . '/sendMessage/' . rawurlencode($R['greenapi_token']),
            ['chatId' => $phone . '@c.us', 'message' => $message], ['Content-Type: application/json']);
        $sent = $code === 200 && strpos($body, 'idMessage') !== false;
        echo 'WhatsApp (Green-API) : ' . ($sent ? 'envoyé' : "échec ($code) " . mb_substr($body, 0, 300)) . "\n";
    }
} elseif ($R['provider'] === 'callmebot') {
    if ($R['callmebot_key'] === '') echo "WhatsApp : CALLMEBOT_APIKEY manquant dans le .env.\n";
    else {
        [$code, $body] = http_call('https://api.callmebot.com/whatsapp.php?phone=%2B' . $phone . '&text=' . rawurlencode($message) . '&apikey=' . rawurlencode($R['callmebot_key']));
        $sent = $code === 200 && stripos($body, 'error') === false;
        echo 'WhatsApp (CallMeBot) : ' . ($sent ? 'envoyé' : "échec ($code) " . mb_substr(strip_tags($body), 0, 200)) . "\n";
    }
} elseif ($R['provider'] === 'meta') {
    if ($R['meta_token'] === '' || $R['meta_phone_id'] === '') echo "WhatsApp : META_WA_TOKEN ou META_WA_PHONE_ID manquant dans le .env.\n";
    else {
        // Avec un modèle approuvé (obligatoire pour un message automatique) : 4 variables d'une ligne chacune.
        // Sans modèle : texte libre, accepté par Meta seulement si tu as écrit au numéro professionnel dans les dernières 24 h.
        $payload = $R['meta_template'] !== ''
            ? ['messaging_product' => 'whatsapp', 'to' => $phone, 'type' => 'template', 'template' => [
                'name' => $R['meta_template'], 'language' => ['code' => $R['meta_lang']],
                'components' => [['type' => 'body', 'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], [
                    $n($total), $n($day(0)), $n($sumDays(7)),
                    $n(count($reviews)) . ($average !== null ? ', moyenne ' . str_replace('.', ',', (string) $average) . '/5' : ''),
                ])]],
            ]]
            : ['messaging_product' => 'whatsapp', 'to' => $phone, 'type' => 'text', 'text' => ['body' => $message]];
        [$code, $body] = http_call('https://graph.facebook.com/v20.0/' . rawurlencode($R['meta_phone_id']) . '/messages', $payload,
            ['Authorization: Bearer ' . $R['meta_token'], 'Content-Type: application/json']);
        $sent = $code >= 200 && $code < 300;
        echo 'WhatsApp (Meta) : ' . ($sent ? 'envoyé' : "échec ($code) " . mb_substr($body, 0, 300)) . "\n";
    }
}
if ($R['email_copy'] || $R['provider'] === 'email' || !$sent) {
    $subject = $milestonesOnly ? "DevLauncher : palier de {$n($reached)} téléchargements" : 'DevLauncher : rapport des téléchargements';
    [$ok, $err] = send_mail($CONFIG['contact_email'], $subject, str_replace('*', '', $message));
    echo 'E-mail : ' . ($ok ? 'envoyé à ' . $CONFIG['contact_email'] : "échec ($err)") . "\n";
    $sent = $sent || $ok;
}

// --- Mémorise l'état seulement si le rapport est parti (sinon il sera renvoyé au prochain passage)
if ($sent) {
    $state = array_merge($state, ['at' => date('c'), 'milestone' => max($reached, (int) ($state['milestone'] ?? 0))]);
    if (!$milestonesOnly) $state = array_merge($state, ['total' => $total, 'reviews' => count($reviews)]);
    file_put_contents(data_path('rapport.json'), json_encode($state), LOCK_EX);
}

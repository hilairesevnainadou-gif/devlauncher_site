<?php
// Bouton « Télécharger » : compte le téléchargement puis envoie l'installateur.
require __DIR__ . '/lib.php';

// Avant la date de lancement (LAUNCH_LOCK=true dans le .env) : pas encore de téléchargement.
date_default_timezone_set($CONFIG['timezone']);
$launchTs = $CONFIG['launch_at'] !== '' ? strtotime($CONFIG['launch_at']) : false;
if ($CONFIG['launch_lock'] && $launchTs && $launchTs > time()) {
    if (wants_json()) json_out(['ok' => false, 'error' => 'Disponible le ' . date('d/m/Y à H:i', $launchTs) . '.'], 403);
    header('Location: index.php', true, 302);
    exit;
}

// ?type=zip : version portable ; sinon l'installateur
$type = ($_GET['type'] ?? '') === 'zip' ? 'zip' : 'exe';
$rel = $type === 'zip' ? $CONFIG['portable'] : $CONFIG['installer'];
$file = __DIR__ . '/' . $rel;
if (!is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Fichier indisponible</title>'
        . '<p style="font:16px system-ui;margin:40px">L\'installateur n\'est pas encore en ligne. Écris-nous à '
        . '<a href="mailto:' . e($CONFIG['contact_email']) . '">' . e($CONFIG['contact_email']) . '</a>.</p>';
    exit;
}

$total = count_download($type);
if (wants_json()) json_out(['ok' => true, 'downloads' => $total, 'url' => $rel, 'type' => $type]);

// Redirection vers le fichier : le serveur web l'envoie directement (rapide, reprise possible).
header('Cache-Control: no-store');
header('Location: ' . $rel, true, 302);

<?php
// Guide d'utilisation de DevLauncher (même page que celle intégrée au logiciel), avec un lien de retour vers le site.
$file = __DIR__ . '/guide.html';
if (!is_file($file)) $file = dirname(__DIR__) . '/docs/Guide-DevLauncher.html'; // dossier de développement
if (!is_file($file)) { http_response_code(404); exit('Guide introuvable.'); }

$html = (string) file_get_contents($file);
$back = '<a href="./" style="position:fixed;right:18px;bottom:18px;z-index:99;display:inline-flex;align-items:center;gap:8px;'
    . 'padding:11px 16px;border-radius:24px;background:#0373bb;color:#fff;font:600 14px/1 \'Segoe UI\',system-ui,sans-serif;'
    . 'text-decoration:none;box-shadow:0 10px 28px rgba(3,115,187,.35)">&larr; Retour au site DevLauncher</a>';
// Insertion juste après l'ouverture de <body>, quels que soient ses attributs
$html = preg_replace('/<body([^>]*)>/i', '<body$1>' . $back, $html, 1);
header('Content-Type: text/html; charset=utf-8');
echo $html;

<?php
// Réception d'un avis : envoi à l'adresse de contact + copie enregistrée (au cas où l'e-mail ne partirait pas).
require __DIR__ . '/lib.php';

$back = function (string $state) {
    if (wants_json()) return;
    header('Location: index.php?avis=' . $state . '#avis', true, 303);
    exit;
};
$fail = function (string $message, int $status = 422) use ($back) {
    if (wants_json()) json_out(['ok' => false, 'error' => $message], $status);
    $back('erreur');
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $fail('Méthode non autorisée.', 405);

// Champ piège invisible : rempli seulement par les robots.
if (trim($_POST['site'] ?? '') !== '') { if (wants_json()) json_out(['ok' => true, 'mailed' => true]); $back('ok'); }

// Texte reçu remis en UTF-8 valide (un navigateur ou un outil mal réglé peut envoyer un autre encodage).
$utf8 = fn ($v) => mb_check_encoding((string) $v, 'UTF-8') ? (string) $v : mb_convert_encoding((string) $v, 'UTF-8', 'Windows-1252');
foreach (['nom', 'email', 'message'] as $k) $_POST[$k] = $utf8($_POST[$k] ?? '');

$name    = trim(mb_substr((string) ($_POST['nom'] ?? ''), 0, 80));
$email   = trim(mb_substr((string) ($_POST['email'] ?? ''), 0, 120));
$rating  = max(0, min(5, (int) ($_POST['note'] ?? 0)));
$message = trim(mb_substr((string) ($_POST['message'] ?? ''), 0, 3000));

if ($name === '') $fail('Indique ton nom.');
if (mb_strlen($message) < 5) $fail('Écris ton avis (quelques mots au moins).');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $fail('Adresse e-mail invalide.');
if (!allow_send()) $fail('Trop d\'envois depuis ta connexion : réessaie dans une heure ou écris-nous sur WhatsApp.', 429);

$entry = ['date' => date('c'), 'nom' => $name, 'email' => $email, 'note' => $rating, 'message' => $message];
file_put_contents(data_path('avis.jsonl'), json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "
", FILE_APPEND | LOCK_EX);

// E-mail lisible : objet court, version texte et version HTML (étoiles, message, bouton Répondre).
$subject = "Nouvel avis de $name" . ($rating ? " ($rating/5)" : '');
$when = date('d/m/Y à H:i');
$text = "$name a laissé un avis sur DevLauncher" . ($rating ? " : $rating/5" : '') . ".

"
    . "$message

"
    . ($email !== '' ? "Pour lui répondre : $email
" : "Pas d'adresse e-mail laissée.
")
    . "Reçu le $when depuis la page DevLauncher.
";
$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$starsHtml = $rating ? '<span style="color:#f5a623;font-size:20px;letter-spacing:2px">' . str_repeat('★', $rating) . '</span><span style="color:#d6dbe1;font-size:20px;letter-spacing:2px">' . str_repeat('★', 5 - $rating) . '</span> <span style="color:#57606a">' . $rating . '/5</span>' : '<span style="color:#8b939c">Pas de note</span>';
$html = '<!doctype html><html><body style="margin:0;padding:24px;background:#f4f5f7;font:15px/1.6 Segoe UI,Arial,sans-serif;color:#1d2125">'
    . '<div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e2e5e9;border-radius:12px;overflow:hidden">'
    . '<div style="height:4px;background:linear-gradient(90deg,#00a651 33%,#f7941d 33% 66%,#ed1c24 66%)"></div>'
    . '<div style="padding:22px 26px">'
    . '<div style="font-size:13px;color:#8b939c">Nouvel avis sur DevLauncher</div>'
    . '<div style="font-size:19px;font-weight:600;margin:4px 0 8px">' . $h($name) . '</div>'
    . '<div style="margin-bottom:16px">' . $starsHtml . '</div>'
    . '<div style="padding:14px 16px;background:#f6f8fa;border-left:3px solid #0373bb;border-radius:6px;white-space:pre-wrap">' . $h($message) . '</div>'
    . ($email !== ''
        ? '<p style="margin:20px 0 0"><a href="mailto:' . $h($email) . '?subject=' . rawurlencode('Re : ton avis sur DevLauncher') . '" style="display:inline-block;background:#0373bb;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600">Répondre à ' . $h($name) . '</a></p>'
        : '<p style="margin:20px 0 0;color:#8b939c;font-size:13px">Pas d\'adresse e-mail laissée.</p>')
    . '</div><div style="padding:12px 26px;border-top:1px solid #eef0f2;font-size:12px;color:#8b939c">Reçu le ' . $h($when) . ' depuis la page DevLauncher.</div>'
    . '</div></body></html>';

[$mailed, $mailError] = send_mail($CONFIG['contact_email'], $subject, $text, $html, $email, $name);
if (!$mailed) file_put_contents(data_path('envoi-erreurs.log'), date('c') . " avis de $name : $mailError
", FILE_APPEND | LOCK_EX);

if (wants_json()) json_out(['ok' => true, 'mailed' => $mailed]);
$back($mailed ? 'ok' : 'enregistre');

<?php
// Page de présentation de DevLauncher. Le nombre de téléchargements est écrit directement dans la page :
// rien à attendre à l'ouverture.
require __DIR__ . '/lib.php';
$logos = require __DIR__ . '/assets/logos.php';
$downloads = download_count();
date_default_timezone_set($CONFIG['timezone']);
// Compteur public : toujours le vrai nombre, affiché à partir du seuil choisi (avant : « Nouveau »).
$showCount = $downloads >= $CONFIG['counter_min'];
// Compte à rebours : avant la date de lancement ; le téléchargement peut être bloqué jusque-là.
$launchTs = $CONFIG['launch_at'] !== '' ? strtotime($CONFIG['launch_at']) : false;
$prelaunch = $launchTs && $launchTs > time();
$locked = $prelaunch && $CONFIG['launch_lock'];
$launchFr = $launchTs ? date('d/m/Y', $launchTs) . ' à ' . date('H:i', $launchTs) : '';
$installer = __DIR__ . '/' . $CONFIG['installer'];
$shaFile = $installer . '.sha256';
$sha = is_file($shaFile) ? strtok((string) file_get_contents($shaFile), " 
") : '';
$sizeMb = is_file($installer) && filesize($installer) > 1048576 ? (int) round(filesize($installer) / 1048576) : 33;
// Version portable (.zip) : taille et empreinte, si le fichier est en ligne
$zip = __DIR__ . '/' . $CONFIG['portable'];
$hasZip = is_file($zip);
$zipMb = $hasZip && filesize($zip) > 1048576 ? (int) round(filesize($zip) / 1048576) : 33;
$zipSha = is_file($zip . '.sha256') ? strtok((string) file_get_contents($zip . '.sha256'), " \n") : '';
$dl = fn (string $type = 'exe') => $locked ? 'href="#" data-locked' : 'href="telecharger.php' . ($type === 'zip' ? '?type=zip' : '') . '" data-download';
$wa = 'https://api.whatsapp.com/send/?phone=' . $CONFIG['whatsapp'] . '&type=phone_number&app_absent=0&text=';
$waHello = $wa . rawurlencode('Bonjour, j\'ai une question sur DevLauncher : ');
$avisState = $_GET['avis'] ?? '';
$community = $CONFIG['community'];
// Logos transmis à la scène 3D (tracé SVG + couleur de la marque)
$fwData = array_values(array_map(fn ($l) => ['name' => $l[0], 'd' => $l[1], 'color' => $l[2]], $logos));
$fmt = fn (int $n) => number_format($n, 0, ',', ' ');
$logo = function (string $id, string $class = '') use ($logos) {
    [$name, $d, $color] = $logos[$id];
    return '<svg class="fw ' . $class . '" viewBox="0 0 24 24" role="img" aria-label="' . e($name) . '"><path fill="' . e($color) . '" d="' . e($d) . '"/></svg>';
};
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DevLauncher · Lance tous tes projets web en un clic</title>
<meta name="description" content="DevLauncher détecte, lance et débogue tes projets Laravel, Next.js, Nuxt, React, Vue, Angular... sur XAMPP, Laragon ou WAMP. Logiciel Windows de Novatech Solution.">
<meta property="og:title" content="DevLauncher · Novatech Solution">
<meta property="og:description" content="Tous tes projets web locaux, lancés en un clic, sans taper de commande.">
<meta property="og:image" content="assets/captures/projets.png">
<link rel="icon" href="assets/logo.svg" type="image/svg+xml">
<style>
  :root {
    --bg: #f6f7f9; --surface: #fff; --surface-2: #f1f3f6; --border: #e2e5e9; --text: #15191d; --text-2: #555e68; --text-3: #8b939c;
    --accent: #0373bb; --accent-2: #025e99; --accent-weak: #e6f1f9; --green: #00a651; --yellow: #f7941d; --red: #ed1c24; --wa: #1fa855;
    --shadow: 0 1px 2px rgba(16, 24, 40, .05), 0 8px 24px rgba(16, 24, 40, .06); --radius: 12px;
  }
  @media (prefers-color-scheme: dark) {
    :root { --bg: #15171b; --surface: #1e2126; --surface-2: #24282e; --border: #31353c; --text: #e7e9ec; --text-2: #a7aeb7; --text-3: #7c838c;
      --accent: #4aa3e0; --accent-2: #6cb5e8; --accent-weak: #1d3243; --shadow: 0 1px 2px rgba(0, 0, 0, .3), 0 8px 24px rgba(0, 0, 0, .25); }
  }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  /* Les éléments animés qui arrivent depuis les côtés ne créent jamais de défilement horizontal */
  html, body { overflow-x: clip; }
  body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.6 "Segoe UI", system-ui, -apple-system, Roboto, sans-serif; -webkit-font-smoothing: antialiased; }
  a { color: var(--accent); }
  img { max-width: 100%; display: block; }
  .wrap { width: min(1140px, 100% - 40px); margin: 0 auto; }
  .tricolor { display: flex; height: 4px; } .tricolor i { flex: 1; } .tricolor i:nth-child(1) { background: var(--green); } .tricolor i:nth-child(2) { background: var(--yellow); } .tricolor i:nth-child(3) { background: var(--red); }

  /* En-tête */
  header.top { position: sticky; top: 0; z-index: 10; background: color-mix(in srgb, var(--bg) 86%, transparent); backdrop-filter: saturate(1.4) blur(10px); border-bottom: 1px solid var(--border); }
  .nav { display: flex; align-items: center; gap: 22px; height: 64px; }
  .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text); margin-right: auto; }
  .brand img { width: 32px; height: 32px; }
  .brand b { font-size: 17px; letter-spacing: -.01em; display: block; line-height: 1.1; }
  .brand span { font-size: 11.5px; color: var(--text-3); }
  .nav a.link { color: var(--text-2); text-decoration: none; font-size: 14.5px; font-weight: 500; }
  .nav a.link:hover { color: var(--text); }
  .btn { display: inline-flex; align-items: center; justify-content: center; gap: 9px; height: 44px; padding: 0 20px; border-radius: 10px; border: 1px solid transparent; font: inherit; font-weight: 600; font-size: 15px; text-decoration: none; cursor: pointer; transition: background .15s, transform .1s, box-shadow .15s; white-space: nowrap; }
  .btn:active { transform: translateY(1px); }
  .btn svg { width: 19px; height: 19px; flex: none; }
  .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 6px 18px color-mix(in srgb, var(--accent) 35%, transparent); }
  .btn-primary:hover { background: var(--accent-2); }
  .btn-ghost { background: var(--surface); color: var(--text); border-color: var(--border); }
  .btn-ghost:hover { border-color: var(--text-3); }
  .btn-wa { background: var(--wa); color: #fff; }
  .btn-wa:hover { background: #178f47; }
  .btn-sm { height: 38px; padding: 0 15px; font-size: 14px; }

  /* Héros */
  .hero { padding: 72px 0 40px; text-align: center; position: relative; overflow: hidden; }
  .hero::before { content: ""; position: absolute; inset: -40% -10% auto; height: 640px; background: radial-gradient(closest-side, color-mix(in srgb, var(--accent) 16%, transparent), transparent); z-index: -1; }
  .badge { display: inline-flex; align-items: center; gap: 8px; padding: 5px 13px; border-radius: 20px; background: var(--surface); border: 1px solid var(--border); font-size: 13px; color: var(--text-2); font-weight: 500; }
  .badge i { width: 7px; height: 7px; border-radius: 50%; background: var(--green); box-shadow: 0 0 0 3px color-mix(in srgb, var(--green) 22%, transparent); }
  h1 { font-size: clamp(34px, 5.6vw, 60px); line-height: 1.07; letter-spacing: -.03em; margin: 20px auto 18px; max-width: 860px; }
  h1 em { font-style: normal; background: linear-gradient(90deg, var(--accent), #00a0c6); -webkit-background-clip: text; background-clip: text; color: transparent; }
  .lead { font-size: clamp(17px, 2vw, 20px); color: var(--text-2); max-width: 700px; margin: 0 auto 30px; }
  .cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
  .cta .btn { height: 52px; padding: 0 26px; font-size: 16px; }
  .meta { margin-top: 20px; font-size: 13.5px; color: var(--text-3); display: flex; gap: 6px 16px; justify-content: center; flex-wrap: wrap; }
  .meta b { color: var(--text); font-variant-numeric: tabular-nums; }
  .shot { margin: 52px auto 0; max-width: 1060px; border-radius: 14px; border: 1px solid var(--border); background: var(--surface); box-shadow: 0 30px 80px rgba(16, 24, 40, .18); overflow: hidden; }
  .shot .bar { display: flex; gap: 7px; padding: 11px 14px; border-bottom: 1px solid var(--border); background: var(--surface-2); }
  .shot .bar i { width: 11px; height: 11px; border-radius: 50%; background: var(--border); }

  /* Frameworks */
  .frameworks { padding: 34px 0 10px; text-align: center; }
  .frameworks p { color: var(--text-3); font-size: 13.5px; text-transform: uppercase; letter-spacing: .08em; font-weight: 600; margin: 0 0 18px; }
  .fw-row { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
  .fw-chip { display: inline-flex; align-items: center; gap: 9px; padding: 9px 15px; border-radius: 30px; background: var(--surface); border: 1px solid var(--border); font-weight: 600; font-size: 14.5px; transition: transform .15s, box-shadow .15s; }
  .fw-chip:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
  .fw { width: 22px; height: 22px; flex: none; color: var(--text); }

  /* Chiffres */
  .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin: 56px auto 0; }
  .stat { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; text-align: center; }
  .stat b { display: block; font-size: 32px; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
  .stat span { color: var(--text-2); font-size: 14px; }

  /* Sections */
  section { padding: 88px 0; }
  .eyebrow { color: var(--accent); font-weight: 700; font-size: 13.5px; letter-spacing: .08em; text-transform: uppercase; }
  h2 { font-size: clamp(28px, 3.6vw, 40px); line-height: 1.15; letter-spacing: -.02em; margin: 8px 0 14px; }
  .sub { color: var(--text-2); font-size: 18px; max-width: 680px; margin: 0; }
  .center { text-align: center; } .center .sub { margin: 0 auto; }

  .feature { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr); gap: 56px; align-items: center; margin-top: 80px; }
  .feature:nth-of-type(even) { grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); }
  .feature:nth-of-type(even) .f-text { order: 2; }
  .f-icon { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; background: var(--accent-weak); color: var(--accent); margin-bottom: 16px; }
  .f-icon svg { width: 24px; height: 24px; }
  .feature h3 { font-size: 26px; letter-spacing: -.015em; line-height: 1.2; margin: 0 0 12px; }
  .feature p { color: var(--text-2); margin: 0 0 16px; }
  .feature ul { margin: 0; padding: 0; list-style: none; display: grid; gap: 9px; }
  .feature li { position: relative; padding-left: 28px; }
  .feature li::before { content: ""; position: absolute; left: 0; top: 3px; width: 18px; height: 18px; border-radius: 50%; background: var(--accent-weak) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230373bb' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12.5l4.5 4.5L19 7.5'/%3E%3C/svg%3E") center / 11px no-repeat; }
  .f-shot { border-radius: 12px; border: 1px solid var(--border); overflow: hidden; box-shadow: var(--shadow); background: var(--surface); }
  .f-shot img { width: 100%; height: auto; }

  .grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 44px; }
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 26px; transition: transform .2s, box-shadow .2s; }
  .card:hover { transform: translateY(-3px); box-shadow: var(--shadow); }
  .card h3 { margin: 14px 0 8px; font-size: 18.5px; }
  .card p { margin: 0; color: var(--text-2); font-size: 15px; }
  .step-n { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; background: var(--accent); color: #fff; font-weight: 700; }

  .alt { background: var(--surface); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }

  /* Avis */
  .review { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr); gap: 48px; margin-top: 44px; align-items: start; }
  .contact-card { display: grid; gap: 12px; }
  .contact-item { display: flex; gap: 14px; align-items: center; padding: 16px 18px; border-radius: var(--radius); background: var(--bg); border: 1px solid var(--border); text-decoration: none; color: var(--text); transition: border-color .15s, transform .15s; }
  .contact-item:hover { border-color: var(--accent); transform: translateX(3px); }
  .contact-item .ic { width: 42px; height: 42px; border-radius: 50%; display: grid; place-items: center; flex: none; color: #fff; }
  .contact-item .ic svg { width: 22px; height: 22px; }
  .contact-item b { display: block; }
  .contact-item span { color: var(--text-2); font-size: 14px; }
  form.avis { background: var(--bg); border: 1px solid var(--border); border-radius: 16px; padding: 26px; display: grid; gap: 16px; }
  .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  label.f { display: grid; gap: 6px; font-weight: 600; font-size: 14px; }
  label.f small { font-weight: 400; color: var(--text-3); }
  input, textarea { font: inherit; font-size: 15px; color: var(--text); background: var(--surface); border: 1px solid var(--border); border-radius: 9px; padding: 10px 12px; width: 100%; transition: border-color .15s, box-shadow .15s; }
  input:focus, textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 20%, transparent); }
  textarea { min-height: 120px; resize: vertical; }
  .stars { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 4px; }
  .stars input { position: absolute; opacity: 0; width: 0; height: 0; }
  .stars label { cursor: pointer; font-size: 30px; line-height: 1; color: var(--border); transition: color .12s, transform .12s; }
  .stars label:hover, .stars label:hover ~ label, .stars input:checked ~ label { color: #f5a623; }
  .stars label:hover { transform: scale(1.12); }
  .stars input:focus-visible + label { outline: 2px solid var(--accent); border-radius: 4px; }
  .send { display: flex; gap: 10px; flex-wrap: wrap; }
  .send .btn { flex: 1; min-width: 200px; }
  .hp { position: absolute; left: -9999px; }
  .notice { padding: 12px 14px; border-radius: 10px; font-size: 14.5px; display: none; }
  .notice.show { display: block; animation: rise .3s ease-out; }
  .notice.ok { background: color-mix(in srgb, var(--green) 14%, var(--surface)); color: var(--text); border: 1px solid color-mix(in srgb, var(--green) 40%, transparent); }
  .notice.err { background: color-mix(in srgb, var(--red) 12%, var(--surface)); border: 1px solid color-mix(in srgb, var(--red) 35%, transparent); }

  /* FAQ */
  .faq { max-width: 780px; margin: 40px auto 0; display: grid; gap: 10px; }
  details { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 0 20px; }
  summary { cursor: pointer; padding: 17px 0; font-weight: 600; list-style: none; display: flex; justify-content: space-between; gap: 16px; }
  summary::-webkit-details-marker { display: none; }
  summary::after { content: "+"; font-size: 22px; line-height: 1; color: var(--text-3); transition: transform .2s; }
  details[open] summary::after { transform: rotate(45deg); }
  details p { margin: 0 0 18px; color: var(--text-2); }
  code.sha { display: inline-block; margin-top: 6px; font-size: 12.5px; word-break: break-all; padding: 6px 9px; border-radius: 8px; background: var(--surface-2); border: 1px solid var(--border); }

  /* Appel final + pied */
  .final { text-align: center; padding: 70px 0; background: linear-gradient(135deg, #025e99, #0373bb 55%, #0a8fc9); color: #fff; }
  .final h2 { color: #fff; }
  .final p { color: rgba(255, 255, 255, .85); font-size: 18px; margin: 0 0 28px; }
  .final .btn-primary { background: #fff; color: #025e99; box-shadow: 0 10px 30px rgba(0, 0, 0, .2); }
  .final .btn-primary:hover { background: #eef6fc; }
  .final .meta, .final .meta b { color: rgba(255, 255, 255, .85); }
  .final-cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
  .final-cta .btn { height: 54px; padding: 0 28px; font-size: 16px; }
  .btn-outline-light { color: #fff; border-color: rgba(255, 255, 255, .55); background: transparent; }
  .btn-outline-light:hover { background: rgba(255, 255, 255, .12); border-color: #fff; }
  footer { padding: 34px 0; border-top: 1px solid var(--border); color: var(--text-3); font-size: 14px; }
  footer .wrap { display: flex; gap: 16px; justify-content: space-between; flex-wrap: wrap; align-items: center; }
  footer .links { display: flex; flex-wrap: wrap; gap: 8px 18px; }
  footer a { color: var(--text-2); text-decoration: none; }
  footer a:hover { color: var(--accent); }

  /* Haut de page en 3D */
  /* Deux colonnes : texte à gauche, scène 3D à droite (sur mobile, la scène passe derrière le texte) */
  .hero-top { position: relative; width: min(1180px, 100% - 40px); margin: 0 auto; min-height: min(700px, calc(100vh - 68px)); display: grid; grid-template-columns: 1.02fr 1fr; gap: 10px; align-items: center; text-align: left; }
  /* Espacements fixes du haut de page : badge, compte à rebours, titre, texte, boutons, informations */
  .hero-top h1 { margin: 22px 0 18px; font-size: clamp(34px, 4.8vw, 60px); }
  .hero-top .lead { margin: 0 0 32px; }
  .hero-top .countdown + h1 { margin-top: 18px; }
  .hero-top .meta { margin-top: 26px; gap: 10px; }
  .hero-top .meta > span { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; background: var(--surface); border: 1px solid var(--border); line-height: 1.3; }
  .hero-top .meta > span::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--green); flex: none; }
  .hero-top .meta a { color: var(--accent); text-decoration: none; font-weight: 500; }
  .hero-top .cta { gap: 14px; }
  .hero-top .cta, .hero-top .meta { justify-content: flex-start; }
  [data-scene] { position: relative; order: 2; height: min(620px, 72vh); z-index: 0; pointer-events: none; opacity: 0; transition: opacity 1s ease; }
  [data-scene].ready { opacity: 1; }
  .gsap [data-scene].ready { transition: none; }
  [data-scene] canvas { width: 100% !important; height: 100% !important; display: block; }
  .hero-copy { position: relative; z-index: 1; padding: 20px 0; }
  h1 .ln { display: block; overflow: hidden; padding-bottom: .06em; }
  h1 .ln > span, h1 .ln > em { display: inline-block; }
  .hero .wrap, .feature, .grid3, .alt > .wrap { perspective: 1400px; }
  .shot, .f-shot { transform-style: preserve-3d; will-change: transform; }
  .scroll-hint { position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%); z-index: 1; width: 24px; height: 38px; border: 2px solid var(--text-3); border-radius: 14px; opacity: .6; }
  .scroll-hint::after { content: ""; position: absolute; left: 50%; top: 7px; width: 4px; height: 8px; margin-left: -2px; border-radius: 2px; background: var(--text-3); animation: wheel 1.8s ease-in-out infinite; }
  @keyframes wheel { 0% { opacity: 0; transform: translateY(0); } 30% { opacity: 1; } 100% { opacity: 0; transform: translateY(12px); } }

  /* Choix du téléchargement */
  .alt-dl { margin-top: 14px; font-size: 14.5px; color: var(--text-2); }
  .alt-dl a { font-weight: 600; }
  .alt-dl.light, .alt-dl.light a { color: rgba(255, 255, 255, .9); }
  .dl-choices { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; max-width: 920px; margin: 40px auto 0; }
  .dl-card { position: relative; display: flex; flex-direction: column; gap: 16px; padding: 28px; border-radius: 18px; background: var(--surface); border: 1px solid var(--border); }
  .dl-card.recommended { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent), var(--shadow); }
  .dl-badge { position: absolute; top: -12px; left: 24px; padding: 3px 12px; border-radius: 12px; background: var(--accent); color: #fff; font-size: 12.5px; font-weight: 700; }
  .dl-head { display: flex; gap: 14px; align-items: center; }
  .dl-head h3 { margin: 0; font-size: 20px; }
  .dl-head > div > span { color: var(--text-3); font-size: 13px; word-break: break-all; }
  .dl-ext { flex: none; width: 54px; height: 54px; border-radius: 14px; display: grid; place-items: center; font-weight: 800; font-size: 15px; color: #fff; background: linear-gradient(135deg, #0373bb, #00a0c6); }
  .dl-ext.zip { background: linear-gradient(135deg, #f7941d, #e8660b); }
  .dl-card ul { margin: 0; padding: 0; list-style: none; display: grid; gap: 9px; flex: 1; }
  .dl-card li { position: relative; padding-left: 26px; color: var(--text-2); font-size: 15px; }
  .dl-card li::before { content: "✓"; position: absolute; left: 0; top: 0; color: var(--green); font-weight: 700; }
  .dl-card .btn { align-self: stretch; }
  .dl-note { margin: 22px 0 0; }
  @media (max-width: 720px) { .dl-choices { grid-template-columns: 1fr; } }

  /* Compte à rebours de lancement */
  .countdown { margin: 18px 0 4px; display: flex; flex-direction: column; gap: 8px; width: max-content; max-width: 100%; }
  .countdown.pulse .cd-boxes span { animation: cd-pulse .6s ease-out 2; }
  @keyframes cd-pulse { 50% { transform: scale(1.08); border-color: var(--accent); } }
  .cd-label { font-size: 13.5px; font-weight: 600; color: var(--accent); }
  .cd-boxes { display: flex; gap: 8px; }
  .cd-boxes span { min-width: 64px; padding: 8px 6px 6px; border-radius: 12px; background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow); text-align: center; }
  .cd-boxes b { display: block; font-size: 26px; line-height: 1.1; font-variant-numeric: tabular-nums; letter-spacing: -.02em; }
  .cd-boxes i { font-style: normal; font-size: 11.5px; color: var(--text-3); text-transform: uppercase; letter-spacing: .05em; }
  .cd-boxes b.tick { animation: tick .35s ease-out; }
  @keyframes tick { from { transform: translateY(-6px); opacity: .3; } }
  @media (max-width: 900px) { .countdown { align-items: center; margin-left: auto; margin-right: auto; } }

  /* Communauté */
  .community { padding: 40px 0 88px; }
  .community-card { position: relative; overflow: hidden; display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 40px; align-items: center; padding: 52px; border-radius: 24px; color: #fff;
    background: radial-gradient(circle at 85% 20%, rgba(255, 255, 255, .16), transparent 45%), linear-gradient(135deg, #075e54 0%, #128c4f 55%, #25d366 120%); box-shadow: 0 30px 80px rgba(7, 94, 84, .35); }
  .community-card::after { content: ""; position: absolute; inset: 0; background-image: radial-gradient(rgba(255, 255, 255, .12) 1px, transparent 1px); background-size: 22px 22px; mask-image: linear-gradient(90deg, transparent, #000 60%); pointer-events: none; }
  .community-card > * { position: relative; z-index: 1; }
  .community .eyebrow { color: #c9ffe1; }
  .community h2 { color: #fff; margin-top: 10px; }
  .community p { color: rgba(255, 255, 255, .88); font-size: 17.5px; margin: 0 0 22px; }
  .community-perks { list-style: none; margin: 0 0 30px; padding: 0; display: grid; gap: 11px; }
  .community-perks li { display: flex; gap: 12px; align-items: flex-start; }
  .community-perks li::before { content: "✓"; flex: none; width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; font-size: 13px; font-weight: 700; background: rgba(255, 255, 255, .18); }
  .btn-white { background: #fff; color: #075e54; box-shadow: 0 10px 30px rgba(0, 0, 0, .2); height: 54px; padding: 0 28px; font-size: 16px; }
  .btn-white:hover { background: #eafff2; }
  .community small { display: block; margin-top: 12px; color: rgba(255, 255, 255, .75); font-size: 13.5px; }
  .community-orb { position: relative; width: min(290px, 70vw); aspect-ratio: 1; margin: 0 auto; display: grid; place-items: center; }
  .community-orb .core { width: 58%; aspect-ratio: 1; border-radius: 50%; display: grid; place-items: center; background: rgba(255, 255, 255, .16); border: 1px solid rgba(255, 255, 255, .35); backdrop-filter: blur(6px); box-shadow: inset 0 0 40px rgba(255, 255, 255, .15); }
  .community-orb .core svg { width: 46%; height: 46%; }
  .community-orb .ring { position: absolute; inset: 21%; border-radius: 50%; border: 2px solid rgba(255, 255, 255, .45); }
  .community-orb .bubble { position: absolute; display: flex; align-items: center; gap: 8px; padding: 8px 13px; border-radius: 14px 14px 14px 4px; background: #fff; color: #15191d; font-size: 13px; font-weight: 600; box-shadow: 0 10px 24px rgba(0, 0, 0, .18); white-space: nowrap; }
  .community-orb .bubble i { width: 8px; height: 8px; border-radius: 50%; background: #25d366; }
  .community-orb .b1 { top: 4%; left: -6%; } .community-orb .b2 { bottom: 12%; right: -10%; border-radius: 14px 14px 4px 14px; } .community-orb .b3 { bottom: -2%; left: 4%; }

  /* Invitation après téléchargement */
  .invite { position: fixed; right: 20px; bottom: 20px; z-index: 50; width: min(380px, calc(100vw - 40px)); padding: 18px 18px 16px; border-radius: 16px; background: var(--surface); border: 1px solid var(--border); box-shadow: 0 24px 60px rgba(16, 24, 40, .25); display: none; }
  .invite.show { display: block; animation: rise .4s cubic-bezier(.2, .8, .2, 1); }
  .invite b { display: block; font-size: 16px; margin: 2px 26px 4px 0; }
  .invite p { margin: 0 0 14px; color: var(--text-2); font-size: 14.5px; }
  .invite .btn { width: 100%; }
  .invite .x { position: absolute; top: 10px; right: 10px; width: 30px; height: 30px; border: 0; border-radius: 8px; background: none; color: var(--text-3); font-size: 20px; cursor: pointer; }
  .invite .x:hover { background: var(--surface-2); color: var(--text); }

  /* Apparition au défilement (le contenu reste visible sans JavaScript) */
  .js .reveal { opacity: 0; transform: translateY(22px); transition: opacity .6s ease, transform .6s cubic-bezier(.2, .8, .2, 1); }
  .js .reveal.in { opacity: 1; transform: none; }
  .gsap .reveal { opacity: 1; transform: none; transition: none; }
  .bump { animation: bump .5s ease; }
  @keyframes bump { 40% { transform: scale(1.25); color: var(--accent); } }
  @keyframes rise { from { opacity: 0; transform: translateY(6px); } }
  @media (prefers-reduced-motion: reduce) { *, .js .reveal { transition: none !important; animation: none !important; } .js .reveal { opacity: 1; transform: none; } html { scroll-behavior: auto; } }

  @media (max-width: 900px) {
    .nav a.link { display: none; }
    .feature, .feature:nth-of-type(even), .review { grid-template-columns: 1fr; gap: 28px; }
    .feature:nth-of-type(even) .f-text { order: 0; }
    .grid3, .stats { grid-template-columns: 1fr 1fr; }
    .community-card { grid-template-columns: 1fr; padding: 36px 26px; }
    .hero-top { grid-template-columns: 1fr; text-align: center; min-height: 0; padding: 30px 0 10px; }
    .hero-top .cta, .hero-top .meta { justify-content: center; }
    .hero-top .lead { margin: 0 auto 30px; }
    .hero-top h1 { margin: 20px auto 16px; }
    .hero-top .meta { gap: 8px; }
    [data-scene] { order: 0; height: 300px; margin: -10px -20px -20px; }
    .scroll-hint { display: none; }
    .community-orb { width: 220px; }
    section { padding: 64px 0; }
  }
  @media (max-width: 560px) {
    .grid3, .stats, .row2 { grid-template-columns: 1fr; }
    .nav .btn span { display: none; }
    .hero { padding-top: 24px; }
    .cta, .final-cta { flex-direction: column; align-items: stretch; }
    .cta .btn, .final-cta .btn, .btn-white, .send .btn { width: 100%; white-space: normal; text-align: center; height: auto; min-height: 50px; padding: 12px 18px; line-height: 1.3; }
    .send .btn { min-width: 0; }
    .community-card { padding: 30px 20px; border-radius: 18px; }
    .community-orb { width: 200px; margin-top: 10px; }
    .community-orb .b1 { top: -4%; left: -18%; } .community-orb .b2 { bottom: 20%; right: -22%; } .community-orb .b3 { bottom: -12%; left: -6%; }
    .community-orb .bubble { font-size: 12px; padding: 6px 10px; }
    form.avis { padding: 20px 16px; }
    .stat b { font-size: 28px; }
    h2 { font-size: 27px; }
    section { padding: 56px 0; }
    .wrap { width: calc(100% - 32px); }
    .hero-top { width: calc(100% - 32px); }
  }
</style>
</head>
<body>
<script>document.documentElement.className = 'js';</script>
<div class="tricolor" aria-hidden="true"><i></i><i></i><i></i></div>
<header class="top">
  <nav class="wrap nav" aria-label="Navigation principale">
    <a class="brand" href="#"><img src="assets/logo.svg" alt="" width="32" height="32"><div><b>DevLauncher</b><span>par Novatech Solution</span></div></a>
    <a class="link" href="#fonctionnalites">Fonctionnalités</a>
    <a class="link" href="#telecharger">Télécharger</a>
    <a class="link" href="#etapes">Installation</a>
    <a class="link" href="#avis">Avis</a>
    <a class="link" href="#communaute">Communauté</a>
    <a class="link" href="#faq">Questions</a>
    <a class="link" href="guide.php">Guide</a>
    <a class="btn btn-primary btn-sm" href="#telecharger">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg><span>Télécharger</span></a>
  </nav>
</header>

<main>
  <div class="hero">
    <div class="hero-top">
    <div class="three-host" data-scene aria-hidden="true"></div>
    <div class="hero-copy">
      <span class="badge"><i></i>Version <?= e($CONFIG['version']) ?> · Windows 10 et 11</span>
      <?php if ($prelaunch): ?>
      <div class="countdown" data-launch="<?= (int) $launchTs * 1000 ?>" role="timer" aria-label="Compte à rebours du lancement">
        <span class="cd-label">Lancement officiel le <?= e($launchFr) ?></span>
        <div class="cd-boxes">
          <span><b data-cd="d">--</b><i>jours</i></span><span><b data-cd="h">--</b><i>heures</i></span><span><b data-cd="m">--</b><i>min</i></span><span><b data-cd="s">--</b><i>s</i></span>
        </div>
      </div>
      <?php endif; ?>
      <h1><span class="ln"><span>Tous tes projets web,</span></span><span class="ln"><span><em>lancés en un clic.</em></span></span></h1>
      <p class="lead">DevLauncher trouve tout seul tes projets Laravel, Next.js, React, Vue... dans XAMPP, Laragon ou WAMP, les lance sur le bon port, et t'explique leurs erreurs en français. Sans taper une seule commande.</p>
      <div class="cta">
        <?php if ($locked): ?>
        <a class="btn btn-primary magnet" href="<?= e($CONFIG['community']) ?>" target="_blank" rel="noopener">Être prévenu du lancement</a>
        <?php else: ?>
        <a class="btn btn-primary magnet" href="<?= $locked ? '#' : 'telecharger.php' ?>" <?= $locked ? 'data-locked' : 'data-download' ?>>
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 5.5 10.4 4.5v7H3zm0 13 7.4 1v-7H3zm8.4 1.2L21 21V12.5h-9.6zm0-15.4v7.2H21V3z"/></svg>
          Télécharger pour Windows</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="#fonctionnalites">Découvrir les fonctionnalités</a>
      </div>
      <?php if ($hasZip && !$locked): ?><div class="alt-dl">ou <a <?= $dl('zip') ?>>la version portable (.zip, <?= $zipMb ?> Mo)</a>, sans installation</div><?php endif; ?>
      <div class="meta">
        <span><?php if ($showCount): ?><b data-count><?= $fmt($downloads) ?></b> téléchargement<?= $downloads > 1 ? 's' : '' ?><?php else: ?><b>Nouveau</b> · version <?= e($CONFIG['version']) ?><?php endif; ?></span>
        <span>Installateur de <?= $sizeMb ?> Mo</span>
        <span><a href="#smartscreen" data-open-smartscreen>Message de Windows à l'ouverture ?</a></span>
        <span>Aucun droit administrateur requis</span>
        <?php if ($CONFIG['signed']): ?><span>Signé par <b><?= e($CONFIG['publisher']) ?></b></span><?php endif; ?>
      </div>
    </div>
    <span class="scroll-hint" aria-hidden="true"></span>
    </div>
    <div class="wrap">
      <div class="shot">
        <div class="bar" aria-hidden="true"><i></i><i></i><i></i></div>
        <img src="assets/captures/projets.png" width="1256" height="708" alt="Fenêtre de DevLauncher : liste des projets détectés avec leur framework, leur port et leur état">
      </div>
    </div>
  </div>

  <div class="frameworks wrap">
    <p>Compatible avec tes frameworks</p>
    <div class="fw-row">
      <?php foreach ($logos as $id => [$name]): ?>
        <span class="fw-chip"><?= $logo($id) ?><?= e($name) ?></span>
      <?php endforeach; ?>
    </div>
    <div class="stats">
      <div class="stat reveal"><b>10</b><span>frameworks pris en charge</span></div>
      <div class="stat reveal"><b>3</b><span>serveurs locaux : XAMPP, Laragon, WAMP</span></div>
      <div class="stat reveal"><b>0</b><span>commande à taper</span></div>
      <?php if ($showCount): ?><div class="stat reveal"><b data-count><?= $fmt($downloads) ?></b><span>téléchargement<?= $downloads > 1 ? 's' : '' ?></span></div>
      <?php else: ?><div class="stat reveal"><b data-static>v<?= e($CONFIG['version']) ?></b><span>nouvelle version</span></div><?php endif; ?>
    </div>
  </div>

  <section id="fonctionnalites">
    <div class="wrap">
      <div class="center reveal">
        <div class="eyebrow">Fonctionnalités</div>
        <h2>Tout ton environnement de développement, dans une seule fenêtre</h2>
        <p class="sub">Fini les terminaux ouverts partout, les ports en conflit et les messages d'erreur incompréhensibles.</p>
      </div>

      <div class="feature reveal">
        <div class="f-text">
          <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4.5v15l12-7.5z"/></svg></div>
          <h3>Tes projets détectés et lancés automatiquement</h3>
          <p>DevLauncher parcourt tes serveurs locaux et reconnaît chaque projet. Un clic suffit pour le lancer, l'arrêter ou l'ouvrir dans le navigateur.</p>
          <ul>
            <li>Un port libre attribué à chaque projet, reporté tout seul dans le <code>.env</code></li>
            <li>La bonne version de PHP choisie pour chaque projet Laravel</li>
            <li>Les projets lancés à la main sont repérés eux aussi</li>
          </ul>
        </div>
        <div class="f-shot"><img src="assets/captures/projets.png" loading="lazy" width="1256" height="708" alt="Vue d'ensemble d'un projet Laravel dans DevLauncher"></div>
      </div>

      <div class="feature reveal">
        <div class="f-text">
          <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></div>
          <h3>Un nouveau projet, sans une ligne de commande</h3>
          <p>Choisis le framework, sa version et le dossier : DevLauncher télécharge tout, crée la base de données et ajoute le projet à ta liste.</p>
          <ul>
            <li>Laravel 12 à 9, Next.js, Nuxt, React, Vue, Svelte, Angular, Astro, Express, PHP</li>
            <li>Récapitulatif clair avant la création, progression en direct</li>
          </ul>
        </div>
        <div class="f-shot"><img src="assets/captures/nouveau-projet.png" loading="lazy" width="1256" height="768" alt="Assistant de création d'un nouveau projet avec les logos des frameworks"></div>
      </div>

      <div class="feature reveal">
        <div class="f-text">
          <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5l9.5 16.5h-19z"/><path d="M12 10v4.5M12 17.5v.01"/></svg></div>
          <h3>Des erreurs enfin compréhensibles</h3>
          <p>Les erreurs de Laravel et de la console sont regroupées, avec le fichier concerné et une explication en français de ce qu'il faut faire.</p>
          <ul>
            <li>Lecture des logs avec filtres et recherche</li>
            <li>Les migrations s'exécutent toujours sur le bon serveur MySQL</li>
          </ul>
        </div>
        <div class="f-shot"><img src="assets/captures/erreurs.png" loading="lazy" width="1256" height="708" alt="Liste des erreurs d'un projet avec explications"></div>
      </div>

      <div class="feature reveal">
        <div class="f-text">
          <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5l8.5 6.5 8.5-6.5"/></svg></div>
          <h3>Une boîte mail de test intégrée</h3>
          <p>Les e-mails envoyés par tes projets sont interceptés : tu les vois exactement comme tes utilisateurs, sur ordinateur, tablette ou mobile, sans qu'ils partent vraiment.</p>
          <ul>
            <li>Pièces jointes ouvertes directement dans l'application</li>
            <li>File d'attente des e-mails Laravel : envoi, relance, suppression</li>
          </ul>
        </div>
        <div class="f-shot"><img src="assets/captures/boite-mail.png" loading="lazy" width="1256" height="708" alt="Boîte mail de test affichant un e-mail capturé"></div>
      </div>

      <div class="feature reveal">
        <div class="f-text">
          <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/></svg></div>
          <h3>Diagrammes UML générés depuis ton code</h3>
          <p>Classes, séquences et activités, rédigés en langage courant : présentables à un client ou à un jury, exportés en PNG ou en PDF.</p>
          <ul>
            <li>Génération de tous les diagrammes d'un projet en une fois</li>
            <li>Version technique disponible pour les développeurs</li>
          </ul>
        </div>
        <div class="f-shot"><img src="assets/captures/uml.png" loading="lazy" alt="Diagramme de séquence généré en langage courant"></div>
      </div>
    </div>
  </section>

  <section class="alt">
    <div class="wrap">
      <div class="center reveal">
        <div class="eyebrow">Et aussi</div>
        <h2>Pensé pour ne jamais te laisser bloqué</h2>
      </div>
      <div class="grid3">
        <div class="card reveal"><div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/></svg></div>
          <h3>Serveur réparé en un clic</h3><p>MySQL ou Apache ne démarre plus ? Le diagnostic trouve la cause (port occupé, tables abîmées...) et propose la réparation.</p></div>
        <div class="card reveal"><div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8M10 12h4"/></svg></div>
          <h3>Sauvegardes automatiques</h3><p>Toutes tes bases sauvegardées chaque jour, restaurables en un clic, avec une copie de sécurité avant chaque restauration.</p></div>
        <div class="card reveal"><div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.6 2.6 0 0 1 5 .8c0 1.7-2.5 2.2-2.5 3.7M12 17v.01"/></svg></div>
          <h3>Guide intégré</h3><p>Bloqué ? Le bouton Aide ou la touche F1 ouvre le guide directement sur la bonne section, avec une recherche.</p></div>
        <div class="card reveal"><div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg></div>
          <h3>Seeders sous contrôle</h3><p>Vois tous les seeders d'un projet Laravel, ce que chacun remplit et son code, puis lance ceux que tu veux, dans l'ordre que tu choisis.</p></div>
        <div class="card reveal"><div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/></svg></div>
          <h3>Tous tes projets retrouvés</h3><p>Bureau, Documents, OneDrive, autres disques : DevLauncher retrouve tes projets où qu'ils soient, même ceux lancés à la main hors des serveurs.</p></div>
        <div class="card reveal"><div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg></div>
          <h3>Sites HTML en un clic</h3><p>Un simple dossier avec un index.html se lance aussi, et la page se recharge toute seule à chaque modification de fichier.</p></div>
      </div>
      <div class="f-shot reveal" style="margin-top:28px"><img src="assets/captures/guide.png" loading="lazy" width="1256" height="768" alt="Guide d'utilisation intégré à DevLauncher"></div>
    </div>
  </section>

  <section id="telecharger" class="alt">
    <div class="wrap">
      <div class="center reveal">
        <div class="eyebrow">Télécharger</div>
        <h2>Choisis ta version</h2>
        <p class="sub">Les deux contiennent le même DevLauncher <?= e($CONFIG['version']) ?>, pour Windows 10 et 11.</p>
      </div>
      <div class="dl-choices">
        <div class="dl-card reveal recommended">
          <span class="dl-badge">Recommandé</span>
          <div class="dl-head"><span class="dl-ext">.exe</span><div><h3>Installateur</h3><span><?= $sizeMb ?> Mo · DevLauncher-Setup-<?= e($CONFIG['version']) ?>.exe</span></div></div>
          <ul>
            <li>Raccourcis dans le menu Démarrer et sur le Bureau</li>
            <li>Démarrage avec Windows et mises à jour simples</li>
            <li>Désinstallation depuis Paramètres &gt; Applications</li>
            <li>Aucun droit administrateur demandé</li>
          </ul>
          <a class="btn btn-primary" <?= $dl('exe') ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>Télécharger l'installateur</a>
        </div>
        <div class="dl-card reveal">
          <div class="dl-head"><span class="dl-ext zip">.zip</span><div><h3>Version portable</h3><span><?= $zipMb ?> Mo · DevLauncher-<?= e($CONFIG['version']) ?>-portable.zip</span></div></div>
          <ul>
            <li>Rien à installer : décompresse, puis lance DevLauncher.exe</li>
            <li>Fonctionne depuis une clé USB ou un dossier de ton choix</li>
            <li>Pratique sur un PC où tu ne veux rien installer</li>
            <li>Pour supprimer : efface simplement le dossier</li>
          </ul>
          <?php if ($hasZip): ?><a class="btn btn-ghost" <?= $dl('zip') ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>Télécharger le .zip</a>
          <?php else: ?><span class="btn btn-ghost" aria-disabled="true">Bientôt disponible</span><?php endif; ?>
        </div>
      </div>
      <p class="center faint small dl-note">Windows peut afficher un avertissement à l'ouverture : <a href="#smartscreen" data-open-smartscreen>voici pourquoi et quoi faire</a>.</p>
    </div>
  </section>

  <section id="etapes">
    <div class="wrap">
      <div class="center reveal">
        <div class="eyebrow">Installation</div>
        <h2>Prêt en moins d'une minute</h2>
        <p class="sub">Un seul fichier, comme XAMPP. Rien d'autre à installer pour l'utiliser.</p>
      </div>
      <div class="grid3">
        <div class="card reveal"><div class="step-n">1</div><h3>Télécharge</h3><p>L'installateur (<?= $sizeMb ?> Mo), ou la version portable .zip si tu ne veux rien installer.</p></div>
        <div class="card reveal"><div class="step-n">2</div><h3>Installe</h3><p>Double-clique, choisis le dossier : aucun droit administrateur n'est demandé. Une présentation des fonctionnalités t'accompagne.</p></div>
        <div class="card reveal"><div class="step-n">3</div><h3>Lance</h3><p>DevLauncher démarre, trouve tes serveurs et tes projets. Choisis-en un et clique sur Lancer.</p></div>
      </div>
    </div>
  </section>

  <section id="avis" class="alt">
    <div class="wrap">
      <div class="center reveal">
        <div class="eyebrow">Ton avis compte</div>
        <h2>Dis-nous ce que tu en penses</h2>
        <p class="sub">Une idée, un bug, un compliment ? Ton message arrive directement chez Novatech Solution, par e-mail ou sur WhatsApp.</p>
      </div>
      <div class="review">
        <div class="contact-card reveal">
          <a class="contact-item" href="<?= e($waHello) ?>" target="_blank" rel="noopener">
            <span class="ic" style="background:var(--wa)"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.8.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6l.4-.5c.2-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3.1 4.9 4.3.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.6-.1 1.7-.7 1.9-1.4.2-.7.2-1.2.2-1.4-.1-.1-.3-.2-.6-.3zM12 21.8c-1.8 0-3.5-.5-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4C2.7 15.6 2.2 13.8 2.2 12 2.2 6.6 6.6 2.2 12 2.2c2.6 0 5.1 1 6.9 2.9 1.8 1.8 2.9 4.3 2.9 6.9 0 5.4-4.4 9.8-9.8 9.8zm8.4-18.2C18.1 1.3 15.2.1 12 .1 5.5.1.1 5.4.1 12c0 2.1.5 4.1 1.6 5.9L0 24l6.3-1.7c1.7.9 3.7 1.4 5.7 1.4 6.6 0 11.9-5.3 11.9-11.9 0-3.2-1.2-6.2-3.5-8.4z"/></svg></span>
            <div><b>WhatsApp</b><span>+229 66 18 55 95 · réponse rapide</span></div>
          </a>
          <a class="contact-item" href="mailto:<?= e($CONFIG['contact_email']) ?>?subject=<?= rawurlencode('DevLauncher') ?>">
            <span class="ic" style="background:var(--accent)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5l8.5 6.5 8.5-6.5"/></svg></span>
            <div><b>E-mail</b><span><?= e($CONFIG['contact_email']) ?></span></div>
          </a>
          <a class="contact-item" href="https://novatechbenin.com" target="_blank" rel="noopener">
            <span class="ic" style="background:#3c4148"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg></span>
            <div><b>Novatech Solution</b><span>novatechbenin.com</span></div>
          </a>
        </div>

        <form class="avis reveal" method="post" action="avis.php" novalidate>
          <div class="notice <?= $avisState === 'ok' ? 'ok show' : ($avisState === 'enregistre' ? 'ok show' : ($avisState === 'erreur' ? 'err show' : '')) ?>" data-notice role="status">
            <?= $avisState === 'ok' ? 'Merci ! Ton avis a bien été envoyé.' : ($avisState === 'enregistre' ? 'Merci ! Ton avis est enregistré. Tu peux aussi nous l\'envoyer sur WhatsApp.' : ($avisState === 'erreur' ? 'L\'envoi n\'a pas abouti : vérifie les champs ou utilise WhatsApp.' : '')) ?>
          </div>
          <div class="row2">
            <label class="f">Nom<input name="nom" required maxlength="80" autocomplete="name" placeholder="Ton nom"></label>
            <label class="f">E-mail <small>(facultatif, pour te répondre)</small><input name="email" type="email" maxlength="120" autocomplete="email" placeholder="toi@exemple.com"></label>
          </div>
          <div class="f" role="radiogroup" aria-label="Note sur 5">
            <span style="font-weight:600;font-size:14px">Ta note</span>
            <div class="stars">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" name="note" id="n<?= $i ?>" value="<?= $i ?>"><label for="n<?= $i ?>" title="<?= $i ?> sur 5">★</label>
              <?php endfor; ?>
            </div>
          </div>
          <label class="f">Ton avis<textarea name="message" required maxlength="3000" placeholder="Ce qui te plaît, ce qui manque, un problème rencontré..."></textarea></label>
          <input class="hp" name="site" tabindex="-1" autocomplete="off" aria-hidden="true">
          <div class="send">
            <button class="btn btn-primary" type="submit" data-mail>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 3L10 14"/><path d="M21 3l-7 18-4-7-7-4z"/></svg>Envoyer par e-mail</button>
            <a class="btn btn-wa" href="<?= e($waHello) ?>" target="_blank" rel="noopener" data-wa>
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 .1C5.5.1.1 5.4.1 12c0 2.1.5 4.1 1.6 5.9L0 24l6.3-1.7c1.7.9 3.7 1.4 5.7 1.4 6.6 0 11.9-5.3 11.9-11.9S18.6.1 12 .1zm0 21.7c-1.8 0-3.5-.5-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4C2.7 15.6 2.2 13.8 2.2 12 2.2 6.6 6.6 2.2 12 2.2s9.8 4.4 9.8 9.8-4.4 9.8-9.8 9.8z"/></svg>Envoyer par WhatsApp</a>
          </div>
        </form>
      </div>
    </div>
  </section>

  <section id="communaute" class="community">
    <div class="wrap">
      <div class="community-card">
        <div>
          <div class="eyebrow">DevLaunch Community</div>
          <h2>Teste en avant-première les prochains produits de Novatech Solution</h2>
          <p>Rejoins notre communauté WhatsApp : tu découvres nos nouveaux outils avant tout le monde, tu donnes ton avis et tu influences ce que nous construisons.</p>
          <ul class="community-perks">
            <li>Accès aux versions de test de DevLauncher et des futurs logiciels Novatech</li>
            <li>Échanges directs avec l'équipe et les autres développeurs</li>
            <li>Annonces des nouveautés, astuces et corrections en premier</li>
            <li>Tes idées prises en compte dans les prochaines versions</li>
          </ul>
          <a class="btn btn-white magnet" href="<?= e($community) ?>" target="_blank" rel="noopener" data-community>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 .1C5.5.1.1 5.4.1 12c0 2.1.5 4.1 1.6 5.9L0 24l6.3-1.7c1.7.9 3.7 1.4 5.7 1.4 6.6 0 11.9-5.3 11.9-11.9S18.6.1 12 .1zm0 21.7c-1.8 0-3.5-.5-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4C2.7 15.6 2.2 13.8 2.2 12 2.2 6.6 6.6 2.2 12 2.2s9.8 4.4 9.8 9.8-4.4 9.8-9.8 9.8z"/></svg>
            Rejoindre la DevLaunch Community</a>
          <small>Groupe WhatsApp gratuit · tu peux le quitter à tout moment</small>
        </div>
        <div class="community-orb" aria-hidden="true">
          <span class="ring"></span><span class="ring"></span><span class="ring"></span>
          <div class="core"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 19.5c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><circle cx="17.5" cy="9" r="2.4"/><path d="M16 14.3c2.8-.4 5 1.5 5 4.6"/></svg></div>
          <span class="bubble b1"><i></i>Nouvelle version à tester !</span>
          <span class="bubble b2"><i></i>Super, je teste 👍</span>
          <span class="bubble b3"><i></i>Idée : Symfony ?</span>
        </div>
      </div>
    </div>
  </section>

  <section id="faq">
    <div class="wrap">
      <div class="center reveal"><div class="eyebrow">Questions fréquentes</div><h2>Avant de télécharger</h2></div>
      <div class="faq">
        <details class="reveal"><summary>De quoi ai-je besoin pour utiliser DevLauncher ?</summary><p>Windows 10 ou 11 et un serveur local (XAMPP, Laragon ou WAMP) pour les projets PHP. Pour les projets JavaScript (Next.js, React, Vue...), Node.js doit être installé. DevLauncher indique en bas de sa fenêtre ce qu'il a trouvé.</p></details>
        <details class="reveal" id="smartscreen"><summary>Windows affiche « Windows a protégé votre ordinateur » : est-ce normal ?</summary>
          <p><?php if ($CONFIG['signed']): ?>DevLauncher est signé numériquement par <b><?= e($CONFIG['publisher']) ?></b> : Windows affiche ce nom comme éditeur. Le message de SmartScreen peut encore apparaître pendant les premières semaines d'une nouvelle version, le temps que Microsoft constate qu'elle est téléchargée sans problème.<?php else: ?>Oui. Microsoft Defender SmartScreen prévient pour tout logiciel récent qu'il n'a pas encore vu souvent, en particulier quand il n'est pas encore signé numériquement (« Éditeur : Inconnu »). Le message disparaît avec la signature et à mesure que DevLauncher est téléchargé.<?php endif; ?>
            Pour lancer l'installation : clique sur <b>Informations complémentaires</b>, puis sur <b>Exécuter quand même</b>. Dans le navigateur, si le téléchargement est bloqué : <b>…</b> &gt; <b>Conserver</b> &gt; <b>Afficher plus</b> &gt; <b>Conserver quand même</b>.</p>
          <?php if ($sha): ?><p>Pour vérifier que le fichier n'a pas été modifié, compare son empreinte SHA-256 (PowerShell : <code>Get-FileHash .\DevLauncher-Setup-<?= e($CONFIG['version']) ?>.exe</code>) avec celle-ci :<br><code class="sha"><?= e($sha) ?></code></p><?php endif; ?>
          <?php if ($zipSha): ?><p>Version portable (.zip) :<br><code class="sha"><?= e($zipSha) ?></code></p><?php endif; ?>
        </details>
        <details class="reveal"><summary>Installateur ou version portable : laquelle choisir ?</summary><p>L'<b>installateur</b> est conseillé : raccourcis, démarrage avec Windows, désinstallation propre. La <b>version portable</b> (.zip) ne s'installe pas : décompresse-la où tu veux (même sur une clé USB) et lance <code>DevLauncher.exe</code>. Tes réglages sont les mêmes avec les deux.</p></details>
        <details class="reveal"><summary>Faut-il des droits administrateur ?</summary><p>Non. DevLauncher s'installe dans ton dossier utilisateur et se désinstalle depuis Paramètres &gt; Applications, comme n'importe quel logiciel.</p></details>
        <details class="reveal"><summary>Mes projets sont-ils modifiés ?</summary><p>Seulement quand tu le demandes (changer un port, configurer la base de données). Chaque fichier modifié est d'abord copié dans un fichier de sauvegarde.</p></details>
        <details class="reveal"><summary>Est-ce que ça marche sans connexion internet ?</summary><p>Oui pour lancer, déboguer et gérer tes projets. Seule la création d'un nouveau projet télécharge des fichiers depuis internet.</p></details>
        <details class="reveal"><summary>Les e-mails de mes projets partent-ils vraiment ?</summary><p>Non : pour les projets lancés depuis DevLauncher, ils sont interceptés par la boîte de test intégrée. Tu peux désactiver ce comportement projet par projet.</p></details>
        <details class="reveal"><summary>Où trouver le mode d'emploi ?</summary><p>Le <a href="guide.php">guide d'utilisation complet</a> est en ligne, et il est aussi intégré au logiciel : bouton <b>Aide</b> ou touche <b>F1</b>, qui l'ouvrent directement sur la bonne section.</p></details>
        <details class="reveal"><summary>Comment signaler un problème ?</summary><p>Écris-nous avec le formulaire d'avis ci-dessus, par e-mail à <a href="mailto:<?= e($CONFIG['contact_email']) ?>"><?= e($CONFIG['contact_email']) ?></a> ou sur <a href="<?= e($waHello) ?>" target="_blank" rel="noopener">WhatsApp</a>.</p></details>
      </div>
    </div>
  </section>

  <div class="final">
    <div class="wrap reveal">
      <h2>Prêt à gagner du temps sur chaque projet ?</h2>
      <p>Télécharge DevLauncher et lance ton premier projet en un clic.</p>
      <div class="final-cta">
      <a class="btn btn-primary magnet" href="<?= $locked ? '#' : 'telecharger.php' ?>" <?= $locked ? 'data-locked' : 'data-download' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>Télécharger DevLauncher <?= e($CONFIG['version']) ?></a>
      <a class="btn btn-outline-light" href="<?= e($community) ?>" target="_blank" rel="noopener">Rejoindre la communauté</a>
      </div>
      <?php if ($hasZip && !$locked): ?><div class="alt-dl light">ou <a <?= $dl('zip') ?>>la version portable (.zip)</a></div><?php endif; ?>
      <div class="meta"><?php if ($showCount): ?><span><b data-count><?= $fmt($downloads) ?></b> téléchargement<?= $downloads > 1 ? 's' : '' ?></span><?php endif; ?><span>Windows 10 et 11</span></div>
    </div>
  </div>
</main>

<footer>
  <div class="wrap">
    <span>© <?= date('Y') ?> Novatech Solution · Conçu et développé par Hilaire AINADOU</span>
    <span class="links"><a href="https://novatechbenin.com" target="_blank" rel="noopener">novatechbenin.com</a><a href="https://github.com/hilairesevnainadou-gif" target="_blank" rel="noopener">GitHub</a><a href="guide.php">Guide d'utilisation</a><a href="mailto:<?= e($CONFIG['contact_email']) ?>">Contact</a><a href="<?= e($waHello) ?>" target="_blank" rel="noopener">WhatsApp</a><a href="<?= e($community) ?>" target="_blank" rel="noopener">Communauté</a></span>
  </div>
</footer>

<div class="invite" role="dialog" aria-label="Rejoindre la communauté" data-invite>
  <button class="x" type="button" aria-label="Fermer" data-invite-close>×</button>
  <b>Merci, ton téléchargement démarre !</b>
  <p>Rejoins la <strong>DevLaunch Community</strong> pour tester en avant-première les prochains produits de Novatech Solution.</p>
  <a class="btn btn-wa" href="<?= e($community) ?>" target="_blank" rel="noopener">Rejoindre le groupe WhatsApp</a>
</div>

<script id="fw-data" type="application/json"><?= json_encode($fwData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<script src="assets/vendor/gsap.min.js" defer></script>
<script src="assets/vendor/ScrollTrigger.min.js" defer></script>
<script src="assets/js/anim.js" defer></script>
<script type="module" src="assets/js/scene.js"></script>
<script>
(() => {
  // Apparition des sections au défilement, si GSAP n'a pas pris le relais (scripts différés exécutés avant DOMContentLoaded)
  addEventListener('DOMContentLoaded', () => {
    if (document.documentElement.classList.contains('gsap')) return;
    const els = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver(list => list.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { rootMargin: '0px 0px -8% 0px' });
      els.forEach(el => io.observe(el));
    } else els.forEach(el => el.classList.add('in'));
  });

  // Compte à rebours du lancement (la page se recharge à l'heure dite pour proposer le téléchargement)
  const cd = document.querySelector('[data-launch]');
  if (cd) {
    const end = Number(cd.dataset.launch), box = k => cd.querySelector('[data-cd="' + k + '"]'), last = {};
    const tickCd = () => {
      const left = Math.max(0, end - Date.now());
      if (!left) return location.reload();
      const v = { d: Math.floor(left / 864e5), h: Math.floor(left / 36e5) % 24, m: Math.floor(left / 6e4) % 60, s: Math.floor(left / 1e3) % 60 };
      for (const k in v) { const t = String(v[k]).padStart(2, '0'); if (last[k] !== t) { const b = box(k); b.textContent = t; b.classList.remove('tick'); void b.offsetWidth; b.classList.add('tick'); last[k] = t; } }
    };
    tickCd(); setInterval(tickCd, 1000);
  }

  document.querySelectorAll('[data-locked]').forEach(a => a.addEventListener('click', e => {
    e.preventDefault();
    if (!cd) return;
    cd.scrollIntoView({ behavior: 'smooth', block: 'center' });
    cd.classList.remove('pulse'); void cd.offsetWidth; cd.classList.add('pulse');
  }));

  // Lien « Message de Windows » : ouvre directement la réponse de la FAQ
  document.querySelectorAll('[data-open-smartscreen]').forEach(a => a.addEventListener('click', () => { const d = document.getElementById('smartscreen'); if (d) d.open = true; }));

  // Invitation à la communauté, montrée une fois après un téléchargement
  const invite = document.querySelector('[data-invite]');
  const showInvite = () => { invite.classList.add('show'); };
  invite.querySelector('[data-invite-close]').addEventListener('click', () => invite.classList.remove('show'));

  // Compteur : actualisé après un téléchargement (le lien fonctionne aussi sans JavaScript)
  const fmt = n => n.toLocaleString('fr-FR').replace(/ /g, ' ');
  const show = n => document.querySelectorAll('[data-count]').forEach(el => { el.textContent = fmt(n); el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump'); });
  document.querySelectorAll('[data-download]').forEach(a => a.addEventListener('click', async e => {
    e.preventDefault();
    try {
      const r = await fetch(a.getAttribute('href'), { headers: { Accept: 'application/json' }, cache: 'no-store' });
      const d = await r.json();
      if (!r.ok || !d.ok) throw new Error();
      show(d.downloads);
      showInvite();
      location.href = d.url;
    } catch { location.href = a.href; }
  }));

  // Avis : e-mail (sans quitter la page) ou WhatsApp (message prérempli)
  const form = document.querySelector('form.avis');
  const notice = form.querySelector('[data-notice]');
  const say = (kind, text) => { notice.className = 'notice show ' + kind; notice.textContent = text; };
  const values = () => {
    const f = new FormData(form);
    return { nom: (f.get('nom') || '').trim(), email: (f.get('email') || '').trim(), note: Number(f.get('note') || 0), message: (f.get('message') || '').trim() };
  };
  const check = v => (!v.nom ? 'Indique ton nom.' : v.message.length < 5 ? 'Écris ton avis (quelques mots au moins).' : '');
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const v = values(), err = check(v);
    if (err) return say('err', err);
    const btn = form.querySelector('[data-mail]'), label = btn.innerHTML;
    btn.disabled = true; btn.textContent = 'Envoi...';
    try {
      const r = await fetch('avis.php', { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
      const d = await r.json();
      if (!d.ok) throw new Error(d.error || 'Envoi impossible.');
      say('ok', d.mailed ? 'Merci ' + v.nom + ' ! Ton avis a bien été envoyé à Novatech Solution.' : 'Merci ' + v.nom + ' ! Ton avis est enregistré. Pour une réponse rapide, envoie-le aussi sur WhatsApp.');
      if (d.mailed) form.reset();
    } catch (x) { say('err', (x && x.message) || 'Envoi impossible : utilise WhatsApp.'); }
    btn.disabled = false; btn.innerHTML = label;
  });
  form.querySelector('[data-wa]').addEventListener('click', e => {
    const v = values(), err = check(v);
    if (err) { e.preventDefault(); return say('err', err); }
    // Message écrit comme on l'écrirait soi-même sur WhatsApp : simple, sans mise en forme de formulaire.
    const intro = v.note ? `Bonjour, c'est ${v.nom}. J'ai testé DevLauncher, je lui mets ${v.note}/5.` : `Bonjour, c'est ${v.nom}. J'ai testé DevLauncher.`;
    const text = `${intro}\n\n${v.message}`;
    e.currentTarget.href = <?= json_encode($wa) ?> + encodeURIComponent(text);
  });
})();
</script>
</body>
</html>

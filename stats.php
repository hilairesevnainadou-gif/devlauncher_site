<?php
// Nombre de téléchargements (JSON), pour actualiser le compteur affiché sans recharger la page.
require __DIR__ . '/lib.php';
json_out(['downloads' => download_count()]);

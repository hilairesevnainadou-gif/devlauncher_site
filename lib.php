<?php
// Fonctions partagées : compteur de téléchargements, limitation des envois, lecture de l'adresse du visiteur.
$CONFIG = require __DIR__ . '/config.php';

function data_path(string $name): string
{
    global $CONFIG;
    if (!is_dir($CONFIG['data_dir'])) mkdir($CONFIG['data_dir'], 0755, true);
    return $CONFIG['data_dir'] . '/' . $name;
}

// Lecture-modification-écriture d'un fichier JSON sous verrou exclusif (deux téléchargements simultanés ne perdent rien).
function json_update(string $name, callable $change)
{
    $fp = fopen(data_path($name), 'c+');
    if (!$fp) return null;
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) $data = [];
    $result = $change($data);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $result;
}

function download_count(): int
{
    $file = data_path('compteur.json');
    if (!is_file($file)) return 0;
    $data = json_decode((string) file_get_contents($file), true);
    return (int) ($data['total'] ?? 0);
}

// Empreinte anonyme du visiteur (l'adresse IP n'est jamais enregistrée en clair).
function visitor_id(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    return substr(hash('sha256', $ip . '|' . __DIR__), 0, 16);
}

// Compte un téléchargement ; un même visiteur n'est compté qu'une fois par période de 10 minutes.
// type : exe (installateur) ou zip (version portable) ; les deux comptent dans le total.
function count_download(string $type = 'exe'): int
{
    return json_update('compteur.json', function (array &$data) use ($type) {
        $now = time();
        $recent = array_filter($data['recent'] ?? [], fn ($t) => $now - $t < 600);
        $id = visitor_id();
        if (!isset($recent[$id])) {
            $data['total'] = (int) ($data['total'] ?? 0) + 1;
            $day = date('Y-m-d');
            $data['days'][$day] = (int) ($data['days'][$day] ?? 0) + 1;
            $data['types'][$type] = (int) ($data['types'][$type] ?? 0) + 1;
            $recent[$id] = $now;
        }
        $data['recent'] = $recent;
        return (int) $data['total'];
    });
}

// Au plus $max envois par visiteur et par heure.
function allow_send(int $max = 5): bool
{
    return json_update('envois.json', function (array &$data) use ($max) {
        $now = time();
        foreach ($data as $id => $times) {
            $data[$id] = array_values(array_filter($times, fn ($t) => $now - $t < 3600));
            if (!$data[$id]) unset($data[$id]);
        }
        $id = visitor_id();
        if (count($data[$id] ?? []) >= $max) return false;
        $data[$id][] = $now;
        return true;
    }) ?? false;
}

// ---------- Envoi d'e-mails ----------
// Avec SMTP_HOST dans le .env : connexion authentifiée au serveur d'envoi (boîte no-reply de cPanel, Gmail...),
// bien plus fiable que mail(). Sans : fonction mail() de PHP (souvent désactivée ou classée en spam).
// Retourne [true, ''] ou [false, 'raison'].
function send_mail(string $to, string $subject, string $text, string $html = '', string $replyTo = '', string $replyName = ''): array
{
    global $CONFIG;
    $s = $CONFIG['smtp'];
    $from = $CONFIG['from_email'];
    $enc = fn ($v) => '=?UTF-8?B?' . base64_encode($v) . '?=';
    $clean = fn ($v) => str_replace(["\r", "\n"], ' ', (string) $v);

    $boundary = 'dl' . bin2hex(random_bytes(8));
    $headers = [
        'Date: ' . date('r'),
        'From: ' . $enc($s['from_name']) . " <$from>",
        "To: <$to>",
        'Subject: ' . $enc($clean($subject)),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>',
        'MIME-Version: 1.0',
    ];
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: ' . ($replyName !== '' ? $enc($clean($replyName)) . ' ' : '') . "<$replyTo>";
    $b64 = fn ($v) => rtrim(chunk_split(base64_encode($v), 76, "\r\n"));
    if ($html !== '') {
        $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($text) . "\r\n"
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($html) . "\r\n--$boundary--";
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $body = $b64($text);
    }

    if ($s['host'] === '') {
        // Sans serveur SMTP configuré : mail() de PHP (To et Subject sont passés à part)
        $extra = array_values(array_filter($headers, fn ($h) => !preg_match('/^(To|Subject):/i', $h)));
        return @mail($to, $enc($clean($subject)), $body, implode("\r\n", $extra))
            ? [true, ''] : [false, 'mail() de PHP a échoué : configure SMTP_HOST dans le .env'];
    }

    $secure = strtolower($s['secure']);
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $s['host'] . ':' . $s['port'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => $s['verify'], 'verify_peer_name' => $s['verify'], 'SNI_enabled' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return [false, "Connexion à {$s['host']}:{$s['port']} impossible ($errstr)"];
    stream_set_timeout($fp, 20);
    $read = function () use ($fp) {
        $out = '';
        while (($line = fgets($fp, 1024)) !== false) { $out .= $line; if (strlen($line) < 4 || $line[3] === ' ') break; }
        return $out;
    };
    $cmd = function (string $c, array $ok) use ($fp, $read) {
        if ($c !== '') fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int) substr($r, 0, 3), $ok, true)) throw new RuntimeException(trim($r) ?: 'pas de réponse du serveur');
        return $r;
    };
    try {
        $cmd('', [220]);
        $host = gethostname() ?: 'localhost';
        $ehlo = $cmd("EHLO $host", [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('chiffrement TLS refusé');
            $ehlo = $cmd("EHLO $host", [250]);
        }
        if ($s['user'] !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($s['user']), [334]);
            try { $cmd(base64_encode($s['pass']), [235]); }
            catch (RuntimeException $e) { throw new RuntimeException('identifiant ou mot de passe SMTP refusé (' . $e->getMessage() . ')'); }
        }
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);
        // Une ligne commençant par un point est doublée (fin de message SMTP)
        $data = preg_replace('/^\./m', '..', implode("\r\n", $headers) . "\r\n\r\n" . $body);
        $cmd($data . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
        return [true, ''];
    } catch (RuntimeException $e) {
        @fclose($fp);
        return [false, 'SMTP : ' . $e->getMessage()];
    }
}

function wants_json(): bool
{
    return strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
}

function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

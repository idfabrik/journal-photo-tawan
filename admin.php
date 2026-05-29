<?php
// ── CONFIG ─────────────────────────────────────────────────────────────────
$_key_path = '/usr/www/users/idfabr/app-facture/.secret_key';
$_key_raw  = @file_get_contents($_key_path);
define('CLAUDE_API_KEY', $_key_raw !== false ? trim($_key_raw) : '');
define('KEY_STATUS', [
    'path'   => $_key_path,
    'exists' => file_exists($_key_path),
    'loaded' => !empty(CLAUDE_API_KEY),
    'len'    => strlen(CLAUDE_API_KEY),
]);
define('IMG_DIR',        'img/');
define('COMMENTS_FILE',  'comments.json');
define('TAGS_FILE',      'tags.json');
define('HIDDEN_FILE',    'hidden.json');
require_once __DIR__ . '/config.php';

session_start();

// ── AUTH ───────────────────────────────────────────────────────────────────
if (($_POST['action'] ?? '') === 'login') {
    if ($_POST['password'] === PASSWORD) {
        $_SESSION['admin'] = true;
    }
    header('Location: admin.php');
    exit;
}
if (($_POST['action'] ?? '') === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}
$logged = !empty($_SESSION['admin']);

// ── HELPERS ────────────────────────────────────────────────────────────────
function load_comments(): array {
    if (!file_exists(COMMENTS_FILE)) return [];
    return json_decode(file_get_contents(COMMENTS_FILE), true) ?? [];
}
function save_comments(array $c): void {
    file_put_contents(COMMENTS_FILE, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
function load_tags(): array {
    if (!file_exists(TAGS_FILE)) return [];
    return json_decode(file_get_contents(TAGS_FILE), true) ?? [];
}
function save_tags_file(array $t): void {
    file_put_contents(TAGS_FILE, json_encode($t, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
function load_hidden(): array {
    if (!file_exists(HIDDEN_FILE)) return [];
    return json_decode(file_get_contents(HIDDEN_FILE), true) ?? [];
}
function save_hidden(array $h): void {
    file_put_contents(HIDDEN_FILE, json_encode(array_values($h), JSON_PRETTY_PRINT));
}
function load_images(): array {
    $ext = ['jpg','jpeg','png','webp','JPG','JPEG','PNG','WEBP'];
    $imgs = [];
    foreach (scandir(IMG_DIR) as $f) {
        if (in_array(pathinfo($f, PATHINFO_EXTENSION), $ext)) $imgs[] = $f;
    }
    rsort($imgs);
    return $imgs;
}

// ── API ENDPOINTS (JSON) ────────────────────────────────────────────────────
if (isset($_POST['action']) && !in_array($_POST['action'], ['login','logout'])) {
    header('Content-Type: application/json');
    if (!$logged) { echo json_encode(['error' => 'Session expirée — rechargez la page']); exit; }

    // Debug — à supprimer après diagnostic
    if ($_POST['action'] === 'debug_key') {
        $path    = '/usr/www/users/idfabr/app-facture/.secret_key';
        $exists  = file_exists($path);
        $raw     = $exists ? file_get_contents($path) : null;
        $trimmed = $raw !== null ? trim($raw) : null;
        echo json_encode([
            'path'    => $path,
            'exists'  => $exists,
            'length'  => $trimmed !== null ? strlen($trimmed) : 0,
            'preview' => $trimmed ? substr($trimmed, 0, 8) . '...' : '(vide)',
        ]);
        exit;
    }

    // Save comment
    if ($_POST['action'] === 'save_comment') {
        $file    = basename($_POST['file'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $c       = load_comments();
        $key     = pathinfo($file, PATHINFO_FILENAME);
        if ($comment === '') {
            unset($c[$key]);
        } else {
            $c[$key] = $comment;
        }
        save_comments($c);
        // Also write .txt file alongside image
        $txt = IMG_DIR . $key . '.txt';
        if ($comment === '') { if (file_exists($txt)) unlink($txt); }
        else                  file_put_contents($txt, $comment);
        echo json_encode(['ok' => true]);
        exit;
    }

    // Save tags
    if ($_POST['action'] === 'save_tags') {
        $file = basename($_POST['file'] ?? '');
        $raw  = $_POST['tags'] ?? '';
        $tags = array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
        $t    = load_tags();
        if (empty($tags)) unset($t[$file]);
        else $t[$file] = $tags;
        save_tags_file($t);
        echo json_encode(['ok' => true]);
        exit;
    }

    // Toggle hidden
    if ($_POST['action'] === 'toggle_hidden') {
        $file = basename($_POST['file'] ?? '');
        $hide = ($_POST['hide'] ?? '') === '1';
        $h    = load_hidden();
        if ($hide) { if (!in_array($file, $h)) $h[] = $file; }
        else        { $h = array_filter($h, fn($f) => $f !== $file); }
        save_hidden($h);
        echo json_encode(['ok' => true]);
        exit;
    }

    // Rename file
    if ($_POST['action'] === 'rename_file') {
        $old  = basename($_POST['old_file'] ?? '');
        $new  = basename(trim($_POST['new_file'] ?? ''));
        if (!$old || !$new) { echo json_encode(['error' => 'Nom invalide']); exit; }
        if ($old === $new)  { echo json_encode(['ok' => true]); exit; }
        $allowed = ['jpg','jpeg','png','webp'];
        if (!in_array(strtolower(pathinfo($new, PATHINFO_EXTENSION)), $allowed)) {
            echo json_encode(['error' => 'Extension non autorisée']); exit;
        }
        $old_path = IMG_DIR . $old;
        $new_path = IMG_DIR . $new;
        if (!file_exists($old_path))  { echo json_encode(['error' => 'Fichier introuvable']); exit; }
        if (file_exists($new_path))   { echo json_encode(['error' => 'Ce nom existe déjà']); exit; }
        rename($old_path, $new_path);
        $old_key = pathinfo($old, PATHINFO_FILENAME);
        $new_key = pathinfo($new, PATHINFO_FILENAME);
        // .txt
        $old_txt = IMG_DIR . $old_key . '.txt';
        $new_txt = IMG_DIR . $new_key . '.txt';
        if (file_exists($old_txt)) rename($old_txt, $new_txt);
        // thumbs
        foreach ([300, 1200] as $sz) {
            $ot = IMG_DIR . 'thumbs/' . $sz . '/' . $old_key . '.jpg';
            $nt = IMG_DIR . 'thumbs/' . $sz . '/' . $new_key . '.jpg';
            if (file_exists($ot)) rename($ot, $nt);
        }
        // comments.json
        $c = load_comments();
        if (isset($c[$old_key])) { $c[$new_key] = $c[$old_key]; unset($c[$old_key]); save_comments($c); }
        // tags.json
        $t = load_tags();
        if (isset($t[$old])) { $t[$new] = $t[$old]; unset($t[$old]); save_tags_file($t); }
        echo json_encode(['ok' => true, 'new_file' => $new]);
        exit;
    }

    // Delete photo
    if ($_POST['action'] === 'delete') {
        $file = basename($_POST['file'] ?? '');
        $path = IMG_DIR . $file;
        $key  = pathinfo($file, PATHINFO_FILENAME);
        if (file_exists($path)) unlink($path);
        $txt  = IMG_DIR . $key . '.txt';
        if (file_exists($txt)) unlink($txt);
        $c    = load_comments();
        unset($c[$key]);
        save_comments($c);
        $t = load_tags();
        unset($t[$file]);
        save_tags_file($t);
        echo json_encode(['ok' => true]);
        exit;
    }

    // AI generate comment
    if ($_POST['action'] === 'ai_generate') {
        $file = basename($_POST['file'] ?? '');
        $path = IMG_DIR . $file;
        if (!file_exists($path)) { echo json_encode(['error' => 'Fichier introuvable']); exit; }

        $mime    = mime_content_type($path);
        $b64     = base64_encode(file_get_contents($path));
        $prompt  = "Décris ce qui est visible dans cette photographie en 1 à 2 phrases courtes et factuelles. Sujet principal, lieu ou contexte, lumière ou moment si évident. Pas de style littéraire, pas d'interprétation, pas d'émotion suggérée, pas de ponctuation de fin. Langue : français.";

        $payload = json_encode([
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 300,
            'messages'   => [[
                'role'    => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $b64]],
                    ['type' => 'text',  'text'    => $prompt],
                ]
            ]]
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . CLAUDE_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
        ]);
        $res  = curl_exec($ch);
        if (!$res) { echo json_encode(['error' => 'curl: ' . curl_error($ch)]); exit; }
        curl_close($ch);
        $data = json_decode($res, true);
        if (isset($data['error'])) { echo json_encode(['error' => $data['error']['message'] ?? 'API error']); exit; }
        $text = $data['content'][0]['text'] ?? '';
        if (!$text) { echo json_encode(['error' => 'Réponse vide — ' . substr($res, 0, 200)]); exit; }
        echo json_encode(['text' => $text]);
        exit;
    }

    // AI correct comment
    if ($_POST['action'] === 'ai_correct') {
        $comment = trim($_POST['comment'] ?? '');
        if ($comment === '') { echo json_encode(['error' => 'Aucun texte']); exit; }

        $payload = json_encode([
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 300,
            'system'     => "Tu es un correcteur orthographique. Règles absolues : 1) renvoie uniquement le texte corrigé, mot pour mot ; 2) corrige seulement les fautes d'orthographe, d'accord et de grammaire ; 3) ne reformule pas, ne complète pas, ne commente pas ; 4) si le texte est déjà correct, renvoie-le tel quel ; 5) même pour un seul mot, renvoie uniquement ce mot corrigé.",
            'messages'   => [[
                'role'    => 'user',
                'content' => $comment,
            ]]
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . CLAUDE_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
        ]);
        $res  = curl_exec($ch);
        if (!$res) { echo json_encode(['error' => 'curl: ' . curl_error($ch)]); exit; }
        curl_close($ch);
        $data = json_decode($res, true);
        if (isset($data['error'])) { echo json_encode(['error' => $data['error']['message'] ?? 'API error']); exit; }
        $text = $data['content'][0]['text'] ?? '';
        echo json_encode(['text' => $text ?: $comment]);
        exit;
    }

    // Upload
    if ($_POST['action'] === 'upload') {
        $results = [];
        $files   = $_FILES['files'] ?? [];
        $ext_ok  = ['jpg','jpeg','png','webp'];
        for ($i = 0; $i < count($files['name']); $i++) {
            $name = basename($files['name'][$i]);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $ext_ok)) { $results[] = ['name' => $name, 'ok' => false]; continue; }
            $dest = IMG_DIR . $name;
            if (file_exists($dest)) unlink($dest);
            if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                $results[] = ['name' => $name, 'ok' => true];
            } else {
                $results[] = ['name' => $name, 'ok' => false];
            }
        }
        echo json_encode(['results' => $results]);
        exit;
    }

    // Generate thumbnail
    if ($_POST['action'] === 'generate_thumb') {
        $file = basename($_POST['file'] ?? '');
        $size = (int)($_POST['size'] ?? 0);
        if (!$file || !in_array($size, [300, 1200])) { echo json_encode(['error' => 'Paramètres invalides']); exit; }

        $src = IMG_DIR . $file;
        if (!file_exists($src)) { echo json_encode(['error' => 'Fichier introuvable']); exit; }

        $thumb_dir = IMG_DIR . 'thumbs/' . $size . '/';
        if (!is_dir($thumb_dir)) mkdir($thumb_dir, 0755, true);

        $dest = $thumb_dir . pathinfo($file, PATHINFO_FILENAME) . '.jpg';

        if (file_exists($dest) && empty($_POST['force'])) {
            [$w, $h] = getimagesize($dest);
            echo json_encode(['ok' => true, 'skipped' => true, 'w' => $w, 'h' => $h]);
            exit;
        }

        $info = getimagesize($src);
        if (!$info) { echo json_encode(['error' => 'Image invalide']); exit; }

        [$w, $h] = $info;
        $mime    = $info['mime'];

        if (max($w, $h) <= $size) {
            copy($src, $dest);
            echo json_encode(['ok' => true, 'skipped' => true, 'w' => $w, 'h' => $h]);
            exit;
        }

        if ($w >= $h) { $nw = $size; $nh = (int)round($h * $size / $w); }
        else          { $nh = $size; $nw = (int)round($w * $size / $h); }

        switch ($mime) {
            case 'image/jpeg': $src_img = imagecreatefromjpeg($src); break;
            case 'image/png':  $src_img = imagecreatefrompng($src);  break;
            case 'image/webp': $src_img = imagecreatefromwebp($src); break;
            default:           $src_img = null;
        }
        if (!$src_img) { echo json_encode(['error' => 'Format non supporté']); exit; }

        $dst_img = imagecreatetruecolor($nw, $nh);
        imagefill($dst_img, 0, 0, imagecolorallocate($dst_img, 255, 255, 255));
        imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagejpeg($dst_img, $dest, 75);
        imagedestroy($src_img);
        imagedestroy($dst_img);

        echo json_encode(['ok' => true, 'skipped' => false, 'w' => $nw, 'h' => $nh]);
        exit;
    }
}

// ── DATA ───────────────────────────────────────────────────────────────────
$images      = $logged ? load_images()   : [];
$comments    = $logged ? load_comments() : [];
$file_tags   = $logged ? load_tags()     : [];
$known_tags  = $file_tags ? array_unique(array_merge(...array_values($file_tags))) : [];
$hidden_list = $logged ? load_hidden()   : [];
sort($known_tags);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin · Journal photographique</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:ital,wght@0,300;0,400;1,300&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:      #0f0f0e;
  --surface: #181816;
  --lift:    #222220;
  --border:  rgba(255,255,255,.08);
  --white:   #ffffff;
  --dim:     rgba(255,255,255,.45);
  --faint:   rgba(255,255,255,.18);
  --accent:  #e8c84a;
  --red:     #e85a4a;
  --green:   #4ae87a;
  --mono:    'DM Mono', monospace;
  --ease:    cubic-bezier(.25,.46,.45,.94);
}

html, body { min-height: 100%; background: var(--bg); color: var(--white); font-family: var(--mono); font-size: 13px; }

/* ── LOGIN ─────────────────────────────────────────────────────────────── */
.login-wrap {
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
}
.login-box {
  border: 1px solid var(--border);
  padding: 3rem 3.5rem;
  display: flex; flex-direction: column; gap: 1.4rem;
  background: var(--surface);
}
.login-box h1 { font-size: .7rem; letter-spacing: .2em; text-transform: uppercase; color: var(--dim); font-weight: 400; }
.login-box input[type=password] {
  background: var(--bg); border: 1px solid var(--border);
  color: var(--white); font-family: var(--mono); font-size: .75rem;
  padding: .65rem 1rem; letter-spacing: .1em; outline: none; width: 240px;
  transition: border-color .2s;
}
.login-box input[type=password]:focus { border-color: var(--accent); }
.login-box button {
  background: var(--accent); border: none; cursor: pointer;
  font-family: var(--mono); font-size: .55rem; letter-spacing: .2em;
  text-transform: uppercase; padding: .6rem 1.2rem; color: #111; font-weight: 400;
  transition: opacity .2s;
}
.login-box button:hover { opacity: .85; }

/* ── LAYOUT ────────────────────────────────────────────────────────────── */
#admin { display: grid; grid-template-rows: 48px 1fr; min-height: 100vh; }

header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 2rem; border-bottom: 1px solid var(--border);
  background: var(--bg); position: sticky; top: 0; z-index: 100;
}
.h-title { font-size: .58rem; letter-spacing: .2em; text-transform: uppercase; color: var(--dim); }
.h-title span { color: var(--accent); }
.h-actions { display: flex; gap: 1rem; align-items: center; }
.btn-sm {
  background: none; border: 1px solid var(--border); cursor: pointer;
  font-family: var(--mono); font-size: .48rem; letter-spacing: .14em;
  text-transform: uppercase; color: var(--faint); padding: .3rem .7rem;
  transition: color .2s, border-color .2s;
}
.btn-sm:hover { color: var(--white); border-color: rgba(255,255,255,.3); }

main { padding: 2rem; max-width: 1400px; margin: 0 auto; width: 100%; }

/* ── DROP ZONE ─────────────────────────────────────────────────────────── */
#drop-zone {
  border: 1px dashed rgba(255,255,255,.15);
  padding: 2.5rem;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: .8rem; cursor: pointer; margin-bottom: 2.5rem;
  transition: border-color .25s, background .25s;
  background: var(--surface);
}
#drop-zone.drag-over { border-color: var(--accent); background: rgba(232,200,74,.05); }
#drop-zone .dz-icon { font-size: 1.6rem; opacity: .3; }
#drop-zone p { font-size: .55rem; letter-spacing: .12em; text-transform: uppercase; color: var(--faint); }
#drop-zone em { font-size: .48rem; color: rgba(255,255,255,.12); font-style: normal; }
#file-input { display: none; }

/* Upload progress */
#upload-progress {
  display: none; margin-bottom: 1rem;
  background: rgba(255,255,255,.04); border: 1px solid var(--border);
  padding: .7rem .9rem; display: flex; flex-direction: column; gap: .45rem;
}
#upload-progress.active { display: flex; }
.up-meta {
  display: flex; justify-content: space-between; align-items: baseline;
}
.up-name {
  font-size: .46rem; letter-spacing: .07em; color: rgba(255,255,255,.55);
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 70%;
}
.up-count {
  font-size: .44rem; letter-spacing: .1em; color: var(--faint); flex-shrink: 0;
}
.up-track {
  height: 2px; background: rgba(255,255,255,.08); position: relative; overflow: hidden;
}
.up-bar {
  position: absolute; inset: 0 auto 0 0;
  background: var(--accent); width: 0%;
  transition: width .1s linear;
}

/* Upload result list */
#upload-log { margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: .3rem; }
.upload-item {
  font-size: .5rem; letter-spacing: .1em; padding: .3rem .6rem;
  display: flex; gap: .6rem; align-items: center;
}
.upload-item.ok  { color: var(--green); background: rgba(74,232,122,.06); }
.upload-item.err { color: var(--red);   background: rgba(232,90,74,.06); }

/* ── SECTION HEADER ────────────────────────────────────────────────────── */
.section-head {
  display: flex; align-items: center; gap: 1rem; margin-bottom: 1.4rem;
}
.section-head h2 { font-size: .55rem; letter-spacing: .2em; text-transform: uppercase; color: var(--dim); font-weight: 400; }
.count-badge {
  font-size: .44rem; letter-spacing: .1em; padding: .15rem .5rem;
  border: 1px solid var(--border); color: var(--faint);
}

/* ── PHOTO GRID ────────────────────────────────────────────────────────── */
#photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1px;
  background: var(--border);
}

.photo-card {
  background: var(--surface);
  display: flex; flex-direction: column;
  transition: background .2s;
}
.photo-card:hover { background: var(--lift); }

.card-thumb {
  position: relative; aspect-ratio: 4/3; overflow: hidden; cursor: pointer;
}
.card-thumb img {
  width: 100%; height: 100%; object-fit: cover; display: block;
  filter: brightness(.85);
  transition: filter .4s var(--ease), transform .5s var(--ease);
}
.photo-card:hover .card-thumb img { filter: brightness(1); transform: scale(1.03); }

.card-filename {
  position: absolute; bottom: 0; left: 0; right: 0;
  padding: .4rem .55rem;
  background: linear-gradient(to top, rgba(0,0,0,.8) 0%, transparent 100%);
  font-size: .42rem; letter-spacing: .06em; color: rgba(255,255,255,.5);
}

.btn-delete, .btn-rename {
  position: absolute;
  background: rgba(0,0,0,.65); border: 1px solid rgba(255,255,255,.12);
  cursor: pointer; font-family: var(--mono); font-size: .42rem;
  letter-spacing: .1em; text-transform: uppercase;
  padding: .25rem .5rem; opacity: 0; transition: opacity .2s;
}
.photo-card:hover .btn-delete,
.photo-card:hover .btn-rename { opacity: 1; }
.btn-delete { top: .5rem; right: .5rem; color: rgba(255,80,60,.7); }
.btn-delete:hover { color: var(--red); background: rgba(0,0,0,.9); }
.btn-rename { top: .5rem; left: .5rem; color: rgba(255,255,255,.5); }
.btn-rename:hover { color: rgba(255,255,255,.9); background: rgba(0,0,0,.9); }

/* Rename modal */
#rename-modal {
  display: none; position: fixed; inset: 0; z-index: 1000;
  background: rgba(0,0,0,.72);
  align-items: center; justify-content: center;
}
#rename-modal.open { display: flex; }
.rename-box {
  background: #1e1e1c; border: 1px solid var(--border);
  padding: 1.4rem 1.6rem; width: 420px; max-width: 92vw;
  display: flex; flex-direction: column; gap: .75rem;
}
.rename-title {
  font-size: .5rem; letter-spacing: .18em; text-transform: uppercase; color: var(--faint);
}
.rename-current {
  font-size: .48rem; letter-spacing: .07em; color: rgba(255,255,255,.3);
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.rename-input {
  background: var(--bg); border: 1px solid var(--border);
  color: rgba(255,255,255,.88); font-family: var(--mono);
  font-size: .55rem; letter-spacing: .06em;
  padding: .48rem .65rem; outline: none; width: 100%;
  transition: border-color .2s;
}
.rename-input:focus { border-color: rgba(255,255,255,.25); }
.rename-source {
  font-size: .4rem; letter-spacing: .1em; text-transform: uppercase;
  color: rgba(255,255,255,.28);
}
.rename-source.exif  { color: rgba(74,232,122,.5); }
.rename-source.mtime { color: rgba(255,255,255,.28); }
.rename-actions { display: flex; gap: .45rem; justify-content: flex-end; }
.rename-cancel, .rename-confirm {
  background: none; border: 1px solid var(--border); cursor: pointer;
  font-family: var(--mono); font-size: .44rem; letter-spacing: .14em;
  text-transform: uppercase; padding: .28rem .7rem; transition: color .18s, border-color .18s;
  color: var(--faint);
}
.rename-confirm { color: var(--green); border-color: rgba(74,232,122,.3); }
.rename-confirm:hover { background: rgba(74,232,122,.07); }
.rename-cancel:hover { color: rgba(255,255,255,.7); }

.card-body { padding: .8rem; display: flex; flex-direction: column; gap: .6rem; }

textarea.comment-field {
  background: var(--bg); border: 1px solid var(--border);
  color: rgba(255,255,255,.75); font-family: var(--mono); font-size: .58rem;
  font-style: italic; font-weight: 300; line-height: 1.7;
  padding: .6rem .7rem; resize: vertical; min-height: 72px; width: 100%;
  outline: none; transition: border-color .2s;
}
textarea.comment-field:focus { border-color: rgba(255,255,255,.2); }
textarea.comment-field::placeholder { color: rgba(255,255,255,.15); }

.card-actions { display: flex; gap: .4rem; flex-wrap: wrap; }

.btn-ai, .btn-correct, .btn-save {
  background: none; border: 1px solid var(--border); cursor: pointer;
  font-family: var(--mono); font-size: .44rem; letter-spacing: .14em;
  text-transform: uppercase; padding: .3rem .65rem;
  transition: color .2s, border-color .2s, background .2s;
  display: flex; align-items: center; gap: .35rem;
}
.btn-ai      { color: var(--accent); border-color: rgba(232,200,74,.3); }
.btn-correct { color: rgba(180,220,255,.8); border-color: rgba(100,180,255,.2); }
.btn-save    { color: var(--faint); margin-left: auto; }

.btn-ai:hover      { background: rgba(232,200,74,.08); border-color: var(--accent); }
.btn-correct:hover { background: rgba(100,180,255,.06); border-color: rgba(100,180,255,.4); }
.btn-save:hover    { color: var(--green); border-color: rgba(74,232,122,.3); }

.btn-ai.loading, .btn-correct.loading { opacity: .5; pointer-events: none; }

.save-status {
  font-size: .44rem; letter-spacing: .1em; color: var(--green);
  opacity: 0; transition: opacity .3s; align-self: center;
}
.save-status.show { opacity: 1; }

.card-hidden .card-thumb img { opacity: .35; }
.card-hidden-badge {
  position: absolute; bottom: 2rem; left: 0; right: 0;
  text-align: center; font-size: .42rem; letter-spacing: .16em;
  text-transform: uppercase; color: rgba(255,220,80,.9);
  background: rgba(0,0,0,.55); padding: .2rem 0;
  pointer-events: none;
}

.toggle-vis {
  display: inline-flex; align-items: center; gap: .35rem;
  font-size: .44rem; letter-spacing: .1em; text-transform: uppercase;
  color: var(--faint); cursor: pointer; margin-left: auto;
  user-select: none;
}
.toggle-vis input { accent-color: rgba(255,220,80,.8); cursor: pointer; }
.toggle-vis:has(input:checked) { color: rgba(255,220,80,.8); }

.card-tags {
  display: flex; flex-wrap: wrap; gap: .3rem; align-items: center;
  padding-top: .2rem;
  min-height: 1.6rem;
}
.tag-chip {
  display: inline-flex; align-items: center; gap: .15rem;
  background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.14);
  padding: .16rem .42rem .16rem .5rem;
  font-family: var(--mono); font-size: .42rem; letter-spacing: .08em;
  color: rgba(255,255,255,.65);
}
.tag-rm {
  background: none; border: none; cursor: pointer;
  color: rgba(255,255,255,.3); font-size: .52rem; padding: 0 0 0 .15rem;
  line-height: 1; transition: color .15s;
}
.tag-rm:hover { color: var(--red); }
.tag-input {
  background: none; border: none;
  border-bottom: 1px solid rgba(255,255,255,.1);
  color: rgba(255,255,255,.45); font-family: var(--mono);
  font-size: .42rem; letter-spacing: .08em;
  padding: .1rem .2rem; width: 78px; outline: none;
  transition: border-color .2s, color .2s;
}
.tag-input:focus { border-color: rgba(255,255,255,.3); color: rgba(255,255,255,.8); }
.tag-input::placeholder { color: rgba(255,255,255,.18); }

/* Thumbnail log */
#thumb-log { margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: .2rem; }
.thumb-row {
  display: grid; grid-template-columns: 4rem 1fr auto;
  gap: .8rem; align-items: center;
  font-size: .48rem; letter-spacing: .08em; padding: .28rem .6rem;
}
.thumb-row.pending { color: var(--faint); background: rgba(255,255,255,.02); }
.thumb-row.ok      { color: var(--green); background: rgba(74,232,122,.05); }
.thumb-row.err     { color: var(--red);   background: rgba(232,90,74,.05);  }
.tr-size  { color: var(--accent); font-weight: 400; }
.tr-file  { color: inherit; opacity: .7; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tr-status { white-space: nowrap; }

/* Spinner */
.spin {
  width: 8px; height: 8px; border: 1px solid currentColor;
  border-top-color: transparent; border-radius: 50%;
  animation: spin .6s linear infinite; display: none;
}
.loading .spin { display: inline-block; }
.loading .btn-label { display: none; }
</style>
</head>
<body>

<?php if (!$logged): ?>
<!-- ── LOGIN PAGE ─────────────────────────────────────────────────────────── -->
<div class="login-wrap">
  <div class="login-box">
    <h1>Journal photographique &mdash; Admin</h1>
    <form method="POST">
      <input type="hidden" name="action" value="login">
      <input type="password" name="password" placeholder="mot de passe" autofocus>
    </form>
    <form method="POST">
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="password" id="pw-hidden">
      <button type="button" onclick="
        document.getElementById('pw-hidden').value = document.querySelector('input[type=password]').value;
        this.closest('form').submit();
      ">Entrer</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ── ADMIN ──────────────────────────────────────────────────────────────── -->
<div id="admin">
  <header>
    <div class="h-title">Journal photo &mdash; <span>Admin</span></div>
    <?php if (!KEY_STATUS['loaded']): ?>
    <div style="font-size:.48rem;letter-spacing:.1em;background:#e85a4a22;border:1px solid #e85a4a55;color:#e85a4a;padding:.25rem .7rem;">
      ⚠ Clé API non chargée — <?php echo KEY_STATUS['exists'] ? 'fichier vide ou illisible' : 'fichier introuvable : '.KEY_STATUS['path']; ?>
    </div>
    <?php endif; ?>
    <div class="h-actions">
      <a href="index.php" class="btn-sm" style="text-decoration:none">← Voir le site</a>
      <form method="POST" style="margin:0">
        <input type="hidden" name="action" value="logout">
        <button class="btn-sm" type="submit">Déconnexion</button>
      </form>
    </div>
  </header>

  <main>

    <!-- DROP ZONE -->
    <div id="drop-zone" onclick="document.getElementById('file-input').click()">
      <div class="dz-icon">⊕</div>
      <p>Glisser-déposer des photos ici</p>
      <em>JPG · PNG · WEBP — le nom de fichier détermine l'ordre d'affichage</em>
    </div>
    <input type="file" id="file-input" multiple accept="image/jpeg,image/png,image/webp">
    <div id="upload-progress">
      <div class="up-meta">
        <span class="up-name" id="up-name"></span>
        <span class="up-count" id="up-count"></span>
      </div>
      <div class="up-track"><div class="up-bar" id="up-bar"></div></div>
    </div>
    <div id="upload-log"></div>

    <!-- MINIATURES -->
    <div class="section-head">
      <h2>Miniatures</h2>
      <button class="btn-sm" id="btn-thumbs" onclick="generateAllThumbs(this)">Générer 300px + 1200px</button>
    </div>
    <div id="thumb-log"></div>

    <!-- PHOTO LIST -->
    <div class="section-head">
      <h2>Photos</h2>
      <div class="count-badge"><?php echo count($images); ?></div>
    </div>

    <div id="photo-grid">
      <?php foreach ($images as $file):
        $key       = pathinfo($file, PATHINFO_FILENAME);
        $comment   = $comments[$key] ?? '';
        $path      = 'img/' . htmlspecialchars($file);
        $img_tags  = $file_tags[$file] ?? [];
        $is_hidden = in_array($file, $hidden_list);
        // Suggested rename
        $fpath_abs = IMG_DIR . $file;
        $fext_lc   = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $exif_ts   = null;
        if (in_array($fext_lc, ['jpg','jpeg'])) {
            $ex = @exif_read_data($fpath_abs);
            foreach (['DateTimeOriginal','DateTime'] as $ef) {
                if (!empty($ex[$ef])) {
                    $ts = strtotime(preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', $ex[$ef]));
                    if ($ts) { $exif_ts = $ts; break; }
                }
            }
        }
        $ts_s        = $exif_ts ?: filemtime($fpath_abs);
        $date_source = $exif_ts ? 'exif' : 'fichier';
        // Strip any existing date/time prefix (e.g. "2026-26-17_16-14_") to get the raw camera name
        $base_name = preg_replace('/^\d{4}[-:]\d{2}[-:]\d{2}[_\-\s]+(?:\d{2}[-:]\d{2}[_\-\s]+)?/', '', $key);
        if (!$base_name) $base_name = $key;
        $sug       = date('Y-m-d', $ts_s) . '_' . $base_name . '.' . $fext_lc;
      ?>
      <div class="photo-card<?php echo $is_hidden ? ' card-hidden' : ''; ?>" data-file="<?php echo htmlspecialchars($file); ?>">
        <div class="card-thumb">
          <img src="<?php echo $path; ?>" alt="" loading="lazy">
          <div class="card-filename"><?php echo htmlspecialchars($file); ?></div>
          <?php if ($is_hidden): ?><div class="card-hidden-badge">masqué</div><?php endif; ?>
          <button class="btn-rename" onclick="openRename(this)"
                  data-suggested="<?php echo htmlspecialchars($sug); ?>"
                  data-source="<?php echo $date_source; ?>">⤢ Renommer</button>
          <button class="btn-delete" onclick="deletePhoto(this)" title="Supprimer">✕ Supprimer</button>
        </div>
        <div class="card-body">
          <textarea class="comment-field" placeholder="Légende…" rows="3"><?php echo htmlspecialchars($comment); ?></textarea>
          <div class="card-actions">
            <button class="btn-ai" onclick="aiGenerate(this)">
              <span class="spin"></span>
              <span class="btn-label">✦ Générer</span>
            </button>
            <button class="btn-correct" onclick="aiCorrect(this)">
              <span class="spin"></span>
              <span class="btn-label">⌥ Corriger</span>
            </button>
            <button class="btn-save" onclick="saveComment(this)">
              <span class="spin"></span>
              <span class="btn-label">Enregistrer</span>
            </button>
            <span class="save-status">✓ Sauvegardé</span>
            <label class="toggle-vis" title="Masquer de la galerie">
              <input type="checkbox" onchange="toggleHidden(this)"
                     <?php echo $is_hidden ? 'checked' : ''; ?>>
              <span>Masquer</span>
            </label>
          </div>
          <div class="card-tags">
            <?php foreach ($img_tags as $tag): ?>
            <span class="tag-chip"><?php echo htmlspecialchars($tag); ?><button class="tag-rm" onclick="removeTag(this)">×</button></span>
            <?php endforeach; ?>
            <input class="tag-input" list="tags-datalist" placeholder="+ projet…"
                   onkeydown="addTagOnEnter(event,this)" onblur="addTagOnBlur(this)">
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <datalist id="tags-datalist">
      <?php foreach ($known_tags as $t): ?>
      <option value="<?php echo htmlspecialchars($t); ?>">
      <?php endforeach; ?>
    </datalist>

  </main>
</div>

<!-- ── RENAME MODAL ──────────────────────────────────────────────────────── -->
<div id="rename-modal">
  <div class="rename-box">
    <div class="rename-title">Renommer le fichier</div>
    <div class="rename-current" id="rename-current"></div>
    <input class="rename-input" id="rename-input" type="text" spellcheck="false">
    <div class="rename-source" id="rename-source"></div>
    <div class="rename-actions">
      <button class="rename-cancel" onclick="closeRename()">Annuler</button>
      <button class="rename-confirm" onclick="confirmRename()">Renommer</button>
    </div>
  </div>
</div>

<script>
// ── UPLOAD ───────────────────────────────────────────────────────────────
const dropZone  = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');
const uploadLog = document.getElementById('upload-log');

dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
  e.preventDefault();
  dropZone.classList.remove('drag-over');
  uploadFiles(e.dataTransfer.files);
});
fileInput.addEventListener('change', () => uploadFiles(fileInput.files));

function uploadOne(file, onProgress) {
  return new Promise(resolve => {
    const fd = new FormData();
    fd.append('action', 'upload');
    fd.append('files[]', file);
    const xhr = new XMLHttpRequest();
    xhr.upload.onprogress = e => {
      if (e.lengthComputable) onProgress(Math.round(e.loaded / e.total * 100));
    };
    xhr.onload = () => {
      try { resolve(JSON.parse(xhr.responseText).results?.[0] || { ok: false, name: file.name }); }
      catch { resolve({ ok: false, name: file.name }); }
    };
    xhr.onerror = () => resolve({ ok: false, name: file.name });
    xhr.open('POST', 'admin.php');
    xhr.send(fd);
  });
}

async function uploadFiles(files) {
  if (!files.length) return;
  const progressEl = document.getElementById('upload-progress');
  const barEl      = document.getElementById('up-bar');
  const nameEl     = document.getElementById('up-name');
  const countEl    = document.getElementById('up-count');

  progressEl.classList.add('active');
  const results = [];

  for (let i = 0; i < files.length; i++) {
    const file = files[i];
    nameEl.textContent  = file.name;
    countEl.textContent = `${i + 1} / ${files.length}`;
    barEl.style.width   = '0%';

    const r = await uploadOne(file, pct => { barEl.style.width = pct + '%'; });
    barEl.style.width = '100%';
    results.push(r);
  }

  // Générer les miniatures pour les fichiers uploadés avec succès
  const uploaded = results.filter(r => r.ok).map(r => r.name);
  if (uploaded.length) {
    const sizes = [300, 1200];
    const total = uploaded.length * sizes.length;
    let done = 0;
    for (const name of uploaded) {
      for (const size of sizes) {
        nameEl.textContent  = `Miniatures — ${name}`;
        countEl.textContent = `${size}px`;
        barEl.style.width   = '0%';
        await post({ action: 'generate_thumb', file: name, size, force: 0 });
        done++;
        barEl.style.width = Math.round(done / total * 100) + '%';
      }
    }
  }

  setTimeout(() => { progressEl.classList.remove('active'); barEl.style.width = '0%'; }, 400);

  for (const r of results) {
    const el = document.createElement('div');
    el.className = 'upload-item ' + (r.ok ? 'ok' : 'err');
    el.textContent = (r.ok ? '✓ ' : '✕ ') + r.name;
    uploadLog.prepend(el);
    setTimeout(() => el.style.opacity = '0', 4000);
    setTimeout(() => el.remove(), 4500);
  }

  if (results.some(r => r.ok)) setTimeout(() => location.reload(), 800);
}

// ── HELPERS ───────────────────────────────────────────────────────────────
function getCard(btn)     { return btn.closest('.photo-card'); }
function getFile(btn)     { return getCard(btn).dataset.file; }
function getTextarea(btn) { return getCard(btn).querySelector('textarea'); }
function showStatus(btn) {
  const s = getCard(btn).querySelector('.save-status');
  s.classList.add('show');
  setTimeout(() => s.classList.remove('show'), 2000);
}

async function post(payload) {
  const fd = new FormData();
  for (const [k, v] of Object.entries(payload)) fd.append(k, v);
  const res = await fetch('admin.php', { method: 'POST', body: fd, credentials: 'same-origin' });
  return res.json();
}

// ── SAVE ──────────────────────────────────────────────────────────────────
async function saveComment(btn) {
  btn.classList.add('loading');
  await post({ action: 'save_comment', file: getFile(btn), comment: getTextarea(btn).value });
  btn.classList.remove('loading');
  showStatus(btn);
}

// ── AI GENERATE ───────────────────────────────────────────────────────────
async function aiGenerate(btn) {
  btn.classList.add('loading');
  try {
    const data = await post({ action: 'ai_generate', file: getFile(btn) });
    btn.classList.remove('loading');
    if (data.error) { alert('Erreur : ' + data.error); return; }
    if (data.text) {
      getTextarea(btn).value = data.text;
      await post({ action: 'save_comment', file: getFile(btn), comment: data.text });
      showStatus(btn);
    }
  } catch(e) {
    btn.classList.remove('loading');
    alert('Erreur réseau : ' + e.message);
  }
}

// ── AI CORRECT ────────────────────────────────────────────────────────────
async function aiCorrect(btn) {
  const ta = getTextarea(btn);
  if (!ta.value.trim()) return;
  btn.classList.add('loading');
  try {
    const data = await post({ action: 'ai_correct', file: getFile(btn), comment: ta.value });
    btn.classList.remove('loading');
    if (data.error) { alert('Erreur : ' + data.error); return; }
    if (data.text) {
      ta.value = data.text;
      await post({ action: 'save_comment', file: getFile(btn), comment: data.text });
      showStatus(btn);
    }
  } catch(e) {
    btn.classList.remove('loading');
    alert('Erreur réseau : ' + e.message);
  }
}

// ── HIDE / SHOW ───────────────────────────────────────────────────────────
async function toggleHidden(checkbox) {
  const card = checkbox.closest('.photo-card');
  const file = card.dataset.file;
  const hide = checkbox.checked;
  await post({ action: 'toggle_hidden', file, hide: hide ? 1 : 0 });
  card.classList.toggle('card-hidden', hide);
  let badge = card.querySelector('.card-hidden-badge');
  if (hide && !badge) {
    badge = document.createElement('div');
    badge.className = 'card-hidden-badge';
    badge.textContent = 'masqué';
    card.querySelector('.card-thumb').appendChild(badge);
  } else if (!hide && badge) {
    badge.remove();
  }
}

// ── RENAME ────────────────────────────────────────────────────────────────
let renameTarget = null;

function openRename(btn) {
  renameTarget = btn.closest('.photo-card').dataset.file;
  document.getElementById('rename-current').textContent = renameTarget;
  const input = document.getElementById('rename-input');
  input.value = btn.dataset.suggested || renameTarget;
  const src   = document.getElementById('rename-source');
  const isExif = btn.dataset.source === 'exif';
  src.textContent  = isExif ? '✦ Date issue des données EXIF' : '○ Date issue de la date du fichier (EXIF absent)';
  src.className    = 'rename-source ' + (isExif ? 'exif' : 'mtime');
  document.getElementById('rename-modal').classList.add('open');
  input.focus();
  input.select();
}

function closeRename() {
  document.getElementById('rename-modal').classList.remove('open');
  renameTarget = null;
}

async function confirmRename() {
  const newName = document.getElementById('rename-input').value.trim();
  if (!newName || newName === renameTarget) { closeRename(); return; }
  const data = await post({ action: 'rename_file', old_file: renameTarget, new_file: newName });
  if (data.error) { alert('Erreur : ' + data.error); return; }
  closeRename();
  location.reload();
}

document.getElementById('rename-modal').addEventListener('click', e => {
  if (e.target === document.getElementById('rename-modal')) closeRename();
});
document.getElementById('rename-input').addEventListener('keydown', e => {
  if (e.key === 'Enter')  confirmRename();
  if (e.key === 'Escape') closeRename();
});

// ── DELETE ────────────────────────────────────────────────────────────────
async function deletePhoto(btn) {
  const file = getFile(btn);
  if (!confirm(`Supprimer "${file}" définitivement ?`)) return;
  await post({ action: 'delete', file });
  getCard(btn).remove();
}

// ── THUMBNAILS ────────────────────────────────────────────────────────────
async function generateAllThumbs(btn) {
  const log   = document.getElementById('thumb-log');
  const sizes = [300, 1200];
  const files = [...document.querySelectorAll('.photo-card')].map(c => c.dataset.file);
  const force = btn.dataset.force === '1';

  btn.disabled    = true;
  btn.textContent = 'En cours…';
  log.innerHTML   = '';

  for (const file of files) {
    for (const size of sizes) {
      const row = document.createElement('div');
      row.className = 'thumb-row pending';
      row.innerHTML = `<span class="tr-size">${size}px</span><span class="tr-file">${file}</span><span class="tr-status">…</span>`;
      log.appendChild(row);
      log.scrollTop = log.scrollHeight;

      try {
        const data = await post({ action: 'generate_thumb', file, size, force: force ? 1 : 0 });
        if (data.ok) {
          row.className = 'thumb-row ok';
          row.querySelector('.tr-status').textContent = data.skipped
            ? `— déjà existante (${data.w}×${data.h})`
            : `✓ ${data.w}×${data.h}`;
        } else {
          row.className = 'thumb-row err';
          row.querySelector('.tr-status').textContent = '✕ ' + (data.error || 'erreur');
        }
      } catch(e) {
        row.className = 'thumb-row err';
        row.querySelector('.tr-status').textContent = '✕ erreur réseau';
      }
    }
  }

  btn.disabled       = false;
  btn.dataset.force  = '1';
  btn.textContent    = 'Régénérer 300px + 1200px';
}

// ── TAGS ─────────────────────────────────────────────────────────────────
function getTagsContainer(el) { return el.closest('.card-tags'); }

function currentTags(container) {
  return [...container.querySelectorAll('.tag-chip')].map(c => c.firstChild.textContent.trim());
}

async function saveTags(container) {
  const file = container.closest('.photo-card').dataset.file;
  const tags = currentTags(container).join(',');
  await post({ action: 'save_tags', file, tags });
  refreshDatalist();
}

function refreshDatalist() {
  const all = new Set();
  document.querySelectorAll('.tag-chip').forEach(c => all.add(c.firstChild.textContent.trim()));
  const dl = document.getElementById('tags-datalist');
  dl.innerHTML = [...all].sort().map(t => `<option value="${t}">`).join('');
}

function addTag(input) {
  const val = input.value.trim().toLowerCase().replace(/\s+/g, '-');
  if (!val) return;
  input.value = '';
  const container = getTagsContainer(input);
  if (currentTags(container).includes(val)) return;
  const chip = document.createElement('span');
  chip.className = 'tag-chip';
  chip.innerHTML = `${val}<button class="tag-rm" onclick="removeTag(this)">×</button>`;
  container.insertBefore(chip, input);
  saveTags(container);
}

function addTagOnEnter(e, input) {
  if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(input); }
}

function addTagOnBlur(input) { if (input.value.trim()) addTag(input); }

function removeTag(btn) {
  const container = getTagsContainer(btn);
  btn.closest('.tag-chip').remove();
  saveTags(container);
}

// ── AUTO-SAVE on blur (uniquement si non vide) ───────────────────────────
document.querySelectorAll('textarea.comment-field').forEach(ta => {
  ta.addEventListener('blur', () => {
    if (!ta.value.trim()) return; // ne pas écraser avec une chaîne vide
    const card = ta.closest('.photo-card');
    const btn  = card.querySelector('.btn-save');
    saveComment(btn);
  });
});
</script>

<?php endif; ?>
</body>
</html>
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
        $prompt  = "Regarde cette photographie et écris une courte légende personnelle, entre 1 et 3 phrases. Style: journal photographique intime, quotidien, observateur attentif. Pas de description technique. Pas de ponctuation de fin inutile. Langue: français.";

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
            'messages'   => [[
                'role'    => 'user',
                'content' => "Corrige les fautes d'orthographe et de grammaire de ce texte sans changer la formulation, le style, ni la structure des phrases. Retourne uniquement le texte corrigé, sans explication.\n\n" . $comment,
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
            if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                $results[] = ['name' => $name, 'ok' => true];
            } else {
                $results[] = ['name' => $name, 'ok' => false];
            }
        }
        echo json_encode(['results' => $results]);
        exit;
    }
}

// ── DATA ───────────────────────────────────────────────────────────────────
$images   = $logged ? load_images() : [];
$comments = $logged ? load_comments() : [];
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

/* Upload progress list */
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

.btn-delete {
  position: absolute; top: .5rem; right: .5rem;
  background: rgba(0,0,0,.65); border: 1px solid rgba(255,255,255,.12);
  cursor: pointer; font-family: var(--mono); font-size: .42rem;
  letter-spacing: .1em; text-transform: uppercase; color: rgba(255,80,60,.7);
  padding: .25rem .5rem; transition: color .2s, background .2s; opacity: 0;
  transition: opacity .2s;
}
.photo-card:hover .btn-delete { opacity: 1; }
.btn-delete:hover { color: var(--red); background: rgba(0,0,0,.9); }

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
    <div id="upload-log"></div>

    <!-- PHOTO LIST -->
    <div class="section-head">
      <h2>Photos</h2>
      <div class="count-badge"><?php echo count($images); ?></div>
    </div>

    <div id="photo-grid">
      <?php foreach ($images as $file):
        $key     = pathinfo($file, PATHINFO_FILENAME);
        $comment = $comments[$key] ?? '';
        $path    = 'img/' . htmlspecialchars($file);
      ?>
      <div class="photo-card" data-file="<?php echo htmlspecialchars($file); ?>">
        <div class="card-thumb">
          <img src="<?php echo $path; ?>" alt="" loading="lazy">
          <div class="card-filename"><?php echo htmlspecialchars($file); ?></div>
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
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </main>
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

async function uploadFiles(files) {
  if (!files.length) return;
  const fd = new FormData();
  fd.append('action', 'upload');
  for (const f of files) fd.append('files[]', f);

  const res  = await fetch('admin.php', { method: 'POST', body: fd });
  const data = await res.json();

  for (const r of data.results) {
    const el = document.createElement('div');
    el.className = 'upload-item ' + (r.ok ? 'ok' : 'err');
    el.textContent = (r.ok ? '✓ ' : '✕ ') + r.name;
    uploadLog.prepend(el);
    setTimeout(() => el.style.opacity = '0', 4000);
    setTimeout(() => el.remove(), 4500);
  }

  if (data.results.some(r => r.ok)) {
    setTimeout(() => location.reload(), 1200);
  }
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

// ── DELETE ────────────────────────────────────────────────────────────────
async function deletePhoto(btn) {
  const file = getFile(btn);
  if (!confirm(`Supprimer "${file}" définitivement ?`)) return;
  await post({ action: 'delete', file });
  getCard(btn).remove();
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
<?php
require_once __DIR__ . '/config.php';
session_start();

if (($_POST['action'] ?? '') === 'login') {
    if ($_POST['password'] === PASSWORD) {
        $_SESSION['visitor'] = true;
    }
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['visitor'])) {
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Journal photographique · Tawan Arun</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
  height: 100%; background: #fff; color: #111110;
  font-family: 'DM Mono', monospace; display: flex;
  align-items: center; justify-content: center;
}
.login-wrap { display: flex; flex-direction: column; gap: 1.6rem; align-items: flex-start; }
.login-title {
  font-size: .6rem; letter-spacing: .2em; text-transform: uppercase;
  color: rgba(0,0,0,.4);
}
.login-title span { color: #111110; }
input[type=password] {
  background: #fff; border: none; border-bottom: 1px solid rgba(0,0,0,.2);
  color: #111110; font-family: 'DM Mono', monospace; font-size: .72rem;
  padding: .5rem 0; letter-spacing: .1em; outline: none; width: 220px;
  transition: border-color .2s;
}
input[type=password]:focus { border-color: #111110; }
button {
  background: none; border: none; cursor: pointer;
  font-family: 'DM Mono', monospace; font-size: .5rem; letter-spacing: .2em;
  text-transform: uppercase; color: rgba(0,0,0,.4); padding: 0;
  transition: color .2s;
}
button:hover { color: #111110; }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-title"><span>journal photo</span> &nbsp;· Tawan Arun</div>
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
    ">Entrer →</button>
  </form>
</div>
</body>
</html><?php
    exit;
}

// ─── Scan & sort images by date (filename or filemtime) ───────────────────
$img_dir = 'img/';
$extensions = ['jpg', 'jpeg', 'png', 'webp', 'JPG', 'JPEG', 'PNG'];

$images = [];
if (is_dir($img_dir)) {
    foreach (scandir($img_dir) as $file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (in_array($ext, $extensions)) {
            $images[] = [
                'file'  => $file,
                'path'  => $img_dir . $file,
                'mtime' => filemtime($img_dir . $file),
                'base'  => pathinfo($file, PATHINFO_FILENAME),
            ];
        }
    }
}

// Sort: newest first
usort($images, fn($a, $b) => strcmp($b['file'], $a['file']));

// Load comments.json
$comments_json = [];
$cjson_path = __DIR__ . '/comments.json';
if (file_exists($cjson_path)) {
    $comments_json = json_decode(file_get_contents($cjson_path), true) ?? [];
}

// Attach caption: json first, fallback to .txt
foreach ($images as &$img) {
    $key = $img['base'];
    if (!empty($comments_json[$key])) {
        $img['caption'] = trim($comments_json[$key]);
    } else {
        $txt = $img_dir . $key . '.txt';
        $img['caption'] = file_exists($txt) ? trim(file_get_contents($txt)) : '';
    }
    $t300  = $img_dir . 'thumbs/300/'  . $key . '.jpg';
    $t1200 = $img_dir . 'thumbs/1200/' . $key . '.jpg';
    $img['thumb300']  = file_exists($t300)  ? $t300  : null;
    $img['thumb1200'] = file_exists($t1200) ? $t1200 : null;
    $srcset = [];
    if ($img['thumb300'])  $srcset[] = $img['thumb300']  . ' 300w';
    if ($img['thumb1200']) $srcset[] = $img['thumb1200'] . ' 1200w';
    $img['srcset'] = $srcset ? implode(', ', $srcset) : '';
}
unset($img);

// Group by day for mobile feed
$days = [];
foreach ($images as $img) {
    $d = date('Y-m-d', $img['mtime']);
    $days[$d][] = $img;
}
krsort($days);

// Map filename → global index (for lightbox)
$img_index = [];
foreach ($images as $i => $img) { $img_index[$img['file']] = $i; }

$count  = count($images);
$latest = $count > 0 ? $images[0] : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Journal photographique · Tawan Arun</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300;1,400&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
<style>
/* ── Reset & base ──────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --white:   #ffffff;
  --off:     #f7f6f4;
  --ink:     #111110;
  --muted:   #8a8a85;
  --accent:  #1a1a18;
  --border:  rgba(0,0,0,.08);
  --font-serif: 'Cormorant Garamond', Georgia, serif;
  --font-mono:  'DM Mono', monospace;
  --ease:    cubic-bezier(.25,.46,.45,.94);
}

html, body {
  height: 100%;
  background: var(--white);
  color: var(--ink);
  font-family: var(--font-serif);
  overflow: hidden;
}

/* ── Layout ────────────────────────────────────────────────────────────── */
#app { height: 100vh; display: flex; flex-direction: column; }

/* ── Header ────────────────────────────────────────────────────────────── */
header {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  display: flex; align-items: center;
  padding: .9rem 1.4rem;
  gap: 1.2rem;
  pointer-events: none;
}

.site-title {
  font-family: var(--font-mono);
  font-weight: 400;
  font-size: .58rem;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--ink);
  text-decoration: none;
  background: var(--white);
  padding: .3rem .65rem;
  pointer-events: auto;
  white-space: nowrap;
}

.site-title span {
  font-style: normal;
  opacity: .5;
}

nav { display: flex; gap: .5rem; align-items: center; pointer-events: auto; }

nav button {
  background: var(--white);
  border: none; cursor: pointer;
  font-family: var(--font-mono);
  font-size: .55rem;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--muted);
  padding: .3rem .65rem;
  transition: color .2s;
}

nav button:hover { color: var(--ink); }
nav button.active { color: var(--ink); }

/* ── VIEWER ────────────────────────────────────────────────────────────── */
#viewer {
  position: relative;
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.photo-wrap {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  opacity: 0;
  transform: translateY(12px);
  transition: opacity .55s var(--ease), transform .55s var(--ease);
  pointer-events: none;
}

.photo-wrap.visible {
  opacity: 1; transform: translateY(0); pointer-events: auto;
}

.photo-wrap img {
  max-width: 100vw;
  max-height: 100vh;
  object-fit: contain;
  display: block;
  cursor: zoom-in;
}

.photo-wrap img.is-zooming {
  transition: width .35s ease, height .35s ease, transform .35s ease;
}

#viewer.is-zoomed .photo-wrap.visible img { cursor: grab; }
#viewer.is-zoomed .photo-wrap.visible img.dragging { cursor: grabbing; }

#viewer.is-cover .photo-wrap img {
  width: 100vw;
  height: 100vh;
  max-width: none;
  max-height: none;
  object-fit: cover;
}

/* ── Caption bar: bottom left badge ────────────────────────────────────── */
.caption-bar {
  position: fixed; bottom: 1rem; left: 1.4rem;
  pointer-events: none;
  z-index: 50;
  display: flex; flex-direction: column; align-items: flex-start; gap: .3rem;
}

.caption-date {
  font-family: var(--font-mono);
  font-size: .52rem;
  letter-spacing: .12em;
  color: var(--ink);
  background: var(--white);
  padding: .22rem .55rem;
  opacity: 0;
  transition: opacity .35s var(--ease);
}
.caption-date.visible { opacity: 1; }

.caption-text {
  font-family: var(--font-mono);
  font-size: .55rem;
  font-style: normal;
  font-weight: 400;
  letter-spacing: .06em;
  color: var(--ink);
  background: var(--white);
  padding: .22rem .55rem;
  max-width: 48ch;
  line-height: 1.55;
  opacity: 0;
  transform: translateY(4px);
  transition: opacity .4s var(--ease) .1s, transform .4s var(--ease) .1s;
}

.caption-text.visible { opacity: 1; transform: translateY(0); }

.meta { display: none; }

/* ── Navigation arrows ─────────────────────────────────────────────────── */
.nav-arrows {
  position: fixed;
  bottom: 50%;
  left: 0; right: 0;
  display: flex; justify-content: space-between;
  padding: 0 1.5rem;
  pointer-events: none;
  z-index: 60;
  transform: translateY(50%);
}

.arrow {
  pointer-events: auto;
  background: none; border: none; cursor: pointer;
  width: 72px; height: 72px;
  display: flex; align-items: center; justify-content: center;
  color: var(--muted);
  transition: color .2s, transform .2s var(--ease);
  opacity: .5;
}

.arrow:hover { color: var(--ink); opacity: 1; transform: scale(1.1); }
.arrow:disabled { opacity: .12; cursor: default; pointer-events: none; }

.arrow svg { width: 44px; height: 44px; }

/* Counter */
.counter {
  position: fixed; top: .9rem; right: 1.4rem;
  font-family: var(--font-mono);
  font-size: .52rem;
  letter-spacing: .12em;
  color: var(--ink);
  background: var(--white);
  padding: .3rem .65rem;
  z-index: 60;
}

/* ── CONTACT SHEET ─────────────────────────────────────────────────────── */
#contact {
  display: none;
  position: fixed; inset: 0; z-index: 50;
  background: var(--white);
  overflow-y: auto;
  padding: 6rem 2.5rem 4rem;
  animation: fadeIn .3s var(--ease);
}

#contact.open { display: block; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.contact-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 2.5rem;
}

.contact-header h2 {
  font-family: var(--font-mono);
  font-weight: 300;
  font-size: .7rem;
  letter-spacing: .2em;
  text-transform: uppercase;
  cursor: pointer;
  transition: color .2s;
}
.contact-header h2:hover { color: var(--muted); }

.close-btn {
  background: none; border: none; cursor: pointer;
  font-family: var(--font-mono);
  font-size: .65rem;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: var(--muted);
  transition: color .2s;
}
.close-btn:hover { color: var(--ink); }

/* Grid */
.contact-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 2px;
}

.thumb {
  aspect-ratio: 1;
  overflow: hidden;
  cursor: pointer;
  background: var(--off);
  position: relative;
}

.thumb img {
  width: 100%; height: 100%;
  object-fit: cover;
  display: block;
  transition: transform .4s var(--ease), opacity .3s;
  filter: grayscale(15%);
}

.thumb:hover img { transform: scale(1.04); filter: grayscale(0%); opacity: .9; }

.thumb-meta {
  position: absolute; bottom: 0; left: 0; right: 0;
  padding: .5rem .6rem;
  background: linear-gradient(transparent, rgba(0,0,0,.45));
  font-family: var(--font-mono);
  font-size: .52rem;
  color: rgba(255,255,255,.85);
  letter-spacing: .06em;
  opacity: 0;
  transition: opacity .25s;
}

.thumb:hover .thumb-meta { opacity: 1; }




/* ── Footer ────────────────────────────────────────────────────────────── */
footer {
  position: fixed; bottom: 1rem; left: 50%; transform: translateX(-50%);
  font-family: var(--font-mono);
  font-size: .52rem;
  letter-spacing: .1em;
  color: var(--muted);
  z-index: 40;
  white-space: nowrap;
}

/* ── No photos ─────────────────────────────────────────────────────────── */
.empty {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; height: 100%;
  gap: 1rem; color: var(--muted);
}

.empty p { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .1em; }

/* ── Responsive desktop ─────────────────────────────────────────────────── */
@media (max-width: 600px) {
  .contact-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
  .counter { display: none; }
}

/* ── Mobile feed ────────────────────────────────────────────────────────── */
#mobile-feed { display: none; }

@media (max-width: 768px) {
  html, body { overflow-x: hidden; overflow-y: auto; height: auto; }
  #app, #contact { display: none !important; }
  #mobile-feed { display: block; background: var(--white); min-height: 100vh; max-width: 100vw; overflow-x: hidden; }

  .m-header {
    position: sticky; top: 0; z-index: 100;
    background: var(--white); border-bottom: 1px solid var(--border);
    padding: .85rem 1.2rem;
    display: flex; align-items: center; justify-content: space-between;
  }
  .m-title { font-family: var(--font-mono); font-size: .55rem; letter-spacing: .14em; text-transform: uppercase; color: var(--ink); }
  .m-title span { opacity: .45; }

  .m-day { margin-bottom: 2rem; }

  .m-carousel {
    position: relative; overflow: hidden;
    aspect-ratio: 3 / 2; background: var(--off);
    max-width: 100vw; touch-action: pan-y;
  }
  .m-slides-wrap { display: flex; width: 100%; height: 100%; transition: transform .28s var(--ease); will-change: transform; }
  .m-slide { min-width: 100%; height: 100%; flex-shrink: 0; cursor: zoom-in; }
  .m-slide img { width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none; }

  .m-dots { display: flex; justify-content: center; gap: .38rem; padding: .55rem 0 .2rem; }
  .m-dot { width: 5px; height: 5px; border-radius: 50%; background: rgba(0,0,0,.15); transition: background .2s; flex-shrink: 0; }
  .m-dot.active { background: var(--ink); }

  .m-day-footer { padding: .45rem 1rem .5rem; }
  .m-date { font-family: var(--font-mono); font-size: .44rem; letter-spacing: .1em; color: var(--muted); text-transform: uppercase; margin-bottom: .35rem; }
  .m-caption { font-family: var(--font-mono); font-size: .65rem; line-height: 1.65; color: var(--ink); }

  /* Lightbox */
  #m-lightbox {
    display: none; position: fixed; inset: 0; z-index: 500;
    background: #000; flex-direction: column;
    align-items: stretch; justify-content: center;
    touch-action: none;
  }
  #m-lightbox.open { display: flex; }
  .lb-close {
    position: absolute; top: .9rem; right: 1rem; z-index: 10;
    background: none; border: none; cursor: pointer;
    font-family: var(--font-mono); font-size: 1.4rem;
    color: rgba(255,255,255,.7); line-height: 1; padding: .2rem .4rem;
  }
  .lb-img-wrap { flex: 1; display: flex; align-items: center; justify-content: center; overflow: hidden; }
  .lb-img-wrap img { max-width: 100%; max-height: 100%; object-fit: contain; display: block; user-select: none; }
  .lb-footer {
    padding: .6rem 1.2rem .8rem;
    display: flex; align-items: center; justify-content: space-between;
  }
  .lb-caption { font-family: var(--font-mono); font-size: .58rem; color: rgba(255,255,255,.6); line-height: 1.5; flex: 1; padding-right: 1rem; }
  .lb-counter { font-family: var(--font-mono); font-size: .44rem; letter-spacing: .1em; color: rgba(255,255,255,.35); white-space: nowrap; }
}
</style>
</head>
<body>

<!-- ── PHP data → JS ─────────────────────────────────────────────────── -->
<script>
const PHOTOS = <?php echo json_encode(array_map(function($img) {
    return [
        'path'     => $img['path'],
        'file'     => $img['file'],
        'caption'  => $img['caption'],
        'date'     => date('d.m.Y', $img['mtime']),
        'time'     => date('H:i', $img['mtime']),
        'thumb1200' => $img['thumb1200'] ?? null,
    ];
}, $images)); ?>;
</script>

<div id="app">

  <!-- Header -->
  <header>
    <a class="site-title" href="#" onclick="showViewer(); return false;">
      Journal photographique &nbsp;·&nbsp; <span>Tawan Arun</span>
    </a>
    <nav>
      <button id="btn-viewer" class="active" onclick="showViewer()">Journal</button>
      <button id="btn-contact" onclick="showContact()">Planche contact</button>
      <button id="btn-cover" onclick="toggleCover()">Cover</button>
    </nav>
  </header>

  <!-- Viewer -->
  <div id="viewer">
    <?php if ($count === 0): ?>
      <div class="empty">
        <p>AUCUNE PHOTO — PLACEZ VOS IMAGES DANS LE DOSSIER <code>img/</code></p>
      </div>
    <?php else: ?>
      <?php foreach ($images as $i => $img): ?>
      <div class="photo-wrap <?php echo $i === 0 ? 'visible' : ''; ?>"
           id="photo-<?php echo $i; ?>"
           data-index="<?php echo $i; ?>">
        <img src="<?php echo htmlspecialchars($img['thumb1200'] ?: $img['path']); ?>"
             <?php if ($img['srcset']): ?>
             srcset="<?php echo htmlspecialchars($img['srcset']); ?>"
             sizes="100vw"
             <?php endif; ?>
             data-full="<?php echo htmlspecialchars($img['path']); ?>"
             alt="<?php echo htmlspecialchars($img['base']); ?>"
             loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>">
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Arrows -->
  <?php if ($count > 1): ?>
  <div class="nav-arrows">
    <button class="arrow" id="prev-btn" onclick="navigate(-1)" disabled aria-label="Photo précédente">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
        <polyline points="15 18 9 12 15 6"/>
      </svg>
    </button>
    <button class="arrow" id="next-btn" onclick="navigate(1)" aria-label="Photo suivante">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
        <polyline points="9 6 15 12 9 18"/>
      </svg>
    </button>
  </div>
  <?php endif; ?>

  <!-- Counter -->
  <div class="counter" id="counter">
    <?php if ($count > 0): ?>1 / <?php echo $count; ?><?php endif; ?>
  </div>

  <!-- Caption bar -->
  <div class="caption-bar">
    <div class="caption-date" id="caption-date">
      <?php if ($latest): echo date('d.m.Y', $latest['mtime']); endif; ?>
    </div>
    <div class="caption-text" id="caption-text">
      <?php if ($latest && $latest['caption']): ?>
        <?php echo htmlspecialchars($latest['caption']); ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Footer -->
  <footer>© <?php echo date('Y'); ?> Tawan Arun — Blog photo quotidien</footer>

</div><!-- /app -->

<!-- ── Contact sheet ─────────────────────────────────────────────────── -->
<div id="contact">
  <div class="contact-header">
    <h2 onclick="showViewer()">← Planche contact</h2>
    <button class="close-btn" onclick="showViewer()">← Fermer</button>
  </div>
  <div class="contact-grid">
    <?php foreach ($images as $i => $img): ?>
    <div class="thumb" onclick="goToPhoto(<?php echo $i; ?>)" title="<?php echo htmlspecialchars($img['base']); ?>">
      <img src="<?php echo htmlspecialchars($img['thumb300'] ?: $img['path']); ?>"
           <?php if ($img['srcset']): ?>
           srcset="<?php echo htmlspecialchars($img['srcset']); ?>"
           sizes="(max-width:600px) 120px, 180px"
           <?php endif; ?>
           alt="<?php echo htmlspecialchars($img['base']); ?>"
           loading="lazy">
      <div class="thumb-meta">
        <?php echo date('d.m.Y', $img['mtime']); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($img['base']); ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── Mobile feed ──────────────────────────────────────────────────── -->
<div id="mobile-feed">
  <header class="m-header">
    <div class="m-title">Journal photo &nbsp;·&nbsp; <span>Tawan Arun</span></div>
  </header>
  <?php
  $wday_fr  = ['dim.','lun.','mar.','mer.','jeu.','ven.','sam.'];
  $month_fr = ['','jan','fév','mar','avr','mai','juin','juil','août','sep','oct','nov','déc'];
  foreach ($days as $date => $day_imgs):
    $ts    = strtotime($date);
    $label = $wday_fr[date('w',$ts)] . ' ' . date('j',$ts) . ' ' . $month_fr[(int)date('n',$ts)] . ' ' . date('Y',$ts);
  ?>
  <div class="m-day">
    <div class="m-carousel">
      <div class="m-slides-wrap">
      <?php foreach ($day_imgs as $j => $img):
        $msrc    = $img['thumb300'] ?: $img['path'];
        $msrcset = $img['srcset'];
        $gidx    = $img_index[$img['file']];
      ?>
        <div class="m-slide"
             data-caption="<?php echo htmlspecialchars($img['caption']); ?>"
             data-idx="<?php echo $gidx; ?>"
             onclick="openLightbox(<?php echo $gidx; ?>)">
          <img src="<?php echo htmlspecialchars($msrc); ?>"
               <?php if ($msrcset): ?>srcset="<?php echo htmlspecialchars($msrcset); ?>" sizes="100vw"<?php endif; ?>
               alt="" loading="<?php echo $j === 0 ? 'eager' : 'lazy'; ?>">
        </div>
      <?php endforeach; ?>
      </div>
    </div>
    <?php if (count($day_imgs) > 1): ?>
    <div class="m-dots">
      <?php for ($j = 0; $j < count($day_imgs); $j++): ?>
      <span class="m-dot<?php echo $j === 0 ? ' active' : ''; ?>"></span>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <div class="m-day-footer">
      <div class="m-date"><?php echo $label; ?></div>
      <div class="m-caption"><?php echo htmlspecialchars($day_imgs[0]['caption']); ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Mobile lightbox ──────────────────────────────────────────────────── -->
<div id="m-lightbox">
  <button class="lb-close" onclick="closeLightbox()">×</button>
  <div class="lb-img-wrap">
    <img id="lb-img" src="" alt="">
  </div>
  <div class="lb-footer">
    <div class="lb-caption" id="lb-caption"></div>
    <div class="lb-counter" id="lb-counter"></div>
  </div>
</div>

<!-- Lightbox removed — contact sheet opens viewer at selected index -->

<script>
// ── State ─────────────────────────────────────────────────────────────────
let current = 0;
const total  = PHOTOS.length;

// ── Navigate viewer ───────────────────────────────────────────────────────
function navigate(dir) {
  if (total === 0) return;
  const prev = current;
  current = Math.max(0, Math.min(total - 1, current + dir));
  if (prev === current) return;

  resetZoom(false);
  document.getElementById('photo-' + prev).classList.remove('visible');
  document.getElementById('photo-' + current).classList.add('visible');

  updateUI();
}

function updateUI() {
  if (total === 0) return;
  const p = PHOTOS[current];

  // Date
  const dateEl = document.getElementById('caption-date');
  dateEl.textContent = p.date;
  dateEl.classList.add('visible');

  // Caption
  const cap = document.getElementById('caption-text');
  cap.textContent = p.caption || '';
  cap.classList.toggle('visible', !!p.caption);

  // Counter
  document.getElementById('counter').textContent =
    (current + 1) + ' / ' + total;

  // Arrows
  const prev = document.getElementById('prev-btn');
  const next = document.getElementById('next-btn');
  if (prev) prev.disabled = (current === 0);
  if (next) next.disabled = (current === total - 1);
}

// ── Keyboard nav ──────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {

  if (document.getElementById('contact').classList.contains('open')) {
    if (e.key === 'Escape') showViewer();
    return;
  }
  if (e.key === 'ArrowRight') navigate(1);
  if (e.key === 'ArrowLeft')  navigate(-1);
});

// ── Touch / swipe (disabled when zoomed) ──────────────────────────────────
let touchX = null;
document.getElementById('viewer').addEventListener('touchstart', e => {
  if (isZoomed) return;
  touchX = e.touches[0].clientX;
});
document.getElementById('viewer').addEventListener('touchend', e => {
  if (isZoomed || touchX === null) return;
  const dx = e.changedTouches[0].clientX - touchX;
  if (Math.abs(dx) > 50) navigate(dx < 0 ? 1 : -1);
  touchX = null;
});

// ── Zoom & Pan ────────────────────────────────────────────────────────────
let isZoomed = false, panX = 0, panY = 0;
let dragActive = false, dragMoved = false;
let dragStartX, dragStartY, dragPanX, dragPanY;

function activeImg() {
  const w = document.querySelector('.photo-wrap.visible');
  return w ? w.querySelector('img') : null;
}

function clampPan(x, y, img) {
  const vr = document.getElementById('viewer').getBoundingClientRect();
  const mx = Math.max(0, (img.naturalWidth  * 2 - vr.width)  / 2);
  const my = Math.max(0, (img.naturalHeight * 2 - vr.height) / 2);
  return { x: Math.max(-mx, Math.min(mx, x)), y: Math.max(-my, Math.min(my, y)) };
}

function resetZoom(animate) {
  isZoomed = false; panX = 0; panY = 0;
  const img = activeImg();
  if (!img) return;
  const viewer  = document.getElementById('viewer');
  const isCover = viewer.classList.contains('is-cover');
  viewer.classList.remove('is-zoomed');
  if (animate) {
    const vr = viewer.getBoundingClientRect();
    let tw, th;
    if (isCover) {
      tw = vr.width; th = vr.height;
    } else {
      const scale = Math.min(vr.width / img.naturalWidth, vr.height / img.naturalHeight);
      tw = img.naturalWidth * scale; th = img.naturalHeight * scale;
    }
    img.classList.add('is-zooming');
    img.style.width     = tw + 'px';
    img.style.height    = th + 'px';
    img.style.transform = '';
    setTimeout(() => { img.classList.remove('is-zooming'); img.style.cssText = ''; }, 380);
  } else {
    img.style.cssText = '';
  }
}

function zoomAt(img, cx, cy) {
  const full = img.dataset.full;
  if (full && img.currentSrc && img.currentSrc.includes('/thumbs/')) {
    img.removeAttribute('srcset');
    img.removeAttribute('sizes');
    img.addEventListener('load', () => _applyZoom(img, cx, cy), { once: true });
    img.src = full;
    return;
  }
  _applyZoom(img, cx, cy);
}

function _applyZoom(img, cx, cy) {
  const ir  = img.getBoundingClientRect();
  const vr  = document.getElementById('viewer').getBoundingClientRect();
  const vcx = vr.left + vr.width  / 2;
  const vcy = vr.top  + vr.height / 2;
  const sx  = img.naturalWidth  * 2 / ir.width;
  const sy  = img.naturalHeight * 2 / ir.height;
  const ncx = (cx - ir.left) * sx - img.naturalWidth;
  const ncy = (cy - ir.top)  * sy - img.naturalHeight;
  const raw = { x: (cx - vcx) - ncx, y: (cy - vcy) - ncy };
  const c   = clampPan(raw.x, raw.y, img);
  panX = c.x; panY = c.y;
  isZoomed = true;
  // Pin current displayed size (transition start point)
  img.style.maxWidth  = 'none';
  img.style.maxHeight = 'none';
  img.style.width     = ir.width  + 'px';
  img.style.height    = ir.height + 'px';
  img.style.transform = '';
  img.getBoundingClientRect(); // force reflow
  img.classList.add('is-zooming');
  img.style.width     = (img.naturalWidth  * 2) + 'px';
  img.style.height    = (img.naturalHeight * 2) + 'px';
  img.style.transform = `translate(${panX}px,${panY}px)`;
  document.getElementById('viewer').classList.add('is-zoomed');
  setTimeout(() => img.classList.remove('is-zooming'), 380);
}

// Mouse drag
const viewerEl = document.getElementById('viewer');
viewerEl.addEventListener('mousedown', e => {
  if (!isZoomed || !e.target.matches('img')) return;
  dragActive = true; dragMoved = false;
  dragStartX = e.clientX; dragStartY = e.clientY;
  dragPanX = panX; dragPanY = panY;
  e.target.classList.add('dragging');
  e.preventDefault();
});
document.addEventListener('mousemove', e => {
  if (!dragActive) return;
  const dx = e.clientX - dragStartX, dy = e.clientY - dragStartY;
  if (Math.abs(dx) > 3 || Math.abs(dy) > 3) dragMoved = true;
  const img = activeImg(); if (!img) return;
  const c = clampPan(dragPanX + dx, dragPanY + dy, img);
  panX = c.x; panY = c.y;
  img.style.transform = `translate(${panX}px,${panY}px)`;
});
document.addEventListener('mouseup', () => {
  if (!dragActive) return;
  dragActive = false;
  const img = activeImg(); if (img) img.classList.remove('dragging');
});

// Click to zoom / unzoom
viewerEl.addEventListener('click', e => {
  if (dragMoved) { dragMoved = false; return; }
  if (!e.target.matches('img')) return;
  isZoomed ? resetZoom(true) : zoomAt(e.target, e.clientX, e.clientY);
});

// Touch pan when zoomed
let tStartX, tStartY, tPanX, tPanY, tMoved;
viewerEl.addEventListener('touchstart', e => {
  if (!isZoomed || e.touches.length !== 1) return;
  tStartX = e.touches[0].clientX; tStartY = e.touches[0].clientY;
  tPanX = panX; tPanY = panY; tMoved = false;
  e.stopPropagation();
}, { capture: true });
viewerEl.addEventListener('touchmove', e => {
  if (!isZoomed || e.touches.length !== 1) return;
  const dx = e.touches[0].clientX - tStartX;
  const dy = e.touches[0].clientY - tStartY;
  if (Math.abs(dx) > 5 || Math.abs(dy) > 5) tMoved = true;
  const img = activeImg(); if (!img) return;
  const c = clampPan(tPanX + dx, tPanY + dy, img);
  panX = c.x; panY = c.y;
  img.style.transform = `translate(${panX}px,${panY}px)`;
  e.preventDefault();
}, { passive: false });
viewerEl.addEventListener('touchend', e => {
  if (!isZoomed) return;
  if (!tMoved) resetZoom();
  e.stopPropagation();
}, { capture: true });

// ── Views ─────────────────────────────────────────────────────────────────
function toggleCover() {
  const viewer = document.getElementById('viewer');
  const btn    = document.getElementById('btn-cover');
  viewer.classList.toggle('is-cover');
  btn.classList.toggle('active', viewer.classList.contains('is-cover'));
}

function showViewer() {
  document.getElementById('contact').classList.remove('open');
  document.getElementById('btn-viewer').classList.add('active');
  document.getElementById('btn-contact').classList.remove('active');
  document.body.style.overflow = 'hidden';
}

function showContact() {
  document.getElementById('contact').classList.add('open');
  document.getElementById('btn-contact').classList.add('active');
  document.getElementById('btn-viewer').classList.remove('active');
  document.body.style.overflow = '';
}

// ── From contact sheet: go to photo in viewer ────────────────────────────
function goToPhoto(index) {
  // Close contact, show viewer
  showViewer();
  // Set to that photo
  if (index === current) return;
  document.getElementById('photo-' + current).classList.remove('visible');
  current = index;
  document.getElementById('photo-' + current).classList.add('visible');
  updateUI();
}

// ── Init ──────────────────────────────────────────────────────────────────
if (total > 0) {
  document.getElementById('caption-date').classList.add('visible');
  const cap = document.getElementById('caption-text');
  if (PHOTOS[0].caption) cap.classList.add('visible');
  updateUI();
}

// ── Mobile lightbox ───────────────────────────────────────────────────────
let lbCur = 0;
const lbEl  = document.getElementById('m-lightbox');
const lbImg = document.getElementById('lb-img');

function lbShow(idx) {
  lbCur = Math.max(0, Math.min(PHOTOS.length - 1, idx));
  const p = PHOTOS[lbCur];
  lbImg.src = p.thumb1200 || p.path;
  document.getElementById('lb-caption').textContent = p.caption || '';
  document.getElementById('lb-counter').textContent = (lbCur + 1) + ' / ' + PHOTOS.length;
}

function openLightbox(idx) {
  lbShow(idx);
  lbEl.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  lbEl.classList.remove('open');
  document.body.style.overflow = '';
}

// Swipe dans le lightbox
let lbTx = null, lbTt = null;
lbEl.addEventListener('touchstart', e => {
  lbTx = e.touches[0].clientX; lbTt = Date.now();
}, { passive: true });
lbEl.addEventListener('touchend', e => {
  if (lbTx === null) return;
  const dx = e.changedTouches[0].clientX - lbTx;
  if (Math.abs(dx) > 40 && Date.now() - lbTt < 350) lbShow(lbCur + (dx < 0 ? 1 : -1));
  else if (Math.abs(dx) < 10) closeLightbox();
  lbTx = null;
});

// ── Mobile feed carousels ─────────────────────────────────────────────────
document.querySelectorAll('.m-day').forEach(day => {
  const wrap    = day.querySelector('.m-slides-wrap');
  const slides  = day.querySelectorAll('.m-slide');
  const dots    = day.querySelectorAll('.m-dot');
  const caption = day.querySelector('.m-caption');
  if (slides.length <= 1) return;

  let cur = 0;

  function goTo(idx) {
    cur = Math.max(0, Math.min(slides.length - 1, idx));
    wrap.style.transform = `translateX(${-cur * 100}%)`;
    dots.forEach((d, i) => d.classList.toggle('active', i === cur));
    if (caption) caption.textContent = slides[cur].dataset.caption || '';
  }

  let startX = null, startT = null;
  const car = day.querySelector('.m-carousel');
  car.addEventListener('touchstart', e => {
    startX = e.touches[0].clientX;
    startT = Date.now();
  }, { passive: true });
  car.addEventListener('touchend', e => {
    if (startX === null) return;
    const dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 40 && Date.now() - startT < 350) goTo(cur + (dx < 0 ? 1 : -1));
    startX = null;
  });
});
</script>
</body>
</html>
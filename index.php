<?php
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
}
unset($img);

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
  width: 48px; height: 48px;
  display: flex; align-items: center; justify-content: center;
  color: var(--muted);
  transition: color .2s, transform .2s var(--ease);
  opacity: .5;
}

.arrow:hover { color: var(--ink); opacity: 1; transform: scale(1.1); }
.arrow:disabled { opacity: .12; cursor: default; pointer-events: none; }

.arrow svg { width: 28px; height: 28px; }

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
  position: fixed; inset: 0; z-index: 200;
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
  font-family: var(--font-serif);
  font-weight: 300;
  font-size: 1.6rem;
  letter-spacing: .06em;
}

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

/* ── Responsive ────────────────────────────────────────────────────────── */
@media (max-width: 600px) {
  header { padding: 1rem 1.2rem; }
  .caption-bar { padding: 1rem 1.2rem 3rem; }
  .caption-text { max-width: 85vw; font-size: .95rem; }
  .contact-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
  nav { gap: 1rem; }
  .counter { display: none; }
}
</style>
</head>
<body>

<!-- ── PHP data → JS ─────────────────────────────────────────────────── -->
<script>
const PHOTOS = <?php echo json_encode(array_map(function($img) {
    return [
        'path'    => $img['path'],
        'file'    => $img['file'],
        'caption' => $img['caption'],
        'date'    => date('d.m.Y', $img['mtime']),
        'time'    => date('H:i', $img['mtime']),
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
        <img src="<?php echo htmlspecialchars($img['path']); ?>"
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
    <h2>Planche contact</h2>
    <button class="close-btn" onclick="showViewer()">← Fermer</button>
  </div>
  <div class="contact-grid">
    <?php foreach ($images as $i => $img): ?>
    <div class="thumb" onclick="goToPhoto(<?php echo $i; ?>)" title="<?php echo htmlspecialchars($img['base']); ?>">
      <img src="<?php echo htmlspecialchars($img['path']); ?>"
           alt="<?php echo htmlspecialchars($img['base']); ?>"
           loading="lazy">
      <div class="thumb-meta">
        <?php echo date('d.m.Y', $img['mtime']); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($img['base']); ?>
      </div>
    </div>
    <?php endforeach; ?>
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

  // Swap visible
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

// ── Touch / swipe ─────────────────────────────────────────────────────────
let touchX = null;
document.getElementById('viewer').addEventListener('touchstart', e => {
  touchX = e.touches[0].clientX;
});
document.getElementById('viewer').addEventListener('touchend', e => {
  if (touchX === null) return;
  const dx = e.changedTouches[0].clientX - touchX;
  if (Math.abs(dx) > 50) navigate(dx < 0 ? 1 : -1);
  touchX = null;
});

// ── Views ─────────────────────────────────────────────────────────────────
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
</script>
</body>
</html>
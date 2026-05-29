# Journal photographique — Tawan Arun

Journal photo quotidien personnel, accessible en ligne à :
**https://tawanarun.fr/blog/**

---

## Présentation

Application PHP minimaliste sans base de données ni framework. Deux fichiers publics : un frontend protégé par mot de passe et une interface d'administration. Aucune dépendance externe côté serveur, pas de JS bundler.

---

## Structure des fichiers

```
/
├── index.php        — frontend public
├── admin.php        — interface d'administration
├── config.php       — mot de passe (ignoré par git, chmod 600 serveur)
├── comments.json    — légendes des photos (ignoré par git)
├── tags.json        — projets/tags par fichier (ignoré par git)
├── hidden.json      — liste des photos masquées (ignoré par git)
├── deploy.sh        — script de déploiement rsync
└── img/             — photos originales (ignoré par git)
    ├── thumbs/
    │   ├── 300/     — miniatures 300px (générées depuis l'admin)
    │   └── 1200/    — miniatures 1200px (générées depuis l'admin)
    └── *.txt        — légendes en fichier texte (miroir de comments.json)
```

---

## Installation serveur

1. Déposer les fichiers sur le serveur (hors `config.php`, `comments.json`, `tags.json`, `hidden.json`, `img/`)
2. Créer `config.php` :
   ```php
   <?php
   define('PASSWORD', 'votre-mot-de-passe');
   ```
3. Appliquer les droits : `chmod 600 config.php`
4. Créer le dossier `img/` avec les droits d'écriture pour PHP
5. La clé API Claude est lue depuis `/usr/www/users/idfabr/app-facture/.secret_key`

### Déploiement

```bash
./deploy.sh
```

Envoie `index.php` et `admin.php` vers le serveur via rsync (connexion SSH `idfabrik`, chemin `/usr/home/idfabr/public_html/dev.tawanarun.fr/blog/`). Premier appui = déploie, second appui = met à jour.

---

## Frontend — `index.php`

### Authentification
Accès protégé par mot de passe (session PHP, même `config.php` que l'admin).

### Navigation

Le header fixe contient :
- Titre **journal photo** (lien retour vers le viewer)
- Boutons **Journal** / **Planche contact** (fond noir/texte blanc quand actif)
- Bouton **Cover** (masqué en mode planche contact)
- Bouton **◑** bascule mode sombre/clair
- Lien **Admin** vers `admin.php`
- Barre de projets (visible uniquement si des tags existent)

### Mode sombre / clair

Bouton `◑` dans le nav. Le choix est mémorisé en `localStorage` et appliqué sans flash au chargement via un script inline dans `<head>`.

### Projets / filtrage par tag

Si des tags sont définis dans l'admin, une barre de boutons apparaît sous le header (desktop) ou sous le titre (mobile). Sélectionner un projet filtre :
- Le viewer (navigation ←→ limitée aux photos du projet, compteur mis à jour)
- La planche contact (vignettes non membres masquées)
- Le feed mobile (jours sans photo du projet masqués)

"Tous" réaffiche l'ensemble.

### Photos masquées

Les photos marquées "Masquer" dans l'admin sont exclues du frontend. Pour les afficher quand même : `index.php?all`.

### Vue desktop (> 768px)

**Viewer**
- Une photo à la fois, plein écran
- Navigation : flèches clavier ←→, clic sur les flèches latérales, swipe tactile
- Tri automatique par nom de fichier (ordre alphabétique inversé)
- Chargement : `src` = miniature 1200px si disponible, sinon originale. `srcset` 300w/1200w pour sélection automatique par le navigateur.

**Zoom**
- Clic sur l'image → zoom 2× centré sur le point cliqué (pixel natif sur écran Retina)
- Si le navigateur a chargé une miniature, le fichier original est rechargé avant le zoom
- Drag souris ou tactile pour se déplacer dans l'image zoomée
- Clic à nouveau → dézoom animé (0,35s) revenant au bon cadrage (contain ou cover)

**Mode Cover**
- Bouton "Cover" : l'image passe en `object-fit: cover` plein écran
- Compatible avec le zoom

**Planche contact**
- Fond adapté au thème clair/sombre
- Grille de vignettes au ratio 3:2 (miniatures 300px)
- Chaque vignette : image + panneau latéral 44px avec numéro de frame, date courte, heure
- Filtrable par projet (voir section Projets)
- Clic sur une vignette → retour au viewer à l'index correspondant

### Vue mobile (≤ 768px)

Le viewer desktop et la planche contact sont masqués. Un feed vertical remplace l'interface.

**Feed par jour**
- Photos regroupées par date (mtime du fichier), jours les plus récents en premier
- Chaque jour = header date + carousel d'images

**Carousel**
- Swipe gauche/droite avec suivi du doigt en temps réel (live feedback)
- Direction détectée après 4px : horizontal → carousel, vertical → scroll page natif
- Commit si déplacement > 25% de la largeur, sinon snap retour
- Points indicateurs sous le carousel
- Lazy loading : premier slide de chaque jour via `loading="lazy"` natif, slides 2+ chargés au swipe

**Lightbox**
- Clic sur une image → lightbox plein écran fond noir
- Swipe pour naviguer dans toutes les photos
- Compteur position/total, tap sans déplacement → ferme

**Images sur mobile**
- `src` = miniature 300px si disponible, `srcset` 300w/1200w
- Aucune image originale chargée si les miniatures existent

---

## Admin — `admin.php`

### Authentification
Session PHP, même mot de passe que le frontend.

### Upload
- Glisser-déposer ou sélection multiple (JPG, PNG, WEBP)
- Barre de progression par fichier (XHR avec `upload.onprogress`)
- Génération automatique des miniatures 300px et 1200px après chaque upload
- Si un fichier du même nom existe déjà → remplacement silencieux

### Génération de miniatures
Bouton "Générer 300px + 1200px" : traite toutes les photos en séquence via PHP GD.
- Skip automatique des miniatures déjà existantes (log `— déjà existante`)
- Bouton "Régénérer" pour forcer le recalcul de toutes les miniatures
- Format de sortie : JPEG à 75%, ratio conservé
- Destination : `img/thumbs/300/` et `img/thumbs/1200/`

### Légendes
- Champ texte par photo, sauvegarde manuelle ou automatique au blur
- Stockées dans `comments.json` et en parallèle dans un fichier `.txt` à côté de l'image
- **✦ Générer** : génère une légende factuelle et neutre via Claude Sonnet (description de ce qui est visible, sans style littéraire)
- **⌥ Corriger** : corrige orthographe et grammaire sans reformuler ni compléter (fonctionne sur un seul mot)

### Projets / tags
- Zone de tags sous la légende de chaque photo
- Saisir un nom + `Entrée` ou `,` → crée un chip, sauvegarde immédiate dans `tags.json`
- Autocomplétion sur les tags existants (datalist)
- `×` sur un chip → retire le tag
- Une photo peut appartenir à plusieurs projets
- Stockés dans `tags.json` (clé = nom de fichier, valeur = tableau de tags)

### Masquage
- Case à cocher "Masquer" par photo
- La photo reste dans l'admin mais disparaît du frontend
- Indication visuelle : image à 35% d'opacité + badge "masqué"
- Stocké dans `hidden.json`
- Pour voir les photos masquées dans le frontend : `?all`

### Renommage
- Bouton "⤢ Renommer" (apparaît au survol, haut gauche de la vignette)
- Popup avec suggestion automatique au format `YYYY-MM-DD_nom-original.jpg`
- La date est lue depuis les métadonnées EXIF (`DateTimeOriginal`) si disponibles, sinon depuis la date du fichier
- L'origine de la date est indiquée dans le popup : **✦ Date issue des données EXIF** (vert) ou **○ Date issue de la date du fichier** (gris)
- Un préfixe date/heure existant dans le nom (ex. `2026-26-17_16-14_TAW0847.jpg`) est automatiquement strippé pour ne garder que le nom caméra original
- Le renommage met à jour : fichier image, miniatures 300/1200, fichier `.txt`, `comments.json`, `tags.json`

### Suppression
- Bouton "✕ Supprimer" par photo (confirmation requise)
- Supprime l'image, le `.txt` associé, la légende dans `comments.json` et les tags dans `tags.json`

### Clé API Claude
Lue depuis `/usr/www/users/idfabr/app-facture/.secret_key` (fichier externe au projet). Un bandeau d'alerte s'affiche dans l'admin si la clé est manquante ou vide.

---

## Fichiers de données (tous ignorés par git)

| Fichier | Format | Contenu |
|---|---|---|
| `config.php` | PHP | Mot de passe (`define('PASSWORD', '...')`) |
| `comments.json` | JSON | `{"nom_sans_extension": "légende"}` |
| `tags.json` | JSON | `{"fichier.jpg": ["projet1", "projet2"]}` |
| `hidden.json` | JSON | `["fichier1.jpg", "fichier2.jpg"]` |

---

## Images — formats et résolution

| Usage | Résolution conseillée | Format | Qualité |
|---|---|---|---|
| Originales (upload) | 4000–5000px grand côté | JPEG / WebP | 80–85 % |
| Miniatures 1200px | générées automatiquement | JPEG | 75 % |
| Miniatures 300px | générées automatiquement | JPEG | 75 % |

Le zoom 2× affiche l'image à `naturalWidth × 2` CSS pixels. Sur écran Retina (DPR = 2), une photo de 4000px s'affiche à 8000px CSS = 4000 pixels physiques — correspondance 1:1 avec les pixels du capteur.

---

## Suggestions d'amélioration

### Expérience utilisateur
- [ ] Zoom progressif à la molette (scroll wheel)
- [ ] Pinch-to-zoom sur mobile (geste deux doigts)
- [ ] Transition de navigation entre photos (fondu enchaîné ou glissement)
- [ ] Affichage EXIF : focale, ouverture, vitesse, ISO au survol
- [ ] Mode diaporama automatique avec intervalle configurable
- [ ] URL avec ancre par photo (`#photo-12`) pour partager un lien direct
- [ ] Raccourci clavier `Z` pour toggler le zoom, `C` pour le cover

### Administration
- [ ] Réorganisation des photos par drag-and-drop (ordre d'affichage manuel)
- [ ] Compression / redimensionnement automatique à l'upload
- [ ] Historique des légendes générées par IA (annuler/restaurer)
- [ ] Tri/filtre dans l'admin par projet ou statut masqué

### Technique
- [ ] Cache HTTP sur les images (headers `Cache-Control`, `ETag`)
- [ ] Préchargement de la photo suivante en arrière-plan
- [ ] Optimisation automatique WebP à l'upload
- [ ] Logs d'accès simples (date, IP, photo vue)

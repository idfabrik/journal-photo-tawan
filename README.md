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
└── img/             — photos originales (ignoré par git)
    └── thumbs/
        ├── 300/     — miniatures 300px (générées depuis l'admin)
        └── 1200/    — miniatures 1200px (générées depuis l'admin)
```

---

## Installation serveur

1. Déposer les fichiers sur le serveur (hors `config.php`, `comments.json`, `img/`)
2. Créer `config.php` :
   ```php
   <?php
   define('PASSWORD', 'votre-mot-de-passe');
   ```
3. Appliquer les droits : `chmod 600 config.php`
4. Créer le dossier `img/` avec les droits d'écriture pour PHP
5. La clé API Claude est lue depuis `/usr/www/users/idfabr/app-facture/.secret_key`

---

## Frontend — `index.php`

### Authentification
Accès protégé par mot de passe (session PHP, même `config.php` que l'admin). Le formulaire de login est en DM Mono, fond blanc, minimaliste.

### Vue desktop (> 768px)

**Viewer**
- Une photo à la fois, plein écran
- Navigation : flèches clavier ←→, clic sur les flèches latérales, swipe tactile
- Tri automatique par nom de fichier (ordre alphabétique inversé)
- Chargement : `src` = miniature 1200px si disponible, sinon originale. `srcset` avec 300w et 1200w pour que le navigateur choisisse la taille optimale.

**Zoom**
- Clic sur l'image → zoom 2× centré sur le point cliqué (correspond au pixel natif sur écran Retina)
- Si le navigateur a chargé une miniature, le full est rechargé automatiquement avant d'appliquer le zoom
- Drag souris ou tactile pour se déplacer dans l'image zoomée
- Clic à nouveau → dézoom animé (0,35s) qui revient au bon cadrage (contain ou cover)

**Mode Cover**
- Bouton "Cover" dans le nav : l'image passe en `object-fit: cover` plein écran
- Compatible avec le zoom (le zoom part du cadrage cover)

**Planche contact**
- Grille de toutes les photos (miniatures 300px si disponibles)
- Clic sur une photo → retour au viewer à l'index correspondant
- Le nav reste visible (z-index inférieur au header fixe)
- Titre "← Planche contact" cliquable pour revenir, bouton "← Fermer" en haut à droite

### Vue mobile (≤ 768px)

Le viewer desktop et la planche contact sont masqués. Un feed vertical remplace l'interface.

**Feed par jour**
- Les photos sont regroupées par date (mtime du fichier)
- Chaque jour = un bloc avec header date + carousel d'images
- Images au ratio 3:2 (proportion native Z8), `object-fit: cover`

**Carousel**
- Swipe gauche/droite pour passer d'une photo à l'autre dans la même journée
- Points indicateurs en dessous (nombre de points = nombre de photos du jour)
- La légende se met à jour à chaque slide
- `touch-action: pan-y` : le scroll vertical de la page reste natif

**Lightbox**
- Clic sur une image → lightbox plein écran fond noir, image en `object-fit: contain`
- Swipe gauche/droite pour naviguer dans toutes les photos (pas seulement le jour en cours)
- Compteur position/total en bas à droite
- Tap sans déplacement → ferme la lightbox

**Images sur mobile**
- `src` = miniature 300px si disponible
- `srcset` = "300w, 1200w" avec `sizes="100vw"` : le navigateur charge la taille adaptée à l'écran et au DPR
- Aucune image originale n'est chargée sur mobile si les miniatures existent

---

## Admin — `admin.php`

### Authentification
Session PHP, même mot de passe que le frontend.

### Upload
- Glisser-déposer ou sélection multiple (JPG, PNG, WEBP)
- Si un fichier du même nom existe déjà → remplacement silencieux
- La page se recharge automatiquement après upload réussi

### Génération de miniatures
Bouton "Générer 300px + 1200px" : traite toutes les photos en séquence via PHP GD.
- Taille : redimensionnement au grand côté, ratio conservé
- Format de sortie : JPEG à 75%
- Destination : `img/thumbs/300/` et `img/thumbs/1200/`
- Si l'image source est plus petite que la cible → copie directe
- Un log ligne par ligne s'affiche en temps réel avec les dimensions produites
- Bouton "Régénérer" après la première passe pour mettre à jour après nouveaux uploads

### Légendes
- Champ texte par photo, sauvegarde manuelle ou automatique au blur
- Stockées dans `comments.json` et en parallèle dans un fichier `.txt` à côté de l'image
- **✦ Générer** : génère une légende par IA (Claude Sonnet via API Anthropic) à partir de l'image
- **⌥ Corriger** : corrige orthographe et grammaire sans changer le style

### Suppression
- Bouton "✕ Supprimer" par photo (confirmation requise)
- Supprime l'image, le `.txt` associé, et la légende dans `comments.json`

### Clé API Claude
Lue depuis `/usr/www/users/idfabr/app-facture/.secret_key` (fichier externe au projet). Un bandeau d'alerte s'affiche dans l'admin si la clé est manquante ou vide.

---

## Images — formats et résolution

| Usage | Résolution conseillée | Format | Qualité |
|---|---|---|---|
| Originales (upload) | 4000–5000px grand côté | JPEG / WebP | 80–85 % |
| Miniatures 1200px | générées automatiquement | JPEG | 75 % |
| Miniatures 300px | générées automatiquement | JPEG | 75 % |

Le zoom 2× affiche l'image à `naturalWidth × 2` CSS pixels. Sur écran Retina (DPR = 2), une photo de 4000px s'affiche donc à 8000px CSS = 4000 pixels physiques — correspondance 1:1 avec les pixels du capteur.

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
- [ ] Renommage de fichier directement dans l'interface
- [ ] Compression / redimensionnement automatique à l'upload
- [ ] Historique des légendes générées par IA (annuler/restaurer)

### Technique
- [ ] Cache HTTP sur les images (headers `Cache-Control`, `ETag`)
- [ ] Préchargement de la photo suivante en arrière-plan
- [ ] Optimisation automatique WebP à l'upload
- [ ] Logs d'accès simples (date, IP, photo vue)

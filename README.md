# Journal photographique — Tawan Arun

Journal photo quotidien personnel, accessible en ligne à l'adresse :
**https://tawanarun.fr/blog/**

---

## Présentation

Application PHP minimaliste sans base de données ni framework. Deux pages : un frontend public protégé par mot de passe et une interface d'administration pour gérer les photos et leurs légendes.

---

## Structure

```
/
├── index.php        — frontend public (viewer + planche contact)
├── admin.php        — interface d'administration
├── config.php       — mot de passe (ignoré par git, chmod 600 sur le serveur)
├── comments.json    — légendes des photos (ignoré par git)
└── img/             — dossier des photos (ignoré par git)
```

---

## Fonctionnalités

### Frontend (`index.php`)
- Accès protégé par mot de passe (session PHP)
- Viewer plein écran, une photo à la fois
- Navigation clavier (←→), swipe mobile, flèches
- **Mode Cover** : image en plein cadre (object-fit cover)
- **Zoom 2×** au clic sur l'image, centré sur le point cliqué
- Drag souris et tactile pour se déplacer dans l'image zoomée
- Planche contact : grille de toutes les photos, clic pour aller directement à la photo
- Légendes par photo affichées en bas à gauche
- Tri automatique par nom de fichier (ordre alphabétique inversé)

### Admin (`admin.php`)
- Authentification par session
- Upload par glisser-déposer ou sélection (JPG, PNG, WEBP)
- Remplacement automatique si un fichier du même nom existe déjà
- Génération de légende par IA (Claude claude-sonnet-4-6 via API Anthropic)
- Correction orthographique par IA
- Suppression de photo
- Sauvegarde automatique de la légende au blur du champ texte
- Clé API Claude chargée depuis un fichier secret externe au projet

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

## Formats et résolution recommandés

- **Format** : JPEG ou WebP
- **Résolution** : 4000–5000px sur le grand côté
- **Qualité** : 80–85 %
- Le zoom 2× affiche l'image à sa résolution native sur écran Retina (1 pixel image = 1 pixel physique)

---

## Suggestions d'amélioration

### Expérience utilisateur
- [ ] Zoom progressif à la molette (scroll wheel), pas seulement bascule contain/zoom
- [ ] Pinch-to-zoom sur mobile (geste deux doigts)
- [ ] Transition de navigation entre photos (fondu enchaîné ou glissement)
- [ ] Affichage EXIF : focale, ouverture, vitesse, ISO au survol ou dans la légende
- [ ] Mode diaporama automatique avec intervalle configurable
- [ ] Raccourci clavier `Z` pour toggler le zoom, `C` pour le cover
- [ ] URL avec ancre par photo (`#photo-12`) pour pouvoir partager un lien direct

### Administration
- [ ] Réorganisation des photos par drag-and-drop (ordre d'affichage manuel)
- [ ] Renommage de fichier directement dans l'interface
- [ ] Aperçu de la légende en style frontend avant publication
- [ ] Compression / redimensionnement automatique à l'upload (GD ou ImageMagick)
- [ ] Historique des légendes générées par IA (annuler/restaurer)
- [ ] Import depuis un dossier ou un FTP distant

### Technique
- [ ] Génération de thumbnails pour la planche contact (évite de charger les full-res)
- [ ] Cache HTTP sur les images (headers `Cache-Control`, `ETag`)
- [ ] Lazy loading optimisé : préchargement de la photo suivante en arrière-plan
- [ ] Optimisation automatique WebP à l'upload
- [ ] Authentification à deux facteurs ou token pour l'admin
- [ ] Logs d'accès simples (date, IP, photo vue)

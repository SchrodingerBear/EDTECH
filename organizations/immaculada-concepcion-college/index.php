<?php
/**
 * Innovatech PH — Organization landing page (index.php)
 * 
 * Directly loads config.json with zero browser caching.
 * Server controls headers to always serve fresh config.
 * No JavaScript cache-busting needed.
 */

// Prevent all caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/html; charset=utf-8');

// Load config
$configFile = __DIR__ . '/config.json';
$config = null;
$published = false;
$name = 'Organization';
$shortName = 'ORG';

if (is_file($configFile)) {
    $raw = file_get_contents($configFile);
    $config = json_decode($raw, true);
    $published = $config['published'] ?? false;
    $name = $config['name'] ?? 'Organization';
    $shortName = $config['short_name'] ?? 'ORG';
}

// Preview bypass for admins
$isPreview = ($_GET['preview'] ?? '') === '1';

// If not published and not preview mode, show not-published overlay
if (!$published && !$isPreview) {
    ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <meta name="theme-color" content="#0b0d16">
  <title>{{NAME}} · Virtual Campus</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div id="not-published" class="np-overlay">
    <div class="np-card">
      <div class="brand-chip"><span class="dot"></span> {{SHORT}}</div>
      <div class="np-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14"/><path d="M5 2h14"/><path d="M17 22v-4.17a2 2 0 0 0-.59-1.42L12 12l-4.41 4.41A2 2 0 0 0 7 17.83V22"/><path d="M7 2v4.17a2 2 0 0 0 .59 1.42L12 12l4.41-4.41A2 2 0 0 0 17 6.17V2"/></svg>
      </div>
      <h1 class="np-title">{{NAME}}</h1>
      <p class="np-msg">This virtual campus experience is not yet published.<br>Please check back soon.</p>
      <p class="np-foot">Powered by Innovatech PH</p>
    </div>
  </div>
</body>
</html><?php
    exit;
}

// Config is published — render full page
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <meta name="theme-color" content="#0b0d16">
  <title>{{NAME}} · Virtual Campus</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <!-- =========================== 360 MODE (A-Frame) =========================== -->
  <div id="mode-360" class="mode hidden">
    <a-scene id="aframe-scene" embedded vr-mode-ui="enabled: false">
      <a-assets>
        <img id="pano" crossorigin="anonymous">
      </a-assets>
      <a-sky id="sky" src="#pano" rotation="0 0 0"></a-sky>
      <a-entity id="camera-rig">
        <a-camera look-controls wasd-controls="enabled:false">
          <a-cursor raycaster="objects: .clickable" far="100"></a-cursor>
        </a-camera>
      </a-entity>
    </a-scene>

    <div class="hud top-left">
      <button class="round-btn" id="btn-360-back" title="Back to floor plan">←</button>
    </div>
    <div class="hud top-right">
      <div class="brand-chip"><span class="dot"></span> {{SHORT}}</div>
    </div>
    <div class="hud bottom hint" id="scene-hint">Drag to look around</div>

    <div id="info-panel" class="info-panel hidden">
      <button class="round-btn" id="info-close">✕</button>
      <h2 id="info-title"></h2>
      <div id="info-body"></div>
    </div>

    <div id="scene-carousel" class="scene-carousel hidden">
      <div id="scene-carousel-track" class="scene-carousel-track"></div>
    </div>
  </div>

  <!-- ========================= FLOOR-PLAN MODE ========================= -->
  <div id="mode-floor" class="mode hidden">
    <div class="fp-stage" id="fp-stage">
      <div class="fp-wrap" id="fp-wrap">
        <img id="fp-image" src="" alt="{{NAME}} floor plan">
        <div id="fp-markers" class="fp-markers"></div>
      </div>
      <div class="fp-buttons">
        <button class="round-btn" id="btn-fp-back" title="Go back">←</button>
        <div class="brand-chip"><span class="dot"></span> <span id="fp-title">{{SHORT}}</span></div>
      </div>
    </div>
  </div>

  <!-- ================================ LOADER ================================ -->
  <div id="loader" class="loader">
    <div class="spinner"></div>
    <p>{{NAME}}</p>
  </div>

  <!-- ====================== CONFIG EMBEDDED IN PAGE ====================== -->
  <script>
  window.__CONFIG__ = <?= json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>

  <!-- A-Frame loaded lazily in openScene() to prevent blocking floor plan -->
  <script>
  (() => {
    const $ = (s) => document.querySelector(s);
    const els = {
      loader: $('#loader'),
      m360:   $('#mode-360'),
      mfp:    $('#mode-floor'),
    };

    let config       = window.__CONFIG__; // Use embedded config, always fresh from PHP
    let fpStack      = [];   // navigation stack of floor plan states [{image, markers, title}]
    let currentMode  = null; // 'floor' | '360'
    let sceneCount   = 0;    // number of scenes available for the carousel

    // Helper to convert yaw/pitch to XYZ on sphere of radius r
    function yawPitchToXYZ(yawDeg, pitchDeg, r = 8) {
      const y = (yawDeg  || 0) * Math.PI / 180;
      const p = (pitchDeg || 0) * Math.PI / 180;
      const x = r * Math.cos(p) * Math.sin(-y);
      const z = -r * Math.cos(p) * Math.cos(y);
      const vY = r * Math.sin(p);
      return `${x.toFixed(4)} ${vY.toFixed(4)} ${z.toFixed(4)}`;
    }

    // ─── boot ────────────────────────────────────────────────────────────────
    function boot() {
      if (!config) {
        config = {
          landing_mode: '360_rotation',
          require_landscape_mobile: true,
          floor_plan_markers: [],
          starting_floor_plan: {},
          starting_scene: {}
        };
      }

      // Apply theme tokens
      if (config.theme) {
        const r = document.documentElement.style;
        if (config.theme.primary)   r.setProperty('--ia-primary', config.theme.primary);
        if (config.theme.accent)    r.setProperty('--ia-accent', config.theme.accent);
        if (config.theme.secondary) r.setProperty('--ia-secondary', config.theme.secondary);
      }

      // Render the 360 scene carousel from config
      renderSceneCarousel();

      if (config.landing_mode === 'floor_plan' && config.starting_floor_plan && config.starting_floor_plan.image_path) {
        openFloorPlan(config.starting_floor_plan.image_path, config.floor_plan_markers || [], config.short_name || 'ORG', false);
      } else if (config.starting_scene && config.starting_scene.equirect_path) {
        openScene(config.starting_scene);
      } else {
        activate('floor'); // fallback
      }
    }

    // ─── activate mode ───────────────────────────────────────────────────────
    function activate(mode) {
      currentMode = mode;
      els.m360.classList.toggle('hidden', mode !== '360');
      els.mfp.classList.toggle('hidden',  mode !== 'floor');
      els.loader.classList.add('hidden');
      const showCarousel = mode === '360' && sceneCount > 1;
      $('#scene-carousel').classList.toggle('hidden', !showCarousel);
    }

    // ─── floor plan ──────────────────────────────────────────────────────────
    function openFloorPlan(imagePath, markers, title, pushStack) {
      if (pushStack !== false) {
        // save current state to stack for back navigation
        fpStack.push({ imagePath: $('#fp-image').src, markers: currentMarkers, title: $('#fp-title').textContent });
      }
      currentMarkers = markers;
      $('#fp-image').src = imagePath;
      $('#fp-title').textContent = title || 'ORG';
      renderMarkers(markers);
      activate('floor');
    }

    let currentMarkers = [];

    function renderMarkers(markers) {
      const box = $('#fp-markers');
      box.innerHTML = '';
      (markers || []).forEach((m) => {
        const b = document.createElement('button');
        b.className = 'fp-marker';
        b.style.left = m.x + '%';
        b.style.top  = m.y + '%';
        b.style.width  = (m.size_percent || 4) + 'vmin';
        b.style.height = (m.size_percent || 4) + 'vmin';
        b.setAttribute('aria-label', m.label);
        b.title = m.label;

        // Label chip under the dot
        const chip = document.createElement('span');
        chip.className = 'fp-marker-label';
        chip.textContent = m.label;
        b.appendChild(chip);

        b.addEventListener('click', () => handleMarkerClick(m));
        box.appendChild(b);
      });
    }

    function handleMarkerClick(m) {
      if (m.target_type === 'scene' && m.scene_equirect) {
        openScene({
          id:            m.target_scene_id,
          title:         m.scene_title || m.label,
          equirect_path: m.scene_equirect,
          initial_yaw:   m.scene_yaw || 0,
          initial_pitch: m.scene_pitch || 0,
        });
      } else if (m.target_type === 'floor_plan' && m.sub_fp_image) {
        openFloorPlan(m.sub_fp_image, m.sub_markers || [], m.sub_fp_title || m.label, true);
      } else {
        // popup (or fallback for non-linked markers)
        openPopup(m);
      }
    }

    // ─── 360 scene ───────────────────────────────────────────────────────────
    function openScene(scene) {
      // Lazily load A-Frame before activating 360 mode
      loadAFrame(() => _doOpenScene(scene));
    }
    function _doOpenScene(scene) {
      const panoEl = $('#pano');
      const skyEl  = $('#sky');
      const hintEl = $('#scene-hint');

      // Dismiss any open popup and highlight the active carousel card
      $('#info-panel').classList.add('hidden');
      setActiveScene(scene.id);

      panoEl.onload = () => {
        skyEl.setAttribute('src', '#pano');
        skyEl.setAttribute('rotation', `0 ${scene.initial_yaw || 0} 0`);
        
        // Remove old hotspots
        document.querySelectorAll('.scene-hs').forEach(e => e.remove());

        // Render new hotspots from config
        const sceneData = config.scenes && config.scenes[scene.id];
        if (sceneData && sceneData.hotspots) {
          const aScene = $('#aframe-scene');
          sceneData.hotspots.forEach(hs => {
            const entity = document.createElement('a-entity');
            entity.className = 'scene-hs';
            entity.setAttribute('position', yawPitchToXYZ(hs.yaw, hs.pitch, 8));
            
            let color = '#38b2ac'; // info
            if (hs.hotspot_type === 'navigation') {
              color = '#5b5bd6'; // nav
            } else if (hs.hotspot_type === 'facility') {
              color = '#ed8936';
            }

            entity.innerHTML = `
              <a-sphere radius="0.25" color="${color}" opacity="0.9" class="clickable"
                event-set__mouseenter="scale: 1.3 1.3 1.3"
                event-set__mouseleave="scale: 1 1 1">
              </a-sphere>
              <a-text value="${hs.label}" align="center" position="0 0.38 0" color="#fff" width="3" wrap-count="20"></a-text>
            `;

            // Click behavior
            entity.querySelector('a-sphere').addEventListener('click', () => {
              if (hs.hotspot_type === 'navigation' && hs.to_scene_id) {
                openScene({
                  id: hs.to_scene_id,
                  title: hs.to_scene_title,
                  equirect_path: hs.to_scene_equirect,
                  initial_yaw: hs.to_scene_yaw,
                  initial_pitch: hs.to_scene_pitch
                });
              } else {
                openPopup({
                  label: hs.label,
                  popup_title: hs.label,
                  popup_html: hs.body_html
                });
              }
            });

            aScene.appendChild(entity);
          });
        }

        activate('360');
      };
      panoEl.src = scene.equirect_path;
      hintEl.textContent = scene.title ? `${scene.title} — drag to look around` : 'Drag to look around';

      if (!panoEl.complete) {
        // trigger load; onload fires when img loads
      } else {
        activate('360');
      }
    }

    // ─── 360 scene carousel ───────────────────────────────────────────────────
    function renderSceneCarousel() {
      const track = $('#scene-carousel-track');
      track.innerHTML = '';
      sceneCount = 0;

      if (!config.scenes) return;
      const scenes = Object.values(config.scenes);
      sceneCount = scenes.length;
      if (sceneCount === 0) return;

      scenes.forEach((scene) => {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'scene-card';
        card.dataset.sceneId = scene.id;
        card.setAttribute('aria-label', scene.title || 'Open scene');
        card.title = scene.title || '';

        const thumb = document.createElement('span');
        thumb.className = 'scene-card-thumb';

        if (scene.featured_image_path) {
          const img = document.createElement('img');
          img.alt = '';
          img.loading = 'lazy';
          img.src = scene.featured_image_path;
          thumb.appendChild(img);
        } else {
          const ph = document.createElement('span');
          ph.className = 'scene-card-placeholder';
          ph.textContent = (scene.title || '?').trim().charAt(0).toUpperCase();
          thumb.appendChild(ph);
        }
        card.appendChild(thumb);

        const label = document.createElement('span');
        label.className = 'scene-card-title';
        label.textContent = scene.title || '';
        card.appendChild(label);

        card.addEventListener('click', () => {
          openScene({
            id:            scene.id,
            title:         scene.title,
            equirect_path: scene.equirect_path,
            initial_yaw:   scene.initial_yaw,
            initial_pitch: scene.initial_pitch,
          });
        });

        track.appendChild(card);
      });
    }

    function setActiveScene(id) {
      const cards = document.querySelectorAll('#scene-carousel-track .scene-card');
      cards.forEach((card) => {
        const isActive = Number(card.dataset.sceneId) === Number(id);
        card.classList.toggle('active', isActive);
        if (isActive) {
          try { card.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' }); }
          catch (e) { card.scrollIntoView(true); }
        }
      });
    }

    // ─── info popup ──────────────────────────────────────────────────────────
    function openPopup(m) {
      $('#info-title').textContent = m.popup_title || m.label || 'Location';
      const body = $('#info-body');
      body.innerHTML = m.popup_html || '<p class="popup-empty">No additional info.</p>';
      $('#info-panel').classList.remove('hidden');
    }

    // ─── back navigation ─────────────────────────────────────────────────────
    $('#info-close') && $('#info-close').addEventListener('click', () => {
      $('#info-panel').classList.add('hidden');
    });

    // Back from 360 → floor plan (or root)
    $('#btn-360-back') && $('#btn-360-back').addEventListener('click', () => {
      if (config.landing_mode === 'floor_plan') {
        // return to the floor plan we came from
        openFloorPlan(
          config.starting_floor_plan.image_path,
          config.floor_plan_markers || [],
          config.short_name || 'ORG',
          false
        );
        fpStack = [];
      } else {
        window.history.back();
      }
    });

    // Back from floor plan → previous floor plan or homepage
    $('#btn-fp-back') && $('#btn-fp-back').addEventListener('click', () => {
      if (fpStack.length > 0) {
        const prev = fpStack.pop();
        currentMarkers = prev.markers;
        $('#fp-image').src = prev.imagePath;
        $('#fp-title').textContent = prev.title;
        renderMarkers(prev.markers);
      } else {
        // If we're at root floor plan, back goes to parent page if any
        window.history.back();
      }
    });

    // ─── lazy A-Frame loader (only injects when 360 mode is needed) ──────────
    let aframeReady = false;
    let aframeCallbacks = [];
    function loadAFrame(cb) {
      if (aframeReady) { cb(); return; }
      aframeCallbacks.push(cb);
      if (document.getElementById('aframe-script')) return;
      const s = document.createElement('script');
      s.id = 'aframe-script';
      s.src = 'assets/aframe.min.js';
      s.onload = () => { aframeReady = true; aframeCallbacks.forEach(fn => fn()); aframeCallbacks = []; };
      document.head.appendChild(s);
    }

    boot();
  })();
  </script>
</body>
</html><?php

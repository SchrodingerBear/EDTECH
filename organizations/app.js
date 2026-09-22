(() => {
  const $ = (s) => document.querySelector(s);
  const els = {
    loader: $('#loader'),
    m360: $('#mode-360'),
    mfp: $('#mode-floor'),
    mar: $('#mode-ar'),
  };

  let config = window.__CONFIG__;
  let fpStack = [];
  let currentMode = null;
  let sceneCount = 0;
  let activeSceneId = null;

  function yawPitchToXYZ(yawDeg, pitchDeg, r = 8) {
    const y = (yawDeg || 0) * Math.PI / 180;
    const p = (pitchDeg || 0) * Math.PI / 180;
    const x = r * Math.cos(p) * Math.sin(y);
    const z = -r * Math.cos(p) * Math.cos(y);
    const vY = r * Math.sin(p);
    return `${x.toFixed(4)} ${vY.toFixed(4)} ${z.toFixed(4)}`;
  }

  function boot() {
    if (!config) {
      config = {
        landing_mode: '360_rotation',
        require_landscape_mobile: true,
        floor_plans: {},
        starting_floor_plan: {},
        starting_scene: {}
      };
    }

    // Show landscape rotation modal on mobile portrait
    if (config.require_landscape_mobile && window.innerWidth < window.innerHeight && window.innerWidth < 768) {
      const landscapeModal = document.getElementById('landscape-modal');
      if (landscapeModal) {
        landscapeModal.classList.remove('hidden');
        document.getElementById('landscape-dismiss').addEventListener('click', () => {
          landscapeModal.classList.add('hidden');
        });
      }
    }

    if (config.theme) {
      const r = document.documentElement.style;
      if (config.theme.primary) r.setProperty('--ia-primary', config.theme.primary);
      if (config.theme.accent) r.setProperty('--ia-accent', config.theme.accent);
      if (config.theme.secondary) r.setProperty('--ia-secondary', config.theme.secondary);
    }

    // Initialization logic for scenes moved to sidebar in openFloorPlan


    // Set initial active scene if needed (removed setActiveScene call, just open it later)

    // Hide back button initially on landing
    const backBtn = $('#btn-back');
    if (backBtn) { backBtn.style.display = 'none'; }

    if (config.landing_mode === 'floor_plan' && config.starting_floor_plan && config.starting_floor_plan.image_path) {
      const fp = config.floor_plans[config.starting_floor_plan.id];
      const markers = fp ? fp.markers : [];
      openFloorPlan(config.starting_floor_plan.image_path, markers, config.short_name || 'ORG', false, config.starting_floor_plan.id);
    } else if (config.starting_scene && config.starting_scene.equirect_path) {
      // If starting in 360 mode, populate the sidebar with ALL scenes
      renderFloorPlanSidebar(null, null);
      openScene(config.starting_scene);
    } else {
      // Default to floor plan mode even if no specific floor plan
      activate('floor');
      // Show sidebar with all scenes as fallback
      renderFloorPlanSidebar(null, null);
    }
  }

  function activate(mode) {
    currentMode = mode;
    els.m360.classList.toggle('hidden', mode !== '360');
    els.mfp.classList.toggle('hidden', mode !== 'floor');
    if (els.mar) els.mar.classList.toggle('hidden', mode !== 'ar');
    els.loader.classList.add('hidden');
    // Carousel is inside 360 mode; sidebar is inside floor mode
    // Their visibility is managed by renderSceneCarousel / renderFloorPlanSidebar
  }

  function openFloorPlan(imagePath, markers, title, pushStack, floorPlanId) {
    if (pushStack !== false) {
      fpStack.push({ imagePath: $('#fp-image').src, markers: currentMarkers, title: $('#fp-title').textContent, floorPlanId: currentFloorPlanId });
    }
    currentMarkers = markers;
    currentFloorPlanId = floorPlanId || null;
    $('#fp-image').src = imagePath;
    $('#fp-title').textContent = title || 'ORG';
    renderMarkers(markers);
    renderFloorPlanSidebar(markers, floorPlanId);
    activate('floor');

    const backBtn = $('#btn-back');
    if (backBtn) {
      backBtn.style.display = fpStack.length > 0 ? '' : 'none';
    }
  }

  let currentMarkers = [];
  let currentFloorPlanId = null;

  // Derive cardinal direction label from facing_angle
  function angleToDirection(angle) {
    const a = ((angle % 360) + 360) % 360;
    if (a >= 337.5 || a < 22.5) return 'N';
    if (a < 67.5) return 'NE';
    if (a < 112.5) return 'E';
    if (a < 157.5) return 'SE';
    if (a < 202.5) return 'S';
    if (a < 247.5) return 'SW';
    if (a < 292.5) return 'W';
    return 'NW';
  }

  const DIRECTION_LABELS = {
    'N': 'North', 'NE': 'North East', 'E': 'East', 'SE': 'South East',
    'S': 'South', 'SW': 'South West', 'W': 'West', 'NW': 'North West'
  };

  function escHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // Render the collapsible 360° scene sidebar for the current floor plan
  function renderFloorPlanSidebar(markers, floorPlanId) {
    const list = $('#fp-scene-list');
    const sidebar = $('#fp-scene-sidebar');
    if (!list || !sidebar) return;
    list.innerHTML = '';

    // Gather linked scenes from markers (scene / 360 type)
    const linkedScenes = [];
    const seenIds = new Set();
    (markers || []).forEach(m => {
      const mtype = (m.marker_type || m.target_type || '').toLowerCase();
      if ((mtype === 'scene' || mtype === '360') && m.target_scene_id) {
        const scene = config.scenes && config.scenes[m.target_scene_id];
        if (scene && !seenIds.has(scene.id)) {
          seenIds.add(scene.id);
          linkedScenes.push({ scene, markerLabel: m.label });
        }
      }
    });

    // Always list EVERY 360 scene in the tour so room-only scenes stay reachable,
    // with the scenes linked to this floor plan shown first (carrying their label).
    const scenes = linkedScenes.slice();
    Object.values(config.scenes || {}).forEach(scene => {
      if (!seenIds.has(scene.id)) {
        seenIds.add(scene.id);
        scenes.push({ scene, markerLabel: null });
      }
    });

    if (scenes.length === 0) {
      sidebar.classList.add('fp-sidebar-hidden');
      return;
    }

    // Auto-show sidebar if there are scenes
    sidebar.classList.remove('fp-sidebar-hidden');

    scenes.forEach(({ scene, markerLabel }) => {
      const card = document.createElement('button');
      card.type = 'button';
      card.className = 'fp-scene-card';
      card.dataset.sceneId = scene.id;
      card.setAttribute('aria-label', scene.title || 'Open 360° scene');
      card.title = scene.title || '';

      const thumb = document.createElement('span');
      thumb.className = 'fp-scene-thumb';
      if (scene.featured_image_path) {
        const img = document.createElement('img');
        img.src = scene.featured_image_path;
        img.alt = '';
        img.loading = 'lazy';
        thumb.appendChild(img);
      } else {
        const ph = document.createElement('span');
        ph.className = 'fp-scene-placeholder';
        ph.textContent = (scene.title || '?').charAt(0).toUpperCase();
        thumb.appendChild(ph);
      }
      card.appendChild(thumb);

      const info = document.createElement('span');
      info.className = 'fp-scene-info';
      info.innerHTML = `<span class="fp-scene-title">${scene.title || ''}</span>`
        + (markerLabel ? `<span class="fp-scene-location">📍 ${markerLabel}</span>` : '');
      card.appendChild(info);

      const badge = document.createElement('span');
      badge.className = 'fp-scene-badge';
      badge.textContent = '360°';
      card.appendChild(badge);

      card.addEventListener('click', () => {
        openScene({
          id: scene.id,
          title: scene.title,
          equirect_path: scene.equirect_path,
          initial_yaw: scene.initial_yaw,
          initial_pitch: scene.initial_pitch,
        });
      });

      list.appendChild(card);
    });

    sidebar.classList.remove('fp-sidebar-hidden');
  }

  function renderMarkers(markers) {
    const box = $('#fp-markers');
    box.innerHTML = '';
    (markers || []).forEach((m) => {
      const b = document.createElement('button');
      b.className = 'fp-marker';
      b.style.left = m.x + '%';
      b.style.top = m.y + '%';
      b.style.width = (m.size_percent || 4) + 'vmin';
      b.style.height = (m.size_percent || 4) + 'vmin';
      b.setAttribute('aria-label', m.label);
      b.title = m.label;

      const mtype = (m.marker_type || m.target_type || 'scene').toLowerCase();

      if (mtype === 'compass') {
        b.classList.add('fp-marker-compass');
        // Screen angle = compass facing minus this plan's north rotation,
        // so the needle points at the true direction on THIS map.
        const fpMeta = currentFloorPlanId && config.floor_plans
          ? config.floor_plans[currentFloorPlanId]
          : (config.starting_floor_plan ? config.floor_plans[config.starting_floor_plan.id] : null);
        const north = fpMeta ? (parseFloat(fpMeta.north_angle) || 0) : 0;
        const planeAngle = (((parseFloat(m.facing_angle) || 0) - north) % 360 + 360) % 360;
        const dir = angleToDirection(m.facing_angle || 0);

        const badge = document.createElement('span');
        badge.className = 'fp-compass-badge';
        badge.innerHTML = `
              <svg viewBox="0 0 40 40" style="transform: rotate(${planeAngle}deg);" aria-hidden="true">
                <g stroke="#ffffff" stroke-width="1.5" stroke-linejoin="round">
                  <path d="M20 2.5 L27.5 20 L20 17.4 L12.5 20 Z" fill="#e11d48"/>
                  <path d="M20 37.5 L27.5 20 L20 22.6 L12.5 20 Z" fill="#94a3b8"/>
                </g>
                <circle cx="20" cy="20" r="3.4" fill="#ffffff" stroke="#64748b" stroke-width="1.4"/>
              </svg>`;
        b.appendChild(badge);
        b.title = 'Compass: ' + dir;
      } else if (mtype === 'ar') {
        b.classList.add('fp-marker-ar');
        const chip = document.createElement('span');
        chip.className = 'fp-marker-label';
        chip.textContent = m.label;
        b.appendChild(chip);
      } else if (mtype === 'floorplan') {
        b.classList.add('fp-marker-fp');
        const chip = document.createElement('span');
        chip.className = 'fp-marker-label';
        chip.textContent = m.label;
        b.appendChild(chip);
      } else if (mtype === 'info' || mtype === 'information') {
        b.classList.add('fp-marker-info');
        const chip = document.createElement('span');
        chip.className = 'fp-marker-label';
        chip.textContent = m.label;
        b.appendChild(chip);
      } else {
        // scene / 360 — pulsing
        b.classList.add('fp-marker-scene');
        const chip = document.createElement('span');
        chip.className = 'fp-marker-label';
        chip.textContent = m.label;
        b.appendChild(chip);
      }

      b.addEventListener('click', () => handleMarkerClick(m));
      box.appendChild(b);
    });
  }

  function handleMarkerClick(m) {
    const mtype = (m.marker_type || m.target_type || '').toLowerCase();

    if (mtype === 'scene' || mtype === '360') {
      const sceneId = m.target_scene_id;
      const targetScene = sceneId && config.scenes ? config.scenes[sceneId] : null;
      if (targetScene) {
        openScene({
          id: targetScene.id,
          title: targetScene.title,
          equirect_path: targetScene.equirect_path,
          initial_yaw: targetScene.initial_yaw,
          initial_pitch: targetScene.initial_pitch,
        });
      }
    } else if (mtype === 'floorplan') {
      const fpId = m.target_floor_plan_id;
      const fp = fpId && config.floor_plans ? config.floor_plans[fpId] : null;
      if (fp) {
        openFloorPlan(fp.image_path, fp.markers || [], fp.title || m.label, true, fpId);
      } else {
        openPopup(m);
      }
    } else if (mtype === 'compass') {
      showCompassPopup(m);
    } else if (mtype === 'ar') {
      if (m.marker_image_path) {
        showMediaPopup(m.marker_image_path, m.popup_title || m.label || 'AR');
      } else if (m.popup_title || m.popup_html) {
        openPopup(m);
      } else {
        openPopup({ ...m, popup_html: '', popup_title: m.label || 'AR' });
      }
    } else if (mtype === 'info' || mtype === 'information') {
      if (m.marker_image_path) {
        showMediaPopup(m.marker_image_path, m.popup_title || m.label || 'Photo');
      } else {
        openPopup(m);
      }
    } else {
      // scene with no target / unknown
      openPopup(m);
    }
  }

  function openScene(scene) {
    loadAFrame(() => _doOpenScene(scene));
  }
  function _doOpenScene(scene) {
    const panoEl = $('#pano');
    const skyEl = $('#sky');
    const hintEl = $('#scene-hint');

    $('#info-modal').classList.add('hidden');

    const brandTitle = $('#fp-title');
    if (brandTitle) { brandTitle.textContent = config.short_name || config.name || 'ORG'; }

    const backBtn = $('#btn-back');
    if (backBtn) {
      backBtn.style.display = (config.landing_mode === 'floor_plan') ? '' : 'none';
    }

    const loadHotspots = () => {
      document.querySelectorAll('.scene-hs').forEach(e => e.remove());
      const sceneData = config.scenes && config.scenes[scene.id];
      if (sceneData && sceneData.hotspots) {
        const aScene = $('#aframe-scene');
        sceneData.hotspots.forEach(hs => {
          const entity = document.createElement('a-entity');
          entity.className = 'scene-hs';
          entity.setAttribute('position', yawPitchToXYZ(hs.yaw, hs.pitch, 8));

          let color = '#38b2ac';
          if (hs.hotspot_type === 'navigation') color = '#5b5bd6';
          else if (hs.hotspot_type === 'facility') color = '#ed8936';

          entity.innerHTML = `
              <a-sphere radius="0.28" color="${color}" opacity="0.92" class="clickable"
                event-set__mouseenter="scale: 1.3 1.3 1.3"
                event-set__mouseleave="scale: 1 1 1">
              </a-sphere>
              <a-text value="${hs.label}" align="center" position="0 0.42 0" color="#fff" width="3" wrap-count="20"></a-text>
            `;

          entity.querySelector('a-sphere').addEventListener('click', () => {
            if (hs.hotspot_type === 'navigation' && hs.to_scene_id) {
              const targetScene = config.scenes[hs.to_scene_id];
              if (targetScene) {
                openScene(targetScene);
              }
            } else if (hs.hotspot_type === 'info') {
              openPopup({
                label: hs.label,
                popup_title: hs.label,
                popup_html: hs.body_html
              });
            } else if (hs.hotspot_type === 'media' && hs.media_path) {
              showMediaPopup(hs.media_path);
            } else {
              openPopup({
                label: hs.label,
                popup_title: hs.label,
                popup_html: hs.body_html || '<p>No additional info.</p>'
              });
            }
          });
          aScene.appendChild(entity);
        });
      }
    };

    // Activate 360 mode FIRST so the a-scene is visible before loading the texture.
    // A-Frame won't render inside a display:none element.
    activate('360');

    const finalizeLoad = () => {
      // Set sky src directly (not via asset) for reliable cross-scene switching
      skyEl.setAttribute('src', scene.equirect_path);
      skyEl.removeAttribute('rotation');

      const applyCamRotation = () => {
        const cam = document.querySelector('a-camera');
        if (!cam) return;
        const lc = cam.components && cam.components['look-controls'];
        if (lc && lc.pitchObject && lc.yawObject) {
          lc.pitchObject.rotation.x = (scene.initial_pitch || 0) * Math.PI / 180;
          lc.yawObject.rotation.y = -(scene.initial_yaw || 0) * Math.PI / 180;
        } else {
          const rig = document.getElementById('camera-rig');
          if (rig) rig.setAttribute('rotation', `0 ${scene.initial_yaw || 0} 0`);
          cam.setAttribute('rotation', `${scene.initial_pitch || 0} 0 0`);
        }
      };

      const sceneEl = document.getElementById('aframe-scene');
      if (sceneEl && sceneEl.hasLoaded) {
        applyCamRotation();
      } else if (sceneEl) {
        sceneEl.addEventListener('loaded', applyCamRotation, { once: true });
      }

      // Re-apply after the next couple of frames so the initial look survives
      // any camera re-initialisation A-Frame does while the sky texture swaps.
      requestAnimationFrame(() => requestAnimationFrame(applyCamRotation));

      loadHotspots();
    };

    // Kick off image load; if already cached the onload fires synchronously
    const srcAttr = panoEl.getAttribute('src');
    const isSameSrc = srcAttr === scene.equirect_path;
    const isLoaded = panoEl.complete && panoEl.naturalWidth > 0;

    panoEl.onload = finalizeLoad;
    panoEl.onerror = () => {
      console.warn('[360] pano load error, retrying:', scene.equirect_path);
      panoEl.onerror = null;
      panoEl.onload = finalizeLoad;
      panoEl.src = scene.equirect_path + '?t=' + Date.now();
    };

    if (isSameSrc && isLoaded) {
      // Re-opening the same scene: browsers will NOT re-fire `load` when the
      // src attribute is unchanged, so finalize (and re-apply the starting
      // point) manually instead of waiting for an event that never comes.
      finalizeLoad();
    } else {
      panoEl.src = scene.equirect_path;
    }

    hintEl.textContent = scene.title ? `${scene.title} — drag to look around` : 'Drag to look around';
  }


  function openPopup(m) {
    const title = m.popup_title || m.label || 'Location';
    const bodyHtml = m.popup_html || '<p class="popup-empty">No additional information.</p>';
    
    // Use the new modal instead of custom overlay
    showInfoModal(title, bodyHtml);
  }

  function showMediaPopup(mediaPath, title) {
    const overlay = document.createElement('div');
    overlay.className = 'media-popup-overlay';
    overlay.innerHTML = `
        <div class="media-popup-content">
          <button class="media-popup-close" aria-label="Close">×</button>
          <img src="${escHtml(mediaPath)}" alt="Media" loading="lazy">
        </div>
      `;

    // Close on button click
    overlay.querySelector('.media-popup-close').addEventListener('click', (e) => {
      e.stopPropagation();
      overlay.remove();
    });

    // Close on background click
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.remove();
      }
    });

    // Close on escape key
    const handleEscape = (e) => {
      if (e.key === 'Escape') {
        overlay.remove();
        document.removeEventListener('keydown', handleEscape);
      }
    };
    document.addEventListener('keydown', handleEscape);

    document.body.appendChild(overlay);

    // Prevent body scroll when popup is open
    document.body.style.overflow = 'hidden';
    overlay.addEventListener('remove', () => {
      document.body.style.overflow = '';
    });
  }

  function showCompassPopup(m) {
    const dir = angleToDirection(m.facing_angle || 0);
    const full = DIRECTION_LABELS[dir] || dir;
    const fpMeta = currentFloorPlanId && config.floor_plans
      ? config.floor_plans[currentFloorPlanId]
      : (config.starting_floor_plan ? config.floor_plans[config.starting_floor_plan.id] : null);
    const north = fpMeta ? (parseFloat(fpMeta.north_angle) || 0) : 0;
    const planeAngle = (((parseFloat(m.facing_angle) || 0) - north) % 360 + 360) % 360;
    const degrees = ((parseFloat(m.facing_angle) || 0) % 360 + 360) % 360;

    const overlay = document.createElement('div');
    overlay.className = 'media-popup-overlay';
    overlay.innerHTML = `
        <div class="compass-card">
          <button class="media-popup-close" aria-label="Close">×</button>
          <div class="compass-card-badge">
            <svg viewBox="0 0 40 40" style="transform: rotate(${planeAngle}deg);" aria-hidden="true">
              <g stroke="#ffffff" stroke-width="1.5" stroke-linejoin="round">
                <path d="M20 2.5 L27.5 20 L20 17.4 L12.5 20 Z" fill="#e11d48"/>
                <path d="M20 37.5 L27.5 20 L20 22.6 L12.5 20 Z" fill="#94a3b8"/>
              </g>
              <circle cx="20" cy="20" r="3.4" fill="#ffffff" stroke="#64748b" stroke-width="1.4"/>
            </svg>
          </div>
          <div class="compass-card-dir">${escHtml(full)}</div>
          <div class="compass-card-deg">${Math.round(degrees)}° · ${escHtml(dir)}</div>
          ${m.label ? `<div class="compass-card-label">${escHtml(m.label)}</div>` : ''}
        </div>
      `;

    // Close on button click
    overlay.querySelector('.media-popup-close').addEventListener('click', (e) => {
      e.stopPropagation();
      overlay.remove();
    });

    // Close on background click
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.remove();
      }
    });

    // Close on escape key
    const handleEscape = (e) => {
      if (e.key === 'Escape') {
        overlay.remove();
        document.removeEventListener('keydown', handleEscape);
      }
    };
    document.addEventListener('keydown', handleEscape);

    document.body.appendChild(overlay);

    // Prevent body scroll when popup is open
    document.body.style.overflow = 'hidden';
    overlay.addEventListener('remove', () => {
      document.body.style.overflow = '';
    });
  }

  $('#info-close') && $('#info-close').addEventListener('click', () => {
    const infoModal = $('#info-modal');
    if (infoModal) infoModal.classList.add('hidden');
  });

  // Close modal on background click
  const infoModal = $('#info-modal');
  if (infoModal) {
    infoModal.addEventListener('click', (e) => {
      if (e.target === infoModal) {
        infoModal.classList.add('hidden');
      }
    });
  }

  // Close modal on escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const modal = $('#info-modal');
      if (modal && !modal.classList.contains('hidden')) {
        modal.classList.add('hidden');
      }
    }
  });

  // Function to show info modal with title and description
  function showInfoModal(title, description) {
    const infoModal = $('#info-modal');
    const infoTitle = $('#info-title');
    const infoBody = $('#info-body');

    if (infoModal && infoTitle && infoBody) {
      infoTitle.textContent = title || '';
      infoBody.innerHTML = description || '';
      infoModal.classList.remove('hidden');
    }
  }

  // Search functionality
  const searchModal = $('#search-modal');
  const searchInput = $('#search-input');
  const searchResults = $('#search-results');
  const searchBackdrop = $('#search-backdrop');

  function openSearch() {
    searchModal.classList.remove('hidden');
    searchInput.focus();
    document.body.style.overflow = 'hidden';
  }

  function closeSearch() {
    searchModal.classList.add('hidden');
    searchInput.value = '';
    searchResults.innerHTML = '<div class="search-empty">Type to search locations...</div>';
    document.body.style.overflow = '';
  }

  function performSearch(query) {
    if (!query || query.trim() === '') {
      searchResults.innerHTML = '<div class="search-empty">Type to search locations...</div>';
      return;
    }

    const q = query.toLowerCase().trim();
    const results = [];

    // Search scenes (360)
    if (config.scenes) {
      Object.values(config.scenes).forEach(scene => {
        const titleMatch = scene.title && scene.title.toLowerCase().includes(q);
        const descMatch = (scene.description || scene.ai_description || '').toLowerCase().includes(q);
        if (titleMatch || descMatch) {
          results.push({
            type: '360',
            title: scene.title,
            description: scene.description || scene.ai_description || '360° virtual tour scene',
            data: scene
          });
        }
      });
    }

    // Search floor plans + their markers
    if (config.floor_plans) {
      // config.floor_plans is an object keyed by id
      const fpList = typeof config.floor_plans === 'object' && !Array.isArray(config.floor_plans)
        ? Object.values(config.floor_plans)
        : config.floor_plans;

      fpList.forEach(fp => {
        // Match floor plan title
        if (fp.title && fp.title.toLowerCase().includes(q)) {
          results.push({
            type: 'floorplan',
            title: fp.title,
            description: `Floor plan · ${(fp.markers || []).length} markers`,
            data: fp
          });
        }

        // Search markers within this floor plan
        (fp.markers || []).forEach(marker => {
          if (!marker.label || !marker.label.toLowerCase().includes(q)) return;

          const mtype = (marker.marker_type || marker.target_type || '').toLowerCase();
          const details = [];
          details.push(`In: ${fp.title}`);

          if ((mtype === 'scene' || mtype === '360') && marker.target_scene_id) {
            const scene = config.scenes && config.scenes[marker.target_scene_id];
            details.push(scene ? `→ 360 Scene: ${scene.title}` : '→ 360 Scene');
          } else if (mtype === 'floorplan') {
            details.push('→ Sub floor plan');
          } else if (mtype === 'compass') {
            details.push('Direction indicator');
          } else if (mtype === 'ar') {
            details.push('AR view');
          } else if (mtype === 'info' || mtype === 'information') {
            details.push('Information point');
          }

          results.push({
            type: 'marker',
            title: marker.label,
            description: details.join(' • '),
            data: marker,
            floorPlan: fp
          });
        });
      });
    }

    if (results.length === 0) {
      searchResults.innerHTML = '<div class="search-empty">No locations found</div>';
      return;
    }

    searchResults.innerHTML = '';
    results.forEach(result => {
      const item = document.createElement('div');
      item.className = 'search-result-item';

      // Type badge
      let badgeClass = 'badge-marker';
      let badgeLabel = 'Location';
      if (result.type === '360') { badgeClass = 'badge-360'; badgeLabel = '360°'; }
      else if (result.type === 'floorplan') { badgeClass = 'badge-floorplan'; badgeLabel = 'Floor Plan'; }

      // Action buttons
      let actionsHTML = '';
      if (result.type === '360') {
        actionsHTML = `<div class="search-result-actions">
              <button class="search-action-btn primary" data-action="open360">▶ Open 360°</button>
            </div>`;
      } else if (result.type === 'floorplan') {
        actionsHTML = `<div class="search-result-actions">
              <button class="search-action-btn primary" data-action="openFP">🗺 View Floor Plan</button>
            </div>`;
      } else if (result.type === 'marker') {
        const m = result.data;
        const mtype = (m.marker_type || m.target_type || '').toLowerCase();
        if ((mtype === 'scene' || mtype === '360') && m.target_scene_id) {
          actionsHTML = `<div class="search-result-actions">
                <button class="search-action-btn" data-action="openFP">🗺 Show on Map</button>
                <button class="search-action-btn primary" data-action="open360">▶ Open 360°</button>
              </div>`;
        } else if (mtype === 'floorplan') {
          actionsHTML = `<div class="search-result-actions">
                <button class="search-action-btn primary" data-action="openFP">🗺 View Floor Plan</button>
              </div>`;
        } else if (mtype === 'compass') {
          // No action buttons for compass
          actionsHTML = '';
        } else {
          actionsHTML = `<div class="search-result-actions">
                <button class="search-action-btn" data-action="openFP">🗺 Show on Map</button>
              </div>`;
        }
      }

      item.innerHTML = `
            <div class="search-result-type"><span class="search-result-type-badge ${badgeClass}">${badgeLabel}</span></div>
            <div class="search-result-title">${result.title}</div>
            <div class="search-result-desc">${result.description}</div>
            ${actionsHTML}
          `;

      // Handle action button clicks
      item.querySelectorAll('.search-action-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const action = btn.dataset.action;
          if (action === 'open360') {
            closeSearch();
            if (result.type === '360') {
              openScene(result.data);
            } else if (result.type === 'marker') {
              const mtype = (result.data.marker_type || '').toLowerCase();
              if ((mtype === 'scene' || mtype === '360') && result.data.target_scene_id) {
                const sc = config.scenes && config.scenes[result.data.target_scene_id];
                if (sc) openScene(sc);
              }
            }
          } else if (action === 'openFP') {
            closeSearch();
            if (result.type === 'floorplan') {
              openFloorPlan(result.data.image_path, result.data.markers || [], result.data.title, true);
            } else if (result.type === 'marker' && result.floorPlan) {
              openFloorPlan(result.floorPlan.image_path, result.floorPlan.markers || [], result.floorPlan.title, true);
            }
          }
        });
      });

      // Click on the item body (not button) = default action
      item.addEventListener('click', (e) => {
        if (e.target.closest('.search-action-btn')) return;
        handleSearchResult(result);
        closeSearch();
      });

      searchResults.appendChild(item);
    });
  }

  function handleSearchResult(result) {
    if (result.type === '360') {
      openScene({
        id: result.data.id,
        title: result.data.title,
        equirect_path: result.data.equirect_path,
        initial_yaw: result.data.initial_yaw,
        initial_pitch: result.data.initial_pitch,
      });
    } else if (result.type === 'floorplan') {
      openFloorPlan(result.data.image_path, result.data.markers || [], result.data.title, true, result.data.id);
    } else if (result.type === 'marker') {
      const marker = result.data;
      const mtype = (marker.marker_type || '').toLowerCase();
      if ((mtype === 'scene' || mtype === '360') && marker.target_scene_id) {
        const scene = config.scenes && config.scenes[marker.target_scene_id];
        if (scene) openScene(scene);
      } else if (mtype === 'floorplan' && marker.target_floor_plan_id) {
        const fp = config.floor_plans && config.floor_plans[marker.target_floor_plan_id];
        if (fp) openFloorPlan(fp.image_path, fp.markers || [], fp.title, true, fp.id);
        else if (result.floorPlan) openFloorPlan(result.floorPlan.image_path, result.floorPlan.markers || [], result.floorPlan.title, true, result.floorPlan.id);
      } else if (result.floorPlan) {
        openFloorPlan(result.floorPlan.image_path, result.floorPlan.markers || [], result.floorPlan.title, true, result.floorPlan.id);
      } else {
        openPopup({ label: marker.label, popup_title: marker.label, popup_html: `<p>${result.description}</p>` });
      }
    }
  }

  $('#btn-search-close') && $('#btn-search-close').addEventListener('click', closeSearch);
  searchBackdrop && searchBackdrop.addEventListener('click', closeSearch);

  // Standalone search & fullscreen toggles
  $('#btn-search-toggle') && $('#btn-search-toggle').addEventListener('click', openSearch);
  $('#btn-fullscreen-toggle') && $('#btn-fullscreen-toggle').addEventListener('click', toggleFullscreen);

  // 360 scenes sidebar toggle (hide/unhide)
  $('#btn-scenes-toggle') && $('#btn-scenes-toggle').addEventListener('click', () => {
    const fpSidebar = $('#fp-scene-sidebar');
    fpSidebar && fpSidebar.classList.toggle('fp-sidebar-hidden');
  });

  searchInput && searchInput.addEventListener('input', (e) => {
    performSearch(e.target.value);
  });

  // Close search on escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !searchModal.classList.contains('hidden')) {
      closeSearch();
    }
  });

  // Sidebar close
  const fpSidebar = $('#fp-scene-sidebar');
  $('#btn-sidebar-close') && $('#btn-sidebar-close').addEventListener('click', () => {
    fpSidebar && fpSidebar.classList.add('fp-sidebar-hidden');
  });

  // Fullscreen functionality
  function toggleFullscreen() {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(err => {
        console.log('Fullscreen error:', err);
      });
    } else {
      document.exitFullscreen();
    }
  }

  $('#btn-back') && $('#btn-back').addEventListener('click', () => {
    if (currentMode === '360') {
      if (config.landing_mode === 'floor_plan' && config.starting_floor_plan) {
        const fp = config.floor_plans && config.floor_plans[config.starting_floor_plan.id];
        openFloorPlan(
          config.starting_floor_plan.image_path,
          fp ? fp.markers : [],
          config.short_name || 'ORG',
          false,
          config.starting_floor_plan.id
        );
        fpStack = [];
      } else {
        window.history.back();
      }
    } else if (currentMode === 'floor' && fpStack.length > 0) {
      const prev = fpStack.pop();
      currentMarkers = prev.markers;
      currentFloorPlanId = prev.floorPlanId || null;
      $('#fp-image').src = prev.imagePath;
      $('#fp-title').textContent = prev.title;
      renderMarkers(prev.markers);
      renderFloorPlanSidebar(prev.markers, prev.floorPlanId);

      const backBtn = $('#btn-back');
      if (backBtn) {
        backBtn.style.display = fpStack.length > 0 ? '' : 'none';
      }
    }
  });

  let aframeReady = false;
  let aframeCallbacks = [];

  function loadAFrame(cb) {
    if (aframeReady) { cb(); return; }
    aframeCallbacks.push(cb);
    if (document.getElementById('aframe-script')) return;
    const s = document.createElement('script');
    s.id = 'aframe-script';
    s.src = window.__CONFIG__.aframe_url;
    s.onload = () => { aframeReady = true; aframeCallbacks.forEach(fn => fn()); aframeCallbacks = []; };
    document.head.appendChild(s);
  }

  // AR Navigation Bridge (used by ar.js)
  window.__iaNav = {
    getCurrentMode: () => currentMode,
    activate: (mode) => activate(mode),
    openScene: (sceneId) => {
      const scene = config.scenes && config.scenes[sceneId];
      if (scene) openScene(scene);
    },
    openFloorPlan: (fpId) => {
      const fp = config.floor_plans && config.floor_plans[fpId];
      if (fp) {
        openFloorPlan(fp.image_path, fp.markers || [], fp.title, true, fpId);
      }
    },
    openPopup: (data) => {
      const title = data.popup_title || data.label || 'Location';
      const bodyHtml = data.popup_html || data.popup_description || '<p class="popup-empty">No additional information.</p>';
      openPopup({ popup_title: title, popup_html: bodyHtml, popup_image_path: data.popup_image_path });
    },
    showMediaPopup: (mediaPath, title) => showMediaPopup(mediaPath, title),
  };

  boot();
})();

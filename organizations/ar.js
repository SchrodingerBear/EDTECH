(() => {
  const $ = (s) => document.querySelector(s);
  const $$ = (s) => document.querySelectorAll(s);

  let config = window.__CONFIG__;
  let arConfig = config?.ar || {};
  let currentArFloorPlanId = null;
  let currentArFloorPlanData = null;
  let arTargetMapping = [];
  let arScene = null;
  let mindarSystem = null;
  let isArActive = false;
  let previousMode = null;
  let deviceOrientationHandler = null;
  let mindarReady = false;
  let mindarCallbacks = [];

  const MINDAR_URL = arConfig.mindar_url || 'https://cdn.jsdelivr.net/npm/mind-ar@1.2.5/dist/mindar-image-aframe.prod.js';
  const AFRAME_URL = config?.aframe_url || 'https://aframe.io/releases/1.4.2/aframe.min.js';

  let aframeReady = false;
  let aframeCallbacks = [];

  function loadAFrame(cb) {
    if (aframeReady) { cb(); return; }
    aframeCallbacks.push(cb);
    if (document.getElementById('aframe-script')) return;
    const s = document.createElement('script');
    s.id = 'aframe-script';
    s.src = AFRAME_URL;
    s.onload = () => { aframeReady = true; aframeCallbacks.forEach(fn => fn()); aframeCallbacks = []; };
    s.onerror = () => { console.error('[AR] Failed to load A-Frame'); aframeCallbacks.forEach(fn => fn()); aframeCallbacks = []; };
    document.head.appendChild(s);
  }

  function loadMindAR(cb) {
    if (mindarReady) { cb(); return; }
    mindarCallbacks.push(cb);
    if (document.getElementById('mindar-script')) return;
    // Load A-Frame first, then MindAR
    loadAFrame(() => {
      const s = document.createElement('script');
      s.id = 'mindar-script';
      s.src = MINDAR_URL;
      s.onload = () => { mindarReady = true; mindarCallbacks.forEach(fn => fn()); mindarCallbacks = []; };
      s.onerror = () => { console.error('[AR] Failed to load MindAR'); mindarCallbacks.forEach(fn => fn()); mindarCallbacks = []; };
      document.head.appendChild(s);
    });
  }

  const els = {
    modeAr: $('#mode-ar'),
    btnArToggle: $('#btn-ar-toggle'),
    btnArBack: $('#btn-ar-back'),
    btnScenesToggle: $('#btn-scenes-toggle'),
    btnFullscreenToggle: $('#btn-fullscreen-toggle'),
    arLoading: $('#ar-loading'),
    arLoadingText: $('#ar-loading-text'),
    arBuildingSelect: $('#ar-building-select'),
    arFloorplanSelect: $('#ar-floorplan-select'),
    arCompassSvg: $('.ar-compass-svg'),
    arCompassDir: $('#ar-compass-dir'),
    arLocationChip: $('#ar-location-chip'),
    arLocationText: $('#ar-location-text'),
    arResultCard: $('#ar-result-card'),
    arResultClose: $('#ar-result-card .ar-result-close'),
    arResultImage: $('#ar-result-image'),
    arResultTitle: $('#ar-result-title'),
    arResultDesc: $('#ar-result-description'),
    arResultActions: $('#ar-result-actions'),
    arHudTopLeft: $('#ar-hud-top-left'),
    arHudTopRight: $('#ar-hud-top-right'),
    arHudBottomLeft: $('#ar-hud-bottom-left'),
  };

  function boot() {
    if (!config) return;
    if (!arConfig.floor_plans?.length) return;

    // Populate building dropdown
    populateBuildingSelect();

    // Event listeners
    els.btnArToggle?.addEventListener('click', toggleArMode);
    els.btnArBack?.addEventListener('click', exitArMode);
    els.btnFullscreenToggle?.addEventListener('click', toggleFullscreen);
    els.arBuildingSelect?.addEventListener('change', onBuildingChange);
    els.arFloorplanSelect?.addEventListener('change', onFloorPlanChange);
    els.arResultClose?.addEventListener('click', hideResultCard);

    // Initialize compass
    initCompass();
  }

  function populateBuildingSelect() {
    const select = els.arBuildingSelect;
    if (!select) return;

    const buildings = arConfig.buildings || [];
    const defaultFp = arConfig.floor_plans.find(fp => fp.id === arConfig.default_floor_plan_id);
    const defaultBuildingId = defaultFp?.building_id || 0;

    select.innerHTML = '<option value="0">All Buildings</option>';
    buildings.forEach(b => {
      const opt = document.createElement('option');
      opt.value = b.id;
      opt.textContent = b.name;
      select.appendChild(opt);
    });
    select.value = defaultBuildingId;
    select.disabled = false;

    // Trigger floor plan population
    onBuildingChange();
  }

  function onBuildingChange() {
    const buildingId = parseInt(els.arBuildingSelect?.value, 10) || 0;
    const fpSelect = els.arFloorplanSelect;
    if (!fpSelect) return;

    const floorPlans = arConfig.floor_plans
      .filter(fp => buildingId === 0 || parseInt(fp.building_id, 10) === buildingId)
      .sort((a, b) => {
        const fa = a.floor_level || '';
        const fb = b.floor_level || '';
        return fa.localeCompare(fb, undefined, { numeric: true }) || a.title.localeCompare(b.title);
      });

    fpSelect.innerHTML = '<option value="0">Select floor plan</option>';
    floorPlans.forEach(fp => {
      const opt = document.createElement('option');
      opt.value = fp.id;
      const label = fp.title + (fp.floor_level ? ` (${fp.floor_level})` : '');
      opt.textContent = label;
      fpSelect.appendChild(opt);
    });
    fpSelect.disabled = floorPlans.length === 0;

    // Auto-select default if in this building
    if (floorPlans.length > 0) {
      const def = floorPlans.find(fp => fp.id === arConfig.default_floor_plan_id) || floorPlans[0];
      fpSelect.value = def.id;
      onFloorPlanChange();
    }
  }

  function onFloorPlanChange() {
    const fpId = parseInt(els.arFloorplanSelect?.value, 10) || 0;
    if (!fpId) return;
    loadArFloorPlan(fpId);
  }

  async function loadArFloorPlan(fpId) {
    const fpData = arConfig.floor_plans.find(fp => fp.id === fpId);
    if (!fpData || !fpData.mind_url) {
      showLoading('No .mind file for this floor plan');
      setTimeout(hideLoading, 2000);
      return;
    }

    currentArFloorPlanId = fpId;
    currentArFloorPlanData = fpData;
    arTargetMapping = fpData.target_mapping || [];

    // Update compass with floor plan's north angle
    updateCompass(fpData.north_angle || 0);

    // Update location chip
    updateLocationText(`Current floor: ${fpData.title}${fpData.floor_level ? ` (${fpData.floor_level})` : ''}`);

    // Load MindAR scene
    await loadMindArScene(fpData.mind_url);
  }

  function loadMindArScene(mindUrl) {
    return new Promise((resolve) => {
      loadMindAR(() => {
        showLoading('Loading AR scene&hellip;');

        // Remove existing scene
        if (arScene) {
          arScene.remove();
          arScene = null;
        }

        // Create new A-Frame scene - match reference exactly
        const container = els.modeAr;
        const sceneEl = document.createElement('a-scene');
        sceneEl.id = 'ar-scene';
        sceneEl.setAttribute('embedded', '');
        sceneEl.setAttribute('vr-mode-ui', 'enabled: false');
        sceneEl.setAttribute('renderer', 'colorManagement: true, physicallyCorrectLights');
        sceneEl.setAttribute('color-space', 'sRGB');
        sceneEl.setAttribute('device-orientation-permission-ui', 'enabled: false');
        sceneEl.setAttribute('mindar-image',
          `imageTargetSrc: ${mindUrl}; filterMinCF: 0.0001; filterBeta: 0.001; warmupTolerance: 5; missTolerance: 5;`
        );

        // Append to DOM FIRST so A-Frame initializes properly
        container.appendChild(sceneEl);
        arScene = sceneEl;

        // Build target entities for each target in mapping
        let targetEntitiesHtml = '<a-camera position="0 0 0" look-controls="enabled: false"></a-camera>';
        arTargetMapping.forEach((mapping, idx) => {
          targetEntitiesHtml += `<a-entity mindar-image-target="targetIndex: ${mapping.index}"></a-entity>`;
        });
        sceneEl.innerHTML = targetEntitiesHtml;

        // Wait for MindAR to load
        sceneEl.addEventListener('loaded', () => {
          mindarSystem = sceneEl.systems['mindar-image-system'];
          setupTargetListeners();
          hideLoading();
          resolve();
        });

        // Error handling
        sceneEl.addEventListener('error', (e) => {
          console.error('[AR] Scene error:', e);
          showLoading('Failed to load AR scene');
          setTimeout(hideLoading, 3000);
          resolve();
        });
      });
    });
  }

  function setupTargetListeners() {
    if (!mindarSystem) return;

    // MindAR fires targetFound/targetLost on the a-scene
    arScene.addEventListener('targetFound', (e) => {
      const targetIndex = e.detail?.targetIndex ?? e.target?.getAttribute('mindar-image-target')?.targetIndex;
      handleTargetFound(targetIndex);
    });

    arScene.addEventListener('targetLost', (e) => {
      const targetIndex = e.detail?.targetIndex ?? e.target?.getAttribute('mindar-image-target')?.targetIndex;
      handleTargetLost(targetIndex);
    });

    // Alternative: listen on mindar-image-system
    if (mindarSystem && mindarSystem.el) {
      mindarSystem.el.addEventListener('targetFound', (e) => handleTargetFound(e.detail.targetIndex));
      mindarSystem.el.addEventListener('targetLost', (e) => handleTargetLost(e.detail.targetIndex));
    }
  }

  function handleTargetFound(index) {
    const mapping = arTargetMapping.find(m => m.index === index);
    if (!mapping) return;

    const target = mapping.target;
    console.log('[AR] Target found:', target.name, 'index:', index, 'id:', target.id, 'facing:', target.facing_angle);

    // Update location
    updateLocationText(`Current location: ${target.name}`);

    // Update compass to target's facing direction
    if (target.facing_angle !== undefined && target.facing_angle !== null) {
      setCompassToTarget(target.facing_angle);
    }

    // Show result card
    showResultCard(target);
  }

  function handleTargetLost(index) {
    // Revert compass to device orientation
    compassTargetHeading = null;
  }

  function showResultCard(target) {
    const card = els.arResultCard;
    if (!card) return;

    // Image: popup_image > target image > placeholder
    let imgUrl = target.popup_image_path || target.image_path;
    if (imgUrl && !imgUrl.startsWith('http')) {
      // Already absolute URLs from config
    }
    els.arResultImage.src = "../" + imgUrl || '';
    els.arResultImage.alt = target.name;

    // Title
    els.arResultTitle.textContent = target.popup_title || target.name;

    // Description
    els.arResultDesc.textContent = target.popup_description || '';
    els.arResultDesc.style.display = target.popup_description ? '' : 'none';

    // Actions
    const actions = els.arResultActions;
    actions.innerHTML = '';

    // Open 360 button
    if (target.target_scene_id && target.ar_type === '360_ar_hotspot') {
      const btn = document.createElement('button');
      btn.className = 'ar-result-btn ar-result-btn-primary';
      btn.textContent = 'Open 360°';
      btn.addEventListener('click', () => {
        hideResultCard();
        exitArMode(() => openSceneBridge(target.target_scene_id));
      });
      actions.appendChild(btn);
    }

    // View Floor Plan button
    if (target.target_floor_plan_id && target.target_floor_plan_id !== currentArFloorPlanId) {
      const btn = document.createElement('button');
      btn.className = 'ar-result-btn';
      btn.textContent = 'View Floor Plan';
      btn.addEventListener('click', () => {
        hideResultCard();
        exitArMode(() => openFloorPlanBridge(target.target_floor_plan_id));
      });
      actions.appendChild(btn);
    }

    // If no actions, show close only (or auto-close after delay?)
    if (actions.children.length === 0) {
      const btn = document.createElement('button');
      btn.className = 'ar-result-btn';
      btn.textContent = 'Close';
      btn.addEventListener('click', hideResultCard);
      actions.appendChild(btn);
    }

    card.classList.remove('hidden');
  }

  function hideResultCard() {
    els.arResultCard?.classList.add('hidden');
  }

  function updateLocationText(text) {
    if (els.arLocationText) {
      els.arLocationText.textContent = text;
    }
  }

  function showLoading(text) {
    if (els.arLoadingText) els.arLoadingText.innerHTML = text;
    els.arLoading?.classList.remove('hidden');
  }

  function hideLoading() {
    els.arLoading?.classList.add('hidden');
  }

  // Compass
  function initCompass() {
    if (typeof DeviceOrientationEvent !== 'undefined' && DeviceOrientationEvent.requestPermission) {
      // iOS 13+ requires permission
      const requestBtn = document.createElement('button');
      requestBtn.className = 'ar-compass-permission-btn';
      requestBtn.textContent = 'Enable Compass';
      requestBtn.addEventListener('click', async () => {
        try {
          const permission = await DeviceOrientationEvent.requestPermission();
          if (permission === 'granted') {
            startCompass();
            requestBtn.remove();
          }
        } catch (e) {
          console.warn('[AR] Compass permission denied');
        }
      });
      els.arCompassSvg?.parentElement?.insertBefore(requestBtn, els.arCompassSvg.nextSibling);
    } else {
      startCompass();
    }
  }

  function startCompass() {
    deviceOrientationHandler = (e) => {
      // If we have a target heading, use that instead of device orientation
      if (compassTargetHeading !== null) {
        const northAngle = currentArFloorPlanData?.north_angle || 0;
        const adjustedHeading = (compassTargetHeading - northAngle + 360) % 360;
        if (els.arCompassSvg) {
          els.arCompassSvg.style.transform = `rotate(${-adjustedHeading}deg)`;
        }
        if (els.arCompassDir) {
          els.arCompassDir.textContent = angleToDirection(compassTargetHeading);
        }
        return;
      }

      let heading = e.webkitCompassHeading ?? e.alpha;
      if (heading === null || heading === undefined) return;

      // Adjust for floor plan north angle
      const northAngle = currentArFloorPlanData?.north_angle || 0;
      heading = (heading - northAngle + 360) % 360;

      // Rotate compass needle
      if (els.arCompassSvg) {
        els.arCompassSvg.style.transform = `rotate(${-heading}deg)`;
      }

      // Update direction label
      const dir = angleToDirection(heading);
      if (els.arCompassDir) {
        els.arCompassDir.textContent = dir;
      }
    };
    window.addEventListener('deviceorientation', deviceOrientationHandler, true);
  }

  function updateCompass(northAngle) {
    // Initial rotation based on north angle
    if (els.arCompassSvg) {
      els.arCompassSvg.style.transform = `rotate(${northAngle}deg)`;
    }
  }

  let compassTargetHeading = null;

  function setCompassToTarget(facingAngle) {
    // Target's facing_angle is the direction the marker faces on the map
    // Compass should point to that direction (so user knows where to face)
    compassTargetHeading = facingAngle;
    const northAngle = currentArFloorPlanData?.north_angle || 0;
    // Adjust for floor plan's north rotation
    const adjustedHeading = (facingAngle - northAngle + 360) % 360;
    if (els.arCompassSvg) {
      els.arCompassSvg.style.transform = `rotate(${-adjustedHeading}deg)`;
    }
    if (els.arCompassDir) {
      els.arCompassDir.textContent = angleToDirection(facingAngle);
    }
  }

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

  // AR Mode toggle
  function toggleArMode() {
    if (isArActive) {
      exitArMode();
    } else {
      enterArMode();
    }
  }

  function enterArMode() {
    if (isArActive) return;

    // Store current mode
    previousMode = window.__iaNav?.getCurrentMode?.() || 'floor';

    // Hide other modes
    $('#mode-360')?.classList.add('hidden');
    $('#mode-floor')?.classList.add('hidden');
    els.modeAr?.classList.remove('hidden');

    // Hide 360 scenes toggle, keep others
    els.btnScenesToggle?.classList.add('hidden');
    els.btnArToggle?.classList.add('ar-active'); // visual feedback

    // Show AR HUDs
    els.arHudTopLeft?.classList.remove('hidden');
    els.arHudTopRight?.classList.remove('hidden');

    // Load default floor plan
    const defaultId = arConfig.default_floor_plan_id;
    if (defaultId) {
      // Set selects to default
      const defFp = arConfig.floor_plans.find(fp => fp.id === defaultId);
      if (defFp) {
        els.arBuildingSelect.value = defFp.building_id || 0;
        onBuildingChange();
        // After building change populates floor plans, select default
        setTimeout(() => {
          els.arFloorplanSelect.value = defaultId;
          onFloorPlanChange();
        }, 50);
      }
    }

    isArActive = true;
    requestCameraPermission();
  }

  function exitArMode(callback) {
    if (!isArActive) { callback?.(); return; }

    // Clean up
    if (arScene) {
      arScene.remove();
      arScene = null;
      mindarSystem = null;
    }
    if (deviceOrientationHandler) {
      window.removeEventListener('deviceorientation', deviceOrientationHandler);
      deviceOrientationHandler = null;
    }

    // Hide AR mode
    els.modeAr?.classList.add('hidden');
    els.arHudTopLeft?.classList.add('hidden');
    els.arHudTopRight?.classList.add('hidden');
    hideResultCard();
    hideLoading();

    // Restore other modes
    els.btnScenesToggle?.classList.remove('hidden');
    els.btnArToggle?.classList.remove('ar-active');

    // Reactivate previous mode
    if (window.__iaNav?.activate) {
      window.__iaNav.activate(previousMode);
    } else if (previousMode === '360') {
      $('#mode-360')?.classList.remove('hidden');
    } else {
      $('#mode-floor')?.classList.remove('hidden');
    }

    isArActive = false;
    callback?.();
  }

  async function requestCameraPermission() {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      stream.getTracks().forEach(t => t.stop());
    } catch (e) {
      console.warn('[AR] Camera permission denied:', e);
      showLoading('Camera permission required for AR');
    }
  }

  // Bridge to main app navigation
  function openSceneBridge(sceneId) {
    if (window.__iaNav?.openScene) {
      window.__iaNav.openScene(sceneId);
    }
  }

  function openFloorPlanBridge(fpId) {
    if (window.__iaNav?.openFloorPlan) {
      window.__iaNav.openFloorPlan(fpId);
    }
  }

  // Expose for debugging
  window.__iaAr = {
    enter: enterArMode,
    exit: exitArMode,
    loadFloorPlan: loadArFloorPlan,
    getMapping: () => arTargetMapping,
    getCurrentFloorPlan: () => currentArFloorPlanId,
  };

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

  // Auto-start when DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
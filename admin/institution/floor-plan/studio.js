document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('studio-canvas');
    const markersLayer = document.getElementById('markers-layer');
    const tools = document.querySelectorAll('.marker-tool');
    const propPanel = document.getElementById('properties-content');
    const propsTitle = document.getElementById('props-panel-title');
    const saveBtn = document.getElementById('save-studio-btn');
    const statusHint = document.getElementById('canvas-status-hint');
    const activeToolBadge = document.getElementById('active-tool-badge');
    const activeToolName = document.getElementById('active-tool-name');

    let markers = [];
    let selectedMarkerId = null;
    let activePlacementType = null;
    let activeFilter = 'all';
    let hasUnsavedChanges = false;

    // Helper: Escapes HTML for safe injection
    function esc(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Helper: Resolve media full URL
    function mediaUrl(path) {
        if (!path) return '';
        if (/^(https?:)?\/\//i.test(path) || path.indexOf('data:') === 0) return path;
        const base = (STUDIO_DATA.baseUrl || '').replace(/\/$/, '');
        return base + '/' + path.replace(/^\/+/, '');
    }

    // Helper: Unique ID generator for markers
    function generateId() {
        return 'mk_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
    }

    // Icons for marker types (shown on the map + tools)
    const TYPE_ICONS = {
        '360': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        'scene': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        'floorplan': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>',
        'ar': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>',
        'compass': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>',
        'info': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        'information': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        'room': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9a2 2 0 0 1 2 2v16l-4-2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M10 8h6"/><path d="M10 12h6"/><path d="M10 3v18"/></svg>',
        'area': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>'
    };

    const TYPE_NAMES = {
        '360': '360° Scene',
        'scene': '360° Scene',
        'floorplan': 'Floor Plan',
        'ar': 'AR View',
        'compass': 'Compass',
        'info': 'Information',
        'room': 'Room',
        'area': 'Area'
    };

    // Category filter chips shown above the canvas (legacy names are grouped)
    const FILTER_GROUPS = [
        { key: 'all', label: 'All' },
        { key: 'scene', label: '360°' },
        { key: 'floorplan', label: 'Floor Plan' },
        { key: 'ar', label: 'AR' },
        { key: 'compass', label: 'Compass' },
        { key: 'info', label: 'Info' }
    ];

    function canonType(t) {
        t = (t || '').toLowerCase();
        if (t === '360' || t === 'scene') return 'scene';
        if (t === 'information') return 'info';
        return t;
    }

    const COMPASS_DIRECTIONS = [
        { label: 'North (N - 0°)', angle: 0 },
        { label: 'North-East (NE - 45°)', angle: 45 },
        { label: 'East (E - 90°)', angle: 90 },
        { label: 'South-East (SE - 135°)', angle: 135 },
        { label: 'South (S - 180°)', angle: 180 },
        { label: 'South-West (SW - 225°)', angle: 225 },
        { label: 'West (W - 270°)', angle: 270 },
        { label: 'North-West (NW - 315°)', angle: 315 }
    ];

    // ── Load Existing Markers ─────────────────────────────────
    fetch(`actions.php?action=get_markers&floor_plan_id=${STUDIO_DATA.floorPlanId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.markers)) {
                markers = data.markers.map(m => ({
                    id: m.id ? String(m.id) : generateId(),
                    type: m.marker_type || 'scene',
                    label: m.label || 'Marker',
                    x: parseFloat(m.x_percent) || 50,
                    y: parseFloat(m.y_percent) || 50,
                    facing_angle: parseFloat(m.facing_angle) || 0,
                    marker_image_path: m.marker_image_path || '',
                    target_scene_id: m.target_scene_id || '',
                    target_floor_plan_id: m.target_floor_plan_id || '',
                    target_building_id: m.target_building_id || '',
                    target_room_id: m.target_room_id || '',
                    target_area_id: m.target_area_id || '',
                    popup_title: m.popup_title || '',
                    popup_html: m.popup_html || ''
                }));
                renderMarkers();
            }
        })
        .catch(err => {
            console.error('Failed to load markers:', err);
        });

    // ── Tool Selection / Placement Mode ───────────────────────
    function setPlacementMode(type) {
        if (activePlacementType === type) {
            cancelPlacementMode();
            return;
        }

        activePlacementType = type;
        selectMarker(null); // Deselect when entering placement mode

        tools.forEach(t => {
            t.classList.toggle('is-active', t.dataset.type === type);
        });

        if (type) {
            canvas.classList.add('is-placing');
            const name = TYPE_NAMES[type] || type;
            activeToolName.textContent = name;
            activeToolBadge.style.display = 'inline-block';
            statusHint.textContent = `Click anywhere on the map to place the ${name} marker (or press Esc to cancel).`;
        } else {
            cancelPlacementMode();
        }
    }

    function cancelPlacementMode() {
        activePlacementType = null;
        tools.forEach(t => t.classList.remove('is-active'));
        canvas.classList.remove('is-placing');
        activeToolBadge.style.display = 'none';
        statusHint.textContent = 'Drag dots to reposition. Click a marker to edit its properties.';
    }

    // Bind tools click & dragstart
    tools.forEach(tool => {
        tool.addEventListener('click', (e) => {
            e.stopPropagation();
            setPlacementMode(tool.dataset.type);
        });

        tool.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', tool.dataset.type);
            e.dataTransfer.effectAllowed = 'copy';
        });
    });

    // Keyboard shortcut Esc to cancel placement mode
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            cancelPlacementMode();
            selectMarker(null);
        } else if (e.key === 'Delete' && selectedMarkerId) {
            if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
                removeSelectedMarker();
            }
        }
    });

    // ── Canvas Drag & Drop from Sidebar ───────────────────────
    canvas.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    });

    canvas.addEventListener('drop', (e) => {
        e.preventDefault();
        const type = e.dataTransfer.getData('text/plain');
        if (!type) return;

        const rect = canvas.getBoundingClientRect();
        const xPercent = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100));
        const yPercent = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100));

        addMarker(type, xPercent, yPercent);
        cancelPlacementMode();
    });

    // ── Canvas Click: Place Marker or Deselect ─────────────────
    canvas.addEventListener('click', (e) => {
        if (e.target.closest('.fp-marker-dot')) {
            return;
        }

        if (activePlacementType) {
            const rect = canvas.getBoundingClientRect();
            const xPercent = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100));
            const yPercent = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100));

            addMarker(activePlacementType, xPercent, yPercent);
            cancelPlacementMode();
        } else {
            selectMarker(null);
        }
    });

    // ── Add Marker ────────────────────────────────────────────
    function addMarker(type, xPercent, yPercent) {
        const defaultName = TYPE_NAMES[type] || 'Marker';
        const newMarker = {
            id: generateId(),
            type: type,
            label: defaultName,
            x: parseFloat(xPercent.toFixed(4)),
            y: parseFloat(yPercent.toFixed(4)),
            facing_angle: 0,
            marker_image_path: '',
            target_scene_id: '',
            target_floor_plan_id: '',
            target_building_id: '',
            target_room_id: '',
            target_area_id: '',
            popup_title: '',
            popup_html: ''
        };

        markers.push(newMarker);
        hasUnsavedChanges = true;
        renderMarkers();
        selectMarker(newMarker.id);
    }

    // ── Render All Markers ────────────────────────────────────
    function renderFilterBar() {
        const bar = document.getElementById('marker-filter-bar');
        if (!bar) return;
        bar.innerHTML = '';

        FILTER_GROUPS.forEach(g => {
            const count = g.key === 'all'
                ? markers.length
                : markers.filter(m => canonType(m.type) === g.key).length;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'marker-filter-chip' + (activeFilter === g.key ? ' is-active' : '');
            btn.dataset.filter = g.key;
            btn.title = `${g.label}: ${count} marker(s)`;
            btn.innerHTML = `<span class="mfc-label">${g.label}</span><span class="mfc-count">${count}</span>`;
            btn.addEventListener('click', () => {
                activeFilter = g.key;
                renderFilterBar();
                renderMarkers();
            });
            bar.appendChild(btn);
        });
    }

    function renderMarkers() {
        markersLayer.innerHTML = '';
        renderFilterBar();

        const visible = activeFilter === 'all'
            ? markers
            : markers.filter(m => canonType(m.type) === activeFilter);

        visible.forEach(m => {
            const dot = document.createElement('div');
            dot.className = `fp-marker-dot ${m.id === selectedMarkerId ? 'fp-selected' : ''}`;
            dot.dataset.id = m.id;
            dot.dataset.type = m.type;
            dot.dataset.name = m.label;
            dot.style.left = m.x + '%';
            dot.style.top = m.y + '%';
            dot.style.pointerEvents = 'auto';

            const badgeHtml = TYPE_ICONS[m.type] || TYPE_ICONS['360'];
            
            // Show facing arrow only for AR and Compass markers (per requirement)
            let arrowHtml = '';
            if (m.type === 'ar' || m.type === 'compass') {
                arrowHtml = `<span class="fp-facing-arrow" style="--facing: ${parseFloat(m.facing_angle) || 0}deg;"></span>`;
            }

            dot.innerHTML = `
                ${arrowHtml}
                <span class="fp-mktype-badge">${badgeHtml}</span>
                <div class="fp-marker-tooltip">${esc(m.label)}</div>
            `;

            attachMarkerEvents(dot, m);
            markersLayer.appendChild(dot);
        });
    }

    // ── Marker Pointer Drag & Click ───────────────────────────
    function attachMarkerEvents(dot, marker) {
        dot.addEventListener('pointerdown', (e) => {
            e.preventDefault();
            e.stopPropagation();

            if (activePlacementType) {
                cancelPlacementMode();
            }

            const startX = e.clientX;
            const startY = e.clientY;
            let isDragging = false;

            const onPointerMove = (ev) => {
                const dx = ev.clientX - startX;
                const dy = ev.clientY - startY;

                if (!isDragging && Math.hypot(dx, dy) < 4) {
                    return;
                }

                if (!isDragging) {
                    isDragging = true;
                    dot.classList.add('is-dragging');
                }

                const rect = canvas.getBoundingClientRect();
                const newX = Math.max(0, Math.min(100, ((ev.clientX - rect.left) / rect.width) * 100));
                const newY = Math.max(0, Math.min(100, ((ev.clientY - rect.top) / rect.height) * 100));

                marker.x = parseFloat(newX.toFixed(4));
                marker.y = parseFloat(newY.toFixed(4));

                dot.style.left = marker.x + '%';
                dot.style.top = marker.y + '%';

                hasUnsavedChanges = true;

                if (selectedMarkerId === marker.id) {
                    const posEl = document.getElementById('prop-pos-val');
                    if (posEl) {
                        posEl.textContent = `${marker.x.toFixed(1)}%, ${marker.y.toFixed(1)}%`;
                    }
                }
            };

            const onPointerUp = () => {
                document.removeEventListener('pointermove', onPointerMove);
                document.removeEventListener('pointerup', onPointerUp);
                dot.classList.remove('is-dragging');

                if (!isDragging) {
                    selectMarker(marker.id);
                } else {
                    if (selectedMarkerId === marker.id) {
                        renderProperties();
                    }
                }
            };

            document.addEventListener('pointermove', onPointerMove);
            document.addEventListener('pointerup', onPointerUp);
        });
    }

    // ── Select Marker ─────────────────────────────────────────
    function selectMarker(id) {
        selectedMarkerId = id;

        markersLayer.querySelectorAll('.fp-marker-dot').forEach(d => {
            d.classList.toggle('fp-selected', d.dataset.id === id);
        });

        renderProperties();
    }

    // ── Remove Marker ─────────────────────────────────────────
    function removeSelectedMarker() {
        if (!selectedMarkerId) return;
        const m = markers.find(x => x.id === selectedMarkerId);
        const name = m ? m.label : 'this marker';
        if (confirm(`Remove "${name}"?`)) {
            markers = markers.filter(x => x.id !== selectedMarkerId);
            hasUnsavedChanges = true;
            selectMarker(null);
            renderMarkers();
        }
    }

    // ── SweetAlert Media Picker Modal ─────────────────────────
    function openMarkerMediaPicker(onSelect) {
        fetch('actions.php?action=list_marker_images')
            .then(res => res.json())
            .then(data => {
                const files = (data.success && data.files) ? data.files : [];
                
                let filesHtml = '';
                if (files.length > 0) {
                    filesHtml = '<div class="row g-2" style="max-height: 260px; overflow-y: auto;">';
                    files.forEach(f => {
                        filesHtml += `
                            <div class="col-4 col-sm-3">
                                <div class="border rounded p-1 text-center swal-file-card position-relative h-100" style="cursor: pointer; transition: all .15s;" data-path="${esc(f.path)}" data-url="${esc(f.url)}">
                                    <div style="height: 55px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border-radius: 4px;">
                                        <img src="${esc(f.url)}" style="max-width: 100%; max-height: 55px; object-fit: cover;">
                                    </div>
                                    <div class="text-truncate small mt-1" style="font-size: 11px;" title="${esc(f.name)}">${esc(f.name)}</div>
                                </div>
                            </div>
                        `;
                    });
                    filesHtml += '</div>';
                } else {
                    filesHtml = '<div class="text-muted text-center py-4">No images found in assets folder. Use the Upload tab to add one.</div>';
                }

                const uploadHtml = `
                    <div class="p-4 border rounded text-center" id="swal-upload-area" style="background: #f8fafc; border-style: dashed !important; border-width: 2px !important;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mb-2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <h6 class="fw-bold mb-1">Upload an image file</h6>
                        <p class="text-muted small mb-3">PNG, JPG, WebP, SVG, AVIF accepted</p>
                        <input type="file" id="swal-marker-file-input" class="d-none" accept="image/*">
                        <button type="button" class="btn btn-primary btn-sm px-4" id="swal-btn-browse">Browse Files...</button>
                        <div id="swal-upload-spinner" class="mt-2 text-primary" style="display:none;">
                            <span class="spinner-border spinner-border-sm me-1"></span> Uploading...
                        </div>
                    </div>
                `;

                const urlHtml = `
                    <div class="p-2">
                        <label class="form-label small fw-bold mb-1">Direct Image URL / Path</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm" id="swal-manual-url" placeholder="https://example.com/image.jpg or assets/...">
                            <button type="button" class="btn btn-primary btn-sm" id="swal-btn-apply-url">Apply</button>
                        </div>
                    </div>
                `;

                Swal.fire({
                    title: 'Choose Image',
                    width: 600,
                    html: `
                        <div class="text-start">
                            <ul class="nav nav-pills nav-fill mb-3" id="swal-tabs">
                                <li class="nav-item"><button class="nav-link active py-1" data-tab="library">Library (${files.length})</button></li>
                                <li class="nav-item"><button class="nav-link py-1" data-tab="upload">Upload New</button></li>
                                <li class="nav-item"><button class="nav-link py-1" data-tab="url">Direct Link</button></li>
                            </ul>
                            <div id="tab-content-library">${filesHtml}</div>
                            <div id="tab-content-upload" style="display:none;">${uploadHtml}</div>
                            <div id="tab-content-url" style="display:none;">${urlHtml}</div>
                        </div>
                    `,
                    showConfirmButton: false,
                    showCloseButton: true,
                    didOpen: () => {
                        const popup = Swal.getPopup();
                        popup.querySelectorAll('#swal-tabs button').forEach(btn => {
                            btn.addEventListener('click', () => {
                                popup.querySelectorAll('#swal-tabs button').forEach(b => b.classList.remove('active'));
                                btn.classList.add('active');
                                const tab = btn.dataset.tab;
                                popup.querySelector('#tab-content-library').style.display = tab === 'library' ? 'block' : 'none';
                                popup.querySelector('#tab-content-upload').style.display = tab === 'upload' ? 'block' : 'none';
                                popup.querySelector('#tab-content-url').style.display = tab === 'url' ? 'block' : 'none';
                            });
                        });

                        popup.querySelectorAll('.swal-file-card').forEach(card => {
                            card.addEventListener('click', () => {
                                onSelect(card.dataset.path, card.dataset.url);
                                Swal.close();
                            });
                        });

                        const fileInput = popup.querySelector('#swal-marker-file-input');
                        const browseBtn = popup.querySelector('#swal-btn-browse');
                        const spinner = popup.querySelector('#swal-upload-spinner');

                        browseBtn.addEventListener('click', () => fileInput.click());
                        fileInput.addEventListener('change', () => {
                            if (!fileInput.files || !fileInput.files[0]) return;
                            const fd = new FormData();
                            fd.append('marker_file_upload', fileInput.files[0]);
                            spinner.style.display = 'block';
                            browseBtn.disabled = true;

                            fetch('actions.php?action=upload_marker_image', {
                                method: 'POST',
                                body: fd
                            })
                            .then(r => r.json())
                            .then(res => {
                                if (res.success) {
                                    onSelect(res.path, res.url);
                                    Swal.close();
                                } else {
                                    alert('Upload error: ' + (res.error || 'Failed'));
                                }
                            })
                            .catch(() => alert('Upload failed.'))
                            .finally(() => {
                                spinner.style.display = 'none';
                                browseBtn.disabled = false;
                            });
                        });

                        const urlInput = popup.querySelector('#swal-manual-url');
                        const applyBtn = popup.querySelector('#swal-btn-apply-url');
                        applyBtn.addEventListener('click', () => {
                            const val = urlInput.value.trim();
                            if (val) {
                                onSelect(val, val);
                                Swal.close();
                            }
                        });
                    }
                });
            })
            .catch(err => {
                console.error(err);
                alert('Could not load media files.');
            });
    }

    // ── Render Dynamic Properties Panel ───────────────────────
    function sceneById(id) {
        return (STUDIO_DATA.scenes || []).find(s => String(s.id) === String(id)) || null;
    }

    // Fill a marker's Building/Room/Area from its linked 360 scene, but only when the
    // marker has no value yet (so existing data is never silently overwritten).
    function fillTargetFromScene(m) {
        const sc = sceneById(m.target_scene_id);
        if (!sc) { return; }
        if (!m.target_building_id && sc.building_id) { m.target_building_id = String(sc.building_id); }
        if (!m.target_room_id && sc.room_id) { m.target_room_id = String(sc.room_id); }
        if (!m.target_area_id && sc.campus_area_id) { m.target_area_id = String(sc.campus_area_id); }
    }

    // Scene is the source of truth: after choosing a target 360 Scene, push its
    // Building/Room/Area into the marker + the open selects so the data can't diverge.
    function syncTargetFromScene(m) {
        const sc = sceneById(m.target_scene_id);
        m.target_building_id = (sc && sc.building_id) ? String(sc.building_id) : '';
        m.target_room_id = (sc && sc.room_id) ? String(sc.room_id) : '';
        m.target_area_id = (sc && sc.campus_area_id) ? String(sc.campus_area_id) : '';
        const bld = document.getElementById('prop-target-bld');
        const room = document.getElementById('prop-target-room');
        const area = document.getElementById('prop-target-area');
        if (bld) { bld.value = m.target_building_id; }
        if (room) { room.value = m.target_room_id; }
        if (area) { area.value = m.target_area_id; }
    }

    function renderProperties() {
        if (!selectedMarkerId) {
            propsTitle.textContent = 'Properties';
            propPanel.innerHTML = `
                <div class="text-muted text-center py-5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mb-2 opacity-50"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    <div>Select a marker or choose a tool on the left to place one.</div>
                </div>
            `;
            return;
        }

        const m = markers.find(x => x.id === selectedMarkerId);
        if (!m) {
            selectMarker(null);
            return;
        }

        propsTitle.textContent = `Marker: ${m.label}`;

        // When this marker is linked to a 360 scene that already has building/room/area,
        // carry them over (only fills empty fields, keeps existing overrides).
        fillTargetFromScene(m);

        // Options for scenes, floorplans, buildings, rooms
        let sceneOpts = '<option value="">— none —</option>';
        if (STUDIO_DATA.scenes) {
            STUDIO_DATA.scenes.forEach(s => {
                const hint = [s.building_name, s.room_name, s.area_name].filter(Boolean).join(' · ');
                sceneOpts += `<option value="${s.id}" ${String(m.target_scene_id) === String(s.id) ? 'selected' : ''}>${esc(s.title)}${hint ? ' (' + esc(hint) + ')' : ''}</option>`;
            });
        }

        let fpOpts = '<option value="">— none —</option>';
        if (STUDIO_DATA.floorPlans) {
            STUDIO_DATA.floorPlans.forEach(f => {
                fpOpts += `<option value="${f.id}" ${String(m.target_floor_plan_id) === String(f.id) ? 'selected' : ''}>${esc(f.title)}</option>`;
            });
        }

        let bldOpts = '<option value="">— none —</option>';
        if (STUDIO_DATA.buildings) {
            STUDIO_DATA.buildings.forEach(b => {
                bldOpts += `<option value="${b.id}" ${String(m.target_building_id) === String(b.id) ? 'selected' : ''}>${esc(b.name)}</option>`;
            });
        }

        let roomOpts = '<option value="">— none —</option>';
        if (STUDIO_DATA.rooms) {
            STUDIO_DATA.rooms.forEach(r => {
                roomOpts += `<option value="${r.id}" ${String(m.target_room_id) === String(r.id) ? 'selected' : ''}>${esc(r.name)}</option>`;
            });
        }

        let areaOpts = '<option value="">— none —</option>';
        if (STUDIO_DATA.areas) {
            STUDIO_DATA.areas.forEach(a => {
                areaOpts += `<option value="${a.id}" ${String(m.target_area_id) === String(a.id) ? 'selected' : ''}>${esc(a.name)}</option>`;
            });
        }

        // Determine if info popup is image or text
        const isInfoImage = (m.type === 'info' || m.type === 'information') && Boolean(m.marker_image_path);

        let dynamicFieldsHtml = '';

        // 1. If 360 / Scene
        if (['scene', '360'].includes(m.type)) {
            dynamicFieldsHtml += `
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Target 360 Tour Scene</label>
                    <select class="form-select form-select-sm" id="prop-target-scene">${sceneOpts}</select>
                </div>
            `;
        }

        // 2. If Floor Plan Link
        if (m.type === 'floorplan') {
            dynamicFieldsHtml += `
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Target Floor Plan</label>
                    <select class="form-select form-select-sm" id="prop-target-fp">${fpOpts}</select>
                </div>
            `;
        }

        // 2b. If Room binding
        if (m.type === 'room') {
            dynamicFieldsHtml += `
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Target Room</label>
                    <select class="form-select form-select-sm" id="prop-target-room-dyn">${roomOpts}</select>
                </div>
            `;
        }

        // 2c. If Area binding
        if (m.type === 'area') {
            dynamicFieldsHtml += `
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Target Area</label>
                    <select class="form-select form-select-sm" id="prop-target-area-dyn">${areaOpts}</select>
                </div>
            `;
        }

        // 3. If AR View: ONLY here we show the Facing Direction + AR Target Image (per prompt requirement)
        if (m.type === 'ar') {
            const hasImg = Boolean(m.marker_image_path);
            dynamicFieldsHtml += `
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold mb-0">Facing Direction (°)</label>
                        <span class="small text-muted" id="prop-facing-label">${Math.round(m.facing_angle)}°</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="range" class="form-range flex-grow-1" id="prop-facing-range" min="0" max="360" step="1" value="${Math.round(m.facing_angle)}">
                        <input type="number" class="form-control form-control-sm text-center" id="prop-facing-num" min="0" max="360" style="width: 65px;" value="${Math.round(m.facing_angle)}">
                    </div>
                    <small class="text-muted" style="font-size: 11px;">The arrow on the map points in this direction.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">AR Target / Preview Image</label>
                    <div class="marker-img-preview-box mb-2" id="ar-img-box">
                        ${hasImg ? `<img src="${mediaUrl(m.marker_image_path)}" alt="AR Image" class="mb-2 d-block mx-auto"><div class="small text-truncate text-muted">${esc(m.marker_image_path)}</div>` : '<div class="text-muted small py-3">No AR image selected</div>'}
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1" id="btn-pick-ar-img">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                            ${hasImg ? 'Change Image' : 'Choose AR Image'}
                        </button>
                        ${hasImg ? '<button type="button" class="btn btn-outline-danger btn-sm" id="btn-clear-ar-img">Remove</button>' : ''}
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Link to 360 Tour Scene (Optional)</label>
                    <select class="form-select form-select-sm" id="prop-target-scene">${sceneOpts}</select>
                </div>
            `;
        }

        // 4. If Compass: Direction dropdown (North, South, East, West, etc.) (per prompt requirement)
        if (m.type === 'compass') {
            let dirOpts = '';
            COMPASS_DIRECTIONS.forEach(d => {
                const isSelected = Math.round(m.facing_angle) === d.angle;
                dirOpts += `<option value="${d.angle}" ${isSelected ? 'selected' : ''}>${d.label}</option>`;
            });

            dynamicFieldsHtml += `
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Compass Direction</label>
                    <select class="form-select form-select-sm" id="prop-compass-dir">
                        ${dirOpts}
                        <option value="custom" ${!COMPASS_DIRECTIONS.some(d => d.angle === Math.round(m.facing_angle)) ? 'selected' : ''}>Custom Angle (${Math.round(m.facing_angle)}°)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold mb-0">Angle Rotation (°)</label>
                        <span class="small text-muted" id="prop-facing-label">${Math.round(m.facing_angle)}°</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="range" class="form-range flex-grow-1" id="prop-facing-range" min="0" max="360" step="1" value="${Math.round(m.facing_angle)}">
                        <input type="number" class="form-control form-control-sm text-center" id="prop-facing-num" min="0" max="360" style="width: 65px;" value="${Math.round(m.facing_angle)}">
                    </div>
                    <small class="text-muted" style="font-size: 11px;">The cyan compass arrow rotates accordingly.</small>
                </div>
            `;
        }

        // 5. If Information marker: Popup title + Content (Text/HTML or Image via Swal picker)
        if (m.type === 'info' || m.type === 'information') {
            const hasImg = Boolean(m.marker_image_path);
            dynamicFieldsHtml += `
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Popup Content Type</label>
                    <select class="form-select form-select-sm" id="prop-info-mode">
                        <option value="text" ${!isInfoImage ? 'selected' : ''}>Text / HTML Content</option>
                        <option value="image" ${isInfoImage ? 'selected' : ''}>Image / Photo Popup</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Popup Headline / Title</label>
                    <input type="text" class="form-control form-control-sm" id="prop-popup-title" value="${esc(m.popup_title)}" placeholder="e.g. Science Lab Hours">
                </div>

                <div id="prop-info-text-section" class="${isInfoImage ? 'd-none' : ''}">
                    <label class="form-label small fw-bold mb-1">Popup Content (HTML / Text)</label>
                    <textarea class="form-control form-control-sm" id="prop-popup-html" rows="3" placeholder="Enter information, schedule, or description...">${esc(m.popup_html)}</textarea>
                </div>

                <div id="prop-info-image-section" class="${isInfoImage ? '' : 'd-none'}">
                    <label class="form-label small fw-bold mb-1">Popup Image</label>
                    <div class="marker-img-preview-box mb-2" id="info-img-box">
                        ${hasImg ? `<img src="${mediaUrl(m.marker_image_path)}" alt="Popup Image" class="mb-2 d-block mx-auto"><div class="small text-truncate text-muted">${esc(m.marker_image_path)}</div>` : '<div class="text-muted small py-3">No popup image selected</div>'}
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1" id="btn-pick-info-img">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                            ${hasImg ? 'Change Image' : 'Choose / Upload Image (Swal)'}
                        </button>
                        ${hasImg ? '<button type="button" class="btn btn-outline-danger btn-sm" id="btn-clear-info-img">Remove</button>' : ''}
                    </div>
                </div>
            `;
        }

        const html = `
            <div class="d-flex flex-column gap-3">
                <div>
                    <label class="form-label small fw-bold mb-1">Label</label>
                    <input type="text" class="form-control form-control-sm" id="prop-label" value="${esc(m.label)}" placeholder="Marker title">
                </div>

                <div>
                    <label class="form-label small fw-bold mb-1">Marker Type</label>
                    <select class="form-select form-select-sm" id="prop-type">
                        ${(m.type === 'room' || m.type === 'area') ? `<option value="${esc(m.type)}" selected>${esc(TYPE_NAMES[m.type])} (legacy — no longer a marker tool)</option>` : ''}
                        <option value="scene" ${['scene', '360'].includes(m.type) ? 'selected' : ''}>360° Scene</option>
                        <option value="floorplan" ${m.type === 'floorplan' ? 'selected' : ''}>Floor Plan Link</option>
                        <option value="ar" ${m.type === 'ar' ? 'selected' : ''}>AR View</option>
                        <option value="compass" ${m.type === 'compass' ? 'selected' : ''}>Compass</option>
                        <option value="info" ${['info', 'information'].includes(m.type) ? 'selected' : ''}>Information</option>
                    </select>
                </div>

                <div class="p-2 rounded bg-light border">
                    <div class="d-flex justify-content-between text-muted small">
                        <span>Coordinates (X, Y):</span>
                        <strong id="prop-pos-val" class="text-body">${m.x.toFixed(1)}%, ${m.y.toFixed(1)}%</strong>
                    </div>
                </div>

                <div id="dynamic-properties-container">
                    ${dynamicFieldsHtml}
                </div>

                <!-- Building, Room & Area (for general markers) -->
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Building</label>
                        <select class="form-select form-select-sm" id="prop-target-bld">${bldOpts}</select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Room</label>
                        <select class="form-select form-select-sm" id="prop-target-room">${roomOpts}</select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1">Area</label>
                        <select class="form-select form-select-sm" id="prop-target-area">${areaOpts}</select>
                    </div>
                </div>

                <div class="pt-2 border-top d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-delete-marker">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                        Remove
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-deselect-marker">Done</button>
                </div>
            </div>
        `;

        propPanel.innerHTML = html;

        // ── Property Event Listeners ──
        const labelInput = document.getElementById('prop-label');
        labelInput.addEventListener('input', (e) => {
            m.label = e.target.value;
            hasUnsavedChanges = true;
            propsTitle.textContent = `Marker: ${m.label}`;
            const dot = markersLayer.querySelector(`.fp-marker-dot[data-id="${m.id}"]`);
            if (dot) {
                dot.dataset.name = m.label;
                const tooltip = dot.querySelector('.fp-marker-tooltip');
                if (tooltip) tooltip.textContent = m.label;
            }
        });

        const typeSelect = document.getElementById('prop-type');
        typeSelect.addEventListener('change', (e) => {
            m.type = e.target.value;
            hasUnsavedChanges = true;
            renderMarkers();
            renderProperties();
        });

        // Scene dropdown
        const sceneSelect = document.getElementById('prop-target-scene');
        if (sceneSelect) {
            sceneSelect.addEventListener('change', (e) => {
                m.target_scene_id = e.target.value;
                syncTargetFromScene(m);
                hasUnsavedChanges = true;
            });
        }

        // Floor plan dropdown
        const fpSelect = document.getElementById('prop-target-fp');
        if (fpSelect) {
            fpSelect.addEventListener('change', (e) => {
                m.target_floor_plan_id = e.target.value;
                hasUnsavedChanges = true;
            });
        }

        // Building / Room dropdowns
        document.getElementById('prop-target-bld').addEventListener('change', (e) => {
            m.target_building_id = e.target.value;
            hasUnsavedChanges = true;
        });

        document.getElementById('prop-target-room').addEventListener('change', (e) => {
            m.target_room_id = e.target.value;
            hasUnsavedChanges = true;
        });

        document.getElementById('prop-target-area').addEventListener('change', (e) => {
            m.target_area_id = e.target.value;
            hasUnsavedChanges = true;
        });

        // Room/Area type-specific dropdowns (dynamic container)
        const roomDynSelect = document.getElementById('prop-target-room-dyn');
        if (roomDynSelect) {
            roomDynSelect.addEventListener('change', (e) => {
                m.target_room_id = e.target.value;
                hasUnsavedChanges = true;
            });
        }

        const areaDynSelect = document.getElementById('prop-target-area-dyn');
        if (areaDynSelect) {
            areaDynSelect.addEventListener('change', (e) => {
                m.target_area_id = e.target.value;
                hasUnsavedChanges = true;
            });
        }

        // Compass Direction Dropdown
        const compassDirSelect = document.getElementById('prop-compass-dir');
        if (compassDirSelect) {
            compassDirSelect.addEventListener('change', (e) => {
                if (e.target.value !== 'custom') {
                    updateFacing(parseFloat(e.target.value));
                }
            });
        }

        // Facing Angle Range & Number Input
        const facingRange = document.getElementById('prop-facing-range');
        const facingNum = document.getElementById('prop-facing-num');
        const facingLabel = document.getElementById('prop-facing-label');

        function updateFacing(val) {
            let deg = (parseFloat(val) || 0) % 360;
            if (deg < 0) deg += 360;
            m.facing_angle = deg;
            hasUnsavedChanges = true;
            if (facingRange) facingRange.value = Math.round(deg);
            if (facingNum) facingNum.value = Math.round(deg);
            if (facingLabel) facingLabel.textContent = `${Math.round(deg)}°`;

            // If compass, update dropdown match
            if (compassDirSelect) {
                const match = COMPASS_DIRECTIONS.find(d => d.angle === Math.round(deg));
                compassDirSelect.value = match ? match.angle : 'custom';
            }

            // Rotate facing arrow on the map in real time
            const dot = markersLayer.querySelector(`.fp-marker-dot[data-id="${m.id}"]`);
            if (dot) {
                const arrow = dot.querySelector('.fp-facing-arrow');
                if (arrow) arrow.style.setProperty('--facing', `${deg}deg`);
            }
        }

        if (facingRange) facingRange.addEventListener('input', (e) => updateFacing(e.target.value));
        if (facingNum) facingNum.addEventListener('input', (e) => updateFacing(e.target.value));

        // AR Image Picker
        const btnPickArImg = document.getElementById('btn-pick-ar-img');
        if (btnPickArImg) {
            btnPickArImg.addEventListener('click', () => {
                openMarkerMediaPicker((path) => {
                    m.marker_image_path = path;
                    hasUnsavedChanges = true;
                    renderProperties();
                });
            });
        }

        const btnClearArImg = document.getElementById('btn-clear-ar-img');
        if (btnClearArImg) {
            btnClearArImg.addEventListener('click', () => {
                m.marker_image_path = '';
                hasUnsavedChanges = true;
                renderProperties();
            });
        }

        // Information Mode (Text vs Image)
        const infoModeSelect = document.getElementById('prop-info-mode');
        if (infoModeSelect) {
            infoModeSelect.addEventListener('change', (e) => {
                const isImg = e.target.value === 'image';
                document.getElementById('prop-info-text-section').classList.toggle('d-none', isImg);
                document.getElementById('prop-info-image-section').classList.toggle('d-none', !isImg);
                if (!isImg) {
                    m.marker_image_path = '';
                    hasUnsavedChanges = true;
                }
            });
        }

        const btnPickInfoImg = document.getElementById('btn-pick-info-img');
        if (btnPickInfoImg) {
            btnPickInfoImg.addEventListener('click', () => {
                openMarkerMediaPicker((path) => {
                    m.marker_image_path = path;
                    hasUnsavedChanges = true;
                    renderProperties();
                });
            });
        }

        const btnClearInfoImg = document.getElementById('btn-clear-info-img');
        if (btnClearInfoImg) {
            btnClearInfoImg.addEventListener('click', () => {
                m.marker_image_path = '';
                hasUnsavedChanges = true;
                renderProperties();
            });
        }

        const popupTitleInput = document.getElementById('prop-popup-title');
        if (popupTitleInput) {
            popupTitleInput.addEventListener('input', (e) => {
                m.popup_title = e.target.value;
                hasUnsavedChanges = true;
            });
        }

        const popupHtmlInput = document.getElementById('prop-popup-html');
        if (popupHtmlInput) {
            popupHtmlInput.addEventListener('input', (e) => {
                m.popup_html = e.target.value;
                hasUnsavedChanges = true;
            });
        }

        document.getElementById('btn-delete-marker').addEventListener('click', removeSelectedMarker);
        document.getElementById('btn-deselect-marker').addEventListener('click', () => selectMarker(null));
    }

    // ── Save All Markers ──────────────────────────────────────
    saveBtn.addEventListener('click', () => {
        saveBtn.disabled = true;
        saveBtn.innerHTML = `
            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
            Saving...
        `;

        fetch('actions.php?action=save_markers', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                floor_plan_id: STUDIO_DATA.floorPlanId,
                markers: markers
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                hasUnsavedChanges = false;
                saveBtn.classList.remove('btn-grad');
                saveBtn.classList.add('btn-success');
                saveBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><polyline points="20 6 9 17 4 12"/></svg>
                    Saved!
                `;
                setTimeout(() => {
                    saveBtn.classList.remove('btn-success');
                    saveBtn.classList.add('btn-grad');
                    saveBtn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><polyline points="20 6 9 17 4 12"/></svg>
                        Save Changes
                    `;
                }, 2000);
            } else {
                alert('Error saving markers: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Save failed:', err);
            alert('Failed to save markers. Please check your network connection.');
        })
        .finally(() => {
            saveBtn.disabled = false;
        });
    });

    window.addEventListener('beforeunload', (e) => {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
});

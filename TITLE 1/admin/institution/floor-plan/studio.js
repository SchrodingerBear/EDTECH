  (() => {
    const esc = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')
    const stage = document.getElementById('map-stage')
    const qsa = (sel) => Array.from(stage.querySelectorAll(sel))
    const propsBody = document.getElementById('props-body')
    const propsTitle = document.getElementById('props-title')
    const propsPanel = document.getElementById('props-panel')
    const pathLayer = document.getElementById('fp-path-layer')
    const compassEl = document.getElementById('fp-compass')
    const stageRect = () => stage.getBoundingClientRect()

    let mode = 'move', selectedEl = null, selectedType = null, northAngle = <?= (float) ($studio['north_angle'] ?? 0) ?>;
    let routeCounter = <?= count($waypoints) + count($paths) + 1 ?>;

    const markerData = {}
    qsa('.fp-marker-dot').forEach(d => {
      markerData[d.dataset.id] = {
        label: d.querySelector('.mk-label')?.value || d.dataset.name,
        type: d.dataset.mktype,
        facing: parseFloat(d.querySelector('.mk-facing')?.value) || 0,
        building: d.querySelector('.mk-building')?.value || '',
        room: d.querySelector('.mk-room')?.value || '',
        scene: d.querySelector('.mk-scene')?.value || '',
        fp: d.querySelector('.mk-fp')?.value || '',
        popupTitle: d.querySelector('.mk-popup-title')?.value || '',
        popupHtml: d.querySelector('.mk-popup-html')?.value || ''
      }
    })

    const routes = <?= json_encode(array_map(function($p) { return ['id' => (int)$p['id'], 'name' => $p['name'], 'nodes' => array_map(function($nid) { return ['id' => (int)$nid]; }, json_decode($p['nodes_json'] ?? '[]', true) ?: [])]; }, $paths), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
    const wpDotById = {}
    qsa('.fp-wp-dot').forEach(d => { wpDotById[d.dataset.wpId] = d })

    routes.forEach(r => {
      const resolvedNodes = []
      r.nodes.forEach(n => {
        if (n.id && wpDotById[String(n.id)]) {
          const dot = wpDotById[String(n.id)]
          resolvedNodes.push({ id: n.id, x: parseFloat(dot.querySelector('.wp-x').value), y: parseFloat(dot.querySelector('.wp-y').value), corner: dot.querySelector('.wp-type')?.value === 'corner' })
        }
      })
      r.nodes = resolvedNodes
    })

    let deletedRouteIds = [], deletedWpIds = []

    /* ── Utilities ─────────────────────────────────────── */
    function pct(ev) {
      const r = stageRect()
      return { x: Math.max(0, Math.min(100, ((ev.clientX - r.left) / r.width) * 100)), y: Math.max(0, Math.min(100, ((ev.clientY - r.top) / r.height) * 100)) }
    }
    function dist(a, b) { return Math.hypot(a.x - b.x, a.y - b.y) }
    function pointToSegDist(p, a, b) {
      const dx = b.x - a.x, dy = b.y - a.y, lenSq = dx * dx + dy * dy
      if (lenSq === 0) return dist(p, a)
      let t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / lenSq
      t = Math.max(0, Math.min(1, t))
      return dist(p, { x: a.x + t * dx, y: a.y + t * dy })
    }

    /* ── Mode switching ────────────────────────────────── */
    const hints = { move: 'Drag any element to reposition. Click to edit properties.', face: 'Click a marker to show facing handle. Drag the arrow to rotate.', route: 'Click on the map to place route points. Click a line to bend it.', compass: 'Drag the compass rose to set North direction.', connections: 'Click an exit marker to create a link to another floor or scene.' }

    function setMode(m) {
      mode = m
      deselect()
      document.querySelectorAll('[data-ar-mode]').forEach(b => {
        b.classList.toggle('btn-grad', b.dataset.arMode === m)
        b.classList.toggle('btn-outline-ia', b.dataset.arMode !== m)
      })
      document.getElementById('mode-hint').textContent = hints[m] || ''
      qsa('.fp-marker-dot').forEach(d => {
        d.style.pointerEvents = 'all'
        d.style.opacity = ''
        const arrow = d.querySelector('.fp-facing-arrow')
        if (arrow) arrow.style.opacity = m === 'face' ? '0.6' : ''
      })
      qsa('.fp-wp-dot').forEach(d => { d.style.pointerEvents = 'all'; d.style.opacity = '' })
      compassEl.classList.toggle('mode-visible', m === 'compass')
      stage.style.cursor = m === 'route' ? 'crosshair' : ''
      renderRoutes()
    }

    document.getElementById('ar-mode-tabs').addEventListener('click', e => {
      const b = e.target.closest('[data-ar-mode]')
      if (b) setMode(b.dataset.arMode)
    })

    /* ── Selection ─────────────────────────────────────── */
    function deselect() {
      if (selectedEl) selectedEl.classList.remove('fp-selected')
      selectedEl = null; selectedType = null
      propsPanel.style.display = 'none'
    }
    function selectElement(el, type) {
      if (!el) return
      deselect()
      selectedEl = el; selectedType = type
      el.classList.add('fp-selected')
    }

    /* ── Draggable ─────────────────────────────────────── */
    function makeDraggable(dot, xEl, yEl, onDrag) {
      dot.addEventListener('pointerdown', e => {
        if (e.target.closest('.fp-facing-arrow')) return
        e.preventDefault(); e.stopPropagation()
        let dragging = false
        const downX = e.clientX, downY = e.clientY
        const onMove = ev => {
          const dx = ev.clientX - downX, dy = ev.clientY - downY
          if (!dragging && Math.hypot(dx, dy) < 5) return
          dragging = true
          const p = pct(ev)
          xEl.value = p.x.toFixed(4); yEl.value = p.y.toFixed(4)
          dot.style.left = p.x + '%'; dot.style.top = p.y + '%'
          if (onDrag) onDrag()
        }
        const onUp = () => { document.removeEventListener('pointermove', onMove); document.removeEventListener('pointerup', onUp) }
        document.addEventListener('pointermove', onMove)
        document.addEventListener('pointerup', onUp)
      })
    }

    /* ── Marker interactions ───────────────────────────── */
    qsa('.fp-marker-dot').forEach(dot => {
      const xEl = dot.querySelector('.mk-x'), yEl = dot.querySelector('.mk-y')
      makeDraggable(dot, xEl, yEl, () => {
        if (selectedEl === dot && selectedType === 'marker') renderMarkerProps(dot)
      })
      dot.addEventListener('click', e => {
        e.stopPropagation()
        if (mode === 'move' || mode === 'face') {
          selectElement(dot, 'marker')
          renderMarkerProps(dot)
        } else if (mode === 'connections') {
          const md = markerData[dot.dataset.id]
          if (md && md.type === 'exit') { selectElement(dot, 'marker'); renderExitLinkProps(dot) }
        }
      })
      const arrow = dot.querySelector('.fp-facing-arrow')
      if (arrow) {
        arrow.addEventListener('pointerdown', e => {
          e.preventDefault(); e.stopPropagation()
          const rotate = ev => {
            const r = stageRect()
            const ang = (Math.atan2(ev.clientY - (r.top + r.height / 2), ev.clientX - (r.left + r.width / 2)) * 180 / Math.PI + 90 + 360) % 360
            setFacing(dot, ang)
          }
          const up = () => { document.removeEventListener('pointermove', rotate); document.removeEventListener('pointerup', up) }
          document.addEventListener('pointermove', rotate)
          document.addEventListener('pointerup', up)
        })
      }
    })

    function setFacing(dot, deg) {
      deg = ((deg % 360) + 360) % 360
      dot.querySelector('.mk-facing').value = deg.toFixed(1)
      dot.querySelector('.fp-facing-arrow').style.setProperty('--facing', deg + 'deg')
      if (markerData[dot.dataset.id]) markerData[dot.dataset.id].facing = deg
      if (selectedEl === dot) { const inp = document.getElementById('mk-facing-input'); if (inp) inp.value = Math.round(deg) }
    }

    function renderMarkerProps(dot) {
      const md = markerData[dot.dataset.id]
      propsTitle.textContent = 'Marker — ' + (md.label || dot.dataset.name)
      propsPanel.style.display = ''
      let amode = 'popup'
      if (md.scene && md.scene !== '0') amode = 'scene'
      else if (md.fp && md.fp !== '0') amode = 'floorplan'
      propsBody.innerHTML = `
        <div class="row g-2 mb-2">
          <div class="col-md-3"><label class="form-label fw-semibold mb-1">Label</label>
            <input class="form-control form-control-sm" id="mk-label-input" value="${esc(md.label)}"></div>
          <div class="col-md-3"><label class="form-label fw-semibold mb-1">AR type</label>
            <select class="form-select form-select-sm" id="mk-type-input">
              <option value="scene" ${md.type==='scene'?'selected':''}>Scene</option>
              <option value="entrance" ${md.type==='entrance'?'selected':''}>Entrance</option>
              <option value="exit" ${md.type==='exit'?'selected':''}>Exit</option>
            </select></div>
          <div class="col-md-3"><label class="form-label fw-semibold mb-1">Facing (°)</label>
            <input class="form-control form-control-sm" id="mk-facing-input" type="number" min="0" max="360" step="1" value="${Math.round(md.facing)}"></div>
          <div class="col-md-3"><label class="form-label fw-semibold mb-1">Position</label>
            <div class="form-control form-control-sm" style="cursor:default;opacity:.7">${parseFloat(dot.querySelector('.mk-x').value).toFixed(1)}%, ${parseFloat(dot.querySelector('.mk-y').value).toFixed(1)}%</div>
          </div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-md-3"><label class="form-label fw-semibold mb-1">Building</label>
            <select class="form-select form-select-sm" id="mk-bld-input"><option value="">— none —</option>
            ${window._fpBuildings.map(b=>`<option value="${b.id}" ${md.building==b.id?'selected':''}>${esc(b.name)}</option>`).join('')}</select></div>
          <div class="col-md-3"><label class="form-label fw-semibold mb-1">Room</label>
            <select class="form-select form-select-sm" id="mk-room-input"><option value="">— none —</option>
            ${window._fpRooms.map(r=>`<option value="${r.id}" ${md.room==r.id?'selected':''}>${esc(r.name)}</option>`).join('')}</select></div>
          <div class="col-md-6"><label class="form-label fw-semibold mb-1">Popup title</label>
            <input class="form-control form-control-sm" id="mk-ptitle-input" value="${esc(md.popupTitle)}"></div>
        </div>
        <p class="form-label form-label-sm fw-semibold mb-1">Click action</p>
        <div class="d-flex gap-1 flex-wrap mb-2">
          ${['popup','scene','floorplan'].map(m=>`<button type="button" class="btn btn-xs ${amode===m?'btn-grad':'btn-outline-ia'}" data-mk-act="${m}">${m==='popup'?'Popup':m==='scene'?'360 Tour':'Sub-Floor Plan'}</button>`).join('')}
        </div>
        <div id="mk-act-popup" class="${amode!=='popup'?'d-none':''}">
          <textarea class="form-control form-control-sm" id="mk-popup-input" rows="2">${esc(md.popupHtml)}</textarea>
        </div>
        <div id="mk-act-scene" class="${amode!=='scene'?'d-none':''}">
          <select class="form-select form-select-sm" id="mk-scene-input"><option value="">— none —</option>
          ${window._fpScenes.map(s=>`<option value="${s.id}" ${md.scene==s.id?'selected':''}>${esc(s.name)}</option>`).join('')}</select>
        </div>
        <div id="mk-act-fp" class="${amode!=='floorplan'?'d-none':''}">
          <select class="form-select form-select-sm" id="mk-fp-input"><option value="">— none —</option>
          ${window._fpPlans.map(p=>`<option value="${p.id}" ${md.fp==p.id?'selected':''}>${esc(p.name)}</option>`).join('')}</select>
        </div>
        <div class="mt-2 d-flex justify-content-between align-items-center">
          <span class="ia-micro">${md.type==='entrance'?'Entrance — where visitors enter.':md.type==='exit'?'Exit — link it in Exit Links mode.':'Drag the arrow on the map to set facing.'}</span>
          <button type="button" class="btn btn-outline-ia btn-sm text-danger" id="mk-delete-btn">Delete marker</button>
        </div>`

      propsBody.querySelectorAll('[data-mk-act]').forEach(btn => {
        btn.addEventListener('click', () => {
          const m = btn.dataset.mkAct
          propsBody.querySelectorAll('[data-mk-act]').forEach(b => { b.classList.remove('btn-grad'); b.classList.add('btn-outline-ia') })
          btn.classList.remove('btn-outline-ia'); btn.classList.add('btn-grad')
          ;['popup','scene','floorplan'].forEach(k => {
            const el = document.getElementById('mk-act-' + k)
            if (el) el.classList.toggle('d-none', k !== m)
          })
          if (m !== 'scene') { md.scene = ''; const si = document.getElementById('mk-scene-input'); if (si) si.value = '' }
          if (m !== 'floorplan') { md.fp = ''; const fi = document.getElementById('mk-fp-input'); if (fi) fi.value = '' }
          if (m !== 'popup') { md.popupHtml = ''; const pi = document.getElementById('mk-popup-input'); if (pi) pi.value = '' }
        })
      })

      const sync = (key, val) => { md[key] = val; dot.querySelector('.mk-' + (key === 'popupTitle' ? 'popup-title' : key === 'popupHtml' ? 'popup-html' : key)) && (dot.querySelector('.mk-' + (key === 'popupTitle' ? 'popup-title' : key === 'popupHtml' ? 'popup-html' : key)).value = val) }
      document.getElementById('mk-label-input').addEventListener('input', e => { sync('label', e.target.value); dot.dataset.name = e.target.value; propsTitle.textContent = 'Marker — ' + e.target.value })
      document.getElementById('mk-type-input').addEventListener('change', e => { sync('type', e.target.value); dot.dataset.mktype = e.target.value; const b = dot.querySelector('.fp-mktype-badge'); if (b) b.textContent = e.target.value === 'entrance' ? 'IN' : e.target.value === 'exit' ? 'OUT' : 'SC' })
      document.getElementById('mk-facing-input').addEventListener('input', e => { const v = ((parseFloat(e.target.value) || 0) + 360) % 360; setFacing(dot, v) })
      document.getElementById('mk-bld-input').addEventListener('change', e => sync('building', e.target.value))
      document.getElementById('mk-room-input').addEventListener('change', e => sync('room', e.target.value))
      document.getElementById('mk-ptitle-input').addEventListener('input', e => sync('popupTitle', e.target.value))
      document.getElementById('mk-popup-input')?.addEventListener('input', e => sync('popupHtml', e.target.value))
      document.getElementById('mk-scene-input')?.addEventListener('change', e => sync('scene', e.target.value))
      document.getElementById('mk-fp-input')?.addEventListener('change', e => sync('fp', e.target.value))
      document.getElementById('mk-delete-btn').addEventListener('click', async () => {
        if (!confirm('Delete marker "' + md.label + '"?')) return
        dot.remove(); delete markerData[dot.dataset.id]; deselect()
      })
    }

    /* ── Waypoint interactions ─────────────────────────── */
    qsa('.fp-wp-dot').forEach(dot => {
      const xEl = dot.querySelector('.wp-x'), yEl = dot.querySelector('.wp-y')
      makeDraggable(dot, xEl, yEl)
      dot.addEventListener('click', e => {
        e.stopPropagation()
        if (mode === 'move') { selectElement(dot, 'waypoint'); renderWaypointProps(dot) }
      })
    })
    function renderWaypointProps(dot) {
      const lbl = dot.querySelector('.wp-label')?.value || dot.dataset.name
      const isCorner = dot.querySelector('.wp-type')?.value === 'corner'
      propsTitle.textContent = 'Waypoint — ' + lbl
      propsPanel.style.display = ''
      propsBody.innerHTML = `
        <div class="d-flex gap-2 align-items-end flex-wrap">
          <div><label class="form-label fw-semibold mb-1">Label</label>
            <input class="form-control form-control-sm" id="wp-label-input" value="${esc(lbl)}" style="max-width:220px"></div>
          <div class="form-check ms-2 mb-1"><input class="form-check-input" type="checkbox" id="wp-corner-input" ${isCorner?'checked':''}><label class="form-check-label" for="wp-corner-input">Corner (elbow)</label></div>
          <span class="ia-micro mb-1">${parseFloat(dot.querySelector('.wp-x').value).toFixed(1)}%, ${parseFloat(dot.querySelector('.wp-y').value).toFixed(1)}%</span>
          <button type="button" class="btn btn-outline-ia btn-sm text-danger ms-auto" id="wp-delete-btn">Remove</button>
        </div>`
      document.getElementById('wp-label-input').addEventListener('input', e => { dot.querySelector('.wp-label').value = e.target.value; dot.dataset.name = e.target.value })
      document.getElementById('wp-corner-input').addEventListener('change', e => { dot.classList.toggle('is-corner', e.target.checked); dot.querySelector('.wp-type').value = e.target.checked ? 'corner' : 'normal'; renderRoutes() })
      document.getElementById('wp-delete-btn').addEventListener('click', async () => {
        if (!confirm('Remove waypoint "' + lbl + '"?')) return
        deletedWpIds.push(dot.dataset.wpId); dot.remove(); delete wpDotById[dot.dataset.wpId]; deselect()
      })
    }

    /* ── Route drawing ─────────────────────────────────── */
    stage.addEventListener('click', e => {
      if (mode !== 'route') return
      if (e.target.closest('.fp-marker-dot') || e.target.closest('.fp-wp-dot') || e.target.closest('.fp-compass-rose') || e.target.closest('.fp-route-node')) return
      const p = pct(e)
      const existing = hitTestRouteNode(p)
      if (existing) { selectElement(existing.route.nodes[existing.idx] ? stage.querySelector(`[data-route-node="${existing.route.nodes[existing.idx].id || ''}"]`) : null, 'route'); return }
      let route = routes.find(r => r.nodes.length < 2)
      if (!route) route = createRoute()
      route.nodes.push({ x: p.x, y: p.y, corner: false, id: 0, label: route.name + ' #' + route.nodes.length })
      renderRoutes(); renderRouteProps(route)
    })

    function hitTestRouteNode(p) {
      for (const r of routes) for (let i = 0; i < r.nodes.length; i++) if (dist(p, r.nodes[i]) < 3.5) return { route: r, idx: i }
      return null
    }

    stage.addEventListener('dblclick', e => {
      if (mode !== 'route') return
      if (e.target.closest('.fp-marker-dot') || e.target.closest('.fp-wp-dot') || e.target.closest('.fp-compass-rose')) return
      const p = pct(e)
      for (const r of routes) {
        for (let i = 0; i < r.nodes.length - 1; i++) {
          if (pointToSegDist(p, r.nodes[i], r.nodes[i + 1]) < 3) {
            r.nodes.splice(i + 1, 0, { ...p, corner: true, id: 0, label: 'bend' })
            renderRoutes(); return
          }
        }
      }
    })

    function createRoute() {
      routeCounter++
      const r = { id: 0, name: 'Route ' + routeCounter, nodes: [] }
      routes.push(r)
      return r
    }

    function renderRoutes() {
      pathLayer.querySelectorAll('.fp-route-group').forEach(g => g.remove())
      routes.forEach(r => {
        if (r.nodes.length < 2) return
        const g = document.createElement('div')
        g.className = 'fp-route-group'
        g.style.cssText = 'position:absolute;inset:0;pointer-events:none;z-index:3;overflow:visible'
        const ns = 'http://www.w3.org/2000/svg'
        const svg = document.createElementNS(ns, 'svg')
        svg.setAttribute('viewBox', '0 0 100 100')
        svg.setAttribute('preserveAspectRatio', 'none')
        svg.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none;overflow:visible'
        const poly = document.createElementNS(ns, 'polyline')
        poly.setAttribute('points', r.nodes.map(n => n.x + ',' + n.y).join(' '))
        poly.setAttribute('fill', 'none')
        poly.setAttribute('stroke', 'rgba(33,150,243,.85)')
        poly.setAttribute('stroke-width', '0.4')
        poly.setAttribute('stroke-linejoin', 'round')
        poly.setAttribute('stroke-linecap', 'round')
        svg.appendChild(poly)
        g.appendChild(svg)
        r.nodes.forEach((n, i) => {
          const nd = document.createElement('div')
          nd.className = 'fp-route-node' + (n.corner ? ' is-corner' : '')
          nd.style.cssText = `position:absolute;left:${n.x}%;top:${n.y}%;transform:translate(-50%,-50%);width:14px;height:14px;border-radius:50%;background:${n.corner?'#f7c948':'#2196f3'};border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4);cursor:grab;touch-action:none;z-index:4;pointer-events:all`
          nd.dataset.routeNode = n.id || ('new-' + i)
          g.appendChild(nd)
          nd.addEventListener('pointerdown', ev => {
            ev.preventDefault(); ev.stopPropagation()
            const onMove = mev => { const pp = pct(mev); n.x = pp.x; n.y = pp.y; nd.style.left = pp.x + '%'; nd.style.top = pp.y + '%'; renderRoutes() }
            const onUp = () => { document.removeEventListener('pointermove', onMove); document.removeEventListener('pointerup', onUp) }
            document.addEventListener('pointermove', onMove)
            document.addEventListener('pointerup', onUp)
          })
          nd.addEventListener('click', ev => { ev.stopPropagation(); if (mode === 'route') { selectElement(nd, 'route'); renderRouteProps(r, i) } })
        })
        pathLayer.appendChild(g)
      })
    }

    function renderRouteProps(route, nodeIdx) {
      propsTitle.textContent = route.name
      propsPanel.style.display = ''
      propsBody.innerHTML = `
        <div class="d-flex gap-2 align-items-end flex-wrap mb-2">
          <div><label class="form-label fw-semibold mb-1">Route name</label>
            <input class="form-control form-control-sm" id="rt-name-input" value="${esc(route.name)}" style="max-width:260px"></div>
          <span class="ia-micro mb-1">${route.nodes.length} point${route.nodes.length !== 1 ? 's' : ''}</span>
          <button type="button" class="btn btn-outline-ia btn-sm text-danger ms-auto" id="rt-delete-btn">Delete route</button>
        </div>
        <div id="rt-nodes" class="d-flex gap-1 flex-wrap mb-1"></div>
        <div class="ia-micro">Click on map to add points. Double-click a line to create a bend. Drag points to reposition.</div>`
      document.getElementById('rt-name-input').addEventListener('input', e => { route.name = e.target.value; propsTitle.textContent = e.target.value })
      document.getElementById('rt-delete-btn').addEventListener('click', async () => {
        if (!confirm('Delete "' + route.name + '"?')) return
        if (route.id) deletedRouteIds.push(route.id)
        routes.splice(routes.indexOf(route), 1); deselect(); renderRoutes()
      })
      const wrap = document.getElementById('rt-nodes')
      route.nodes.forEach((n, i) => {
        const chip = document.createElement('span')
        chip.className = 'badge rounded-pill ' + (n.corner ? 'text-bg-warning' : 'text-bg-primary')
        chip.style.cursor = 'pointer'
        chip.textContent = (n.corner ? '⤺ ' : '') + (i + 1)
        chip.title = `Point ${i + 1} — ${n.x.toFixed(1)}%, ${n.y.toFixed(1)}%` + (n.corner ? ' (corner)' : '')
        chip.addEventListener('click', () => { n.corner = !n.corner; renderRoutes(); renderRouteProps(route, i) })
        wrap.appendChild(chip)
        if (i < route.nodes.length - 1) {
          const arrow = document.createElement('span')
          arrow.style.cssText = 'color:var(--ia-muted,#999);font-size:11px'
          arrow.textContent = '→'
          wrap.appendChild(arrow)
        }
      })
    }

    /* ── Compass rose (directional markers) ─────────────── */
    const compassRose = document.getElementById('fp-compass')
    const compassDirs = compassRose.querySelectorAll('.compass-dir')
    const compassCenter = document.getElementById('compass-center')
    const roseRadiusPct = 36.67
    const DIR_ANGLES = { N: 0, NE: 45, E: 90, SE: 135, S: 180, SW: 225, W: 270, NW: 315 }

    function positionRose() {
      const rad = northAngle * Math.PI / 180
      compassDirs.forEach(d => {
        const dar = (DIR_ANGLES[d.dataset.dir] || 0) * Math.PI / 180
        const total = dar + rad
        d.style.left = (50 + Math.sin(total) * roseRadiusPct) + '%'
        d.style.top = (50 - Math.cos(total) * roseRadiusPct) + '%'
      })
    }
    function applyNorth(deg) {
      northAngle = ((parseFloat(deg) || 0) % 360 + 360) % 360
      compassRose.style.setProperty('--north', northAngle + 'deg')
      document.getElementById('north-angle-input').value = northAngle.toFixed(1)
      positionRose()
    }

    compassDirs.forEach(dir => {
      dir.addEventListener('pointerdown', e => {
        e.preventDefault(); e.stopPropagation()
        const startAng = northAngle
        const onMove = ev => {
          const r = compassRose.getBoundingClientRect()
          const cx = r.left + r.width / 2, cy = r.top + r.height / 2
          const ang = (Math.atan2(ev.clientY - cy, ev.clientX - cx) * 180 / Math.PI + 90 + 360) % 360
          const dirBase = DIR_ANGLES[dir.dataset.dir] || 0
          applyNorth((ang - dirBase + 360) % 360)
        }
        const onUp = () => { document.removeEventListener('pointermove', onMove); document.removeEventListener('pointerup', onUp) }
        document.addEventListener('pointermove', onMove)
        document.addEventListener('pointerup', onUp)
      })
    })

    compassCenter.addEventListener('pointerdown', e => {
      e.preventDefault(); e.stopPropagation()
      let dragging = false
      const downX = e.clientX, downY = e.clientY
      const startLeft = parseFloat(compassRose.style.left) || 85
      const startTop = parseFloat(compassRose.style.top) || 15
      const onMove = ev => {
        const dx = ev.clientX - downX, dy = ev.clientY - downY
        if (!dragging && Math.hypot(dx, dy) < 3) return
        dragging = true
        const r = stageRect()
        const newLeft = startLeft + (dx / r.width) * 100
        const newTop = startTop + (dy / r.height) * 100
        compassRose.style.left = Math.max(5, Math.min(95, newLeft)) + '%'
        compassRose.style.top = Math.max(5, Math.min(95, newTop)) + '%'
      }
      const onUp = () => { document.removeEventListener('pointermove', onMove); document.removeEventListener('pointerup', onUp) }
      document.addEventListener('pointermove', onMove)
      document.addEventListener('pointerup', onUp)
    })
    positionRose()

    /* ── Exit links ────────────────────────────────────── */
    function renderExitLinkProps(dot) {
      const md = markerData[dot.dataset.id]
      const existing = window._fpConnections.find(c => c.from_marker_id == dot.dataset.id)
      propsTitle.textContent = 'Exit Link — ' + md.label
      propsPanel.style.display = ''
      propsBody.innerHTML = `
        <div class="d-flex gap-2 flex-wrap mb-2">
          <div><label class="form-label fw-semibold mb-1">Sub-floor plan</label>
            <select class="form-select form-select-sm" id="el-fp"><option value="">— none —</option>
            ${window._fpPlans.map(p=>`<option value="${p.id}" ${existing?.to_floor_plan_id==p.id?'selected':''}>${esc(p.name)}</option>`).join('')}</select></div>
          <div><label class="form-label fw-semibold mb-1">360 scene</label>
            <select class="form-select form-select-sm" id="el-scene"><option value="">— none —</option>
            ${window._fpScenes.map(s=>`<option value="${s.id}" ${existing?.to_scene_id==s.id?'selected':''}>${esc(s.name)}</option>`).join('')}</select></div>
          <div><label class="form-label fw-semibold mb-1">Entrance marker</label>
            <select class="form-select form-select-sm" id="el-ent"><option value="">— none —</option>
            ${qsa('.fp-marker-dot[data-mktype="entrance"]').map(d=>`<option value="${d.dataset.id}" ${existing?.to_marker_id==d.dataset.id?'selected':''}>${esc(markerData[d.dataset.id]?.label||d.dataset.name)}</option>`).join('')}</select></div>
          <div><label class="form-label fw-semibold mb-1">Building</label>
            <select class="form-select form-select-sm" id="el-bld"><option value="">— none —</option>
            ${window._fpBuildings.map(b=>`<option value="${b.id}" ${existing?.to_building_id==b.id?'selected':''}>${esc(b.name)}</option>`).join('')}</select></div>
        </div>
        <div class="d-flex gap-2 align-items-end">
          <div class="flex-grow-1"><label class="form-label fw-semibold mb-1">Note</label>
            <input class="form-control form-control-sm" id="el-note" value="${esc(existing?.note||'')}" placeholder="Optional note"></div>
          <button type="button" class="btn btn-grad btn-sm px-3" id="el-save">Save link</button>
          ${existing ? '<button type="button" class="btn btn-outline-ia btn-sm text-danger" id="el-del">Delete</button>' : ''}
        </div>`
      document.getElementById('el-save').addEventListener('click', () => {
        const f = document.getElementById('fp-connections-form')
        let html = `<input type="hidden" name="fp_action" value="connection-save">
          <input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>">
          <input type="hidden" name="from_marker_id" value="${dot.dataset.id}">`
        if (existing) html += `<input type="hidden" name="connection_id" value="${existing.id}">`
        html += `<input type="hidden" name="to_floor_plan_id" value="${document.getElementById('el-fp').value}">
          <input type="hidden" name="to_scene_id" value="${document.getElementById('el-scene').value}">
          <input type="hidden" name="to_marker_id" value="${document.getElementById('el-ent').value}">
          <input type="hidden" name="to_building_id" value="${document.getElementById('el-bld').value}">
          <input type="hidden" name="note" value="${document.getElementById('el-note').value}">`
        f.innerHTML = html; f.submit()
      })
      if (existing) {
        document.getElementById('el-del').addEventListener('click', async () => {
          if (!confirm('Delete this exit link?')) return
          const f = document.getElementById('fp-connections-form')
          f.innerHTML = `<input type="hidden" name="fp_action" value="connection-delete"><input type="hidden" name="plan_id" value="<?= (int) $studio['id'] ?>"><input type="hidden" name="id" value="${existing.id}">`
          f.submit()
        })
      }
    }

    /* ── Close props panel ─────────────────────────────── */
    document.getElementById('props-close').addEventListener('click', deselect)

    /* ── Stage click deselect ──────────────────────────── */
    stage.addEventListener('click', e => { if (mode !== 'route' && !e.target.closest('.fp-marker-dot') && !e.target.closest('.fp-wp-dot') && !e.target.closest('.fp-compass-rose') && !e.target.closest('.fp-route-node')) deselect() })

    /* ── Save all ──────────────────────────────────────── */
    document.getElementById('studio-save-btn').addEventListener('click', () => {
      const planId = <?= (int) $studio['id'] ?>
      const f = document.createElement('form')
      f.method = 'post'
      let html = `<input type="hidden" name="fp_action" value="studio-save-all"><input type="hidden" name="plan_id" value="${planId}">`
      html += `<input type="hidden" name="north_angle" value="${northAngle.toFixed(1)}">`
      let mi = 0
      qsa('.fp-marker-dot').forEach(d => {
        const md = markerData[d.dataset.id]
        if (!md) return
        html += `<input type="hidden" name="markers[${mi}][id]" value="${d.dataset.id}">
          <input type="hidden" name="markers[${mi}][x]" value="${d.querySelector('.mk-x').value}">
          <input type="hidden" name="markers[${mi}][y]" value="${d.querySelector('.mk-y').value}">
          <input type="hidden" name="markers[${mi}][label]" value="${esc(md.label)}">
          <input type="hidden" name="markers[${mi}][marker_type]" value="${md.type}">
          <input type="hidden" name="markers[${mi}][facing_angle]" value="${md.facing}">
          <input type="hidden" name="markers[${mi}][target_building_id]" value="${md.building}">
          <input type="hidden" name="markers[${mi}][target_room_id]" value="${md.room}">
          <input type="hidden" name="markers[${mi}][target_scene_id]" value="${md.scene}">
          <input type="hidden" name="markers[${mi}][target_floor_plan_id]" value="${md.fp}">
          <input type="hidden" name="markers[${mi}][popup_title]" value="${esc(md.popupTitle)}">
          <input type="hidden" name="markers[${mi}][popup_html]" value="${esc(md.popupHtml)}">`
        mi++
      })
      html += `<input type="hidden" name="routes" value="${esc(JSON.stringify(routes.map(r => ({ id: r.id || 0, name: r.name, nodes: r.nodes.map(n => ({ id: n.id || 0, x: n.x, y: n.y, corner: n.corner, label: n.label || '' })) }))))}">`
      html += `<input type="hidden" name="routes_delete" value="${deletedRouteIds.join(',')}">`
      html += `<input type="hidden" name="waypoints_delete" value="${deletedWpIds.join(',')}">`
      f.innerHTML = html
      document.body.appendChild(f)
      f.submit()
    })

    /* ── Init ──────────────────────────────────────────── */
    setMode('move')
    renderRoutes()

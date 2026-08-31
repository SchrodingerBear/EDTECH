(() => {
  const root = document.documentElement
  const menu = document.getElementById('mobile-nav')
  const menuToggle = document.getElementById('menu-toggle')
  const themeButtons = document.querySelectorAll('[data-theme-toggle]')
  const search = document.getElementById('campus-search')
  const filters = document.querySelectorAll('[data-filter]')
  const grid = document.getElementById('campus-grid')
  const modal = document.getElementById('tour-modal')
  const modalName = document.getElementById('modal-campus-name')
  const modalHint = document.getElementById('modal-campus-hint')
  const modalImg = document.getElementById('modal-campus-img')
  const campuses = window.CAMPUS_DATA || []

  let filter = 'All'
  let query = ''
  let isDark = window.matchMedia('(prefers-color-scheme: dark)').matches

  const applyTheme = () => {
    root.classList.toggle('dark', isDark)
    root.classList.toggle('light', !isDark)
    themeButtons.forEach((btn) => {
      const label = btn.querySelector('[data-theme-label]')
      btn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode')
      btn.querySelector('[data-icon-sun]')?.classList.toggle('is-hidden', !isDark)
      btn.querySelector('[data-icon-moon]')?.classList.toggle('is-hidden', isDark)
      if (label) label.textContent = isDark ? 'Light mode' : 'Dark mode'
    })
  }

  const closeMenu = () => {
    menu?.classList.remove('is-open')
    menuToggle?.setAttribute('aria-expanded', 'false')
    document.body.style.overflow = ''
  }

  const renderCampuses = () => {
    if (!grid) return
    const q = query.trim().toLowerCase()
    const list = campuses.filter((c) => {
      const typeOk = filter === 'All' || c.type === filter
      const hay = `${c.name} ${c.location}`.toLowerCase()
      return typeOk && hay.includes(q)
    })

    if (!list.length) {
      grid.innerHTML = '<p class="empty">No campuses match that search.</p>'
      return
    }

    grid.innerHTML = list.map((c) => `
      <article class="campus-card" data-type="${c.type}">
        <img src="${c.cover}" alt="${c.name}">
        <div class="body">
          <div class="campus-meta">
            <span class="short">${c.short}</span>
            <span class="status">${c.status}</span>
          </div>
          <h3>${c.name}</h3>
          <p>${c.location} · ${c.buildings} building${c.buildings !== 1 ? 's' : ''}</p>
          <a class="view-tour" href="${c.url}" target="_blank" rel="noopener">
            View tour
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 7h10v10"/><path d="M7 17 17 7"/></svg>
          </a>
        </div>
      </article>
    `).join('')
  }

  const openTour = (id) => {
    const campus = campuses.find((c) => String(c.id) === String(id))
    if (!campus || !modal) return
    modalName.textContent = campus.name
    modalHint.textContent = `Drag to look around ${campus.name}`
    modalImg.src = campus.cover
    modalImg.alt = `${campus.name} virtual tour preview`
    modal.classList.add('is-open')
  }

  const closeTour = () => modal?.classList.remove('is-open')

  menuToggle?.addEventListener('click', () => {
    const open = !menu.classList.contains('is-open')
    menu.classList.toggle('is-open', open)
    menuToggle.setAttribute('aria-expanded', String(open))
    menuToggle.setAttribute('aria-label', open ? 'Close menu' : 'Toggle menu')
    document.body.style.overflow = open ? 'hidden' : ''
  })

  document.querySelectorAll('[data-close-menu]').forEach((el) => {
    el.addEventListener('click', closeMenu)
  })
  
  // Close menu when clicking outside the menu content
  menu?.addEventListener('click', (e) => {
    if (e.target === menu) {
      closeMenu()
    }
  })

  themeButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      isDark = !isDark
      applyTheme()
    })
  })

  search?.addEventListener('input', (e) => {
    query = e.target.value
    renderCampuses()
  })

  filters.forEach((btn) => {
    btn.addEventListener('click', () => {
      filter = btn.dataset.filter
      filters.forEach((b) => b.classList.toggle('is-active', b === btn))
      renderCampuses()
    })
  })

  grid?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-open-tour]')
    if (btn) openTour(btn.dataset.openTour)
  })

  modal?.addEventListener('click', (e) => {
    if (e.target === modal || e.target.closest('[data-close-modal]')) closeTour()
  })

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeTour()
      closeMenu()
    }
  })

  applyTheme()
  renderCampuses()
})()

/* ===========================================================================
   Innovatech PH — dashboard interactions
   Dark/light toggle (data-bs-theme), sidebar collapse, toasts, autoback,
   confirm helpers, delete-row fetch, flash rendering.
   =========================================================================== */

/* ── Global modal confirm/prompt (replaces browser alert/confirm/prompt) ── */
; (function () {
  function ensureModalRoot() {
    let el = document.getElementById('ia-confirm-root')
    if (el) return el
    el = document.createElement('div')
    el.id = 'ia-confirm-root'
    el.innerHTML = `
      <div id="ia-cm" class="modal fade" tabindex="-1" role="dialog" aria-modal="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
          <div class="modal-content">
            <div class="modal-header pb-2">
              <h5 class="modal-title" id="ia-cm-title" style="font-size:15px;font-weight:700"></h5>
              <button type="button" class="btn-close" data-ia-cm-cancel></button>
            </div>
            <div class="modal-body pt-2">
              <p id="ia-cm-msg" style="margin:0;font-size:13.5px;color:var(--ia-muted)"></p>
              <div id="ia-cm-input-wrap" style="margin-top:10px;display:none">
                <input id="ia-cm-input" class="form-control" type="text" autocomplete="off">
              </div>
            </div>
            <div class="modal-footer pt-2" style="gap:8px">
              <button type="button" class="btn btn-sm btn-outline-ia" data-ia-cm-cancel>Cancel</button>
              <button type="button" class="btn btn-sm btn-grad" id="ia-cm-ok">OK</button>
            </div>
          </div>
        </div>
      </div>`
    document.body.appendChild(el)
    return el
  }

  /**
   * iaConfirm(message, title?) → Promise<boolean>
   * Shows a modal confirmation dialog. Resolves true on OK, false on Cancel.
   */
  window.iaConfirm = function (message, title) {
    return new Promise(function (resolve) {
      ensureModalRoot()
      const modalEl = document.getElementById('ia-cm')
      document.getElementById('ia-cm-title').textContent = title || 'Confirm'
      document.getElementById('ia-cm-msg').textContent = message || 'Are you sure?'
      const wrap = document.getElementById('ia-cm-input-wrap')
      wrap.style.display = 'none'
      const okBtn = document.getElementById('ia-cm-ok')
      okBtn.textContent = 'Confirm'
      okBtn.className = 'btn btn-sm btn-danger'

      let bsModal = bootstrap.Modal.getOrCreateInstance(modalEl)
      bsModal.show()

      function cleanup(result) {
        bsModal.hide()
        okBtn.replaceWith(okBtn.cloneNode(true)) // remove old listeners
        resolve(result)
      }

      document.getElementById('ia-cm-ok').addEventListener('click', function () { cleanup(true) }, { once: true })
      modalEl.querySelectorAll('[data-ia-cm-cancel]').forEach(function (btn) {
        btn.addEventListener('click', function () { cleanup(false) }, { once: true })
      })
      modalEl.addEventListener('hidden.bs.modal', function () { resolve(false) }, { once: true })
    })
  }

  /**
   * iaPrompt(message, defaultValue?, title?) → Promise<string|null>
   * Shows a modal prompt dialog. Resolves with string on OK, null on Cancel.
   */
  window.iaPrompt = function (message, defaultValue, title) {
    return new Promise(function (resolve) {
      ensureModalRoot()
      const modalEl = document.getElementById('ia-cm')
      document.getElementById('ia-cm-title').textContent = title || 'Enter value'
      document.getElementById('ia-cm-msg').textContent = message || ''
      const wrap = document.getElementById('ia-cm-input-wrap')
      const input = document.getElementById('ia-cm-input')
      wrap.style.display = ''
      input.value = defaultValue || ''
      const okBtn = document.getElementById('ia-cm-ok')
      okBtn.textContent = 'OK'
      okBtn.className = 'btn btn-sm btn-grad'

      let bsModal = bootstrap.Modal.getOrCreateInstance(modalEl)
      bsModal.show()
      setTimeout(function () { input.focus(); input.select() }, 300)

      function cleanup(val) {
        bsModal.hide()
        okBtn.replaceWith(okBtn.cloneNode(true))
        resolve(val)
      }

      document.getElementById('ia-cm-ok').addEventListener('click', function () { cleanup(input.value) }, { once: true })
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); cleanup(input.value) }
        if (e.key === 'Escape') { e.preventDefault(); cleanup(null) }
      })
      modalEl.querySelectorAll('[data-ia-cm-cancel]').forEach(function (btn) {
        btn.addEventListener('click', function () { cleanup(null) }, { once: true })
      })
      modalEl.addEventListener('hidden.bs.modal', function () { resolve(null) }, { once: true })
    })
  }
})();

(() => {
  'use strict'

  const ROOT = document.documentElement
  const STORE_KEY = 'ia-theme'

  /* ------------------------------ theme toggle ----------------------------- */
  const applyTheme = (dark) => {
    ROOT.setAttribute('data-bs-theme', dark ? 'dark' : 'light')
    try { localStorage.setItem(STORE_KEY, dark ? 'dark' : 'light') } catch (e) { }
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
      btn.classList.toggle('is-dark', dark)
      const label = btn.querySelector('[data-theme-label]')
      if (label) label.textContent = dark ? 'Dark' : 'Light'
    })
  }

  const initial = (() => {
    try {
      const saved = localStorage.getItem(STORE_KEY)
      if (saved) return saved === 'dark'
    } catch (e) { }
    return window.matchMedia('(prefers-color-scheme: dark)').matches
  })()

  applyTheme(initial)

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-theme-toggle]')
    if (!btn) return
    applyTheme(ROOT.getAttribute('data-bs-theme') !== 'dark')
  })

  /* ----------------------------- sidebar state ----------------------------- */
  const shell = document.querySelector('.ia-shell')
  const collapseBtn = document.getElementById('sidebar-collapse')
  const menuBtn = document.getElementById('sidebar-open')

  if (collapseBtn && shell) {
    collapseBtn.addEventListener('click', () => {
      shell.classList.toggle('is-collapsed')
    })
  }

  if (menuBtn) {
    menuBtn.addEventListener('click', () => {
      shell.querySelector('.ia-backdrop').classList.add('show')
      shell.classList.add('sidebar-open')
    })
  }

  document.addEventListener('click', (e) => {
    if (!shell) return
    if (e.target.closest('.ia-backdrop')) {
      shell.classList.remove('sidebar-open')
      shell.querySelector('.ia-backdrop').classList.remove('show')
    }
  })

  /* -------------------------------- toasts -------------------------------- */
  const toastBox = document.createElement('div')
  toastBox.className = 'ia-toast'
  document.body.appendChild(toastBox)

  window.iaToast = (message, type = 'info', title = '') => {
    const el = document.createElement('div')
    el.className = `toast-item ${type}`
    const icon = { success: '✓', error: '✕', warning: '!', info: 'ℹ' }[type] || 'ℹ'
    el.innerHTML = `
      <div style="width:20px;height:20px;border-radius:50%;background:var(--ia-surface-2);
           display:grid;place-items:center;font-size:12px;color:var(--ia-text);flex:0 0 20px">${icon}</div>
      <div style="flex:1">
        ${title ? `<strong style="display:block;font-size:13px">${escapeHtml(title)}</strong>` : ''}
        <span style="font-size:13.5px;color:var(--ia-text)">${escapeHtml(message)}</span>
      </div>`
    toastBox.appendChild(el)
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .25s'; setTimeout(() => el.remove(), 260) }, 3400)
  }

  /* ------------------------------ flash render ----------------------------- */
  const flashEl = document.getElementById('flash-data')
  if (flashEl) {
    try {
      const flashes = JSON.parse(flashEl.textContent)
      flashes.forEach((f) => window.iaToast(f.message, f.type))
    } catch (e) { }
  }

  /* ------------------------------ auto submit ------------------------------ */
  document.body.addEventListener('change', (e) => {
    if (e.target.matches('[data-autosubmit]')) e.target.form?.submit()
  })

  /* ------------------------------- confirm --------------------------------- */
  document.body.addEventListener('click', async (e) => {
    const target = e.target.closest('[data-confirm]')
    if (!target) return
    // Skip if this element is also part of a data-delete-form (handled below)
    if (target.closest('[data-delete-form]')) return
    e.preventDefault()
    const ok = await window.iaConfirm(target.dataset.confirm || 'Are you sure?')
    if (ok) {
      target.removeAttribute('data-confirm')
      target.click()
    }
  })

  /* ----------------------------- delete via fetch -------------------------- */
  document.body.addEventListener('submit', async (e) => {
    const form = e.target
    if (!form.matches('[data-delete-form]')) return
    e.preventDefault()
    const ok = await window.iaConfirm(form.dataset.confirm || 'Delete this record?', 'Confirm delete')
    if (!ok) return
    try {
      const res = await fetch(form.action || location.href, { method: 'POST', body: new FormData(form) })
      const data = await res.json().catch(() => ({}))
      if (data.ok) {
        window.iaToast(data.message || 'Deleted', 'success')
        form.closest('tr')?.remove()
        setTimeout(() => location.reload(), 700)
      } else {
        window.iaToast(data.message || 'Delete failed', 'error')
        setTimeout(() => location.reload(), 900)
      }
    } catch (err) {
      window.iaToast('Network error', 'error')
    }
  })

  function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
  }

  /* --------------------------- password visibility ------------------------- */
  // Any element with [data-pw] (id of an input) toggles that input's type.
  const togglePw = (input, btn) => {
    const isText = input.type === 'text'
    input.type = isText ? 'password' : 'text'
    if (btn) {
      btn.classList.toggle('is-revealed', !isText)
      btn.setAttribute('aria-pressed', isText ? 'false' : 'true')
      btn.setAttribute('title', isText ? 'Show password' : 'Hide password')
    }
  }
  document.body.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-pw]')
    if (!btn) return
    const input = document.getElementById(btn.dataset.pw)
    if (input) togglePw(input, btn)
  })

  /* ----------------------- automated temp-password tools ------------------- */
  // [data-gen] fills an autogenerated temporary password into its target input
  // (and reveals it).
  const genPw = (len = 14) => {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'
    const out = []
    for (let i = 0; i < len; i++) out.push(chars[Math.floor(Math.random() * chars.length)])
    const num = Math.floor(Math.random() * 10)
    const special = ['!', '@', '#', '$', '%', '&'][Math.floor(Math.random() * 6)]
    out.splice(Math.floor(Math.random() * (len + 1)), 0, num)
    out.splice(Math.floor(Math.random() * (len + 1)), 0, special)
    return out.join('')
  }
  document.body.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-gen]')
    if (!btn) return
    const input = document.getElementById(btn.dataset.gen)
    if (!input) return
    input.value = genPw()
    input.type = 'text'
    const eye = document.querySelector(`[data-pw="${btn.dataset.gen}"]`)
    if (eye) eye.classList.add('is-revealed')
  })

  // Keep the "email credentials to" label in sync with the email field.
  const syncEmailTarget = () => {
    const modal = document.getElementById('acc-create')
    if (!modal) return
    const email = modal.querySelector('input[name="email"]')
    const label = document.getElementById('acc-send-target')
    if (email && label) {
      label.textContent = email.value.trim() ? email.value.trim() : 'the account email'
    }
  }
  document.body.addEventListener('input', syncEmailTarget)
  document.body.addEventListener('change', syncEmailTarget)
  document.addEventListener('shown.bs.modal', syncEmailTarget)
})()

/* Global helper so other scripts can reuse the generator. */
window.iaGenPassword = () => {
  /* forwarded implementation — live in the IIFE above */
}

/* ------------------------------ datatables ------------------------------- */
document.addEventListener('DOMContentLoaded', () => {
  if (typeof simpleDatatables === 'undefined') return

  const perPageSizes = [10, 25, 50, 100]

  const applyPerPageLabel = (table, dt) => {
    const wrap = table.closest('.datatable-wrapper, .dataTable-wrapper')
    if (!wrap) return
    wrap.querySelectorAll('.datatable-dropdown, .dataTable-dropdown').forEach(drop => {
      let sel = drop.querySelector('select')
      if (!sel) {
        sel = document.createElement('select')
        sel.className = 'datatable-selector'
        perPageSizes.forEach(n => {
          const opt = document.createElement('option')
          opt.value = String(n)
          opt.textContent = String(n)
          sel.appendChild(opt)
        })
      }
      sel.classList.add('datatable-selector')
      if (!sel.options.length) {
        perPageSizes.forEach(n => {
          const opt = document.createElement('option')
          opt.value = String(n)
          opt.textContent = String(n)
          sel.appendChild(opt)
        })
      }
      const current = (dt.options && dt.options.perPage) ? String(dt.options.perPage) : '10'
      sel.value = current
      if (!sel.dataset.iaBound) {
        sel.dataset.iaBound = '1'
        sel.addEventListener('change', () => {
          const n = parseInt(sel.value, 10) || 10
          if (typeof dt.setPerPage === 'function') dt.setPerPage(n)
          else if (dt.options) {
            dt.options.perPage = n
            if (typeof dt.update === 'function') dt.update()
          }
        })
      }
      drop.replaceChildren()
      const lab = document.createElement('label')
      lab.append('Show ', sel, ' entries')
      drop.appendChild(lab)
    })
  }

  document.querySelectorAll('table.table-ia').forEach(table => {
    if (table.classList.contains('no-datatable')) return
    if (table.closest('.modal')) return
    const tbody = table.tBodies[0]
    if (!tbody) return
    const rows = Array.from(tbody.rows)
    if (!table.hasAttribute('data-force-datatable')) {
      if (!rows.length) return
      if (rows.some(r => Array.from(r.cells).some(c => c.colSpan > 1))) return
    }

    const lastCol = table.tHead && table.tHead.rows[0] ? table.tHead.rows[0].cells.length - 1 : -1
    const opts = {
      searchable: true,
      fixedHeight: false,
      perPage: 10,
      perPageSelect: perPageSizes,
      labels: {
        placeholder: 'Search…',
        noRows: 'No entries found',
        noResults: 'No matching results',
        info: 'Showing {start}–{end} of {rows} entries'
      }
    }
    if (lastCol >= 0) {
      opts.columns = [{ select: lastCol, sortable: false }]
    }
    const dt = new simpleDatatables.DataTable(table, opts)
    applyPerPageLabel(table, dt)
  })
})
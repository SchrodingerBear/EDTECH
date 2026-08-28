/* ===========================================================================
   Innovatech PH — dashboard interactions
   Dark/light toggle (data-bs-theme), sidebar collapse, toasts, autoback,
   confirm helpers, delete-row fetch, flash rendering.
   =========================================================================== */

(() => {
  'use strict'

  const ROOT = document.documentElement
  const STORE_KEY = 'ia-theme'

  /* ------------------------------ theme toggle ----------------------------- */
  const applyTheme = (dark) => {
    ROOT.setAttribute('data-bs-theme', dark ? 'dark' : 'light')
    try { localStorage.setItem(STORE_KEY, dark ? 'dark' : 'light') } catch (e) {}
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
    } catch (e) {}
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
    } catch (e) {}
  }

  /* ------------------------------ auto submit ------------------------------ */
  document.body.addEventListener('change', (e) => {
    if (e.target.matches('[data-autosubmit]')) e.target.form?.submit()
  })

  /* ------------------------------- confirm --------------------------------- */
  document.body.addEventListener('click', (e) => {
    const target = e.target.closest('[data-confirm]')
    if (!target) return
    if (!window.confirm(target.dataset.confirm || 'Are you sure?')) {
      e.preventDefault()
      e.stopImmediatePropagation()
    }
  })

  /* ----------------------------- delete via fetch -------------------------- */
  document.body.addEventListener('submit', async (e) => {
    const form = e.target
    if (!form.matches('[data-delete-form]')) return
    e.preventDefault()
    if (!window.confirm(form.dataset.confirm || 'Delete this record?')) return
    try {
      const res = await fetch(form.action, { method: 'POST', body: new FormData(form) })
      const data = await res.json().catch(() => ({}))
      if (data.ok) {
        window.iaToast(data.message || 'Deleted', 'success')
        form.closest('tr')?.remove()
        setTimeout(() => location.reload(), 700)
      } else {
        window.iaToast(data.message || 'Delete failed', 'error')
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
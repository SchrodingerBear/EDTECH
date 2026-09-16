document.addEventListener('DOMContentLoaded', () => {
  const stage = document.getElementById('stage')
  if (!stage) return
  let moving = null
  document.querySelectorAll('.mk-dot').forEach((d) => {
    d.addEventListener('click', () => {
      document.querySelectorAll('.mk-dot').forEach(x => x.classList.remove('moving'))
      if (moving === d) { moving = null; return }
      moving = d; d.classList.add('moving')
    })
  })
  stage.addEventListener('click', (e) => {
    if (!moving) return
    if (e.target === stage.querySelector('img') || e.target.id === 'stage') {
      const r = stage.getBoundingClientRect()
      const fix = (el) => {
        const r2 = el.getBoundingClientRect()
        return ((e.clientX - r2.left) / r2.width * 100).toFixed(3)
      }
      const x = ((e.clientX - r.left) / r.width * 100).toFixed(3)
      const y = ((e.clientY - r.top) / r.height * 100).toFixed(3)
      const id = moving.dataset.mid
      fetch(location.href.split('?')[0], {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ sfp_action: 'marker-save', id, x, y, label: moving.dataset.name, plan_id: <?= (int) ($plan['id'] ?? 0) ?> }).toString(),
        credentials: 'same-origin'
      }).then(() => location.reload())
      }
    }
  })
  document.getElementById('mk-modal')?.addEventListener('show.bs.modal', (e) => {
    const b = e.relatedTarget
    document.getElementById('mk-x-new').value = b.dataset.x || 50
    document.getElementById('mk-y-new').value = b.dataset.y || 50
  })
})

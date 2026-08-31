/* Innovatech PH — wires every [data-ai-gen] button to the AI generator endpoint.
   Button attrs: data-ai-type, data-ai-id-el (id of the record-id input) or data-ai-id,
   data-ai-target-el (textarea id to fill), optional data-ai-url. */
(function () {
  function bindIaAiGen() {
    document.querySelectorAll('[data-ai-gen]').forEach(function (btn) {
      if (btn.__iaAiBound) return;
      btn.__iaAiBound = true;
      btn.addEventListener('click', function () {
        var idEl = btn.dataset.aiIdEl ? document.getElementById(btn.dataset.aiIdEl) : null;
        var id = parseInt((idEl || {}).value || '0', 10) || parseInt(btn.dataset.aiId || '0', 10);
        if (!id) { alert('Save this record first, then generate its description.'); return; }
        btn.disabled = true;
        var fd = new FormData();
        fd.append('type', btn.dataset.aiType);
        fd.append('id', id);
        fetch(btn.dataset.aiUrl || 'ai-generate', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            btn.disabled = false;
            if (!d.ok) { alert(d.error || 'Generation failed.'); return; }
            var ta = btn.dataset.aiTargetEl ? document.getElementById(btn.dataset.aiTargetEl) : btn.parentElement.querySelector('textarea');
            if (ta) ta.value = d.text;
            btn.classList.remove('btn-outline-ia');
            btn.classList.add('btn-grad');
            btn.textContent = '\u2713 Generated';
          })
          .catch(function () { btn.disabled = false; alert('Generation failed.'); });
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindIaAiGen);
  else bindIaAiGen();
})();
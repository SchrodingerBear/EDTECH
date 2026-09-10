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
        if (!id) { 
          // Use inline error instead of alert
          showError(btn, 'Save this record first, then generate its description.');
          return; 
        }
        btn.disabled = true;
        var originalText = btn.textContent;
        btn.textContent = 'Generating...';
        var fd = new FormData();
        fd.append('type', btn.dataset.aiType);
        fd.append('id', id);
        fetch(btn.dataset.aiUrl || 'ai-generate', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            btn.disabled = false;
            btn.textContent = originalText;
            if (!d.ok) { 
              showError(btn, d.error || 'Generation failed.'); 
              return; 
            }
            var ta = btn.dataset.aiTargetEl ? document.getElementById(btn.dataset.aiTargetEl) : btn.parentElement.querySelector('textarea');
            if (ta) ta.value = d.text;
            btn.classList.remove('btn-outline-ia');
            btn.classList.add('btn-grad');
            btn.textContent = '\u2713 Generated';
            showSuccess(btn, 'Description generated successfully!');
          })
          .catch(function () { 
            btn.disabled = false; 
            btn.textContent = originalText;
            showError(btn, 'Generation failed.'); 
          });
      });
    });
  }
  
  function showError(btn, message) {
    // Remove existing alerts
    removeAlerts(btn);
    // Create error alert
    var alert = document.createElement('div');
    alert.className = 'alert alert-danger alert-dismissible fade show mt-2';
    alert.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    btn.parentElement.appendChild(alert);
    // Auto-dismiss after 3 seconds
    setTimeout(function() {
      if (alert.parentElement) {
        alert.classList.remove('show');
        setTimeout(function() { if (alert.parentElement) alert.remove(); }, 150);
      }
    }, 3000);
  }
  
  function showSuccess(btn, message) {
    // Remove existing alerts
    removeAlerts(btn);
    // Create success alert
    var alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show mt-2';
    alert.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    btn.parentElement.appendChild(alert);
    // Auto-dismiss after 2 seconds
    setTimeout(function() {
      if (alert.parentElement) {
        alert.classList.remove('show');
        setTimeout(function() { if (alert.parentElement) alert.remove(); }, 150);
      }
    }, 2000);
  }
  
  function removeAlerts(btn) {
    var existingAlerts = btn.parentElement.querySelectorAll('.alert');
    existingAlerts.forEach(function(alert) {
      alert.remove();
    });
  }
  
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindIaAiGen);
  else bindIaAiGen();
})();
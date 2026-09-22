<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
/**
 * Lightweight AI Description Generator Demo
 * Simple template-based generation (can be replaced with real LLM later)
 */
$pageTitle = 'AI Description Generator Demo';
$pageSub = 'Lightweight description generation for testing';
$active = 'AI Demo';
$bodyClass = 'page-ai-demo';
require_once __DIR__ . '/layout/header.php';

// Simple template-based generation (placeholder for real AI)
function generate_description($name, $type, $context = '') {
    $templates = [
        'building' => [
            "The {name} building is a key part of campus life, featuring modern facilities and accessible design. Students and visitors can easily locate it using the campus floor plan and explore its interior through the 360° virtual tour.",
            "{name} serves as an important hub for campus activities. With its distinctive architecture and central location, it's easily recognizable and fully accessible via the interactive floor plan system.",
            "A cornerstone of the campus, {name} combines functionality with aesthetic appeal. The building houses essential facilities and is prominently featured in the virtual campus tour for prospective students."
        ],
        'room' => [
            "The {name} room provides a focused environment for learning and collaboration. Its modern design and equipment support various educational activities, making it a valuable space for students and faculty.",
            "{name} offers a versatile space designed for multiple uses. Whether for lectures, study sessions, or group work, this room adapts to different needs while maintaining comfort and functionality.",
            "Designed with student success in mind, {name} features state-of-the-art facilities and a welcoming atmosphere. It's easily accessible via the campus navigation system and included in the virtual tour."
        ],
        'facility' => [
            "{name} is an essential facility that enhances the campus experience. Its strategic location and modern amenities make it a popular destination for students and visitors alike.",
            "The {name} facility supports the academic and social life of the campus community. With its comprehensive services and accessible design, it plays a vital role in daily campus operations.",
            "A key campus resource, {name} provides important services to students and staff. Its integration with the campus navigation system ensures easy discovery and access."
        ]
    ];
    
    $type_templates = $templates[$type] ?? $templates['building'];
    $template = $type_templates[array_rand($type_templates)];
    
    $description = str_replace('{name}', $name, $template);
    
    if ($context) {
        $description .= " " . $context;
    }
    
    return $description;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'building';
    $context = trim($_POST['context'] ?? '');
    
    if ($name) {
        $generated = generate_description($name, $type, $context);
    }
}
?>
<div class="ia-card">
  <div class="card-head">
    <h3>AI Description Generator Demo</h3>
    <p class="mb-0 text-muted small">Lightweight template-based generation (placeholder for real LLM)</p>
  </div>
  <div class="card-body">
    <form method="post" class="d-grid gap-3">
      <div>
        <label class="form-label">Name/Title</label>
        <input class="form-control" name="name" required placeholder="e.g., Main Library, Science Building" value="<?= h($name ?? '') ?>">
      </div>
      
      <div>
        <label class="form-label">Type</label>
        <select class="form-select" name="type">
          <option value="building" <?= ($type ?? '') === 'building' ? 'selected' : '' ?>>Building</option>
          <option value="room" <?= ($type ?? '') === 'room' ? 'selected' : '' ?>>Room</option>
          <option value="facility" <?= ($type ?? '') === 'facility' ? 'selected' : '' ?>>Facility</option>
        </select>
      </div>
      
      <div>
        <label class="form-label">Additional Context (optional)</label>
        <textarea class="form-control" name="context" rows="2" placeholder="Any additional details to include..."><?= h($context ?? '') ?></textarea>
      </div>
      
      <button type="submit" class="btn btn-grad">Generate Description</button>
    </form>
    
    <?php if (isset($generated)): ?>
      <div class="mt-4 p-3 border rounded-3 bg-light">
        <label class="form-label small fw-semibold">Generated Description:</label>
        <p class="mb-0"><?= h($generated) ?></p>
        <div class="mt-2">
          <button class="btn btn-sm btn-outline-ia" onclick="copyToClipboard('<?= h(addslashes($generated)) ?>')">Copy</button>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="ia-card mt-3">
  <div class="card-head">
    <h3>Integration Notes</h3>
  </div>
  <div class="card-body">
    <p class="mb-2"><strong>Current Implementation:</strong> Template-based generation (simple, fast, no API calls)</p>
    <p class="mb-2"><strong>Future Enhancement:</strong> Replace with real LLM API (OpenAI, Anthropic, or local WebGPU model)</p>
    <p class="mb-0 text-muted small">This demo page can be easily removed or replaced with a production AI integration.</p>
  </div>
</div>

<script>
function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    // Show inline success message instead of alert
    showCopySuccess();
  }).catch(err => {
    console.error('Failed to copy:', err);
    showCopyError();
  });
}

function showCopySuccess() {
  var btn = document.querySelector('[onclick*="copyToClipboard"]');
  if (btn) {
    var originalText = btn.textContent;
    btn.textContent = '✓ Copied!';
    btn.classList.add('btn-success');
    setTimeout(function() {
      btn.textContent = originalText;
      btn.classList.remove('btn-success');
    }, 2000);
  }
}

function showCopyError() {
  var btn = document.querySelector('[onclick*="copyToClipboard"]');
  if (btn) {
    var originalText = btn.textContent;
    btn.textContent = '✗ Failed';
    btn.classList.add('btn-danger');
    setTimeout(function() {
      btn.textContent = originalText;
      btn.classList.remove('btn-danger');
    }, 2000);
  }
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>

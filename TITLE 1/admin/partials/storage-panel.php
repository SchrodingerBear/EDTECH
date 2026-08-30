<?php
/**
 * Doughnut + legend for project storage.
 * Optional: $storageChartId
 */
$storageChartId = $storageChartId ?? 'ia-storage-chart';
if (!isset($storage) || !isset($storageUsed)) {
    $st = project_storage_stats();
    $storage = $st['storage'];
    $storageUsed = $st['storageUsed'];
    $diskFree = $st['diskFree'];
    $diskTotal = $st['diskTotal'];
}
$diskFree = $diskFree ?? 0;
$diskTotal = $diskTotal ?? 0;
?>
<div class="ia-card h-100">
  <div class="card-head">
    <h3>System storage</h3>
    <span class="fs-125 text-ia-muted"><?= h(format_bytes((int) $storageUsed)) ?> in use</span>
  </div>
  <div class="card-body">
    <div class="row g-4 align-items-center">
      <div class="col-md-5">
        <div class="ia-storage-chart-wrap">
          <canvas id="<?= h($storageChartId) ?>" aria-label="Storage breakdown"></canvas>
          <div class="ia-storage-chart-center">
            <strong><?= h(format_bytes((int) $storageUsed)) ?></strong>
            <span>project files</span>
          </div>
        </div>
      </div>
      <div class="col-md-7">
        <?php
        $legend = [
          ['Campuses', $storage['organizations'], '#5b5bd6'],
          ['Public media', $storage['public'], '#38b2ac'],
          ['Core assets', $storage['assets'], '#ed8936'],
        ];
        foreach ($legend as [$lab, $bytes, $color]):
          $pct = $storageUsed > 0 ? round(($bytes / $storageUsed) * 100) : 0;
        ?>
          <div class="ia-storage-row">
            <span class="ia-storage-dot" style="background:<?= h($color) ?>"></span>
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between gap-2">
                <span><?= h($lab) ?></span>
                <strong><?= h(format_bytes((int) $bytes)) ?></strong>
              </div>
              <div class="ia-storage-bar"><span style="width:<?= $pct ?>%;background:<?= h($color) ?>"></span></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if ($diskTotal > 0): ?>
          <p class="mb-0 mt-3 fs-125 text-ia-muted">
            Disk <?= h(format_bytes($diskTotal - $diskFree)) ?> of <?= h(format_bytes($diskTotal)) ?> used on this volume
            (<?= h(format_bytes($diskFree)) ?> free).
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  var el = document.getElementById(<?= json_encode($storageChartId) ?>);
  if (!el || typeof Chart === 'undefined') return;
  var used = <?= json_encode(array_values($storage)) ?>;
  new Chart(el, {
    type: 'doughnut',
    data: {
      labels: ['Campuses', 'Public media', 'Core assets'],
      datasets: [{
        data: used,
        backgroundColor: ['#5b5bd6', '#38b2ac', '#ed8936'],
        borderWidth: 0,
        hoverOffset: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '72%',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function (ctx) {
              var n = ctx.raw || 0;
              var units = ['B', 'KB', 'MB', 'GB'];
              var i = 0, v = n;
              while (v >= 1024 && i < 3) { v /= 1024; i++; }
              return ' ' + ctx.label + ': ' + v.toFixed(v >= 10 || i === 0 ? 0 : 1) + ' ' + units[i];
            }
          }
        }
      }
    }
  });
})();
</script>

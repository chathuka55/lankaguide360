<?php
$pageTitle = 'Chatbot Logs';
require __DIR__ . '/_layout_top.php';

$logs = $db->query("SELECT * FROM chatbot_logs ORDER BY created_at DESC LIMIT 100")->fetchAll();

$intentBreakdown = $db->query(
    "SELECT matched_intent, COUNT(*) AS c FROM chatbot_logs GROUP BY matched_intent ORDER BY c DESC"
)->fetchAll();
?>

<h3 class="mb-4">Chatbot conversation logs</h3>

<div class="row g-4 mb-4">
  <div class="col-lg-4">
    <div class="admin-card">
      <h6 class="mb-3">Intent breakdown</h6>
      <?php foreach ($intentBreakdown as $row): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small">
          <span class="text-capitalize"><?= e(str_replace('_',' ', $row['matched_intent'] ?: 'unmatched')) ?></span>
          <span class="text-muted"><?= (int)$row['c'] ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$intentBreakdown): ?><p class="text-muted small mb-0">No conversations logged yet — try the chat widget on the site.</p><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="admin-card">
      <h6 class="mb-3">Recent messages</h6>
      <table class="table table-lg align-middle">
        <thead><tr><th>Time</th><th>Message</th><th>Matched intent</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td class="small text-muted"><?= e($l['created_at']) ?></td>
            <td class="small"><?= e($l['user_message']) ?></td>
            <td><span class="tag-pill"><?= e($l['matched_intent'] ?: 'fallback') ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="3" class="text-muted small">No conversations yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>

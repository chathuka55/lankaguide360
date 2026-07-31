<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/Workflow.php';

$db = getDbConnection();
$id = (int)($_GET['booking'] ?? $_POST['booking_id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->execute([$id]);
$b = $stmt->fetch();

$errors = [];
$paid = false;

if ($b && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $errors[] = 'Session expired, please retry.';
    } elseif ($b['workflow_status'] !== 'awaiting_payment') {
        $errors[] = 'This booking is not awaiting payment.';
    } else {
        $method = in_array($_POST['method'] ?? '', ['card','bank_transfer','wallet'], true) ? $_POST['method'] : 'card';
        // --- MOCK GATEWAY: validate lightly, always "succeeds". ---
        if ($method === 'card') {
            $num = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
            if (strlen($num) < 12) $errors[] = 'Enter a valid card number (mock — any 12+ digits).';
        }
        if (!$errors) {
            $txn = 'TXN-' . strtoupper(bin2hex(random_bytes(4)));
            $db->prepare(
                "INSERT INTO payments (booking_id, amount_lkr, discount_lkr, method, status, txn_ref, payer_name, payer_email)
                 VALUES (?,?,?,?, 'paid', ?,?,?)"
            )->execute([
                $id, $b['total_lkr'], $b['discount_lkr'], $method, $txn,
                $_POST['payer_name'] ?? $b['full_name'], $b['email'],
            ]);
            Workflow::advance($db, $id, 'paid', $b['user_id'] ? (int)$b['user_id'] : null, "Payment received ($txn) via $method.");
            Workflow::advance($db, $id, 'confirmed', $b['user_id'] ? (int)$b['user_id'] : null, 'Booking confirmed.');
            $paid = true;
            $stmt->execute([$id]);
            $b = $stmt->fetch();
        }
    }
}

$pageTitle = 'Payment';
require __DIR__ . '/includes/header.php';
?>
<section class="section-pad" style="padding-top:3rem">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">

        <?php if (!$b): ?>
          <div class="admin-card text-center py-5"><h4>Booking not found</h4></div>

        <?php elseif ($paid || $b['workflow_status'] === 'confirmed'): ?>
          <div class="planner-shell p-4 p-lg-5 text-center">
            <i class="bi bi-patch-check-fill" style="font-size:3rem;color:var(--color-main,#008000)"></i>
            <h2 class="mt-3">Booking confirmed!</h2>
            <p class="text-muted">Your payment for <strong><?= e($b['reference']) ?></strong> was successful. Ayubowan — your Sri Lankan journey is booked.</p>
            <div class="h4 text-teal"><?= lkr($b['total_lkr']) ?> paid</div>
            <div class="d-flex gap-2 justify-content-center mt-4">
              <a href="<?= url('booking-status.php?ref=' . urlencode($b['reference'])) ?>" class="btn btn-lg-outline">View booking</a>
              <a href="<?= url('index.php') ?>" class="btn btn-lg-primary">Back to home</a>
            </div>
          </div>

        <?php elseif ($b['workflow_status'] !== 'awaiting_payment'): ?>
          <div class="admin-card text-center py-5">
            <h4>Not ready for payment</h4>
            <p class="text-muted">This booking is currently <strong><?= e(Workflow::label($b['workflow_status'])) ?></strong>. You'll be able to pay once a manager approves it.</p>
            <a href="<?= url('booking-status.php?ref=' . urlencode($b['reference'])) ?>" class="btn btn-lg-primary">Track booking</a>
          </div>

        <?php else: ?>
          <div class="section-head text-center mx-auto">
            <span class="eyebrow">Secure checkout (demo)</span>
            <h2>Pay for <?= e($b['reference']) ?></h2>
            <p>This is a mock payment gateway for demonstration — no real card is charged.</p>
          </div>

          <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>

          <div class="row g-4">
            <div class="col-md-7">
              <form method="post" class="planner-shell p-4">
                <?= csrfField() ?>
                <input type="hidden" name="booking_id" value="<?= (int)$id ?>">
                <div class="mb-3">
                  <label class="form-label small text-muted">Payment method</label>
                  <select name="method" id="method" class="form-select">
                    <option value="card">Credit / debit card</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="wallet">Mobile wallet</option>
                  </select>
                </div>
                <div id="cardFields">
                  <div class="mb-3">
                    <label class="form-label small text-muted">Name on card</label>
                    <input type="text" name="payer_name" class="form-control" value="<?= e($b['full_name']) ?>">
                  </div>
                  <div class="mb-3">
                    <label class="form-label small text-muted">Card number</label>
                    <input type="text" name="card_number" class="form-control" placeholder="4242 4242 4242 4242" inputmode="numeric">
                  </div>
                  <div class="row g-3">
                    <div class="col-6"><label class="form-label small text-muted">Expiry</label><input type="text" name="expiry" class="form-control" placeholder="MM/YY"></div>
                    <div class="col-6"><label class="form-label small text-muted">CVV</label><input type="text" name="cvv" class="form-control" placeholder="123"></div>
                  </div>
                </div>
                <button class="btn btn-lg-primary w-100 mt-4"><i class="bi bi-lock-fill"></i> Pay <?= lkr($b['total_lkr']) ?></button>
                <p class="text-muted text-center mt-2" style="font-size:.72rem">Mock gateway — use any values. Card 4242… works.</p>
              </form>
            </div>
            <div class="col-md-5">
              <div class="planner-shell p-4">
                <h6 class="mb-3">Order summary</h6>
                <div class="d-flex justify-content-between small"><span>Customer</span><span><?= e($b['full_name']) ?></span></div>
                <div class="d-flex justify-content-between small"><span>Travellers</span><span><?= (int)$b['party_size'] ?></span></div>
                <div class="d-flex justify-content-between small"><span>Dates</span><span><?= e($b['travel_date'] ?: 'TBC') ?></span></div>
                <hr>
                <div class="d-flex justify-content-between small"><span>Base price</span><span><?= lkr($b['base_price_lkr']) ?></span></div>
                <?php if ($b['discount_lkr'] > 0): ?><div class="d-flex justify-content-between small text-success"><span>Discount</span><span>- <?= lkr($b['discount_lkr']) ?></span></div><?php endif; ?>
                <div class="d-flex justify-content-between fw-bold mt-2 border-top pt-2"><span>Total</span><span class="text-teal"><?= lkr($b['total_lkr']) ?></span></div>
              </div>
            </div>
          </div>
          <script>
            document.getElementById('method').addEventListener('change', function(){
              document.getElementById('cardFields').style.display = this.value === 'card' ? 'block' : 'none';
            });
          </script>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

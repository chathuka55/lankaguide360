<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/ResourceMatcher.php';
require_once __DIR__ . '/includes/Workflow.php';

$db = getDbConnection();

$planId = isset($_GET['plan']) ? (int)$_GET['plan'] : (isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0);
$destSlug = trim($_GET['destination'] ?? '');

// Load the plan + its priced package (recompute so pricing is authoritative).
$plan = null;
$package = null;
if ($planId) {
    $stmt = $db->prepare("SELECT * FROM trip_plans WHERE id = ?");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    if ($plan) {
        $cached = $_SESSION['package'][$planId] ?? [];
        $travelers = max(1, (int)($cached['travelers'] ?? $plan['travelers'] ?? 2));
        $itemsStmt = $db->prepare(
            "SELECT tpi.day_number, tpi.match_score, d.*
             FROM trip_plan_items tpi JOIN destinations d ON d.id = tpi.destination_id
             WHERE tpi.trip_plan_id = ? ORDER BY tpi.day_number, tpi.sort_order"
        );
        $itemsStmt->execute([$planId]);
        $planResult = ['items' => [], 'meta' => []];
        foreach ($itemsStmt->fetchAll() as $r) {
            $planResult['items'][] = ['day' => (int)$r['day_number'], 'destination' => $r, 'score' => (float)$r['match_score']];
        }
        $matcher = new ResourceMatcher($db);
        $package = $matcher->match($planResult, $plan['budget_tier'], (int)$plan['duration_days'], $travelers, 'English');
    }
}

// Optional single-destination booking (legacy path).
$destination = null;
if (!$plan && $destSlug !== '') {
    $stmt = $db->prepare("SELECT * FROM destinations WHERE slug = ?");
    $stmt->execute([$destSlug]);
    $destination = $stmt->fetch();
}

$user = currentUser();
$errors = [];
$defaultParty = ($plan && isset($plan['travelers'])) ? (int)$plan['travelers'] : 2;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $errors[] = 'Session expired, please resubmit.';
    }
    $name    = trim($_POST['full_name'] ?? ($user['full_name'] ?? ''));
    $email   = trim($_POST['email'] ?? ($user['email'] ?? ''));
    $phone   = trim($_POST['phone'] ?? ($user['phone'] ?? ''));
    $country = trim($_POST['country'] ?? ($user['country'] ?? ''));
    $travelDate = trim($_POST['travel_date'] ?? '');
    $endDate    = trim($_POST['end_date'] ?? '');
    $partySize  = max(1, (int)($_POST['party_size'] ?? $defaultParty));
    $notes      = trim($_POST['notes'] ?? '');

    if ($name === '') $errors[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if ($travelDate !== '' && !DateTime::createFromFormat('Y-m-d', $travelDate)) $errors[] = 'Travel date is invalid.';

    if (!$errors) {
        // Resolve the customer: logged-in user, or find/create a guest (fills info once).
        if ($user && $user['role'] === 'customer') {
            $userId = (int)$user['id'];
        } else {
            $userId = findOrCreateGuest($name, $email, $phone, $country);
        }

        // Pricing + proposed resources from the package.
        $base      = $package ? ($package['pricing']['subtotal'] ?? 0) : 0;
        $total     = $package ? ($package['pricing']['total'] ?? 0) : 0;
        $guideId   = $package['guide']['id']   ?? null;
        $driverId  = $package['driver']['id']  ?? null;
        $vehicleId = $package['vehicle']['id'] ?? null;
        $hotelId   = $package['hotels'][0]['id'] ?? null;
        $destId    = $destination['id'] ?? null;

        $ins = $db->prepare(
            "INSERT INTO bookings
             (trip_plan_id, user_id, destination_id, full_name, email, phone, country,
              travel_date, end_date, party_size, notes,
              guide_id, driver_id, vehicle_id, hotel_id,
              base_price_lkr, total_lkr, workflow_status, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'submitted', 'pending')"
        );
        $ins->execute([
            $plan['id'] ?? null, $userId, $destId, $name, $email, $phone ?: null, $country ?: null,
            $travelDate ?: null, $endDate ?: null, $partySize, $notes ?: null,
            $guideId, $driverId, $vehicleId, $hotelId,
            $base, $total,
        ]);
        $bookingId = (int)$db->lastInsertId();
        $ref = bookingRef($bookingId);
        $db->prepare("UPDATE bookings SET reference = ? WHERE id = ?")->execute([$ref, $bookingId]);

        Workflow::advance($db, $bookingId, 'submitted', $userId, 'Booking submitted by ' . ($user ? 'registered customer' : 'guest') . '.');
        unset($_SESSION['package'][$planId]);

        header('Location: ' . url('booking-status.php?ref=' . urlencode($ref) . '&new=1'));
        exit;
    }
}

$pageTitle = 'Submit booking';
require __DIR__ . '/includes/header.php';
?>
<section class="section-pad" style="padding-top:3rem">
  <div class="container">
    <div class="row g-4 justify-content-center">
      <div class="col-lg-7">
        <div class="section-head">
          <span class="eyebrow">Step 3 &middot; Submit</span>
          <h2>Confirm your details</h2>
          <p>We only need your details once. After you submit, a dispatcher assigns your team and a manager confirms pricing before any payment.</p>
        </div>

        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>

        <?php if (!$user): ?>
          <div class="alert alert-light border small d-flex justify-content-between align-items-center">
            <span>Have an account? Sign in to reuse your saved details.</span>
            <a href="<?= url('login.php?next=' . urlencode($_SERVER['REQUEST_URI'])) ?>" class="btn btn-sm btn-lg-outline py-1">Sign in</a>
          </div>
        <?php endif; ?>

        <form method="post" class="planner-shell p-4">
          <?= csrfField() ?>
          <?php if ($plan): ?><input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>"><?php endif; ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small text-muted">Full name</label>
              <input type="text" name="full_name" class="form-control" required value="<?= e($_POST['full_name'] ?? ($user['full_name'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted">Email</label>
              <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? ($user['email'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? ($user['phone'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted">Country</label>
              <input type="text" name="country" class="form-control" value="<?= e($_POST['country'] ?? ($user['country'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted">Start date</label>
              <input type="date" name="travel_date" class="form-control" value="<?= e($_POST['travel_date'] ?? ($_SESSION['package'][$planId]['start'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small text-muted">End date</label>
              <input type="date" name="end_date" class="form-control" value="<?= e($_POST['end_date'] ?? ($_SESSION['package'][$planId]['end'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label small text-muted">Party size</label>
              <input type="number" name="party_size" min="1" class="form-control" value="<?= e($_POST['party_size'] ?? $defaultParty) ?>">
            </div>
            <div class="col-12">
              <label class="form-label small text-muted">Notes (optional)</label>
              <textarea name="notes" rows="3" class="form-control" placeholder="Dietary needs, accessibility, special requests…"><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>
          </div>
          <button class="btn btn-lg-primary w-100 mt-4"><i class="bi bi-send-check"></i> Submit booking request</button>
        </form>
      </div>

      <!-- Summary -->
      <div class="col-lg-4">
        <?php if ($package): $p = $package['pricing']; ?>
          <div class="planner-shell p-4">
            <h6 class="mb-3">Package summary</h6>
            <div class="small text-muted mb-2"><?= (int)$plan['duration_days'] ?> days &middot; <?= (int)($_SESSION['package'][$planId]['travelers'] ?? $plan['travelers']) ?> travellers &middot; <?= e(ucfirst($plan['budget_tier'])) ?> budget</div>
            <?php if ($package['guide']): ?><div class="small"><i class="bi bi-person-badge text-amber"></i> <?= e($package['guide']['full_name']) ?></div><?php endif; ?>
            <?php if ($package['vehicle']): ?><div class="small"><i class="bi bi-truck-front text-amber"></i> <?= e($package['vehicle']['name']) ?></div><?php endif; ?>
            <?php foreach ($package['hotels'] as $h): ?><div class="small"><i class="bi bi-building text-amber"></i> <?= e($h['name']) ?></div><?php endforeach; ?>
            <hr>
            <div class="d-flex justify-content-between fw-bold"><span>Estimated total</span><span class="text-teal"><?= lkr($p['total']) ?></span></div>
          </div>
        <?php elseif ($destination): ?>
          <div class="planner-shell p-4">
            <h6 class="mb-2">Booking for</h6>
            <div class="fw-semibold"><?= e($destination['name']) ?></div>
            <div class="small text-muted"><?= e($destination['region']) ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

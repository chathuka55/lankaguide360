</main>

<footer class="lg-footer">
  <div class="container">
    <div class="row gy-4">
      <div class="col-lg-4">
        <div class="lg-brand footer-brand"><span class="lg-brand-mark">LG</span>360</div>
        <p class="footer-tag">An intelligent, web-based tourism management system built to make exploring Sri Lanka easier — smart trip planning, curated destinations, and a travel assistant in your pocket.</p>
      </div>
      <div class="col-lg-2 col-6">
        <h6>Explore</h6>
        <ul class="footer-links">
          <li><a href="<?= url('destinations.php') ?>">Destinations</a></li>
          <li><a href="<?= url('trip-planner.php') ?>">Trip Planner</a></li>
          <li><a href="<?= url('booking-status.php') ?>">Track a booking</a></li>
        </ul>
      </div>
      <div class="col-lg-2 col-6">
        <h6>Regions</h6>
        <ul class="footer-links">
          <li><a href="<?= url('destinations.php?region=Hill+Country') ?>">Hill Country</a></li>
          <li><a href="<?= url('destinations.php?region=Southern+Coast') ?>">Southern Coast</a></li>
          <li><a href="<?= url('destinations.php?region=Cultural+Triangle') ?>">Cultural Triangle</a></li>
        </ul>
      </div>
      <div class="col-lg-4">
        <h6>Project</h6>
        <p class="footer-tag">LankaGuide 360 — Final Year / Research Project. Built with HTML, CSS, Bootstrap, JavaScript, PHP &amp; MySQL.</p>
        <a href="<?= url('admin/login.php') ?>" class="footer-admin"><i class="bi bi-shield-lock"></i> Admin console</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> LankaGuide 360. Ayubowan!</span>
    </div>
  </div>
</footer>

<!-- Chatbot widget — Bot360 -->
<div id="lg-chat-teaser" class="lg-chat-teaser" role="button" tabindex="0">
  <button type="button" class="teaser-close" id="lg-chat-teaser-close" aria-label="Dismiss">&times;</button>
  <strong>Bot360</strong>
  <span id="lg-chat-teaser-text">Hello! How can I help you today?</span>
</div>

<div id="lg-chat-launcher" class="lg-chat-launcher" aria-label="Open Bot360 travel assistant">
  <i class="bi bi-robot"></i>
  <span id="lg-chat-badge" class="lg-chat-badge">1</span>
</div>

<div id="lg-chat-window" class="lg-chat-window d-none">
  <div class="lg-chat-header">
    <div class="d-flex align-items-center">
      <span class="bot-avatar"><i class="bi bi-robot"></i></span>
      <div>
        <strong>Bot360</strong> · LankaGuide assistant
        <div class="lg-chat-status"><span class="dot"></span> Online now</div>
      </div>
    </div>
    <button id="lg-chat-close" aria-label="Close chat">&times;</button>
  </div>
  <div id="lg-chat-body" class="lg-chat-body">
    <div class="lg-msg lg-msg-bot">Ayubowan! 🙏 I'm <strong>Bot360</strong>, your LankaGuide 360 travel assistant. Ask me about destinations, budgets, the best travel seasons, guides &amp; vehicles — or tap a suggestion below to get started.</div>
  </div>
  <div class="lg-chat-quick">
    <button data-q="plan a trip">🧭 Plan a trip</button>
    <button data-q="best beaches">🏖️ Best beaches</button>
    <button data-q="wildlife safari">🐘 Wildlife safari</button>
    <button data-q="hill country tea">⛰️ Hill country</button>
    <button data-q="budget for 5 days">💰 Budget tips</button>
    <button data-q="best time to visit">📅 Best season</button>
  </div>
  <form id="lg-chat-form" class="lg-chat-input">
    <input type="text" id="lg-chat-text" placeholder="Ask Bot360 anything about Sri Lanka…" autocomplete="off" required>
    <button type="submit" aria-label="Send"><i class="bi bi-send-fill"></i></button>
  </form>
</div>

<script>window.LG_BASE_URL = <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- marked.js (MIT) — renders Bot360's rich markdown replies -->
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
<script src="<?= asset('js/main.js') ?>"></script>
<script src="<?= asset('js/chatbot.js') ?>"></script>
</body>
</html>

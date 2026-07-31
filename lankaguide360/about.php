<?php
require_once __DIR__ . '/config/app.php';
$pageTitle = 'About the Project — LankaGuide 360';
include __DIR__ . '/includes/header.php';
?>

<section class="section-pad" style="padding-top:3rem">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-6">
        <span class="eyebrow">About</span>
        <h2>What LankaGuide 360 is</h2>
        <p class="text-muted">LankaGuide 360 is a web-based intelligent tourism management system built to bring integrated travel services, smart destination discovery, and personalised trip planning to Sri Lankan tourism in a single platform.</p>
        <p class="text-muted">It combines a rule-based recommendation engine, a conversational tourism assistant, and an admin console for managing destinations, so both visitors and tourism-board staff have the tools they need.</p>

        <h5 class="mt-5">Core objectives</h5>
        <ul class="text-muted">
          <li>Evaluate existing tourism management systems and identify gaps specific to Sri Lanka.</li>
          <li>Apply appropriate web technologies and architecture for an intelligent, responsive system.</li>
          <li>Deliver functional and non-functional requirements gathered through system analysis and literature review.</li>
          <li>Provide intelligent trip planning, destination administration, chatbot support and optional booking.</li>
          <li>Evaluate the system through usability and functional testing, and document findings for future work.</li>
        </ul>
      </div>
      <div class="col-lg-6">
        <div class="admin-card">
          <h6 class="text-muted mb-3">Technology stack</h6>
          <div class="row g-3">
            <div class="col-6"><span class="tag-pill">HTML5</span><span class="tag-pill">CSS3</span><span class="tag-pill">Bootstrap 5</span></div>
            <div class="col-6"><span class="tag-pill">JavaScript</span><span class="tag-pill">PHP 8</span><span class="tag-pill">MySQL</span></div>
          </div>
          <hr>
          <h6 class="text-muted mb-2">Recommendation logic</h6>
          <p class="small text-muted mb-0">A transparent, explainable scoring model: destinations are matched against chosen interest categories using weighted relevance, adjusted for budget fit, and balanced for regional diversity across the trip — see <code>includes/TripRecommender.php</code>.</p>
          <hr>
          <h6 class="text-muted mb-2">Chatbot approach</h6>
          <p class="small text-muted mb-0">Keyword/intent matching against an editable knowledge base (<code>chatbot_intents</code> table), logged per session for future analysis and evaluation — see <code>includes/ChatbotEngine.php</code>.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

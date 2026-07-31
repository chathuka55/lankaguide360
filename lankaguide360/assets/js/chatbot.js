// LankaGuide 360 — Bot360 chatbot widget controller
(function () {
  const launcher   = document.getElementById('lg-chat-launcher');
  const win        = document.getElementById('lg-chat-window');
  const closeBtn   = document.getElementById('lg-chat-close');
  const body       = document.getElementById('lg-chat-body');
  const form       = document.getElementById('lg-chat-form');
  const input      = document.getElementById('lg-chat-text');
  const badge      = document.getElementById('lg-chat-badge');
  const teaser     = document.getElementById('lg-chat-teaser');
  const teaserText = document.getElementById('lg-chat-teaser-text');
  const teaserClose= document.getElementById('lg-chat-teaser-close');
  const quickWrap  = document.querySelector('.lg-chat-quick');

  if (!launcher || !win) return;

  const BOT_NAME = 'Bot360';
  const hasMarked = (typeof window.marked !== 'undefined');
  const BASE = (window.LG_BASE_URL || '').replace(/\/$/, '');

  function appUrl(path) {
    path = String(path || '').replace(/^\//, '');
    return BASE ? BASE + '/' + path : path;
  }

  // Rotating teaser messages that pop up to invite the user to chat.
  const TEASERS = [
    'Hello! How can I help you today?',
    'Still struggling? Want my suggestions? 🤔',
    'Planning a trip to Sri Lanka? I can build an itinerary! 🧭',
    'Ask me about beaches, wildlife or hill country 🌴',
    'Not sure where to go? Tell me your budget 💰',
    'Need a guide, driver or vehicle? Just ask! 🚐'
  ];

  // Session id stored for this browser tab, used to group chatbot_logs rows
  let sessionId = sessionStorage.getItem('lg_chat_session');
  if (!sessionId) {
    sessionId = 'sess_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
    sessionStorage.setItem('lg_chat_session', sessionId);
  }

  let teaserIndex = 0;
  let teaserTimer = null;
  let unread = 0;

  function isOpen() { return !win.classList.contains('d-none'); }

  function setUnread(n) {
    unread = n;
    if (n > 0) { badge.textContent = n; badge.classList.add('show'); }
    else { badge.classList.remove('show'); }
  }

  function showTeaser() {
    if (isOpen() || sessionStorage.getItem('lg_chat_teaser_off')) return;
    teaserText.textContent = TEASERS[teaserIndex % TEASERS.length];
    teaserIndex++;
    teaser.classList.add('show');
    setUnread(unread + 1);
    clearTimeout(teaser._hide);
    teaser._hide = setTimeout(() => teaser.classList.remove('show'), 6000);
  }

  function scheduleTeasers() {
    setTimeout(showTeaser, 4000);
    teaserTimer = setInterval(showTeaser, 18000);
  }

  function openChat() {
    win.classList.remove('d-none');
    teaser.classList.remove('show');
    clearInterval(teaserTimer);
    setUnread(0);
    setTimeout(() => input.focus(), 50);
  }
  function closeChat() { win.classList.add('d-none'); }

  launcher.addEventListener('click', () => { isOpen() ? closeChat() : openChat(); });
  closeBtn.addEventListener('click', closeChat);
  teaser.addEventListener('click', openChat);
  teaserClose.addEventListener('click', (e) => {
    e.stopPropagation();
    teaser.classList.remove('show');
    clearInterval(teaserTimer);
    sessionStorage.setItem('lg_chat_teaser_off', '1');
  });

  // --- rendering helpers -------------------------------------------------
  function addMessage(text, who) {
    const div = document.createElement('div');
    div.className = 'lg-msg ' + (who === 'user' ? 'lg-msg-user' : 'lg-msg-bot');
    if (who === 'bot' && hasMarked) {
      div.innerHTML = window.marked.parseInline(text);
    } else {
      div.textContent = text;
    }
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
    return div;
  }

  function addTyping() {
    const t = document.createElement('div');
    t.className = 'lg-msg lg-msg-bot lg-msg-typing';
    t.innerHTML = '<span class="lg-dots"><span></span><span></span><span></span></span>';
    body.appendChild(t);
    body.scrollTop = body.scrollHeight;
    return t;
  }

  function addActionLink(href, label) {
    const link = document.createElement('a');
    link.href = appUrl(href);
    link.className = 'lg-msg lg-msg-bot lg-chat-action';
    link.innerHTML = '<i class="bi bi-arrow-right-circle"></i> ' + label;
    body.appendChild(link);
    body.scrollTop = body.scrollHeight;
  }

  function addCards(cards) {
    if (!cards || !cards.length) return;
    const wrap = document.createElement('div');
    wrap.className = 'lg-chat-cards';
    cards.forEach(c => {
      const a = document.createElement('a');
      a.href = c.link;
      a.className = 'lg-chat-card';
      const img = c.image
        ? '<span class="lg-chat-card-img" style="background-image:url(\'' + c.image + '\')"></span>'
        : '<span class="lg-chat-card-img lg-chat-card-img--ph"><i class="bi bi-image"></i></span>';
      a.innerHTML = img +
        '<span class="lg-chat-card-body">' +
          '<strong>' + c.name + '</strong>' +
          '<span class="lg-chat-card-meta">' + c.region + ' · ★ ' + c.rating + '</span>' +
        '</span>';
      wrap.appendChild(a);
    });
    body.appendChild(wrap);
    body.scrollTop = body.scrollHeight;
  }

  // Render clickable suggestion chips beneath the latest reply.
  function renderSuggestions(list) {
    document.querySelectorAll('.lg-chat-suggest').forEach(el => el.remove());
    if (!list || !list.length) return;
    const wrap = document.createElement('div');
    wrap.className = 'lg-chat-suggest';
    list.forEach(s => {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = s;
      b.addEventListener('click', () => sendMessage(s));
      wrap.appendChild(b);
    });
    body.appendChild(wrap);
    body.scrollTop = body.scrollHeight;
  }

  // --- messaging ---------------------------------------------------------
  async function sendMessage(text) {
    if (!isOpen()) openChat();
    document.querySelectorAll('.lg-chat-suggest').forEach(el => el.remove());
    addMessage(text, 'user');
    input.value = '';

    const typing = addTyping();

    try {
      const res = await fetch(appUrl('chatbot-api.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text, session_id: sessionId })
      });
      const data = await res.json();
      typing.remove();

      addMessage(data.reply || "Sorry, I didn't quite catch that. Try asking about destinations or trip planning.", 'bot');
      addCards(data.cards);

      if (data.action === 'open_trip_planner') addActionLink('trip-planner.php', 'Open the Trip Planner');
      if (data.action === 'open_booking')      addActionLink('booking.php', 'Open the Booking form');

      renderSuggestions(data.suggestions);
    } catch (err) {
      typing.remove();
      addMessage("I'm having trouble connecting right now — please try again shortly.", 'bot');
    }
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (text) sendMessage(text);
  });

  // Seed quick-reply buttons in the footer also route through sendMessage.
  if (quickWrap) {
    quickWrap.querySelectorAll('button').forEach(btn => {
      btn.addEventListener('click', () => sendMessage(btn.dataset.q));
    });
  }

  scheduleTeasers();
})();

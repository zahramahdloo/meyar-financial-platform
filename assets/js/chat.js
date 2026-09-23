/* MEYAR — ویجت چت آنلاین */
(function () {
  'use strict';
  var fab = document.getElementById('chatFab');
  var panel = document.getElementById('chatPanel');
  var closeBtn = document.getElementById('chatClose');
  var minimizeBtn = document.getElementById('chatMinimize');
  var body = document.getElementById('chatBody');
  var form = document.getElementById('chatForm');
  var nameInp = document.getElementById('chatName');
  var textInp = document.getElementById('chatText');
  var badge = document.getElementById('chatBadge');
  if (!fab || !panel) return;
  if (!panel.hasAttribute('tabindex')) panel.setAttribute('tabindex', '-1');

  var API = (window.MEYAR_BASE || './') + 'api/chat.php';
  var token = null, lastId = 0, open = false, pollTimer = null;
  var lastTrigger = null;
  var widget = document.getElementById('chatWidget') || panel.parentElement;
  var inertBackground = [];
  var faD = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
  function faNum(s){return String(s).replace(/\d/g,function(d){return faD[+d];});}

  try {
    token = localStorage.getItem('meyar_chat_token');
    lastId = parseInt(localStorage.getItem('meyar_chat_last') || '0', 10) || 0;
    var savedName = localStorage.getItem('meyar_chat_name');
    if (savedName && nameInp) { nameInp.value = savedName; nameInp.style.display = 'none'; }
  } catch (e) {}

  function addMsg(m) {
    var div = document.createElement('div');
    div.className = 'chat-msg ' + (m.s === 'a' ? 'a' : 'v');
    var who = m.s === 'a' ? (m.name ? m.name + ' — پشتیبانی' : 'پشتیبانی') : 'شما';
    div.innerHTML = (m.s === 'a'
      ? '<span class="chat-avatar"><img src="' + (window.MEYAR_BASE || './') + 'assets/img/meyar-logo/Meyar-logo.png" alt="معیار"></span><div class="chat-bubble"></div>'
      : '<span class="chat-avatar chat-user-avatar"><i class="hgi hgi-stroke hgi-rounded hgi-user-03" aria-hidden="true"></i></span><div class="chat-bubble"></div>')
      + '<div class="chat-meta"></div>';
    div.querySelector('.chat-bubble').textContent = m.body;
    div.querySelector('.chat-meta').textContent = who + ' · ' + (m.t || '');
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
  }

  function poll() {
    if (!token) return;
    fetch(API + '?action=poll&token=' + token + '&after=' + lastId, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok || !j.messages) return;
        var newAdmin = 0;
        j.messages.forEach(function (m) {
          if (m.id > lastId) {
            lastId = m.id;
            addMsg(m);
            if (m.s === 'a') newAdmin++;
          }
        });
        if (newAdmin && !open) {
          badge.hidden = false;
          badge.textContent = faNum(newAdmin);
        }
        try { localStorage.setItem('meyar_chat_last', String(lastId)); } catch (e) {}
      })
      .catch(function () {});
  }

  function startPolling(fast) {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(poll, fast ? 6000 : 25000);
  }

  function getFocusable() {
    return Array.prototype.slice.call(panel.querySelectorAll(
      'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter(function (el) { return !el.hidden && el.offsetParent !== null; });
  }

  function setBackgroundInert(enabled) {
    if (!widget) return;
    if (enabled) {
      inertBackground = Array.prototype.slice.call(document.body.children).filter(function (node) { return node !== widget; });
      inertBackground.forEach(function (node) { node.inert = true; });
    } else {
      inertBackground.forEach(function (node) { node.inert = false; });
      inertBackground = [];
    }
  }

  function setOpen(nextOpen, trigger) {
    open = nextOpen;
    if (open) lastTrigger = trigger || lastTrigger || fab;
    panel.hidden = !open;
    fab.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (!open) {
      setBackgroundInert(false);
      panel.classList.remove('is-minimized');
      if (minimizeBtn) {
        minimizeBtn.setAttribute('aria-expanded', 'true');
        minimizeBtn.setAttribute('aria-label', 'کوچک کردن گفتگو');
      }
      if (lastTrigger && document.contains(lastTrigger)) lastTrigger.focus();
      startPolling(false);
      return;
    }
    setBackgroundInert(true);
    badge.hidden = true;
    window.requestAnimationFrame(function () {
      var focusables = getFocusable();
      (textInp || focusables[0] || panel).focus();
    });
    poll();
    startPolling(true);
  }

  fab.addEventListener('click', function () { setOpen(!open, fab); });
  closeBtn.addEventListener('click', function () { setOpen(false); });
  if (minimizeBtn) {
    minimizeBtn.addEventListener('click', function () {
      var minimized = panel.classList.toggle('is-minimized');
      minimizeBtn.setAttribute('aria-expanded', minimized ? 'false' : 'true');
      minimizeBtn.setAttribute('aria-label', minimized ? 'باز کردن گفتگو' : 'کوچک کردن گفتگو');
    });
  }

  document.addEventListener('keydown', function (event) {
    if (!open) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      setOpen(false);
      return;
    }
    if (event.key !== 'Tab') return;
    var focusables = getFocusable();
    if (!focusables.length) {
      event.preventDefault();
      panel.focus();
      return;
    }
    var first = focusables[0];
    var last = focusables[focusables.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = textInp.value.trim();
    if (!text) return;
    var name = nameInp ? nameInp.value.trim() : '';
    var fd = new FormData();
    fd.append('action', 'send');
    fd.append('body', text);
    fd.append('name', name);
    if (token) fd.append('token', token);
    textInp.value = '';
    addMsg({ s: 'v', body: text, t: '' });
    fetch(API, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.ok) {
          token = j.token;
          if (j.id > lastId) lastId = j.id;
          try {
            localStorage.setItem('meyar_chat_token', token);
            localStorage.setItem('meyar_chat_last', String(lastId));
            if (name) { localStorage.setItem('meyar_chat_name', name); nameInp.style.display = 'none'; }
          } catch (e) {}
        }
      })
      .catch(function () {});
  });

  if (token) { poll(); startPolling(false); }
})();

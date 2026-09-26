/* MEYAR — صفحه توضیح تحلیل هوشمند */
(function () {
  'use strict';
  var modal = document.getElementById('aiAnalysisModal');
  var page = document.querySelector('[data-ai-page]');
  var lastTrigger = null;
  var modalCard = modal ? modal.querySelector('[data-ai-modal-card]') : null;
  var dialog = modal ? modal.querySelector('.ai-analysis-modal-dialog') : null;

  function getFocusable(container) {
    if (!container) return [];
    return Array.prototype.slice.call(container.querySelectorAll(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter(function (el) { return !el.hidden && el.offsetParent !== null; });
  }

  function focusDialog() {
    var focusables = getFocusable(dialog);
    (focusables[0] || dialog).focus();
  }

  function typeExplanation(card, text) {
    var target = card.querySelector('[data-ai-explanation]');
    if (card._typingTimer) clearInterval(card._typingTimer);
    var chars = Array.from(String(text || ''));
    var index = 0;
    target.textContent = '';
    target.classList.add('is-typing');
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      target.textContent = chars.join('');
      target.classList.remove('is-typing');
      return;
    }
    card._typingTimer = setInterval(function () {
      target.textContent += chars[index++] || '';
      if (index >= chars.length) {
        clearInterval(card._typingTimer);
        card._typingTimer = null;
        target.classList.remove('is-typing');
      }
    }, 14);
  }

  function loadCard(card, topic, trend) {
    var loading = card.querySelector('[data-ai-loading]');
    var error = card.querySelector('[data-ai-error]');
    var content = card.querySelector('[data-ai-content]');
    card.setAttribute('data-ai-topic', topic);
    card.setAttribute('data-ai-trend', trend);
    card.setAttribute('aria-busy', 'true');
    card.querySelector('[data-ai-title]').textContent = 'در حال آماده‌سازی تحلیل…';
    card.querySelector('[data-ai-topic-label]').textContent = topic || 'بازار امروز';
    loading.hidden = false;
    error.hidden = true;
    content.hidden = true;
    fetch((window.MEYAR_BASE || './') + 'api/market-insight-detail.php?topic=' + encodeURIComponent(topic) + '&trend=' + encodeURIComponent(trend), { cache: 'no-store' })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (payload) {
        if (!payload || !payload.ok || !payload.detail) throw new Error('analysis_failed');
        var detail = payload.detail;
        card.querySelector('[data-ai-title]').textContent = detail.title || topic;
        card.querySelector('[data-ai-topic-label]').textContent = detail.topic || topic;
        var explanation = String(detail.explanation || '').replace(/\\r\\n|\\n|\\r/g, '\n');
        var factors = card.querySelector('[data-ai-factors]');
        factors.textContent = '';
        (detail.factors || []).forEach(function (factor) {
          var li = document.createElement('li');
          li.textContent = factor;
          factors.appendChild(li);
        });
        card.querySelector('[data-ai-disclaimer]').textContent = detail.disclaimer || '';
        loading.hidden = true;
        content.hidden = false;
        card.setAttribute('aria-busy', 'false');
        typeExplanation(card, explanation);
      })
      .catch(function () {
        loading.hidden = true;
        error.hidden = false;
        card.setAttribute('aria-busy', 'false');
      });
  }

  function closeModal() {
    if (!modal) return;
    if (modalCard && modalCard._typingTimer) {
      clearInterval(modalCard._typingTimer);
      modalCard._typingTimer = null;
    }
    modal.hidden = true;
    document.body.classList.remove('ai-modal-open');
    if (lastTrigger && document.contains(lastTrigger)) lastTrigger.focus();
  }

  if (modal) {
    if (dialog && !dialog.hasAttribute('tabindex')) dialog.setAttribute('tabindex', '-1');
    function openModal(trigger) {
      lastTrigger = trigger || null;
      modal.hidden = false;
      document.body.classList.add('ai-modal-open');
      window.requestAnimationFrame(focusDialog);
      loadCard(modalCard, trigger.getAttribute('data-ai-topic') || trigger.textContent.trim(), trigger.getAttribute('data-ai-trend') || 'flat');
    }
    document.addEventListener('click', function (event) {
      var link = event.target.closest('.market-insight-link[data-ai-topic]');
      if (!link) return;
      event.preventDefault();
      openModal(link);
    });
    modal.querySelectorAll('[data-ai-close]').forEach(function (button) { button.addEventListener('click', closeModal); });
    document.addEventListener('keydown', function (event) {
      if (event.key === ' ' && modal.hidden) {
        var trigger = event.target.closest('.market-insight-link[data-ai-topic]');
        if (trigger) {
          event.preventDefault();
          openModal(trigger);
          return;
        }
      }
      if (modal.hidden) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        closeModal();
        return;
      }
      if (event.key !== 'Tab') return;
      var focusables = getFocusable(dialog);
      if (!focusables.length) {
        event.preventDefault();
        focusDialog();
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
  }

  if (page) loadCard(page, page.getAttribute('data-ai-topic') || 'روند کلی قیمت‌های امروز بازار', page.getAttribute('data-ai-trend') || 'flat');
})();

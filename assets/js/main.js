/* MEYAR — اسلایدر، ماسونری، انیمیشن‌های اسکرول + بروزرسانی زنده قیمت‌ها */
(function () {
  'use strict';

  /* ---------- reveal on scroll ---------- */
  var revealEls = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e, idx) {
        if (e.isIntersecting) {
          var el = e.target;
          setTimeout(function () { el.classList.add('visible'); }, (idx % 4) * 90);
          io.unobserve(el);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add('visible'); });
  }

  /* ---------- شمارنده آمار ---------- */
  var faDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
  function toFa(n) { return String(n).replace(/\d/g, function (d) { return faDigits[+d]; }); }
  var counters = document.querySelectorAll('[data-count]');
  if ('IntersectionObserver' in window && counters.length) {
    var cio = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        cio.unobserve(e.target);
        var el = e.target, target = parseInt(el.getAttribute('data-count'), 10) || 0;
        var start = null, dur = 1600;
        function step(ts) {
          if (!start) start = ts;
          var p = Math.min((ts - start) / dur, 1);
          var eased = 1 - Math.pow(1 - p, 3);
          el.textContent = toFa(Math.round(target * eased));
          if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
      });
    }, { threshold: 0.5 });
    counters.forEach(function (el) { cio.observe(el); });
  }

  /* ---------- ذرات طلایی هیرو ---------- */
  var pWrap = document.getElementById('heroParticles');
  if (pWrap) {
    for (var i = 0; i < 22; i++) {
      var s = document.createElement('span');
      var size = 3 + Math.random() * 8;
      s.style.width = s.style.height = size + 'px';
      s.style.left = Math.random() * 100 + '%';
      s.style.animationDuration = (7 + Math.random() * 12) + 's';
      s.style.animationDelay = (-Math.random() * 15) + 's';
      pWrap.appendChild(s);
    }
  }

  /* ---------- چیدمان ماسونری جدول‌ها ---------- */
  var mGrid = document.querySelector('.tables-grid');
  function masonry() {
    if (!mGrid) return;
    var cards = [].filter.call(mGrid.children, function (c) {
      return !c.classList.contains('is-filtered-out') && (c.offsetParent !== null || mGrid.classList.contains('masonry-on'));
    });
    if (!cards.length) return;
    var W = mGrid.clientWidth;
    var cols = W > 900 ? 2 : 1;
    var gap = 26;
    if (cols === 1) {
      mGrid.classList.remove('masonry-on');
      mGrid.style.height = '';
      cards.forEach(function (c) { c.style.top = c.style.right = c.style.width = ''; });
      return;
    }
    mGrid.classList.add('masonry-on');
    var colW = (W - gap * (cols - 1)) / cols;
    var y = []; for (var i = 0; i < cols; i++) y.push(0);
    cards.forEach(function (c) {
      var full = c.classList.contains('w-full');
      if (full) {
        var top = Math.max.apply(null, y);
        c.style.width = W + 'px'; c.style.right = '0px'; c.style.top = top + 'px';
        var t = top + c.offsetHeight + gap;
        y = y.map(function () { return t; });
      } else {
        var col = 0;
        for (var k = 1; k < cols; k++) { if (y[k] < y[col]) col = k; }
        c.style.width = colW + 'px';
        c.style.right = (col * (colW + gap)) + 'px'; // RTL: ستون اول سمت راست
        c.style.top = y[col] + 'px';
        y[col] += c.offsetHeight + gap;
      }
    });
    mGrid.style.height = Math.max.apply(null, y) - gap + 'px';
  }
  if (mGrid) {
    var mT;
    function stabilizeMasonry() {
      requestAnimationFrame(function () {
        masonry();
        requestAnimationFrame(masonry);
      });
    }
    window.addEventListener('resize', function () { clearTimeout(mT); mT = setTimeout(stabilizeMasonry, 120); });
    window.addEventListener('load', stabilizeMasonry);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(stabilizeMasonry);
    if ('ResizeObserver' in window) {
      var gridObserver = new ResizeObserver(function () { stabilizeMasonry(); });
      gridObserver.observe(mGrid);
      mGrid.querySelectorAll('[data-market-container]').forEach(function (card) { gridObserver.observe(card); });
    }
    masonry();
    setTimeout(stabilizeMasonry, 400);
  }

  /* ---------- فیلتر بازار در صفحه قیمت‌ها ---------- */
  var marketFilters = document.querySelectorAll('[data-market-filter]');
  if (marketFilters.length && mGrid) {
    var marketCards = mGrid.querySelectorAll('[data-market-container]');
    function expandMarketCard(card) {
      var button = card.querySelector('.table-expand-toggle');
      if (button && button.getAttribute('aria-expanded') !== 'true') button.click();
    }
    marketFilters.forEach(function (filter) {
      filter.addEventListener('click', function () {
        var selected = filter.getAttribute('data-market-filter');
        marketFilters.forEach(function (item) {
          var active = item === filter;
          item.classList.toggle('is-active', active);
          item.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        marketCards.forEach(function (card) {
          var visible = selected === 'all' || card.getAttribute('data-market-group') === selected;
          card.classList.toggle('is-filtered-out', !visible);
          if (visible && selected !== 'all') expandMarketCard(card);
        });
        masonry();
      });
    });
    var requestedMarket = new URLSearchParams(window.location.search).get('market');
    if (requestedMarket) {
      var requestedFilter = document.querySelector('[data-market-filter="' + requestedMarket + '"]');
      if (requestedFilter) setTimeout(function () { requestedFilter.click(); }, 0);
    }
  }

  /* ---------- هدر و منو ---------- */
  var header = document.getElementById('siteHeader');
  var backTop = document.getElementById('backTop');
  var previousScrollY = window.scrollY;
  window.addEventListener('scroll', function () {
    var y = window.scrollY;
    if (header && Math.abs(y - previousScrollY) > 1) {
      if (y <= 10) {
        header.classList.remove('scrolled');
        header.classList.remove('header-hidden');
      } else if (y < previousScrollY) {
        header.classList.add('scrolled');
        header.classList.remove('header-hidden');
      } else {
        header.classList.remove('scrolled');
        header.classList.add('header-hidden');
      }
    }
    previousScrollY = y;
    if (backTop) backTop.classList.toggle('show', y > 500);
  }, { passive: true });
  if (backTop) backTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
  var overviewSection = document.querySelector('.market-overview');
  var overviewLink = document.querySelector('.market-overview-all');
  if (overviewSection && overviewLink && 'IntersectionObserver' in window) {
    var overviewObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          overviewLink.classList.remove('is-attention');
          void overviewLink.offsetWidth;
          overviewLink.classList.add('is-attention');
        }
      });
    }, { threshold: .35 });
    overviewObserver.observe(overviewSection);
  }
  var navToggle = document.getElementById('navToggle');
  var mainNav = document.getElementById('mainNav');
  if (navToggle && mainNav) {
    navToggle.addEventListener('click', function () {
      var isOpen = mainNav.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    mainNav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        mainNav.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
      });
    });
  }
  document.querySelectorAll('.nav-dropdown-toggle').forEach(function (toggle) {
    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      var dropdown = toggle.closest('.nav-dropdown');
      var open = dropdown.classList.toggle('open');
      dropdown.classList.toggle('is-click-closed', !open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.querySelectorAll('.nav-dropdown').forEach(function (other) {
        if (other !== dropdown) {
          other.classList.remove('open');
          var otherToggle = other.querySelector('.nav-dropdown-toggle');
          if (otherToggle) otherToggle.setAttribute('aria-expanded', 'false');
        }
      });
    });
  });
  document.querySelectorAll('.nav-dropdown').forEach(function (dropdown) {
    dropdown.addEventListener('mouseleave', function () {
      dropdown.classList.remove('is-click-closed');
    });
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.nav-dropdown')) {
      document.querySelectorAll('.nav-dropdown.open').forEach(function (dropdown) {
        dropdown.classList.remove('open');
        var toggle = dropdown.querySelector('.nav-dropdown-toggle');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
      });
    }
  });

  var searchForm = document.getElementById('headerSearchForm');
  var searchInput = document.getElementById('headerSearch');
  var searchResults = document.getElementById('headerSearchResults');
  if (searchForm && searchInput) {
    function normalizeSearch(value) {
      return String(value || '').toLowerCase().replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/\u200c/g, '').replace(/[،,٬]/g, '').replace(/\s+/g, ' ').trim();
    }
    function marketForItem(itemId, text) {
      var id = normalizeSearch(itemId);
      var value = normalizeSearch(text);
      if (/^(sekee|sekeb|nim|rob|gerami|parsian_)/.test(id) || /سکه|پارسیان/.test(value)) return 'coins';
      if (id === 'silver999' || /نقره/.test(value)) return 'silver';
      if (/^(geram|mesghal|ons)/.test(id) || /طلا|اونس|مثقال|عیار/.test(value)) return 'gold';
      if (/^(usd|eur|aed|gbp|try|chf|cny|jpy|krw|cad|aud|nzd|sgd|inr|pkr|iqd|syp|afn|dkk|sek|nok|sar|qar|omr|kwd|bhd|thb|myr|rub|azn|amd|gel|tjs|tmt|kgs)$/.test(id)) return 'currency';
      if (/دلار|یورو|درهم|پوند|لیر|فرانک|یوان|ین|وون|روپیه|دینار|افغانی|کرون|ریال|بات|روبل|منات|درام|لاری|سامانی|سوم/.test(value)) return 'currency';
      return null;
    }
    function marketHref(market) {
      return (window.MEYAR_BASE || './') + 'prices.php?market=' + encodeURIComponent(market);
    }
    function closeSearchResults() {
      if (!searchResults) return;
      searchResults.hidden = true;
      searchResults.innerHTML = '';
    }
    function showSearchResults(query) {
      if (!searchResults) return;
      searchResults.innerHTML = '';
      if (!query) { closeSearchResults(); return; }
      var entries = [];
      var categories = [
        { keys: ['طلا', 'اونس', 'مثقال', 'عیار'], label: 'بازار طلا', market: 'gold' },
        { keys: ['سکه', 'پارسیان'], label: 'بازار سکه', market: 'coins' },
        { keys: ['ارز', 'دلار', 'یورو', 'درهم', 'پوند', 'لیر'], label: 'بازار ارز', market: 'currency' },
        { keys: ['نقره'], label: 'بازار نقره', market: 'silver' }
      ];
      categories.forEach(function (category) {
        if (!category.keys.some(function (key) { return query.indexOf(key) !== -1; })) return;
        entries.push({ text: category.label, href: marketHref(category.market), meta: 'مشاهده کامل بازار' });
      });
      document.querySelectorAll('[data-id], [data-overview-card]').forEach(function (item) {
        var title = item.querySelector('.market-asset-title, .cell-title, .market-card-title, .market-overview-title');
        var text = (title || item).textContent.trim();
        var searchableText = item.textContent.trim();
        if (!text || normalizeSearch(searchableText).indexOf(query) === -1) return;
        var itemId = item.getAttribute('data-id') || item.getAttribute('data-overview-card');
        var market = marketForItem(itemId, text);
        if (!market) return;
        var href = marketHref(market);
        if (entries.some(function (entry) { return entry.href === href; })) return;
        entries.push({ text: text, href: href, meta: 'مشاهده کامل بازار' });
      });
      entries.slice(0, 7).forEach(function (entry) {
        var link = document.createElement('a');
        link.className = 'header-search-result';
        link.setAttribute('role', 'option');
        link.href = entry.href;
        link.innerHTML = '<span></span><small></small>';
        link.querySelector('span').textContent = entry.text;
        link.querySelector('small').textContent = entry.meta;
        searchResults.appendChild(link);
      });
      if (!entries.length) {
        var empty = document.createElement('div');
        empty.className = 'header-search-empty';
        empty.textContent = 'نتیجه‌ای برای این عبارت پیدا نشد';
        searchResults.appendChild(empty);
      }
      searchResults.hidden = false;
    }
    searchInput.addEventListener('input', function () {
      showSearchResults(normalizeSearch(searchInput.value));
    });
    searchForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var query = normalizeSearch(searchInput.value);
      if (!query) return;
      var firstResult = searchResults && searchResults.querySelector('a');
      if (firstResult) {
        window.location.href = firstResult.href;
        return;
      }
      var fallbackMarket = null;
      if (query.indexOf('سکه') !== -1 || query.indexOf('پارسیان') !== -1) fallbackMarket = 'coins';
      else if (query.indexOf('نقره') !== -1) fallbackMarket = 'silver';
      else if (query.indexOf('طلا') !== -1 || query.indexOf('اونس') !== -1 || query.indexOf('مثقال') !== -1) fallbackMarket = 'gold';
      else if (query.indexOf('ارز') !== -1 || /دلار|یورو|درهم|پوند|لیر/.test(query)) fallbackMarket = 'currency';
      window.location.href = fallbackMarket ? marketHref(fallbackMarket) : (window.MEYAR_BASE || './') + 'prices.php';
    });
    document.addEventListener('click', function (e) {
      if (!e.target.closest('#headerSearchForm')) closeSearchResults();
    });
  }

  /* ---------- ساعت و تاریخ شمسی ---------- */
  var clockEl = document.getElementById('liveClock');
  var dateEl = document.getElementById('liveDate');
  if (clockEl || dateEl) {
    var dateFmt = null, timeFmt = null;
    try {
      dateFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
      timeFmt = new Intl.DateTimeFormat('fa-IR', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
    } catch (e) {}
    function tick() {
      var now = new Date();
      if (clockEl) clockEl.textContent = timeFmt ? timeFmt.format(now) : now.toLocaleTimeString();
      if (dateEl && dateFmt) dateEl.textContent = dateFmt.format(now);
    }
    tick();
    setInterval(tick, 1000);
  }

  /* ---------- کلیک روی ردیف جدول → صفحه آیتم ---------- */
  document.querySelectorAll('tr.row-link').forEach(function (row) {
    row.addEventListener('click', function (e) {
      if (e.target.closest('a')) return;
      var href = row.getAttribute('data-href');
      if (href) window.location.href = (window.MEYAR_BASE || './') + href;
    });
  });

  /* ---------- باز و بسته کردن جدول‌های بازار ---------- */
  var marketContainers = document.querySelectorAll('[data-market-container]');
  function syncMarketTableHeight(wrap) {
    if (!wrap) return;
    wrap.style.maxHeight = wrap.scrollHeight + 'px';
  }
  function toggleMarketTable(container, expanded) {
    var wrap = container.querySelector('[data-collapsible-table]');
    var button = container.querySelector('.table-expand-toggle');
    if (!wrap || !button) return;

    // بسته‌شدن کمی آهسته‌تر از بازشدن انجام شود تا جمع‌شدن container نرم‌تر دیده شود.
    var animationDuration = expanded ? 950 : 1350;
    wrap.style.transitionDuration = animationDuration + 'ms';

    var currentHeight = wrap.getBoundingClientRect().height;
    wrap.style.maxHeight = currentHeight + 'px';
    void wrap.offsetHeight;

    if (expanded) {
      wrap.classList.add('is-expanded');
    } else {
      wrap.classList.remove('is-expanded');
    }

    requestAnimationFrame(function () {
      wrap.style.maxHeight = wrap.scrollHeight + 'px';
      masonry();
    });

    // کارت‌های بعدی در چیدمان Masonry باید همزمان با تغییر ارتفاع حرکت کنند.
    var animationToken = {};
    wrap._masonryAnimationToken = animationToken;
    if (mGrid) mGrid.classList.add('is-layout-animating');
    var startedAt = performance.now();
    function animateLayout(now) {
      if (wrap._masonryAnimationToken !== animationToken) return;
      masonry();
      if (now - startedAt < animationDuration + 60) {
        requestAnimationFrame(animateLayout);
      } else {
        masonry();
        if (mGrid) mGrid.classList.remove('is-layout-animating');
        wrap.style.transitionDuration = '';
      }
    }
    requestAnimationFrame(animateLayout);
  }
  marketContainers.forEach(function (container) {
    var wrap = container.querySelector('[data-collapsible-table]');
    var button = container.querySelector('.table-expand-toggle');
    if (!wrap || !button) return;
    syncMarketTableHeight(wrap);
    if (button.disabled) return;
    button.addEventListener('click', function () {
      var expanded = wrap.classList.contains('is-expanded');
      var label = button.querySelector('.table-expand-label');
      toggleMarketTable(container, !expanded);
      button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      button.setAttribute('aria-label', expanded ? 'نمایش موارد بیشتر' : 'بستن موارد اضافی');
      if (label) label.textContent = expanded ? 'نمایش بیشتر' : 'بستن';
    });
  });
  masonry();
  window.addEventListener('resize', function () {
    marketContainers.forEach(function (container) {
      syncMarketTableHeight(container.querySelector('[data-collapsible-table]'));
    });
  });

  /* ---------- بروزرسانی زنده قیمت‌ها ---------- */
  var API = (window.MEYAR_BASE || './') + 'api/prices.php';
  var REFRESH_MS = 30000;
  var refreshMarketInsights = null;
  var pricesRequest = null;
  var pricesRequestSeq = 0;
  var priceBaseline = Object.create(null);
  var priceBaselineReady = false;
  var liveItemsById = Object.create(null);

  function normalizeNumeric(value) {
    if (typeof value === 'number') return Number.isFinite(value) ? value : NaN;
    var text = String(value == null ? '' : value)
      .replace(/[۰-۹]/g, function (digit) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)); })
      .replace(/[٠-٩]/g, function (digit) { return String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)); })
      .replace(/[−–—]/g, '-')
      .replace(/[٬،]/g, ',')
      .replace(/\s+/g, '')
      .replace(/,/g, '')
      .replace(/٫/g, '.')
      .replace(/[^\d.-]/g, '');
    var number = Number(text);
    return Number.isFinite(number) ? number : NaN;
  }

  function priceDirection(previous, next) {
    var oldValue = normalizeNumeric(previous);
    var newValue = normalizeNumeric(next);
    if (!Number.isFinite(oldValue) || !Number.isFinite(newValue) || oldValue === newValue) return '';
    return newValue > oldValue ? 'up' : 'down';
  }

  function updateCell(cell, newVal, dir) {
    if (!cell || cell.textContent.trim() === String(newVal == null ? '' : newVal)) return 0;
    if (dir) {
      window.clearTimeout(cell._priceFlashTimer);
      cell.classList.remove('flash-up', 'flash-down');
      void cell.offsetWidth;
      cell.classList.add(dir === 'up' ? 'flash-up' : 'flash-down');
      cell._priceFlashTimer = window.setTimeout(function () {
        cell.classList.remove('flash-up', 'flash-down');
      }, 1250);
    }
    cell.textContent = newVal;
    return 1;
  }

  function setDirectionValue(el, dir, value, diagonal) {
    if (!el) return;
    el.textContent = '';
    if (dir === 'high' || dir === 'low') {
      var icon = document.createElement('i');
      icon.className = 'hgi-stroke hgi-' + (dir === 'high' ? (diagonal ? 'arrow-up-right-01' : 'arrow-up-01') : (diagonal ? 'arrow-down-right-01' : 'arrow-down-01'));
      icon.setAttribute('aria-hidden', 'true');
      el.appendChild(icon);
    } else {
      el.appendChild(document.createTextNode('–'));
    }
    if (value !== '') el.appendChild(document.createTextNode(' ' + value + '٪'));
  }

  function applyData(data) {
    if (!data || !data.items) return;
    var byId = {};
    var directions = {};
    data.items.forEach(function (i) {
      byId[i.id] = i;
      liveItemsById[i.id] = i;
      directions[i.id] = {};
      ['live', 'buy', 'sell'].forEach(function (field) {
        var key = i.id + ':' + field;
        var next = normalizeNumeric(i[field]);
        directions[i.id][field] = priceBaselineReady ? priceDirection(priceBaseline[key], next) : '';
        priceBaseline[key] = next;
      });
    });

    // جداول
    document.querySelectorAll('tr[data-id]').forEach(function (row) {
      var it = byId[row.getAttribute('data-id')];
      if (!it) return;
      updateCell(row.querySelector('[data-cell="live"]'), it.live_fmt, directions[it.id].live);
      updateCell(row.querySelector('[data-cell="buy"]'), it.buy_fmt, directions[it.id].buy);
      updateCell(row.querySelector('[data-cell="sell"]'), it.sell_fmt, directions[it.id].sell);
      var chg = row.querySelector('[data-cell="chg"]');
      if (chg) {
        setDirectionValue(chg, it.dir, it.change_pct, false);
        chg.className = 'chg ' + (it.dir === 'high' ? 'up' : (it.dir === 'low' ? 'down' : 'flat'));
      }
    });

    // کارت‌های بازار در بخش اصلی
    document.querySelectorAll('.market-asset[data-id]').forEach(function (asset) {
      var it = byId[asset.getAttribute('data-id')];
      if (!it) return;
      var buyPrice = asset.querySelector('[data-cell="buy"]');
      var sellPrice = asset.querySelector('[data-cell="sell"]');
      if (buyPrice) updateCell(buyPrice, it.buy_fmt || '', directions[it.id].buy);
      if (sellPrice) updateCell(sellPrice, it.sell_fmt || '', directions[it.id].sell);
      var chg = asset.querySelector('[data-cell="chg"]');
      if (chg) {
        chg.textContent = '';
        var trend = document.createElement('span');
        trend.className = 'market-asset-trend';
        trend.setAttribute('aria-hidden', 'true');
        setDirectionValue(trend, it.dir, '', true);
        chg.appendChild(document.createTextNode((it.change_pct || '') + '٪'));
        chg.appendChild(trend);
        chg.className = 'market-asset-change ' + (it.dir === 'high' ? 'up' : (it.dir === 'low' ? 'down' : 'flat'));
      }
    });

    // نمای کلی چهار بازار اصلی
    document.querySelectorAll('[data-overview-card]').forEach(function (card) {
      var it = byId[card.getAttribute('data-overview-card')];
      if (!it) return;
      var buyPrice = card.querySelector('[data-cell="buy"]');
      var sellPrice = card.querySelector('[data-cell="sell"]');
      if (buyPrice) updateCell(buyPrice, it.buy_fmt || '', directions[it.id].buy);
      if (sellPrice) updateCell(sellPrice, it.sell_fmt || '', directions[it.id].sell);
      var change = card.querySelector('.market-overview-change');
      if (change) {
        var direction = it.dir === 'high' ? 'up' : (it.dir === 'low' ? 'down' : 'flat');
        var percentage = change.querySelector('strong');
        if (percentage) percentage.textContent = (direction === 'up' ? '+' : (direction === 'down' ? '−' : '')) + (it.change_pct || '۰') + '٪';
        change.className = 'market-overview-change ' + direction;
      }
    });

    // داشبورد بازار داخل Hero
    document.querySelectorAll('.market-card[data-id]').forEach(function (card) {
      var it = byId[card.getAttribute('data-id')];
      if (!it) return;
      var live = card.querySelector('[data-cell="live"]');
      if (live) {
        var liveText = (it.live_fmt || '') + (it.unit ? ' ' + it.unit : '');
        updateCell(live, liveText, directions[it.id].live);
      }
      var chg = card.querySelector('[data-cell="chg"]');
      if (chg) {
        setDirectionValue(chg, it.dir, it.change_pct, false);
        chg.className = 'market-card-change ' + (it.dir === 'high' ? 'up' : (it.dir === 'low' ? 'down' : ''));
      }
    });
    document.querySelectorAll('.market-insight-asset[data-id]').forEach(function (asset) {
      var it = byId[asset.getAttribute('data-id')];
      if (!it) return;
      var live = asset.querySelector('[data-cell="insight-live"]');
      if (live) updateCell(live, (it.live_fmt || '') + ' ' + (it.unit || ''), directions[it.id].live);
      var change = asset.querySelector('[data-cell="insight-change"]');
      if (change) {
        change.textContent = (it.dir === 'high' ? '+' : (it.dir === 'low' ? '−' : '')) + (it.change_pct || '۰') + '٪';
        change.className = it.dir === 'low' ? 'down' : (it.dir === 'high' ? 'up' : 'flat');
      }
    });
    // باکس قیمت صفحه اختصاصی آیتم
    document.querySelectorAll('[data-item-price]').forEach(function (box) {
      var it = byId[box.getAttribute('data-item-price')];
      if (!it) return;
      var lv = box.querySelector('[data-cell="live"]');
      if (lv) updateCell(lv, it.live_fmt, directions[it.id].live);
      var chg = box.querySelector('[data-cell="chg"]');
      if (chg) {
        setDirectionValue(chg, it.dir, it.change_pct, false);
        chg.className = 'chg ' + (it.dir === 'high' ? 'up' : (it.dir === 'low' ? 'down' : 'flat'));
      }
    });
    document.querySelectorAll('.item-cards [data-cell="sell"]').forEach(function (el) {
      var box = document.querySelector('[data-item-price]');
      if (!box) return;
      var it = byId[box.getAttribute('data-item-price')];
      if (it) updateCell(el, it.sell_fmt, directions[it.id].sell);
    });
    document.querySelectorAll('.item-cards [data-cell="buy"]').forEach(function (el) {
      var box = document.querySelector('[data-item-price]');
      if (!box) return;
      var it = byId[box.getAttribute('data-item-price')];
      if (it) updateCell(el, it.buy_fmt, directions[it.id].buy);
    });

    // تیکر
    document.querySelectorAll('.ticker-item').forEach(function (t) {
      var it = byId[t.getAttribute('data-tid')];
      if (!it) return;
      var priceEl = t.querySelector('.ticker-price');
      if (priceEl) updateCell(priceEl, it.live_fmt, directions[it.id].live);
      var chEl = t.querySelector('.ticker-change');
      if (chEl) {
        setDirectionValue(chEl, it.dir, it.change_pct, false);
        chEl.className = 'ticker-change ' + (it.dir === 'high' ? 'up' : (it.dir === 'low' ? 'down' : ''));
      }
    });

    // همگام‌سازی نمودارهای وضعیت بازار با همان قیمت لحظه‌ای چارت هیرو
    if (typeof updateOverviewCharts === 'function') updateOverviewCharts(data, byId);
    if (typeof refreshMarketInsights === 'function') refreshMarketInsights();

    // زمان‌ها
    var lu = document.getElementById('lastUpdate');
    if (lu && data.updated) lu.textContent = data.updated;
    var overviewUpdated = document.getElementById('marketOverviewUpdated');
    if (overviewUpdated && data.updated) {
      var overviewDate = document.getElementById('marketOverviewDate');
      var overviewTime = document.getElementById('marketOverviewTime');
      var updateTime = String(data.updated).split(':').slice(0, 2).join(':');
      if (overviewDate && data.updated_date) overviewDate.textContent = data.updated_date;
      if (overviewTime) overviewTime.textContent = updateTime;
      if (!overviewDate && !overviewTime) overviewUpdated.textContent = updateTime;
    }
    priceBaselineReady = true;
  }

  function requestPrices() {
    if (pricesRequest) return pricesRequest;
    var requestSeq = ++pricesRequestSeq;
    pricesRequest = fetch(API, { cache: 'no-store' })
      .then(function (response) {
        if (!response.ok) throw new Error('price_api_failed');
        return response.json();
      })
      .then(function (data) {
        if (requestSeq === pricesRequestSeq) applyData(data);
        return data;
      })
      .finally(function () {
        if (requestSeq === pricesRequestSeq) pricesRequest = null;
      });
    return pricesRequest;
  }

  /* ---------- نمودار واقعی داشبورد بازار ---------- */
  var historyEl = document.getElementById('marketHistoryData');
  var marketChartEl = document.getElementById('marketChart');
  var overviewModalChartEl = document.getElementById('marketOverviewModalChart');
  if (historyEl && (marketChartEl || overviewModalChartEl)) {
    var marketHistory = {};
    try { marketHistory = JSON.parse(historyEl.textContent || '{}'); } catch (e) { marketHistory = {}; }
    var chartTooltip = marketChartEl ? marketChartEl.querySelector('[data-chart-tooltip]') : null;
    var chartMessage = marketChartEl ? marketChartEl.querySelector('[data-chart-message]') : null;
    var marketChartInstance = null;
    var goldSeries = null;
    var goldPriceLine = null;
    var goldChartData = [];
    var goldPollTimer = null;
    var goldPollInFlight = false;
    var goldLegacyMode = false;
    var goldResizeObserver = null;
    var goldResizeHandler = null;
    var GOLD_POLL_MS = 30000;

    function faNum(value) {
      return String(value).replace(/\d/g, function (digit) { return ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'][+digit]; });
    }
    function formatGoldPrice(value) {
      return faNum(Math.round(value).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','));
    }
    function normalizeUnixTimestamp(value) {
      var timestamp = typeof value === 'number' ? value : Number(value);
      if (!Number.isFinite(timestamp) || timestamp <= 0) return NaN;
      if (timestamp > 100000000000) timestamp /= 1000;
      return Math.floor(timestamp);
    }
    function historicalDateToUnix(dateValue) {
      var dateText = String(dateValue || '').replace(/\//g, '-');
      if (!/^\d{4}-\d{2}-\d{2}$/.test(dateText)) return NaN;
      var timestamp = Date.parse(dateText + 'T12:00:00+03:30');
      return Number.isFinite(timestamp) ? Math.floor(timestamp / 1000) : NaN;
    }
    function formatChartDate(timestamp) {
      var date = new Date(timestamp * 1000);
      try {
        var datePart = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { timeZone: 'Asia/Tehran', year: 'numeric', month: 'long', day: 'numeric' }).format(date);
        var timePart = new Intl.DateTimeFormat('fa-IR', { timeZone: 'Asia/Tehran', hour: '2-digit', minute: '2-digit', hour12: false }).format(date);
        return datePart + ' - ' + timePart;
      } catch (e) {
        return date.toLocaleString('fa-IR');
      }
    }
    function formatChartAxisDate(timestamp) {
      var date = new Date(timestamp * 1000);
      try {
        var parts = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
          timeZone: 'Asia/Tehran', year: 'numeric', month: 'long', day: 'numeric'
        }).formatToParts(date).reduce(function (result, part) {
          if (part.type === 'day' || part.type === 'month' || part.type === 'year') result[part.type] = part.value;
          return result;
        }, {});
        return '\u2067' + parts.day + '\u00a0' + parts.month + '\u00a0' + parts.year + '\u2069';
      } catch (e) {
        return formatChartDate(timestamp).split(' - ')[0];
      }
    }
    function normalizeChartData(rows) {
      var unique = {};
      (Array.isArray(rows) ? rows : []).forEach(function (row) {
        var time = normalizeUnixTimestamp(row && (row.time || row.ts));
        if (!Number.isFinite(time) && row) time = historicalDateToUnix(row.g);
        var value = Number(row && (row.value !== undefined ? row.value : row.v));
        if (Number.isFinite(time) && Number.isFinite(value) && value > 0) unique[time] = { time: time, value: value };
      });
      return Object.keys(unique).map(function (key) { return unique[key]; }).sort(function (a, b) { return a.time - b.time; });
    }
    function showChartMessage(message) {
      if (!chartMessage) return;
      chartMessage.textContent = message;
      chartMessage.hidden = false;
    }
    function hideChartMessage() {
      if (chartMessage) chartMessage.hidden = true;
    }
    function showChartTooltip(time, price, point) {
      if (!chartTooltip || !Number.isFinite(time) || !Number.isFinite(price) || !point) return;
      chartTooltip.textContent = 'قیمت: ' + formatGoldPrice(price) + ' تومان';
      chartTooltip.hidden = false;
      var left = Math.max(8, Math.min(marketChartEl.clientWidth - chartTooltip.offsetWidth - 8, point.x + 10));
      var top = Math.max(5, Math.min(marketChartEl.clientHeight - chartTooltip.offsetHeight - 5, point.y - chartTooltip.offsetHeight - 8));
      chartTooltip.style.left = left + 'px';
      chartTooltip.style.top = top + 'px';
    }
    function hideChartTooltip() {
      if (chartTooltip) chartTooltip.hidden = true;
    }
    function resizeMarketChart() {
      if (marketChartInstance) marketChartInstance.resize(marketChartEl.clientWidth, marketChartEl.clientHeight);
    }
    function setupChartCrosshair() {
      if (!marketChartInstance || !goldSeries) return;
      marketChartInstance.subscribeCrosshairMove(function (param) {
        if (!param || !param.time || !param.point) { hideChartTooltip(); return; }
        var data = param.seriesData && param.seriesData.get(goldSeries);
        if (!data || !Number.isFinite(Number(data.value))) { hideChartTooltip(); return; }
        showChartTooltip(normalizeUnixTimestamp(param.time), Number(data.value), param.point);
      });
    }
    function destroyMarketChart() {
      if (goldPollTimer) { clearTimeout(goldPollTimer); goldPollTimer = null; }
      if (goldResizeObserver) { goldResizeObserver.disconnect(); goldResizeObserver = null; }
      if (goldResizeHandler) { window.removeEventListener('resize', goldResizeHandler); goldResizeHandler = null; }
      if (marketChartInstance) { marketChartInstance.remove(); marketChartInstance = null; }
      goldSeries = null;
      goldPriceLine = null;
      goldPollInFlight = false;
      hideChartTooltip();
    }
    function updateLatestGoldPrice(latestPrice, latestTimestamp) {
      if (!goldSeries) return;
      var price = Number(latestPrice);
      var time = normalizeUnixTimestamp(latestTimestamp);
      if (!Number.isFinite(price) || price <= 0 || !Number.isFinite(time)) return;
      var last = goldChartData.length ? goldChartData[goldChartData.length - 1] : null;
      if (last && time < last.time) return;
      var point = { time: time, value: price };
      goldSeries.update(point);
      if (goldPriceLine) goldPriceLine.applyOptions({ price: price });
      if (last && last.time === time) last.value = price;
      else if (!last || time > last.time) goldChartData.push(point);
      hideChartMessage();
    }
    function loadHistoricalGoldData() {
      goldChartData = normalizeChartData(marketHistory.geram18);
      if (!goldChartData.length) {
        showChartMessage('تاریخچه قیمت طلای ۱۸ عیار در دسترس نیست.');
        return;
      }
      goldSeries.setData(goldChartData);
      goldPriceLine = goldSeries.createPriceLine({ price: goldChartData[goldChartData.length - 1].value, color: 'rgba(212,175,55,.7)', lineWidth: 1, lineStyle: 2, axisLabelVisible: false, title: '' });
      marketChartInstance.timeScale().fitContent();
      hideChartMessage();
    }
    function scheduleGoldPoll() {
      if (goldPollTimer) clearTimeout(goldPollTimer);
      if (!document.hidden) goldPollTimer = setTimeout(startRealtimeGoldUpdates, GOLD_POLL_MS);
    }
    function startRealtimeGoldUpdates() {
      if (goldLegacyMode) return;
      if (goldPollInFlight || document.hidden || !goldSeries) { scheduleGoldPoll(); return; }
      goldPollInFlight = true;
      requestPrices()
        .then(function (data) {
          var item = (data.items || []).find(function (entry) { return entry.id === 'geram18'; });
          if (item) updateLatestGoldPrice(item.live, data.fetched_at);
        })
        .catch(function () { /* داده قبلی حفظ می‌شود */ })
        .then(function () { goldPollInFlight = false; scheduleGoldPoll(); });
    }
    function initializeMarketChart() {
      destroyMarketChart();
      var legacySvg = marketChartEl.querySelector('.market-legacy-chart');
      if (legacySvg) {
        goldLegacyMode = true;
        var legacyLine = legacySvg.querySelector('.market-chart-line');
        var legacyArea = legacySvg.querySelector('.market-chart-area');
        var legacyValues = normalizeChartData(marketHistory.geram18).map(function (point) { return point.value; });
        if (legacyValues.length && legacyLine && legacyArea) {
          var legacyMin = Math.min.apply(null, legacyValues);
          var legacyMax = Math.max.apply(null, legacyValues);
          var legacySpread = legacyMax - legacyMin || Math.max(legacyMax * .01, 1);
          var legacyPoints = legacyValues.map(function (value, index) {
            var x = legacyValues.length === 1 ? 260 : 8 + (index / (legacyValues.length - 1)) * 504;
            var y = 72 - ((value - legacyMin) / legacySpread) * 60;
            return [x, y];
          });
          var legacyPath = legacyPoints.map(function (point, index) {
            return (index ? 'L' : 'M') + point[0].toFixed(2) + ' ' + point[1].toFixed(2);
          }).join(' ');
          legacyLine.setAttribute('d', legacyPath);
          legacyArea.setAttribute('d', legacyPath + ' L512 82 L8 82 Z');
        }
        return;
      }
      if (!window.LightweightCharts || typeof window.LightweightCharts.createChart !== 'function') {
        console.error('Lightweight Charts is not available.');
        showChartMessage('نمودار در حال حاضر در دسترس نیست.');
        return;
      }
      marketChartInstance = window.LightweightCharts.createChart(marketChartEl, {
        width: marketChartEl.clientWidth,
        height: marketChartEl.clientHeight,
        layout: { background: { type: 'solid', color: 'transparent' }, textColor: '#94a3b8', fontFamily: 'IRANSansXFaNum', attributionLogo: false },
        localization: { priceFormatter: formatGoldPrice, timeFormatter: function (time) { return formatChartAxisDate(normalizeUnixTimestamp(time)); } },
        grid: { vertLines: { color: 'rgba(148,163,184,.06)' }, horzLines: { color: 'rgba(148,163,184,.06)' } },
        rightPriceScale: { borderColor: 'rgba(212,175,55,.15)', visible: true },
        leftPriceScale: { visible: false },
        timeScale: { borderColor: 'rgba(212,175,55,.15)', timeVisible: true, secondsVisible: false, rightOffset: 2 },
        crosshair: { mode: 0, vertLine: { color: 'rgba(212,175,55,.65)', width: 1, style: 0 }, horzLine: { color: 'rgba(212,175,55,.35)', width: 1, style: 2 } },
        handleScroll: { mouseWheel: false, pressedMouseMove: true, horzTouchDrag: true, vertTouchDrag: false },
        handleScale: { mouseWheel: false, pinch: true, axisPressedMouseMove: false },
      });
      goldSeries = marketChartInstance.addAreaSeries({
        lineColor: '#d4af37', topColor: 'rgba(212,175,55,.26)', bottomColor: 'rgba(212,175,55,0)',
        lineWidth: 2, priceScaleId: 'right', priceFormat: { type: 'price', precision: 0, minMove: 1 },
      });
      loadHistoricalGoldData();
      setupChartCrosshair();
      if ('ResizeObserver' in window) {
        goldResizeObserver = new ResizeObserver(resizeMarketChart);
        goldResizeObserver.observe(marketChartEl);
      } else {
        goldResizeHandler = resizeMarketChart;
        window.addEventListener('resize', goldResizeHandler);
      }
      startRealtimeGoldUpdates();
    }
    if (marketChartEl) {
      document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
          if (goldPollTimer) { clearTimeout(goldPollTimer); goldPollTimer = null; }
        } else {
          startRealtimeGoldUpdates();
        }
      });
      initializeMarketChart();
    }

    /* ---------- جزئیات دارایی‌های نمای کلی بازار ---------- */
    var overviewModal = document.getElementById('marketOverviewModal');
    var overviewModalChartEl = document.getElementById('marketOverviewModalChart');
    if (overviewModal && overviewModalChartEl) {
      var overviewModalDialog = overviewModal.querySelector('.market-overview-modal-dialog');
      var overviewModalClose = overviewModal.querySelector('.market-overview-modal-close');
      var overviewModalMessage = overviewModal.querySelector('[data-overview-modal-message]');
      var overviewModalTooltip = overviewModal.querySelector('[data-overview-modal-tooltip]');
      var overviewModalChart = null;
      var overviewModalTrigger = null;

      function formatOverviewModalPrice(value, assetId) {
        var numeric = Number(value);
        if (!Number.isFinite(numeric)) return '—';
        var decimals = assetId === 'usd' ? 2 : 0;
        return faNum(numeric.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }));
      }

      function getOverviewModalFocusable() {
        return Array.prototype.slice.call(overviewModalDialog.querySelectorAll(
          'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(function (el) { return !el.hidden && el.offsetParent !== null; });
      }

      function showOverviewModalMessage(message) {
        if (!overviewModalMessage) return;
        overviewModalMessage.textContent = message;
        overviewModalMessage.hidden = false;
      }

      function hideOverviewModalMessage() {
        if (overviewModalMessage) overviewModalMessage.hidden = true;
      }

      function destroyOverviewModalChart() {
        if (overviewModalChart) {
          overviewModalChart.remove();
          overviewModalChart = null;
        }
        if (overviewModalTooltip) overviewModalTooltip.hidden = true;
        hideOverviewModalMessage();
      }

      function createOverviewModalChart(assetId, assetName, unit) {
        destroyOverviewModalChart();
        if (!window.LightweightCharts || typeof window.LightweightCharts.createChart !== 'function') {
          showOverviewModalMessage('نمودار در حال حاضر در دسترس نیست.');
          return;
        }

        overviewModalChart = window.LightweightCharts.createChart(overviewModalChartEl, {
          width: overviewModalChartEl.clientWidth,
          height: overviewModalChartEl.clientHeight,
          layout: { background: { type: 'solid', color: 'transparent' }, textColor: '#94a3b8', fontFamily: 'IRANSansXFaNum', attributionLogo: false },
          localization: {
            priceFormatter: function (price) { return formatOverviewModalPrice(price, assetId); },
            timeFormatter: function (time) { return formatChartAxisDate(normalizeUnixTimestamp(time)); },
          },
          grid: { vertLines: { color: 'rgba(148,163,184,.06)' }, horzLines: { color: 'rgba(148,163,184,.06)' } },
          rightPriceScale: { borderColor: 'rgba(212,175,55,.15)', visible: true },
          leftPriceScale: { visible: false },
          timeScale: { borderColor: 'rgba(212,175,55,.15)', timeVisible: true, secondsVisible: false, rightOffset: 2 },
          crosshair: { mode: 0, vertLine: { color: 'rgba(212,175,55,.65)', width: 1, style: 0 }, horzLine: { color: 'rgba(212,175,55,.35)', width: 1, style: 2 } },
          handleScroll: { mouseWheel: false, pressedMouseMove: true, horzTouchDrag: true, vertTouchDrag: false },
          handleScale: { mouseWheel: false, pinch: true, axisPressedMouseMove: false },
        });

        var series = overviewModalChart.addAreaSeries({
          lineColor: '#d4af37', topColor: 'rgba(212,175,55,.26)', bottomColor: 'rgba(212,175,55,0)',
          lineWidth: 2, priceScaleId: 'right', priceFormat: { type: 'price', precision: assetId === 'usd' ? 2 : 0, minMove: assetId === 'usd' ? 0.01 : 1 },
        });
        var chartData = normalizeChartData(marketHistory[assetId]);
        if (!chartData.length) {
          showOverviewModalMessage('تاریخچه قیمت ' + assetName + ' در دسترس نیست.');
        } else {
          series.setData(chartData);
          series.createPriceLine({ price: chartData[chartData.length - 1].value, color: 'rgba(212,175,55,.7)', lineWidth: 1, lineStyle: 2, axisLabelVisible: false, title: '' });
          overviewModalChart.timeScale().fitContent();
          hideOverviewModalMessage();
        }

        overviewModalChart.subscribeCrosshairMove(function (param) {
          if (!overviewModalTooltip || !param || !param.time || !param.point) {
            if (overviewModalTooltip) overviewModalTooltip.hidden = true;
            return;
          }
          var pointData = param.seriesData && param.seriesData.get(series);
          if (!pointData || !Number.isFinite(Number(pointData.value))) {
            overviewModalTooltip.hidden = true;
            return;
          }
          overviewModalTooltip.textContent = 'قیمت: ' + formatOverviewModalPrice(pointData.value, assetId) + (unit ? ' ' + unit : '');
          overviewModalTooltip.hidden = false;
          var left = Math.max(8, Math.min(overviewModalChartEl.clientWidth - overviewModalTooltip.offsetWidth - 8, param.point.x + 10));
          var top = Math.max(5, Math.min(overviewModalChartEl.clientHeight - overviewModalTooltip.offsetHeight - 5, param.point.y - overviewModalTooltip.offsetHeight - 8));
          overviewModalTooltip.style.left = left + 'px';
          overviewModalTooltip.style.top = top + 'px';
        });

        var resize = function () {
          if (overviewModalChart) overviewModalChart.resize(overviewModalChartEl.clientWidth, overviewModalChartEl.clientHeight);
        };
        if ('ResizeObserver' in window) {
          var observer = new ResizeObserver(resize);
          observer.observe(overviewModalChartEl);
          overviewModalChart._meyarResizeObserver = observer;
        } else {
          window.addEventListener('resize', resize);
          overviewModalChart._meyarResizeHandler = resize;
        }
        var originalRemove = overviewModalChart.remove.bind(overviewModalChart);
        overviewModalChart.remove = function () {
          if (overviewModalChart._meyarResizeObserver) overviewModalChart._meyarResizeObserver.disconnect();
          if (overviewModalChart._meyarResizeHandler) window.removeEventListener('resize', overviewModalChart._meyarResizeHandler);
          originalRemove();
        };
      }

      function renderOverviewModal(assetId, card) {
        var item = liveItemsById[assetId] || {};
        var assetName = card.getAttribute('data-overview-name') || 'دارایی بازار';
        var unit = item.unit || card.getAttribute('data-overview-unit') || '';
        var live = item.live_fmt || card.getAttribute('data-overview-live-fmt') || formatOverviewModalPrice(card.getAttribute('data-overview-live'), assetId);
        var change = item.change_pct || card.getAttribute('data-overview-change') || '۰';
        var dir = item.dir || card.getAttribute('data-overview-dir') || 'flat';
        var trend = dir === 'high' ? 'up' : (dir === 'low' ? 'down' : 'flat');
        var trendLabel = trend === 'up' ? 'صعودی' : (trend === 'down' ? 'نزولی' : 'خنثی');
        var nameEl = overviewModal.querySelector('[data-overview-modal-name]');
        var priceEl = overviewModal.querySelector('[data-overview-modal-price]');
        var changeEl = overviewModal.querySelector('[data-overview-modal-change]');
        var trendEl = overviewModal.querySelector('[data-overview-modal-trend]');
        var iconEl = overviewModal.querySelector('[data-overview-modal-icon]');
        var sourceIcon = card.querySelector('.market-overview-icon');
        if (nameEl) nameEl.textContent = assetName;
        if (priceEl) priceEl.textContent = live + (unit ? ' ' + unit : '');
        if (changeEl) {
          changeEl.textContent = (trend === 'up' ? '+' : (trend === 'down' ? '−' : '')) + change + '٪';
          changeEl.className = trend;
        }
        if (trendEl) {
          trendEl.textContent = trendLabel;
          trendEl.className = trend;
        }
        if (iconEl && sourceIcon) iconEl.innerHTML = sourceIcon.innerHTML;
        overviewModal.querySelector('[data-overview-modal-chart-label]').setAttribute('aria-label', 'روند ' + assetName);
        overviewModalChartEl.setAttribute('aria-label', 'نمودار روند ' + assetName);
        createOverviewModalChart(assetId, assetName, unit);
      }

      function closeOverviewModal() {
        if (overviewModal.hidden) return;
        overviewModal.hidden = true;
        document.body.classList.remove('market-overview-modal-open');
        destroyOverviewModalChart();
        var trigger = overviewModalTrigger;
        overviewModalTrigger = null;
        if (trigger && document.contains(trigger)) trigger.focus();
      }

      function openOverviewModal(card) {
        overviewModalTrigger = card;
        overviewModal.hidden = false;
        document.body.classList.add('market-overview-modal-open');
        renderOverviewModal(card.getAttribute('data-overview-card'), card);
        window.requestAnimationFrame(function () {
          if (overviewModalClose) overviewModalClose.focus();
          if (overviewModalChart) overviewModalChart.resize(overviewModalChartEl.clientWidth, overviewModalChartEl.clientHeight);
        });
      }

      document.querySelectorAll('[data-overview-card]').forEach(function (card) {
        card.addEventListener('click', function (event) {
          if (event.target.closest('.market-overview-action')) {
            event.preventDefault();
            openOverviewModal(card);
            return;
          }
          if (event.target.closest('button, a')) return;
          openOverviewModal(card);
        });
        card.addEventListener('keydown', function (event) {
          if (event.key !== 'Enter' && event.key !== ' ') return;
          if (event.target !== card) return;
          event.preventDefault();
          openOverviewModal(card);
        });
      });
      overviewModal.querySelectorAll('[data-overview-modal-close]').forEach(function (closeEl) {
        closeEl.addEventListener('click', function (event) {
          if (closeEl.classList.contains('market-overview-modal-backdrop') && event.target !== closeEl) return;
          closeOverviewModal();
        });
      });
      overviewModal.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          event.preventDefault();
          closeOverviewModal();
          return;
        }
        if (event.key !== 'Tab' || overviewModal.hidden) return;
        var focusables = getOverviewModalFocusable();
        if (!focusables.length) {
          event.preventDefault();
          overviewModalDialog.focus();
          return;
        }
        var first = focusables[0], last = focusables[focusables.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      });
    }
  }

  /* ---------- تحلیل هوشمند بازار ---------- */
  var insightList = document.querySelector('[data-market-insights]');
  var insightTrend = document.querySelector('[data-insight-trend]');
  var insightRequest = null;
  var insightHasValidResponse = false;
  if (insightList && insightTrend) {
    function renderMarketInsights(insight) {
      var trend = insight.trend === 'up' ? 'up' : (insight.trend === 'down' ? 'down' : 'flat');
      insightTrend.textContent = insight.trend_label || (trend === 'flat' ? 'نامشخص' : trend === 'up' ? 'صعودی' : 'نزولی');
      insightTrend.className = trend;
      insightList.textContent = '';
      (insight.insights || []).slice(0, 4).forEach(function (entry) {
        var value = typeof entry === 'string' ? entry : entry && entry.title;
        if (!value) return;
        var li = document.createElement('li');
        var link = document.createElement('span');
        link.className = 'market-insight-link';
        link.textContent = value;
        li.appendChild(link);
        insightList.appendChild(li);
      });
      insightList.setAttribute('aria-busy', 'false');
    }
    function renderMarketInsightFallback() {
      renderMarketInsights({ trend: 'mixed', trend_label: 'نامشخص', insights: ['اطلاعات کافی برای جمع‌بندی بازار در دسترس نیست'] });
    }
    refreshMarketInsights = function () {
      if (insightRequest) return insightRequest;
      insightList.setAttribute('aria-busy', 'true');
      insightRequest = fetch((window.MEYAR_BASE || './') + 'api/market-insight.php', { cache: 'no-store' })
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (payload) {
          if (!payload || !payload.ok || !payload.insight || !Array.isArray(payload.insight.insights)) throw new Error('invalid_market_insight');
          renderMarketInsights(payload.insight);
          insightHasValidResponse = true;
        })
        .catch(function (error) {
          if (!insightHasValidResponse) renderMarketInsightFallback();
          console.warn('Market insights unavailable:', error);
        })
        .then(function () { insightRequest = null; });
      return insightRequest;
    }
    refreshMarketInsights();
  }

  /* ---------- ویجت‌های نمای کلی بازار ---------- */
  var overviewHistory = {};
  var overviewSelectedRanges = {};
  var overviewDirections = {};
  if (historyEl) {
    try { overviewHistory = JSON.parse(historyEl.textContent || '{}'); } catch (e) { overviewHistory = {}; }
  }
  var overviewRanges = { day: 7, week: 30, month: 90 };
  function smoothOverviewPath(points) {
    if (points.length < 2) return points.length ? 'M' + points[0][0].toFixed(2) + ' ' + points[0][1].toFixed(2) : '';
    var path = 'M' + points[0][0].toFixed(2) + ' ' + points[0][1].toFixed(2);
    for (var i = 1; i < points.length - 1; i++) {
      var midpointX = (points[i][0] + points[i + 1][0]) / 2;
      var midpointY = (points[i][1] + points[i + 1][1]) / 2;
      path += ' Q' + points[i][0].toFixed(2) + ' ' + points[i][1].toFixed(2) + ' ' + midpointX.toFixed(2) + ' ' + midpointY.toFixed(2);
    }
    var last = points[points.length - 1], previous = points[points.length - 2];
    path += ' Q' + previous[0].toFixed(2) + ' ' + previous[1].toFixed(2) + ' ' + last[0].toFixed(2) + ' ' + last[1].toFixed(2);
    return path;
  }
  function drawOverviewChart(card, range) {
    var chartEl = card.querySelector('[data-overview-chart]');
    if (!chartEl) return;
    var marketId = chartEl.getAttribute('data-overview-chart');
    var allValues = Array.isArray(overviewHistory[marketId]) ? overviewHistory[marketId].map(function (p) { return Number(p.v) || 0; }).filter(function (v) { return v > 0; }) : [];
    var values = allValues.slice(-(overviewRanges[range] || 7));
    var line = chartEl.querySelector('.market-overview-line');
    var area = chartEl.querySelector('.market-overview-area');
    if (!values.length || !line || !area) return;
    var min = Math.min.apply(null, values), max = Math.max.apply(null, values);
    var spread = max - min || Math.max(max * .01, 1);
    var coords = values.map(function (value, index) {
      var x = values.length === 1 ? 160 : 5 + (index / (values.length - 1)) * 310;
      var y = 67 - ((value - min) / spread) * 52;
      return [x, y];
    });
    var d = smoothOverviewPath(coords);
    line.setAttribute('d', d);
    area.setAttribute('d', d + ' L315 76 L5 76 Z');
    var direction = overviewDirections[marketId] || (values[values.length - 1] > values[0] ? 'up' : (values[values.length - 1] < values[0] ? 'down' : 'flat'));
    chartEl.classList.remove('up', 'down', 'flat');
    chartEl.classList.add(direction);
  }
  function updateOverviewCharts(data, byId) {
    var timestamp = normalizeUnixTimestamp(data && data.fetched_at);
    if (!Number.isFinite(timestamp)) timestamp = Math.floor(Date.now() / 1000);
    document.querySelectorAll('[data-overview-card]').forEach(function (card) {
      var marketId = card.getAttribute('data-overview-card');
      var item = byId && byId[marketId];
      var live = Number(item && item.live);
      if (!Number.isFinite(live) || live <= 0) return;

      overviewDirections[marketId] = item.dir === 'high' ? 'up' : (item.dir === 'low' ? 'down' : 'flat');

      var rows = Array.isArray(overviewHistory[marketId]) ? overviewHistory[marketId] : [];
      var last = rows.length ? rows[rows.length - 1] : null;
      if (last && String(last.time || '') === String(timestamp)) last.v = live;
      else rows.push({ time: timestamp, v: live });
      overviewHistory[marketId] = rows.slice(-120);
      drawOverviewChart(card, overviewSelectedRanges[marketId] || 'day');
    });
  }
  document.querySelectorAll('[data-overview-card]').forEach(function (card) {
    var marketId = card.getAttribute('data-overview-card');
    overviewSelectedRanges[marketId] = 'day';
    var initialChart = card.querySelector('[data-overview-chart]');
    overviewDirections[marketId] = initialChart && initialChart.classList.contains('down') ? 'down' : (initialChart && initialChart.classList.contains('flat') ? 'flat' : 'up');
    var buttons = card.querySelectorAll('[data-overview-range]');
    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        buttons.forEach(function (other) {
          var selected = other === button;
          other.classList.toggle('active', selected);
          other.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
        var selectedRange = button.getAttribute('data-overview-range');
        overviewSelectedRanges[marketId] = selectedRange;
        drawOverviewChart(card, selectedRange);
      });
    });
    drawOverviewChart(card, 'day');
  });

  /* ---------- تیکر بی‌نهایت ---------- */
  function initializeInfiniteTicker() {
    var tickerTrack = document.querySelector('.ticker-track');
    if (!tickerTrack) return;

    tickerTrack.querySelectorAll(':scope > .ticker-item[data-ticker-clone="true"]').forEach(function (item) {
      item.remove();
    });

    var originalItems = Array.prototype.slice.call(tickerTrack.children).filter(function (item) {
      return item.classList.contains('ticker-item') && item.getAttribute('data-ticker-clone') !== 'true';
    });
    if (!originalItems.length) return;

    var cycleSources = originalItems.slice();
    var cycleWidth = cycleSources.reduce(function (total, item) { return total + item.offsetWidth; }, 0);
    var viewportWidth = tickerTrack.parentElement ? tickerTrack.parentElement.clientWidth : 0;
    var sourceIndex = 0;

    while (cycleWidth < viewportWidth && originalItems.length) {
      var source = originalItems[sourceIndex % originalItems.length];
      var filler = source.cloneNode(true);
      filler.setAttribute('data-ticker-clone', 'true');
      filler.setAttribute('aria-hidden', 'true');
      tickerTrack.appendChild(filler);
      cycleSources.push(source);
      cycleWidth += source.offsetWidth;
      sourceIndex += 1;
    }

    cycleSources.forEach(function (source) {
      var clonedItem = source.cloneNode(true);
      clonedItem.setAttribute('data-ticker-clone', 'true');
      clonedItem.setAttribute('aria-hidden', 'true');
      tickerTrack.appendChild(clonedItem);
    });
  }

  initializeInfiniteTicker();
  var tickerResizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(tickerResizeTimer);
    tickerResizeTimer = setTimeout(initializeInfiniteTicker, 150);
  });

  function refresh() {
    requestPrices().catch(function () { /* بی‌صدا؛ تلاش بعدی */ });
  }
  var refreshTimer = window.setInterval(refresh, REFRESH_MS);
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      window.clearInterval(refreshTimer);
      refreshTimer = null;
      return;
    }
    refresh();
    if (!refreshTimer) refreshTimer = window.setInterval(refresh, REFRESH_MS);
  });
})();

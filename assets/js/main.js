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
        { key: 'طلا', label: 'بازار طلا', market: 'gold' },
        { key: 'سکه', label: 'بازار سکه', market: 'coins' },
        { key: 'ارز', label: 'بازار ارز', market: 'currency' },
        { key: 'نقره', label: 'بازار نقره', market: 'silver' }
      ];
      categories.forEach(function (category) {
        if (query.indexOf(category.key) === -1) return;
        entries.push({ text: category.label, href: (window.MEYAR_BASE || './') + 'prices.php?market=' + category.market, meta: 'ورود به بازار' });
      });
      document.querySelectorAll('[data-id], [data-overview-card]').forEach(function (item) {
        var title = item.querySelector('.market-asset-title, .cell-title, .market-card-title, .market-overview-title');
        var text = (title || item).textContent.trim();
        var searchableText = item.textContent.trim();
        if (!text || normalizeSearch(searchableText).indexOf(query) === -1) return;
        var href = item.getAttribute('href') || item.getAttribute('data-href');
        var itemId = item.getAttribute('data-id') || item.getAttribute('data-overview-card');
        if (!href && itemId) href = (window.MEYAR_BASE || './') + 'price/' + itemId;
        if (!href) return;
        if (entries.some(function (entry) { return entry.href === href; })) return;
        entries.push({ text: text, href: href, meta: 'مشاهده قیمت' });
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
      var match = [].find.call(document.querySelectorAll('tr[data-id], [data-overview-card]'), function (row) {
        return normalizeSearch(row.textContent).indexOf(query) !== -1;
      });
      if (match) {
        match.scrollIntoView({ behavior: 'smooth', block: 'center' });
        match.classList.add('search-hit');
        setTimeout(function () { match.classList.remove('search-hit'); }, 1600);
        return;
      }
      window.location.href = (window.MEYAR_BASE || './') + '#prices';
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
  var REFRESH_MS = 60000;

  function updateCell(cell, newVal) {
    if (!cell || cell.textContent.trim() === newVal) return 0;
    var dir = cell.textContent.trim() < newVal ? 'up' : 'down'; // مقایسه‌ی تقریبی نمایشی
    cell.textContent = newVal;
    cell.classList.remove('flash-up', 'flash-down');
    void cell.offsetWidth;
    cell.classList.add(dir === 'up' ? 'flash-up' : 'flash-down');
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
    data.items.forEach(function (i) { byId[i.id] = i; });

    // جداول
    document.querySelectorAll('tr[data-id]').forEach(function (row) {
      var it = byId[row.getAttribute('data-id')];
      if (!it) return;
      updateCell(row.querySelector('[data-cell="live"]'), it.live_fmt);
      updateCell(row.querySelector('[data-cell="buy"]'), it.buy_fmt);
      updateCell(row.querySelector('[data-cell="sell"]'), it.sell_fmt);
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
      var price = asset.querySelector('[data-cell="live"]');
      if (price) {
        price.textContent = it.live_fmt || '';
        var unit = document.createElement('small');
        unit.textContent = it.unit || '';
        price.appendChild(unit);
      }
      var chg = asset.querySelector('[data-cell="chg"]');
      if (chg) {
        chg.textContent = '';
        var trend = document.createElement('span');
        trend.className = 'market-asset-trend';
        trend.setAttribute('aria-hidden', 'true');
        setDirectionValue(trend, it.dir, '', true);
        chg.appendChild(trend);
        chg.appendChild(document.createTextNode(' ' + (it.change_pct || '') + '٪'));
        chg.className = 'market-asset-change ' + (it.dir === 'high' ? 'up' : (it.dir === 'low' ? 'down' : 'flat'));
      }
    });

    // نمای کلی چهار بازار اصلی
    document.querySelectorAll('[data-overview-card]').forEach(function (card) {
      var it = byId[card.getAttribute('data-overview-card')];
      if (!it) return;
      var price = card.querySelector('.market-overview-price strong');
      if (price) price.textContent = it.live_fmt || '';
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
        live.textContent = it.live_fmt || '';
        var unit = document.createElement('small');
        unit.textContent = it.unit || '';
        live.appendChild(document.createTextNode(' '));
        live.appendChild(unit);
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
      if (lv) lv.textContent = it.live_fmt;
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
      if (it) el.textContent = it.sell_fmt;
    });
    document.querySelectorAll('.item-cards [data-cell="buy"]').forEach(function (el) {
      var box = document.querySelector('[data-item-price]');
      if (!box) return;
      var it = byId[box.getAttribute('data-item-price')];
      if (it) el.textContent = it.buy_fmt;
    });

    // تیکر
    document.querySelectorAll('.ticker-item').forEach(function (t) {
      var it = byId[t.getAttribute('data-tid')];
      if (!it) return;
      var priceEl = t.querySelector('.ticker-price');
      if (priceEl) priceEl.textContent = it.live_fmt;
      var chEl = t.querySelector('.ticker-change');
      if (chEl) {
        setDirectionValue(chEl, it.dir, it.change_pct, false);
        chEl.className = 'ticker-change ' + (it.dir === 'high' ? 'up' : (it.dir === 'low' ? 'down' : ''));
      }
    });

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
  }

  /* ---------- نمودار واقعی داشبورد بازار ---------- */
  var historyEl = document.getElementById('marketHistoryData');
  var chart = document.getElementById('marketChart');
  if (historyEl && chart) {
    var marketHistory = {};
    try { marketHistory = JSON.parse(historyEl.textContent || '{}'); } catch (e) { marketHistory = {}; }
    var chartLine = chart.querySelector('.market-chart-line');
    var chartArea = chart.querySelector('.market-chart-area');
    var chartButtons = document.querySelectorAll('[data-chart-market]');

    function drawMarketChart(marketId) {
      var points = Array.isArray(marketHistory[marketId]) ? marketHistory[marketId] : [];
      if (!chartLine || !chartArea || !points.length) {
        if (chartLine) chartLine.setAttribute('d', '');
        if (chartArea) chartArea.setAttribute('d', '');
        return;
      }
      var values = points.map(function (p) { return Number(p.v) || 0; });
      var min = Math.min.apply(null, values), max = Math.max.apply(null, values);
      var spread = max - min || Math.max(max * .01, 1);
      var coords = values.map(function (value, index) {
        var x = values.length === 1 ? 260 : 8 + (index / (values.length - 1)) * 504;
        var y = 72 - ((value - min) / spread) * 60;
        return [x, y];
      });
      var d = coords.map(function (p, index) { return (index ? 'L' : 'M') + p[0].toFixed(2) + ' ' + p[1].toFixed(2); }).join(' ');
      var area = d + ' L512 82 L8 82 Z';
      chartLine.setAttribute('d', d);
      chartArea.setAttribute('d', area);
      chartLine.style.opacity = '0';
      chartArea.style.opacity = '0';
      requestAnimationFrame(function () {
        chartLine.style.opacity = '1';
        chartArea.style.opacity = '.16';
      });
    }
    chartButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        chartButtons.forEach(function (b) { b.classList.toggle('active', b === button); b.setAttribute('aria-selected', b === button ? 'true' : 'false'); });
        drawMarketChart(button.getAttribute('data-chart-market'));
      });
    });
    drawMarketChart('geram18');
  }

  /* ---------- تحلیل هوشمند بازار ---------- */
  var insightList = document.querySelector('[data-market-insights]');
  var insightTrend = document.querySelector('[data-insight-trend]');
  if (insightList && insightTrend) {
    fetch((window.MEYAR_BASE || './') + 'api/market-insight.php', { cache: 'no-store' })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (payload) {
        if (!payload || !payload.ok || !payload.insight) return;
        var insight = payload.insight;
        insightTrend.textContent = insight.trend_label || 'خنثی';
        insightTrend.className = insight.trend || 'flat';
        insightList.textContent = '';
        (insight.insights || []).slice(0, 4).forEach(function (value) {
          var li = document.createElement('li');
          li.textContent = value;
          insightList.appendChild(li);
        });
      })
      .catch(function () { /* متن اولیه کارت حفظ می‌شود */ });
  }

  /* ---------- ویجت‌های نمای کلی بازار ---------- */
  var overviewHistory = {};
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
    var direction = values[values.length - 1] > values[0] ? 'up' : (values[values.length - 1] < values[0] ? 'down' : 'flat');
    chartEl.classList.remove('up', 'down', 'flat');
    chartEl.classList.add(direction);
  }
  document.querySelectorAll('[data-overview-card]').forEach(function (card) {
    var buttons = card.querySelectorAll('[data-overview-range]');
    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        buttons.forEach(function (other) {
          var selected = other === button;
          other.classList.toggle('active', selected);
          other.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
        drawOverviewChart(card, button.getAttribute('data-overview-range'));
      });
    });
    drawOverviewChart(card, 'day');
  });

  function refresh() {
    fetch(API, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(applyData)
      .catch(function () { /* بی‌صدا؛ تلاش بعدی */ });
  }
  setInterval(refresh, REFRESH_MS);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) refresh();
  });
})();

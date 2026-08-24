/* SoftwareHub — minimal vanilla JS. No external dependencies. */
(function () {
  'use strict';

  var root = document.documentElement;

  // --- Theme toggle ----------------------------------------------------------
  function currentTheme() {
    return root.getAttribute('data-theme') ||
      (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  }
  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var next = currentTheme() === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('theme', next); } catch (e) {}
    });
  });

  // --- Mobile nav ------------------------------------------------------------
  var navToggle = document.querySelector('[data-nav-toggle]');
  var nav = document.querySelector('[data-nav]');
  if (navToggle && nav) {
    navToggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // --- Filter toggle (mobile) ------------------------------------------------
  var filterToggle = document.querySelector('[data-filter-toggle]');
  var filters = document.querySelector('[data-filters]');
  if (filterToggle && filters) {
    filterToggle.addEventListener('click', function () { filters.classList.toggle('open'); });
  }

  // --- Search autocomplete ---------------------------------------------------
  var base = (function () {
    var a = document.querySelector('a.brand');
    return a ? a.getAttribute('href').replace(/\/$/, '') : '';
  })();

  document.querySelectorAll('[data-suggest]').forEach(function (input) {
    var box = input.parentNode.querySelector('[data-suggest-box]');
    if (!box) return;
    var timer, lastQuery = '';

    input.addEventListener('input', function () {
      var q = input.value.trim();
      if (q === lastQuery) return;
      lastQuery = q;
      clearTimeout(timer);
      if (q.length < 2) { box.hidden = true; box.innerHTML = ''; return; }
      timer = setTimeout(function () {
        fetch(base + '/api/suggest?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!data.results || !data.results.length) { box.hidden = true; return; }
            box.innerHTML = data.results.map(function (s) {
              return '<a href="' + base + '/software/' + encodeURIComponent(s.slug) + '">' +
                '<strong>' + escapeHtml(s.name) + '</strong></a>';
            }).join('');
            box.hidden = false;
          }).catch(function () { box.hidden = true; });
      }, 180);
    });

    document.addEventListener('click', function (e) {
      if (!input.parentNode.contains(e.target)) { box.hidden = true; }
    });
  });

  // --- Compare picker --------------------------------------------------------
  var compareForm = document.getElementById('compare-form');
  if (compareForm) {
    var idsInput = compareForm.querySelector('[data-compare-ids]');
    var submitBtn = compareForm.querySelector('[data-compare-submit]');
    var counter = compareForm.querySelector('[data-compare-count]');
    var picks = compareForm.querySelectorAll('[data-compare-pick]');
    function refresh() {
      var chosen = Array.prototype.filter.call(picks, function (p) { return p.checked; });
      if (chosen.length > 4) { chosen[chosen.length - 1].checked = false; return refresh(); }
      idsInput.value = chosen.map(function (p) { return p.value; }).join(',');
      if (counter) counter.textContent = chosen.length;
      if (submitBtn) submitBtn.disabled = chosen.length < 2;
    }
    picks.forEach(function (p) { p.addEventListener('change', refresh); });
  }

  // --- Software Finder -------------------------------------------------------
  var finderForm = document.getElementById('finder-form');
  if (finderForm) {
    var selectedNeed = '';
    // "What do you need" — single select.
    finderForm.querySelectorAll('.finder-options[data-group="need"] .finder-chip').forEach(function (chip) {
      chip.addEventListener('click', function () {
        finderForm.querySelectorAll('.finder-options[data-group="need"] .finder-chip').forEach(function (c) { c.classList.remove('is-active'); });
        chip.classList.add('is-active');
        selectedNeed = chip.getAttribute('data-value');
      });
    });
    // Preferences — multi select.
    finderForm.querySelectorAll('.finder-options[data-group="prefs"] .finder-chip').forEach(function (chip) {
      chip.addEventListener('click', function () { chip.classList.toggle('is-active'); });
    });

    finderForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(finderForm);
      fd.set('need', selectedNeed);
      fd.set('_csrf', finderForm.getAttribute('data-csrf') || '');
      var params = new URLSearchParams();
      fd.forEach(function (v, k) { params.append(k, v); });
      finderForm.querySelectorAll('.finder-options[data-group="prefs"] .finder-chip.is-active').forEach(function (c) {
        params.append('prefs[]', c.getAttribute('data-pref'));
      });

      var results = document.getElementById('finder-results');
      var grid = results.querySelector('[data-results-grid]');
      grid.innerHTML = '<p class="muted">Finding matches…</p>';
      results.hidden = false;

      fetch(base + '/software-finder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: params.toString()
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data.matches || !data.matches.length) {
          grid.innerHTML = '<div class="empty-state"><p>No strong matches — try widening your filters.</p></div>';
          return;
        }
        grid.innerHTML = data.matches.map(function (m) {
          var reasons = (m.match_reasons || []).map(function (r) { return '<span class="reason-ok">✓ ' + escapeHtml(r) + '</span>'; }).join('');
          var cautions = (m.cautions || []).map(function (r) { return '<span class="reason-warn">⚠ ' + escapeHtml(r) + '</span>'; }).join('');
          var score = m.match_score || 0;
          var lvlClass = score >= 90 ? 'ms-exc' : score >= 75 ? 'ms-good' : score >= 50 ? 'ms-ok' : 'ms-low';
          var logo = m.logo ? '<img src="' + escapeHtml(m.logo) + '" alt="" width="40" height="40">' : escapeHtml((m.name || '?').charAt(0).toUpperCase());
          var compat = (m.compatibility != null) ? '<span class="chip chip-soft small" title="Device compatibility">🖥️ ' + m.compatibility + '% compatible</span>' : '';
          return '<article class="card match-card"><a class="card-link" href="' + base + '/software/' + encodeURIComponent(m.slug) + '">' +
            '<div class="card-head"><div class="card-logo">' + logo + '</div>' +
            '<div class="card-title"><h3>' + escapeHtml(m.name) + '</h3><span class="muted small">' + escapeHtml(m.developer_name || '') + '</span></div>' +
            '<span class="match-score ' + lvlClass + '">' + score + '<small>%</small></span></div>' +
            '<p class="card-desc">' + escapeHtml((m.short_description || '').slice(0, 100)) + '</p>' +
            '<div class="match-why">' + reasons + cautions + '</div>' +
            '<div class="card-meta"><span class="chip chip-soft small">' + escapeHtml(m.match_level || '') + '</span> ' + compat + '</div>' +
            '</a></article>';
        }).join('');
        results.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }).catch(function () {
        grid.innerHTML = '<p class="muted">Something went wrong. Please try again.</p>';
      });
    });
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
})();

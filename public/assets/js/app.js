/* OyubiaCYF client — PWA registration, offline queue, sync, form enhancements. */
(function () {
  'use strict';
  var BASE = window.OyubiaCYF_BASE || '';
  var u = function (p) { return BASE + p; };

  /* ----------------------- Service worker registration ----------------------- */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(u('/service-worker.js'), { scope: u('/') })
        .catch(function (e) { console.warn('SW registration failed', e); });
    });
  }

  /* ------------------------- Mobile nav (hamburger) ------------------------- */
  (function () {
    var toggle = document.getElementById('navToggle');
    var bar = document.querySelector('.topbar');
    if (!toggle || !bar) return;
    toggle.addEventListener('click', function () {
      var open = bar.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  })();

  /* ------------------------------- IndexedDB --------------------------------- */
  var DB_NAME = 'oyubiacyf', STORE = 'queue';
  function openDB() {
    return new Promise(function (resolve, reject) {
      var req = indexedDB.open(DB_NAME, 1);
      req.onupgradeneeded = function () {
        var db = req.result;
        if (!db.objectStoreNames.contains(STORE)) {
          db.createObjectStore(STORE, { keyPath: 'client_uuid' });
        }
      };
      req.onsuccess = function () { resolve(req.result); };
      req.onerror = function () { reject(req.error); };
    });
  }
  function idbAll() {
    return openDB().then(function (db) {
      return new Promise(function (resolve) {
        var tx = db.transaction(STORE, 'readonly').objectStore(STORE).getAll();
        tx.onsuccess = function () { resolve(tx.result || []); };
        tx.onerror = function () { resolve([]); };
      });
    });
  }
  function idbPut(item) {
    return openDB().then(function (db) {
      return new Promise(function (resolve) {
        var tx = db.transaction(STORE, 'readwrite').objectStore(STORE).put(item);
        tx.onsuccess = function () { resolve(true); };
        tx.onerror = function () { resolve(false); };
      });
    });
  }
  function idbDelete(uuid) {
    return openDB().then(function (db) {
      return new Promise(function (resolve) {
        var tx = db.transaction(STORE, 'readwrite').objectStore(STORE)['delete'](uuid);
        tx.onsuccess = function () { resolve(true); };
        tx.onerror = function () { resolve(false); };
      });
    });
  }

  function uuid() {
    if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  /* --------------------------- Online / offline UI --------------------------- */
  var banner = document.getElementById('offlineBanner');
  var syncDot = document.getElementById('syncStatus');

  function refreshStatus() {
    if (banner) { banner.hidden = navigator.onLine; }
    idbAll().then(function (items) {
      if (!syncDot) { return; }
      if (items.length > 0) {
        syncDot.classList.add('pending');
        syncDot.title = items.length + ' registration(s) waiting to sync';
      } else {
        syncDot.classList.remove('pending');
        syncDot.title = navigator.onLine ? 'Online — all synced' : 'Offline';
      }
    });
  }
  window.addEventListener('online', function () { refreshStatus(); flushQueue(); });
  window.addEventListener('offline', refreshStatus);

  /* -------------------------------- Syncing ---------------------------------- */
  var csrfToken = (document.querySelector('input[name="_csrf"]') || {}).value || '';

  function postSync(items) {
    return fetch(u('/api/sync'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'fetch' },
      body: JSON.stringify({ _csrf: csrfToken, items: items })
    }).then(function (r) {
      if (!r.ok) { throw new Error('sync failed: ' + r.status); }
      return r.json();
    });
  }

  var flushing = false;
  function flushQueue() {
    if (flushing || !navigator.onLine) { return Promise.resolve(); }
    flushing = true;
    return idbAll().then(function (items) {
      if (!items.length) { flushing = false; return; }
      return postSync(items).then(function (resp) {
        var done = (resp.results || []).filter(function (r) {
          return r.status === 'created' || r.status === 'duplicate';
        });
        return Promise.all(done.map(function (r) { return idbDelete(r.client_uuid); }));
      }).then(function () {
        flushing = false;
        refreshStatus();
      })['catch'](function () { flushing = false; });
    });
  }

  /* ---------------- Searchable <select> (combobox) enhancement --------------- */
  function enhanceSearchableSelect(select) {
    if (select.dataset.enhanced) { return; }
    select.dataset.enhanced = '1';
    var placeholder = select.getAttribute('data-searchable') || 'Search…';

    var wrap = document.createElement('div');
    wrap.className = 'ss';
    select.parentNode.insertBefore(wrap, select);
    select.style.display = 'none';
    wrap.appendChild(select);

    var input = document.createElement('input');
    input.type = 'text'; input.className = 'ss-input'; input.placeholder = placeholder;
    input.autocomplete = 'off';
    var caret = document.createElement('span'); caret.className = 'ss-caret'; caret.textContent = '▾';
    var panel = document.createElement('div'); panel.className = 'ss-panel'; panel.hidden = true;
    wrap.appendChild(input); wrap.appendChild(caret); wrap.appendChild(panel);

    var opts = Array.prototype.map.call(select.options, function (o) {
      return { value: o.value, label: o.text.replace(/\s+/g, ' ').trim() };
    });
    var addOpt = null;
    opts.forEach(function (o) { if (o.value === '') { addOpt = o; } });

    function selectedLabel() {
      var o = select.options[select.selectedIndex];
      return (o && o.value) ? o.text.replace(/\s+/g, ' ').trim() : '';
    }
    input.value = selectedLabel();

    var activeIdx = -1;
    function close() { panel.hidden = true; activeIdx = -1; wrap.classList.remove('open'); }

    function setValue(val, dispatch) {
      select.value = val;
      if (dispatch) { select.dispatchEvent(new Event('change')); }
    }

    // Normalize "COC" / "C.O.C" and "Church of Christ" to the same token so
    // either abbreviation matches the full name and vice versa.
    function normLabel(s) {
      return s.toLowerCase().replace(/\bc\.?o\.?c\.?\b/g, 'church of christ');
    }

    function render(q) {
      var normQ = normLabel(q || '');
      var matches = opts.filter(function (o) {
        return o.value !== '' && normLabel(o.label).indexOf(normQ) >= 0;
      });
      panel.innerHTML = '';
      var list = [];
      if (addOpt) { list.push(addOpt); }
      list = list.concat(matches);
      list.forEach(function (o) {
        var d = document.createElement('div');
        d.className = 'ss-item' + (o.value === '' ? ' ss-add' : '');
        d.textContent = (o.value === '') ? '＋ Add a new congregation' : o.label;
        d.addEventListener('mousedown', function (ev) {
          ev.preventDefault();
          input.value = (o.value === '') ? '' : o.label;
          setValue(o.value, true);
          close();
        });
        panel.appendChild(d);
      });
      if (matches.length === 0 && q) {
        var none = document.createElement('div');
        none.className = 'lookup-empty';
        none.textContent = 'No match — add it as new.';
        panel.appendChild(none);
      }
      panel.hidden = false; wrap.classList.add('open');
    }

    function highlight(items) {
      items.forEach(function (it, i) { it.classList.toggle('active', i === activeIdx); });
      if (items[activeIdx]) { items[activeIdx].scrollIntoView({ block: 'nearest' }); }
    }

    input.addEventListener('focus', function () { render(input.value === selectedLabel() ? '' : input.value); });
    input.addEventListener('input', function () { setValue('', true); render(input.value); });
    caret.addEventListener('mousedown', function (ev) {
      ev.preventDefault();
      if (panel.hidden) { input.focus(); render(''); } else { close(); }
    });
    input.addEventListener('keydown', function (e) {
      var items = panel.querySelectorAll('.ss-item');
      if (e.key === 'ArrowDown') { e.preventDefault(); if (panel.hidden) { render(''); } activeIdx = Math.min(activeIdx + 1, items.length - 1); highlight(items); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); highlight(items); }
      else if (e.key === 'Enter') { if (activeIdx >= 0 && items[activeIdx]) { e.preventDefault(); items[activeIdx].dispatchEvent(new Event('mousedown')); } }
      else if (e.key === 'Escape') { close(); }
    });
    document.addEventListener('click', function (ev) { if (!wrap.contains(ev.target)) { close(); } });
  }

  Array.prototype.forEach.call(document.querySelectorAll('select[data-searchable]'), enhanceSearchableSelect);

  /* ---------------------- Register form: reveal new-cong --------------------- */
  var congSelect = document.getElementById('congSelect');
  var newCong = document.getElementById('newCongFields');
  if (congSelect && newCong) {
    var toggleCong = function () { newCong.style.display = congSelect.value ? 'none' : 'block'; };
    congSelect.addEventListener('change', toggleCong);
    toggleCong();
  }

  /* --------------------- Returning-attendee search & link -------------------- */
  var searchInput = document.getElementById('returningSearch');
  var resultsBox = document.getElementById('returningResults');
  var linkedNote = document.getElementById('linkedNote');
  var attendeeIdEl = document.getElementById('attendeeId');

  if (searchInput && resultsBox && attendeeIdEl && window.fetch) {
    var searchTimer = null;

    function setField(name, val) {
      var el = document.querySelector('#regForm [name="' + name + '"]');
      if (el) { el.value = (val === null || val === undefined) ? '' : val; }
    }

    function closeResults() { resultsBox.hidden = true; resultsBox.innerHTML = ''; }

    function showLinked(a) {
      attendeeIdEl.value = a.id;
      setField('full_name', a.full_name);
      setField('gender', a.gender || '');
      setField('phone', a.phone || '');
      setField('email', a.email || '');
      setField('birth_day', a.birth_day || '');
      setField('birth_month', a.birth_month || '');
      setField('home_state', a.home_state || '');
      setField('home_city', a.home_city || '');

      var warn = a.in_active
        ? '<div class="flash flash-warn" style="margin:.4rem 0 0">Already registered for this edition — submitting will just show their existing record.</div>'
        : '';
      linkedNote.innerHTML =
        '<span class="linked-pill">Linked to existing record'
        + (a.years ? ' · attended ' + escapeHtml(a.years) : '')
        + ' <button type="button" id="unlinkBtn" title="Unlink">&times;</button></span>' + warn;
      linkedNote.hidden = false;
      searchInput.value = '';
      searchInput.style.display = 'none';
      closeResults();

      var unlink = document.getElementById('unlinkBtn');
      if (unlink) { unlink.addEventListener('click', clearLink); }
    }

    function clearLink() {
      attendeeIdEl.value = '';
      linkedNote.hidden = true;
      linkedNote.innerHTML = '';
      searchInput.style.display = '';
      searchInput.focus();
    }

    function render(results) {
      if (!results.length) {
        resultsBox.innerHTML = '<div class="lookup-empty">No matching past attendees.</div>';
        resultsBox.hidden = false;
        return;
      }
      resultsBox.innerHTML = '';
      results.forEach(function (a) {
        var div = document.createElement('div');
        div.className = 'lookup-item';
        var title = (a.is_member && a.gender === 'male') ? 'Bro. '
                  : (a.is_member && a.gender === 'female') ? 'Sis. ' : '';
        var meta = [];
        if (a.phone) { meta.push(escapeHtml(a.phone)); }
        if (a.years) { meta.push('attended ' + escapeHtml(a.years)); }
        if (a.in_active) { meta.push('⚠ already registered this year'); }
        div.innerHTML = '<div class="li-name">' + escapeHtml(title + a.full_name) + '</div>'
          + '<div class="li-meta">' + meta.join(' · ') + '</div>';
        div.addEventListener('click', function () { showLinked(a); });
        resultsBox.appendChild(div);
      });
      resultsBox.hidden = false;
    }

    searchInput.addEventListener('input', function () {
      var q = searchInput.value.trim();
      if (searchTimer) { clearTimeout(searchTimer); }
      if (q.length < 2 || !navigator.onLine) { closeResults(); return; }
      searchTimer = setTimeout(function () {
        fetch(u('/api/attendees/search?q=') + encodeURIComponent(q), {
          headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin'
        }).then(function (r) { return r.json(); })
          .then(function (d) { render(d.results || []); })
          ['catch'](function () { closeResults(); });
      }, 250);
    });

    document.addEventListener('click', function (ev) {
      if (!resultsBox.contains(ev.target) && ev.target !== searchInput) { closeResults(); }
    });
  }

  /* ----------------------- Merge tool attendee pickers ----------------------- */
  if (document.getElementById('mergePickForm') && window.fetch) {
    var attachMergePicker = function (prefix) {
      var input = document.getElementById(prefix + 'Search');
      var results = document.getElementById(prefix + 'Results');
      var hidden = document.getElementById(prefix);
      var label = document.getElementById(prefix + 'Label');
      var chosen = document.getElementById(prefix + 'Chosen');
      var wrap = document.getElementById(prefix + 'Wrap');
      if (!input || !results || !hidden) { return; }
      var timer = null;
      var close = function () { results.hidden = true; results.innerHTML = ''; };

      input.addEventListener('input', function () {
        var q = input.value.trim();
        if (timer) { clearTimeout(timer); }
        if (q.length < 2 || !navigator.onLine) { close(); return; }
        timer = setTimeout(function () {
          fetch(u('/api/attendees/search?q=') + encodeURIComponent(q), {
            headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin'
          }).then(function (r) { return r.json(); })
            .then(function (d) { renderList(d.results || []); })['catch'](close);
        }, 250);
      });

      function renderList(list) {
        if (!list.length) { results.innerHTML = '<div class="lookup-empty">No matches.</div>'; results.hidden = false; return; }
        results.innerHTML = '';
        list.forEach(function (a) {
          var div = document.createElement('div');
          div.className = 'lookup-item';
          var title = (a.is_member && a.gender === 'male') ? 'Bro. '
                    : (a.is_member && a.gender === 'female') ? 'Sis. ' : '';
          var meta = [];
          if (a.phone) { meta.push(escapeHtml(a.phone)); }
          if (a.years) { meta.push('attended ' + escapeHtml(a.years)); }
          div.innerHTML = '<div class="li-name">' + escapeHtml(title + a.full_name) + '</div>'
            + '<div class="li-meta">' + meta.join(' · ') + '</div>';
          div.addEventListener('click', function () {
            hidden.value = a.id;
            if (label) { label.textContent = (title + a.full_name) + (a.phone ? ' · ' + a.phone : ''); }
            if (chosen) { chosen.hidden = false; }
            if (wrap) { wrap.hidden = true; }
            close();
          });
          results.appendChild(div);
        });
        results.hidden = false;
      }

      document.addEventListener('click', function (ev) {
        if (!results.contains(ev.target) && ev.target !== input) { close(); }
      });
    };
    attachMergePicker('target');
    attachMergePicker('source');

    Array.prototype.forEach.call(document.querySelectorAll('[data-clear]'), function (btn) {
      btn.addEventListener('click', function () {
        var p = btn.getAttribute('data-clear');
        var hidden = document.getElementById(p); if (hidden) { hidden.value = ''; }
        var chosen = document.getElementById(p + 'Chosen'); if (chosen) { chosen.hidden = true; }
        var wrap = document.getElementById(p + 'Wrap'); if (wrap) { wrap.hidden = false; }
      });
    });
  }

  /* ------------------------- Register form submission ------------------------ */
  var form = document.getElementById('regForm');
  if (form && !form.hasAttribute('data-bulk')) {
    form.addEventListener('submit', function (ev) {
      // Progressive enhancement: only intercept if fetch + IndexedDB exist.
      if (!window.fetch || !window.indexedDB) { return; }
      ev.preventDefault();

      var fd = new FormData(form);
      var data = {};
      fd.forEach(function (v, k) { if (k !== '_csrf') { data[k] = v; } });
      data.client_uuid = uuid();

      var submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving…'; }

      function finish(regNumber, regId, pending) {
        renderSuccess(data, regNumber, regId, pending);
      }

      if (navigator.onLine) {
        postSync([data]).then(function (resp) {
          var r = (resp.results || [])[0] || {};
          if (r.status === 'created' || r.status === 'duplicate') {
            finish(r.reg_number, r.registration_id, false);
          } else {
            // Validation error from server — re-enable and show message.
            showFormError(r.error || 'Could not save. Check the fields and try again.');
          }
        })['catch'](function () {
          // Network died mid-request: queue it.
          idbPut(data).then(function () { finish(null, null, true); refreshStatus(); });
        });
      } else {
        idbPut(data).then(function () { finish(null, null, true); refreshStatus(); });
      }
    });
  }

  function showFormError(msg) {
    var submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Register & assign reg number'; }
    var box = document.createElement('div');
    box.className = 'flash flash-error';
    box.textContent = msg;
    form.parentNode.insertBefore(box, form);
    window.scrollTo(0, 0);
  }

  function renderSuccess(data, regNumber, regId, pending) {
    var name = (data.full_name || '').trim();
    if (data.category !== 'visitor' && data.gender === 'male') name = 'Bro. ' + name;
    else if (data.category !== 'visitor' && data.gender === 'female') name = 'Sis. ' + name;

    var category = data.category || '';
    var html = ''
      + '<div class="card reg-success" style="max-width:560px;margin:1rem auto">'
      + '<p class="muted" style="margin:0">' + (pending ? 'Saved on this device — will sync when online' : 'Registration saved') + '</p>'
      + '<div class="regno">' + (regNumber ? escapeHtml(regNumber) : 'PENDING') + '</div>'
      + '<p style="margin:.2rem 0 1rem"><strong>' + escapeHtml(name) + '</strong></p>'
      + '<div class="inline-actions" style="justify-content:center">'
      + '<a class="btn" href="' + u('/register?category=' + encodeURIComponent(category)) + '">Register another</a>'
      + '<a class="btn ghost" href="' + u('/') + '">Dashboard</a>'
      + '</div>'
      + (pending ? '<p class="help" style="margin-top:.8rem">The reg number will be available once this syncs.</p>' : '')
      + '</div>';
    var main = document.querySelector('main.container');
    if (main) { main.innerHTML = html; window.scrollTo(0, 0); }
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------------------- Animated count-up for stats ------------------------ */
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduceMotion && window.requestAnimationFrame) {
    Array.prototype.forEach.call(document.querySelectorAll('.stat .num'), function (el) {
      var target = parseInt(el.textContent, 10);
      if (isNaN(target) || target <= 0) { return; }
      var dur = 700, t0 = null;
      el.textContent = '0';
      function step(ts) {
        if (t0 === null) { t0 = ts; }
        var p = Math.min((ts - t0) / dur, 1);
        // easeOutCubic
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(eased * target);
        if (p < 1) { requestAnimationFrame(step); }
      }
      requestAnimationFrame(step);
    });
  }

  /* --------------------------------- Boot ------------------------------------ */
  refreshStatus();
  if (navigator.onLine) { flushQueue(); }
})();

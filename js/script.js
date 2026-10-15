// Live updates: every element with data-live="url" re-fetches its HTML from PHP/MySQL every few seconds.
document.querySelectorAll('[data-live]').forEach(function (el) {
  function load() {
    fetch(el.dataset.live, { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(); })
      .then(function (html) { el.innerHTML = html; el.classList.remove('offline'); })
      .catch(function () { el.classList.add('offline'); });
  }
  setInterval(load, 4000);
});

// 2-minute departure countdown (the server value is re-synced on every refresh)
setInterval(function () {
  document.querySelectorAll('.cd').forEach(function (e) {
    var s = Math.max(0, (+e.dataset.s) - 1);
    e.dataset.s = s;
    e.textContent = Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
  });
}, 1000);

// Admin header clock: "Sunday, 3 May 2026 - 4:39 PM" (uses the device's own time)
(function () {
  var d = document.getElementById('clk-date'), t = document.getElementById('clk-time');
  if (!d || !t) return;
  var D = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    M = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  function tick() {
    var n = new Date(), h = n.getHours();
    d.textContent = D[n.getDay()] + ', ' + n.getDate() + ' ' + M[n.getMonth()] + ' ' + n.getFullYear();
    t.textContent = ((h % 12) || 12) + ':' + ('0' + n.getMinutes()).slice(-2) + ' ' + (h < 12 ? 'AM' : 'PM');
  }
  tick(); setInterval(tick, 1000);
})();

// Cards fade in only on first load (not on every live refresh)
setTimeout(function () { document.body.classList.remove('enter'); }, 900);

// Depart button: the MDSD checklist opens in a modal. The boat departs when the PCG submits it, so the checklist is the confirmation.
(function () {
  var box, body, err, count, sub, go, url, mid, busy;
  function build() {
    box = document.createElement('div');
    box.className = 'mdl';
    box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
    box.innerHTML = '<div class="mdl-box wide md-box"><div class="mdl-head"><div class="mdl-title"><h3>Master\u2019s Declaration of Safe Departure</h3><p class="sub"></p></div>' +
      '<div class="mdl-tools"><button type="button" class="x" aria-label="Close">&times;</button></div></div>' +
      '<form class="md-form" novalidate><div class="mdl-body md-body"></div><div class="alert bad md-err" hidden></div>' +
      '<div class="md-foot"><span class="md-count"></span><button type="button" class="btn sm ghost md-all">Mark all Yes</button><button type="button" class="btn sm ghost md-cancel">Cancel</button><button type="submit" class="btn sm md-go">Depart</button></div></form></div>';
    document.body.appendChild(box);
    body = box.querySelector('.md-body'); err = box.querySelector('.md-err'); count = box.querySelector('.md-count'); sub = box.querySelector('.sub'); go = box.querySelector('.md-go');
    box.addEventListener('click', function (e) { if (e.target.classList.contains('x') || e.target.classList.contains('md-cancel')) close(); });   // no backdrop close: a half-filled checklist is not lost by a stray tap
    box.addEventListener('change', function (e) { if (e.target.type === 'radio') { e.target.closest('.md-q').classList.remove('miss'); tally(); } });
    box.querySelector('.md-all').addEventListener('click', function () {
      body.querySelectorAll('.md-yes input').forEach(function (i) { i.checked = true; });
      body.querySelectorAll('.md-q.miss').forEach(function (q) { q.classList.remove('miss'); });
      tally();
    });
    box.querySelector('form').addEventListener('submit', submit);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box.classList.contains('open')) close(); });
  }
  function close() { box.classList.remove('open'); }
  function show(t) { err.textContent = t || ''; err.hidden = !t; }
  function tally() {
    var all = body.querySelectorAll('.md-q'), done = body.querySelectorAll('.md-q input:checked').length;
    count.textContent = done + ' of ' + all.length + ' answered';
  }
  function submit(e) {
    e.preventDefault(); if (busy) return;
    var first = null;
    body.querySelectorAll('.md-q').forEach(function (q) {   // every item needs an answer
      var miss = !q.querySelector('input:checked');
      q.classList.toggle('miss', miss);
      if (miss && !first) first = q;
    });
    if (first) { show('Please answer every item with Yes, No or N/A.'); first.scrollIntoView({ block: 'center', behavior: 'smooth' }); return; }
    show(''); busy = true; go.disabled = true; go.textContent = 'Departing\u2026';
    var d = new FormData(box.querySelector('form')); d.append('mid', mid); d.append('ajax', '1');
    fetch(url, { method: 'POST', body: d, credentials: 'same-origin' })
      .then(function (x) { return x.json(); })
      .then(function (j) { if (j.ok) location.reload(); else { show(j.error || 'Could not depart the boat.'); busy = false; go.disabled = false; go.textContent = 'Depart'; } })
      .catch(function () { show('Could not reach the server. Check your connection and try again.'); busy = false; go.disabled = false; go.textContent = 'Depart'; });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.depart') : null;
    if (!b) return;
    e.preventDefault();
    var f = b.closest('form');
    mid = f.querySelector('[name=mid]').value;
    url = f.action.replace(/depart\.php.*$/, 'mdsd.php');
    if (!box) build();
    busy = false; go.disabled = false; go.textContent = 'Depart'; show('');
    sub.textContent = 'Loading\u2026'; body.innerHTML = ''; count.textContent = '';
    void box.offsetWidth; box.classList.add('open');
    fetch(url + '?ajax=1&mid=' + encodeURIComponent(mid), { credentials: 'same-origin' })
      .then(function (x) { return x.json(); })
      .then(function (d) {
        if (d.ok === false) { sub.textContent = ''; show(d.error); return; }
        sub.textContent = d.sub;
        body.innerHTML = d.html;   // built and escaped on the server
        tally();
      })
      .catch(function () { sub.textContent = ''; show('Could not load the checklist.'); });
  });
})();

// Trip history: double-click a row to see that trip's passengers
(function () {
  var box;
  function build() {
    box = document.createElement('div');
    box.className = 'mdl';
    box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
    box.innerHTML = '<div class="mdl-box wide"><div class="mdl-head"><div class="mdl-title"><h3></h3><p class="sub"></p></div>' +
      '<div class="mdl-tools"><button type="button" class="btn sm ghost pdfbtn" data-print="" data-kind="print_crew"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6M7 17H5a1 1 0 01-1-1v-5a2 2 0 012-2h12a2 2 0 012 2v5a1 1 0 01-1 1h-2M7 14h10v7H7z"/></svg><span>Crew list</span></button>' +
      '<button type="button" class="btn sm pdfbtn" data-print="" data-kind="print"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6M7 17H5a1 1 0 01-1-1v-5a2 2 0 012-2h12a2 2 0 012 2v5a1 1 0 01-1 1h-2M7 14h10v7H7z"/></svg><span>Manifest</span></button>' +
      '<button type=\"button\" class=\"btn sm ghost pdfbtn\" data-print=\"\" data-kind=\"admin/mdsd_print\" disabled><svg class=\"ico\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M7 9V3h10v6M7 17H5a1 1 0 01-1-1v-5a2 2 0 012-2h12a2 2 0 012 2v5a1 1 0 01-1 1h-2M7 14h10v7H7z\"/></svg><span>MDSD</span></button>' +
      '<button type="button" class="x" aria-label="Close">&times;</button></div></div><div class="mdl-body"></div></div>';
    document.body.appendChild(box);
    box.addEventListener('click', function (e) { if (e.target === box || e.target.classList.contains('x')) box.classList.remove('open'); });
  }
  document.addEventListener('dblclick', function (e) {
    var r = e.target.closest ? e.target.closest('.trip-row') : null;
    if (!r) return;
    if (window.getSelection) window.getSelection().removeAllRanges();
    if (!box) build();
    box.querySelector('h3').textContent = r.dataset.name || 'Trip';
    box.querySelector('.sub').textContent = 'Loading…';
    box.querySelectorAll('[data-print]').forEach(function (b) {   // straight print of this trip's form (a kind with a folder, like admin/mdsd_print, is used as it is)
      var k = b.getAttribute('data-kind');
      b.setAttribute('data-print', r.dataset.url.replace('admin/passengers.php', (k.indexOf('/') < 0 ? 'operator/' : '') + k + '.php') + '&print=1');
    });
    var mb = box.querySelector('[data-kind="admin/mdsd_print"]'); mb.disabled = true; mb.title = '';   // on only if this trip has an MDSD
    box.querySelector('.mdl-body').innerHTML = '';
    void box.offsetWidth;
    box.classList.add('open');
    fetch(r.dataset.url, { credentials: 'same-origin' })
      .then(function (x) { return x.ok ? x.json() : Promise.reject(); })
      .then(function (d) {
        box.querySelector('h3').textContent = d.title;
        box.querySelector('.sub').textContent = d.sub;
        box.querySelector('.mdl-body').innerHTML = d.html;   // built and escaped on the server
        mb.disabled = !d.mdsd; mb.title = d.mdsd ? '' : 'No MDSD was made for this trip';
      })
      .catch(function () { box.querySelector('.sub').textContent = 'Could not load the passengers.'; });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box) box.classList.remove('open'); });
})();

// Register page: double-click a motorbanca row to edit it
(function () {
  var m = document.getElementById('edit-mdl'); if (!m) return;
  var f = document.getElementById('edit-form'), err = document.getElementById('edit-err'), sub = document.getElementById('edit-sub'), busy = false;
  function close() { m.classList.remove('open'); }
  function el(n) { return f.elements[n]; }
  document.addEventListener('dblclick', function (e) {
    var r = e.target.closest ? e.target.closest('.mb-row') : null; if (!r) return;
    if (window.getSelection) window.getSelection().removeAllRanges();
    el('mid').value = r.dataset.id; el('name').value = r.dataset.name; el('owner').value = r.dataset.owner;
    el('capacity').value = r.dataset.cap; el('cluster').value = r.dataset.cluster;
    var locked = r.dataset.status !== 'Not Queued';
    el('cluster').disabled = locked;
    sub.textContent = locked ? 'The cluster cannot be changed while this boat is on the queue.' : 'Update the details of this motorbanca.';
    err.hidden = true; busy = false;
    void m.offsetWidth; m.classList.add('open'); el('name').focus();
  });
  m.addEventListener('click', function (e) { if (e.target === m || e.target.classList.contains('x') || e.target.classList.contains('no')) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  f.addEventListener('submit', function (e) {
    e.preventDefault(); if (busy) return; busy = true;
    var d = new FormData(f);
    if (el('cluster').disabled) d.set('cluster', el('cluster').value);
    fetch(f.dataset.url, { method: 'POST', body: d, credentials: 'same-origin' })
      .then(function (x) { return x.json(); })
      .then(function (j) { if (j.ok) location.reload(); else { err.textContent = j.error || 'Could not save.'; err.hidden = false; busy = false; } })
      .catch(function () { err.textContent = 'Could not save. Check your connection.'; err.hidden = false; busy = false; });
  });
})();

// Styled hint for double-clickable rows. It appears only after the cursor stops (it does not follow the cursor)
// and also shows the full value of a cell that is cut with "...".
(function () {
  var tip, tx, timer = null, shown = false, ax = 0, ay = 0, lx = 0, ly = 0, lr = null, lt = null;
  function build() {
    tip = document.createElement('div');
    tip.className = 'rtip';
    tip.innerHTML = '<span class="rtip-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3l14 7-6 2-2 6z"/></svg></span><span class="rtip-tx"></span>';
    tx = tip.querySelector('.rtip-tx');
    document.body.appendChild(tip);
  }
  function hide() { clearTimeout(timer); timer = null; shown = false; if (tip) tip.classList.remove('show'); }
  function show() {
    timer = null;
    if (!lr || !document.body.contains(lr)) return;
    if (!tip) build();
    var cell = lt && lt.closest ? lt.closest('td') : null, full = '';
    if (cell) { var el = cell.querySelector('code') || cell; if (el.scrollWidth > el.clientWidth + 1) full = cell.textContent.trim(); }
    tx.textContent = '';
    if (full) { var b = document.createElement('b'); b.textContent = full; tx.appendChild(b); }
    var s = document.createElement(full ? 'small' : 'span'); s.textContent = lr.dataset.hint || ''; tx.appendChild(s);
    var w = tip.offsetWidth, h = tip.offsetHeight;
    var x = Math.min(lx + 16, window.innerWidth - w - 8), y = ly + 20;
    if (y + h > window.innerHeight - 8) y = ly - h - 14;
    tip.style.left = Math.max(8, x) + 'px'; tip.style.top = Math.max(8, y) + 'px';
    tip.classList.add('show');
    shown = true; ax = lx; ay = ly;
  }
  document.addEventListener('mousemove', function (e) {
    var r = e.target.closest ? e.target.closest('.mb-row,.trip-row') : null;
    if (!r) { hide(); lr = null; return; }
    if (shown && Math.abs(e.clientX - ax) < 6 && Math.abs(e.clientY - ay) < 6) return;   // tiny jitter: keep it
    hide();
    lr = r; lt = e.target; lx = e.clientX; ly = e.clientY;
    timer = setTimeout(show, 450);   // shown only once the cursor has stopped for a moment
  });
  document.addEventListener('mouseleave', function () { hide(); lr = null; });
  document.addEventListener('dblclick', function () { hide(); lr = null; });
  document.addEventListener('mousedown', hide);
  window.addEventListener('scroll', function () { hide(); lr = null; }, true);
})();

// Download buttons ([data-pdf]): the form is built in a hidden frame and saved as a PDF file.
(function () {
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-pdf]') : null;
    if (!b || b.disabled || !b.getAttribute('data-pdf')) return;
    var u = b.getAttribute('data-pdf'), sp = b.querySelector('span'), label = sp.textContent, f = document.createElement('iframe'), t, fname = u.indexOf('print_crew') === 0 ? 'Crew List.pdf' : 'Manifest.pdf';
    var finish = function (ok) {
      clearTimeout(t); window.removeEventListener('message', on); f.remove();
      b.disabled = false; sp.textContent = label;
      if (!ok) window.open(u, '_blank');   // PDF tool could not load (offline?): show the form instead
    };
    var on = function (ev) {
      if (ev.source !== f.contentWindow || !ev.data || !ev.data.pdf) return;
      if (ev.data.pdf === 'done' && ev.data.blob) {   // save from this page (a hidden frame can not reliably download on phones)
        var url = URL.createObjectURL(ev.data.blob), a = document.createElement('a');
        var d = new Date(), z = function (n) { return (n < 10 ? '0' : '') + n; };   // unique name each time, so the phone does not treat it as already downloaded
        a.href = url; a.download = fname.replace('.pdf', ' ' + d.getFullYear() + '-' + z(d.getMonth() + 1) + '-' + z(d.getDate()) + ' ' + z(d.getHours()) + z(d.getMinutes()) + z(d.getSeconds()) + '.pdf'); a.style.display = 'none'; document.body.appendChild(a); a.click();
        setTimeout(function () { a.remove(); URL.revokeObjectURL(url); }, 60000);
        finish(true);
      } else finish(false);
    };
    b.disabled = true; sp.textContent = 'Preparing…';
    window.addEventListener('message', on);
    f.style.cssText = 'position:fixed;left:-10000px;top:0;width:900px;height:1200px;border:0';
    f.src = u; document.body.appendChild(f);
    t = setTimeout(function () { finish(false); }, 30000);
  });
})();

// Print buttons ([data-print]): load the form in a hidden frame and open the print dialog straight away.
(function () {
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-print]') : null;
    if (!b || !b.getAttribute('data-print')) return;
    var f = document.createElement('iframe');
    f.style.cssText = 'position:fixed;left:-10000px;top:0;width:900px;height:1200px;border:0';
    f.onload = function () { try { f.contentWindow.focus(); f.contentWindow.print(); } catch (x) { window.open(b.getAttribute('data-print'), '_blank'); } };
    f.src = b.getAttribute('data-print'); document.body.appendChild(f);
    setTimeout(function () { f.remove(); }, 120000);
  });
})();

// Bottom tab bar: the tapped icon pops, then the page opens (log out keeps its own confirm).
document.addEventListener('click', function (e) {
  var a = e.target.closest ? e.target.closest('.btabs a') : null;
  if (!a || a.classList.contains('lo') || a.classList.contains('on') || e.defaultPrevented || e.ctrlKey || e.metaKey) return;
  e.preventDefault();
  a.classList.add('pop');
  setTimeout(function () { location.href = a.href; }, 230);
});
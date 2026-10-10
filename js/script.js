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

// Admin header clock: "Sunday, 3 May 2026 - 4.39 PM" (uses the device's own time)
(function () {
  var d = document.getElementById('clk-date'), t = document.getElementById('clk-time');
  if (!d || !t) return;
  var D = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    M = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  function tick() {
    var n = new Date(), h = n.getHours();
    d.textContent = D[n.getDay()] + ', ' + n.getDate() + ' ' + M[n.getMonth()] + ' ' + n.getFullYear();
    t.textContent = ((h % 12) || 12) + '.' + ('0' + n.getMinutes()).slice(-2) + ' ' + (h < 12 ? 'AM' : 'PM');
  }
  tick(); setInterval(tick, 1000);
})();

// Cards fade in only on first load (not on every live refresh)
setTimeout(function () { document.body.classList.remove('enter'); }, 900);

// Depart confirmation modal (replaces the browser's confirm box). It lives outside the live board,
// so it stays open even while the board refreshes behind it.
(function () {
  var box, pending;
  function build() {
    box = document.createElement('div');
    box.className = 'mdl';
    box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
    box.innerHTML = '<div class="mdl-box"><div class="mdl-ic"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M8 7h9v9"/></svg></div>' +
      '<h3></h3><p>This marks the motorbanca as departed and starts the 2-minute countdown on the board.</p>' +
      '<div class="mdl-act"><button type="button" class="no">Cancel</button><button type="button" class="go">Depart</button></div></div>';
    document.body.appendChild(box);
    box.addEventListener('click', function (e) { if (e.target === box || e.target.classList.contains('no')) close(); });
    box.querySelector('.go').addEventListener('click', function () {
      this.disabled = true;
      var f = document.createElement('form'), i = document.createElement('input');
      f.method = 'post'; f.action = pending.action;
      i.type = 'hidden'; i.name = 'mid'; i.value = pending.mid;
      f.appendChild(i); document.body.appendChild(f); f.submit();
    });
  }
  function close() { if (box) box.classList.remove('open'); }
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.depart') : null;
    if (!b) return;
    e.preventDefault();
    var f = b.closest('form');
    pending = { action: f.action, mid: f.querySelector('[name=mid]').value };
    if (!box) build();
    box.querySelector('h3').textContent = 'Depart ' + (f.dataset.name || 'this motorbanca') + '?';
    box.querySelector('.go').disabled = false;
    void box.offsetWidth;
    box.classList.add('open');
    box.querySelector('.go').focus();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();

// Trip history: double-click a row to see that trip's passengers
(function () {
  var box;
  function build() {
    box = document.createElement('div');
    box.className = 'mdl';
    box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
    box.innerHTML = '<div class="mdl-box wide"><div class="mdl-head"><div><h3></h3><p class="sub"></p></div>' +
      '<button type="button" class="x" aria-label="Close">&times;</button></div><div class="mdl-body"></div></div>';
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
    box.querySelector('.mdl-body').innerHTML = '';
    void box.offsetWidth;
    box.classList.add('open');
    fetch(r.dataset.url, { credentials: 'same-origin' })
      .then(function (x) { return x.ok ? x.json() : Promise.reject(); })
      .then(function (d) {
        box.querySelector('h3').textContent = d.title;
        box.querySelector('.sub').textContent = d.sub;
        box.querySelector('.mdl-body').innerHTML = d.html;   // built and escaped on the server
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
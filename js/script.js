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
  var D = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
      M = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  function tick() {
    var n = new Date(), h = n.getHours();
    d.textContent = D[n.getDay()] + ', ' + n.getDate() + ' ' + M[n.getMonth()] + ' ' + n.getFullYear();
    t.textContent = ((h % 12) || 12) + '.' + ('0' + n.getMinutes()).slice(-2) + ' ' + (h < 12 ? 'AM' : 'PM');
  }
  tick(); setInterval(tick, 1000);
})();

// Admin menu: the highlight slides to the clicked link, then the page opens.
// The 'enter' class only lets cards fade in on first load (not on every live refresh).
(function () {
  setTimeout(function () { document.body.classList.remove('enter'); }, 1000);
  var nav = document.querySelector('.pills'); if (!nav) return;
  var bg = nav.querySelector('.pill-bg'), links = nav.querySelectorAll('a'), cur = nav.querySelector('a.on');
  if (!bg || !cur) return;
  function place(a, animate) {
    bg.style.transition = animate ? '' : 'none';
    bg.style.width = a.offsetWidth + 'px';
    bg.style.transform = 'translateX(' + a.offsetLeft + 'px)';
    if (!animate) { void bg.offsetWidth; bg.style.transition = ''; }
  }
  nav.classList.add('has-bg');
  place(cur, false);
  window.addEventListener('resize', function () { place(cur, false); });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { place(cur, false); });
  var still = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  links.forEach(function (a) {
    a.addEventListener('click', function (e) {
      if (a === cur || still || window.innerWidth < 1000 || e.ctrlKey || e.metaKey || e.shiftKey || e.button) return;
      e.preventDefault();
      cur = a; place(a, true);
      links.forEach(function (l) { l.classList.toggle('on', l === a); });
      setTimeout(function () { location.href = a.href; }, 280);
    });
  });
})();
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

// 5-minute departure countdown (the server value is re-synced on every refresh)
setInterval(function () {
  document.querySelectorAll('.cd').forEach(function (e) {
    var s = Math.max(0, (+e.dataset.s) - 1);
    e.dataset.s = s;
    e.textContent = Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
  });
}, 1000);
document.querySelectorAll('[data-live]').forEach(function (el) {
  function load() {
    fetch(el.dataset.live, { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(); })
      .then(function (html) { el.innerHTML = html; el.classList.remove('offline'); })
      .catch(function () { el.classList.add('offline'); });
  }
  setInterval(load, 4000);
});

setInterval(function () {
  document.querySelectorAll('.cd').forEach(function (e) {
    var s = Math.max(0, (+e.dataset.s) - 1);
    e.dataset.s = s;
    e.textContent = Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
  });
}, 1000);

(function () {
  var d = document.getElementById('clk-date'), t = document.getElementById('clk-time');
  if (!d || !t) return;
  var D = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    M = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var short = window.matchMedia('(max-width:1399px)');
  var noDay = document.body.classList.contains('r-operator');
  function tick() {
    var n = new Date(), h = n.getHours();

    var wd = noDay ? '' : (short.matches ? D[n.getDay()].slice(0, 3) : D[n.getDay()]) + ', ';
    d.textContent = wd + n.getDate() + ' ' + (short.matches ? M[n.getMonth()].slice(0, 3) : M[n.getMonth()]) + ' ' + n.getFullYear();
    t.textContent = ((h % 12) || 12) + ':' + ('0' + n.getMinutes()).slice(-2) + ' ' + (h < 12 ? 'AM' : 'PM');
  }
  tick(); setInterval(tick, 1000);
})();

setTimeout(function () { document.body.classList.remove('enter'); }, 900);

(function () {
  var step = 1, rtrip = 0, rbox, rbody, rerr, rsub, rnext, pbox, dbox, pbody, perr, pcount, psub, pgo, url, mid, started, busy;
  var PRINT = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6M7 17H5a1 1 0 01-1-1v-5a2 2 0 012-2h12a2 2 0 012 2v5a1 1 0 01-1 1h-2M7 14h10v7H7z"/></svg>';

  var EXPAND = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>';
  var CHL = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>';
  var CHR = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>';
  var CLOSE = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>';

  function make(html) {
    var m = document.createElement('div');
    m.className = 'mdl';
    m.setAttribute('role', 'dialog'); m.setAttribute('aria-modal', 'true');
    m.innerHTML = html;
    document.body.appendChild(m);
    return m;
  }
  function openM(m) { void m.offsetWidth; m.classList.add('open'); }
  function closeM(m) { m.classList.remove('open'); }
  function show(el, t) { el.textContent = t || ''; el.hidden = !t; }

  function draftKey() { return 'batis_depart_' + mid; }
  function readFields(body) {
    var o = {};
    body.querySelectorAll('input,select,textarea').forEach(function (i) {
      if (!i.name) return;
      if (i.type === 'radio' || i.type === 'checkbox') { if (i.checked) o[i.name] = i.value; }
      else if (i.value) o[i.name] = i.value;
    });
    return o;
  }
  function writeFields(body, o) {
    Object.keys(o).forEach(function (k) {
      body.querySelectorAll('[name="' + k.replace(/"/g, '\"') + '"]').forEach(function (i) {
        if (i.type === 'radio' || i.type === 'checkbox') i.checked = (i.value === o[k]); else i.value = o[k];
      });
    });
  }
  function saveDraft() {
    if (!pbody || !pbody.firstChild) return;
    try { localStorage.setItem(draftKey(), JSON.stringify({ trip: rtrip, step: step, p: readFields(pbody) })); } catch (x) { }
  }
  function loadDraft() {
    try { var j = JSON.parse(localStorage.getItem(draftKey())); return j && j.trip === rtrip ? j : null; } catch (x) { return null; }
  }
  function clearDraft() { try { localStorage.removeItem(draftKey()); } catch (x) { } }

  function goStep(n) {
    [rbox, pbox].forEach(closeM);
    openM(n === 2 ? pbox : rbox);
    step = n; saveDraft();
  }
  function tally(body, out) {
    var all = body.querySelectorAll('.md-q');
    out.textContent = body.querySelectorAll('.md-q input:checked').length + ' of ' + all.length + ' answered';
  }

  function firstMissing(body) {
    var first = null;
    body.querySelectorAll('.md-q').forEach(function (q) {
      var miss = !q.querySelector('input:checked');
      q.classList.toggle('miss', miss);
      if (miss && !first) first = q;
    });
    return first;
  }
  function clearOnChange(m, body, out) {
    m.addEventListener('change', function (e) {
      if (e.target.type === 'radio') {
        e.target.closest('.md-q').classList.remove('miss'); tally(body, out);
        var er = m.querySelector('.md-err');
        if (er && body.querySelectorAll('.md-q').length === body.querySelectorAll('.md-q input:checked').length) show(er, '');
      }
    });
  }

  function build() {

    rbox = make('<div class="mdl-box wide md-box md-rv"><div class="mdl-head"><div class="mdl-title"><h3>Crew List, Manifest and MDSD</h3><p class="sub"></p></div>' +
      '<div class="mdl-tools"><button type="button" class="x" aria-label="Close">&times;</button></div></div>' +
      '<div class="mdl-body md-body"></div><div class="alert bad md-err" hidden></div>' +
      '<div class="md-foot"><span class="md-count"></span><button type="button" class="btn sm ghost md-cancel">Cancel</button><button type="button" class="btn sm md-next md-go-wide" disabled>Continue to PDIC</button></div></div>');
    rbody = rbox.querySelector('.md-body'); rerr = rbox.querySelector('.md-err'); rsub = rbox.querySelector('.sub'); rnext = rbox.querySelector('.md-next');
    rbox.addEventListener('click', function (e) {
      var tb = e.target.closest('.rv-tabs button');
      if (tb) { showDoc(tb.getAttribute('data-t')); return; }
      if (e.target.closest('.rv-fs')) { lbOpen(); return; }
      if (e.target.classList.contains('x') || e.target.classList.contains('md-cancel')) closeM(rbox);
      else if (e.target.classList.contains('md-next') && !rnext.disabled) goStep(2);
    });

    pbox = make('<div class="mdl-box wide md-box"><div class="mdl-head"><div class="mdl-title"><h3>Pre-Departure Inspection Checklist</h3><p class="sub"></p></div>' +
      '<div class="mdl-tools"><button type="button" class="x" aria-label="Close">&times;</button></div></div>' +
      '<form class="md-form" novalidate><div class="mdl-body md-body"></div><div class="alert bad md-err" hidden></div>' +
      '<div class="md-foot"><span class="md-count"></span><button type="button" class="btn sm ghost md-prev md-pair">Back</button><button type="submit" class="btn sm md-go md-go-wide md-pair">Depart motorbanca</button></div></form></div>');
    pbody = pbox.querySelector('.md-body'); perr = pbox.querySelector('.md-err'); pcount = pbox.querySelector('.md-count'); psub = pbox.querySelector('.sub'); pgo = pbox.querySelector('.md-go');
    clearOnChange(pbox, pbody, pcount);
    pbox.addEventListener('click', function (e) {
      if (e.target.classList.contains('md-prev')) goStep(1);
      else if (e.target.classList.contains('x')) { closeM(pbox); saveDraft(); }
    });
    ['input', 'change'].forEach(function (ev) { pbox.addEventListener(ev, saveDraft); });
    pbox.addEventListener('input', function (e) { if (e.target.name === 'inspector') e.target.closest('.md-ins').classList.remove('miss'); });
    pbox.querySelector('form').addEventListener('submit', submit);

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (dbox && dbox.classList.contains('open')) location.reload();
      else if (pbox.classList.contains('open')) goStep(1);
      else if (rbox.classList.contains('open')) closeM(rbox);
    });
  }

  function showDoc(kind) {
    var fr = rbody.querySelector('.rv-frame'); if (!fr || !rtrip) return;
    rbody.querySelectorAll('.rv-tabs button').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-t') === kind); });
    fr.style.visibility = 'hidden';
    fr.onload = function () { fitDoc(); fr.style.visibility = 'visible'; };
    fr.src = docUrl(kind);
  }

  function docUrl(kind) {
    return (kind === 'mdsd_print' ? '../admin/' : '../operator/') + kind + '.php?trip=' + rtrip + '&print=1';
  }

  function docTabs() {
    return [].slice.call(rbody.querySelectorAll('.rv-tabs button'));
  }

  function docIndex(kind) {
    var tabs = docTabs(), i = 0;
    tabs.forEach(function (b, n) { if (b.getAttribute('data-t') === kind) i = n; });
    return i;
  }

  var lb = null, lbFrame = null, lbKind = '';

  function lbOpen() {
    var cur = rbody.querySelector('.rv-tabs button.on'); if (!cur) return;
    if (!lb) {
      lb = document.createElement('div');
      lb.className = 'rv-lb';
      lb.setAttribute('role', 'dialog');
      lb.setAttribute('aria-modal', 'true');
      lb.innerHTML = '<span class="rv-lb-name"></span><button type="button" class="rv-lb-x" aria-label="Close">' + CLOSE + '</button>' +
        '<button type="button" class="rv-lb-prev" aria-label="Previous document">' + CHL + '</button>' +
        '<div class="rv-lb-stage"><iframe class="rv-lb-frame" title="Document" scrolling="no"></iframe></div>' +
        '<button type="button" class="rv-lb-next" aria-label="Next document">' + CHR + '</button>';
      document.body.appendChild(lb);
      lbFrame = lb.querySelector('.rv-lb-frame');
      lb.addEventListener('click', function (e) {
        if (e.target.closest('.rv-lb-x') || e.target === lb || e.target.classList.contains('rv-lb-stage')) lbClose();
        else if (e.target.closest('.rv-lb-prev')) lbStep(-1);
        else if (e.target.closest('.rv-lb-next')) lbStep(1);
      });
    }
    lb.classList.add('open');
    lbShow(cur.getAttribute('data-t'));
  }

  function lbClose() {
    if (!lb) return;
    lb.classList.remove('open');
    if (lbKind) showDoc(lbKind);
  }

  function lbShow(kind) {
    var tabs = docTabs(), i = docIndex(kind);
    lbKind = kind;
    lb.querySelector('.rv-lb-name').textContent = tabs[i].textContent + '  \u00b7  ' + (i + 1) + ' of ' + tabs.length;
    lb.querySelector('.rv-lb-prev').disabled = i === 0;
    lb.querySelector('.rv-lb-next').disabled = i === tabs.length - 1;
    lbFrame.style.visibility = 'hidden';
    lbFrame.onload = function () { lbFit(); lbFrame.style.visibility = 'visible'; };
    lbFrame.src = docUrl(kind);
  }

  function lbStep(d) {
    var tabs = docTabs(), i = docIndex(lbKind) + d;
    if (i >= 0 && i < tabs.length) lbShow(tabs[i].getAttribute('data-t'));
  }

  function lbFit() {
    var st = lb.querySelector('.rv-lb-stage'), w = 816, h = 1056;
    try { var d = lbFrame.contentDocument.documentElement; w = Math.max(d.scrollWidth, 816); h = Math.max(d.scrollHeight, 1056); } catch (x) { }
    lbFrame.style.width = w + 'px'; lbFrame.style.height = h + 'px';
    var k = Math.min((window.innerWidth - 240) / w, (window.innerHeight - 96) / h);
    lbFrame.style.transform = 'scale(' + k + ')';
    st.style.width = Math.round(w * k) + 'px'; st.style.height = Math.round(h * k) + 'px';
  }

  function fitDoc() {
    var st = rbody && rbody.querySelector('.rv-stage'), fr = st && st.querySelector('.rv-frame'); if (!fr) return;
    var w = 816, h = 1056;
    try { var d = fr.contentDocument.documentElement; w = Math.max(d.scrollWidth, 816); h = Math.max(d.scrollHeight, 1056); } catch (x) { }
    fr.style.width = w + 'px'; fr.style.height = h + 'px';
    st.style.width = '';
    var k = Math.min(st.clientWidth / w, Math.max(320, window.innerHeight * 0.62) / h);
    st.style.height = Math.round(h * k) + 'px';
    fr.style.transform = 'scale(' + k + ')';
    st.style.width = Math.round(w * k) + 'px'; st.style.margin = '0 auto';
    fr.style.left = '0px';
  }
  window.addEventListener('resize', fitDoc);
  window.addEventListener('resize', function () { if (lb && lb.classList.contains('open')) lbFit(); });
  document.addEventListener('keydown', function (e) {
    if (!lb || !lb.classList.contains('open')) return;
    if (e.key === 'Escape') { e.preventDefault(); e.stopImmediatePropagation(); lbClose(); }
    else if (e.key === 'ArrowRight') { e.preventDefault(); lbStep(1); }
    else if (e.key === 'ArrowLeft') { e.preventDefault(); lbStep(-1); }
  });

  function submit(e) {
    e.preventDefault(); if (busy) return;
    var first = firstMissing(pbody), ins = pbody.querySelector('[name=inspector]'), name = ins.value.trim();
    ins.closest('.md-ins').classList.toggle('miss', !name);
    if (first || !name) {
      show(perr, first ? 'Please answer every item' : 'Please enter the name of the inspector.');
      (first || ins.closest('.md-ins')).scrollIntoView({ block: 'center', behavior: 'smooth' });
      return;
    }
    show(perr, ''); busy = true; pgo.disabled = true; pgo.textContent = 'Departing\u2026';
    var d = new FormData();
    d.append('mid', mid); d.append('ajax', '1'); d.append('started', started); d.append('inspector', name);
    pbody.querySelectorAll('input[type=radio]:checked').forEach(function (i) { d.append(i.name, i.value); });
    pbody.querySelectorAll('input.md-rm').forEach(function (i) { if (i.value.trim()) d.append(i.name, i.value); });
    function fail(t) { show(perr, t); busy = false; pgo.disabled = false; pgo.textContent = 'Depart motorbanca'; }
    fetch(url, { method: 'POST', body: d, credentials: 'same-origin' })
      .then(function (x) { return x.json(); })
      .then(function (j) { if (j.ok) done(j.trip); else fail(j.error || 'Could not depart the boat.'); })
      .catch(function () { fail('Could not reach the server. Check your connection and try again.'); });
  }

  function done(trip) {
    clearDraft(); closeM(pbox);
    if (!trip) { location.reload(); return; }
    dbox = make('<div class="mdl-box wide md-done"><div class="mdl-head"><div class="mdl-title"><h3>Motorbanca departed</h3><p class="sub">Do you want to print the documents of this trip? You can also print them later from Trip History.</p></div></div>' +
      '<div class="mdl-body md-docs">' +
      '<div class="md-doc"><div><b>MDSD</b><span>Master\u2019s Declaration of Safe Departure</span></div><button type="button" class="btn sm ghost pdfbtn" data-print="mdsd_print.php?trip=' + trip + '&print=1">' + PRINT + '<span>Print</span></button></div>' +
      '<div class="md-doc"><div><b>PDIC</b><span>Pre-Departure Inspection Checklist</span></div><button type="button" class="btn sm ghost pdfbtn" data-print="pdic_print.php?trip=' + trip + '&print=1">' + PRINT + '<span>Print</span></button></div>' +
      '</div><div class="md-foot"><button type="button" class="btn sm ghost md-close">Close</button></div></div>');
    dbox.addEventListener('click', function (e) { if (e.target.classList.contains('md-close')) location.reload(); });
    openM(dbox);
  }

  function openBoat(id, u) {
    mid = id;
    url = u;
    if (!pbox) build();
    busy = false; pgo.disabled = false; pgo.textContent = 'Depart motorbanca'; show(perr, '');
    step = 1; rtrip = 0; rsub.textContent = 'Loading\u2026'; rbody.innerHTML = ''; rnext.disabled = true; show(rerr, '');
    pbody.innerHTML = ''; pcount.textContent = '';
    openM(rbox);
    fetch(url + '?ajax=1&mid=' + encodeURIComponent(mid), { credentials: 'same-origin' })
      .then(function (x) { return x.json(); })
      .then(function (d) {
        if (d.ok === false) { rsub.textContent = ''; show(rerr, d.error); return; }
        psub.textContent = d.sub; rsub.textContent = d.sub;
        rtrip = d.trip || 0;
        rbody.innerHTML = rtrip ? '<div class="rv-tabs"><button type="button" class="on" data-t="print_crew">Crew list</button><button type="button" data-t="print">Manifest</button>' + (d.mdsd ? '<button type="button" data-t="mdsd_print">MDSD</button>' : '') + '</div><div class="rv-stage"><iframe class="rv-frame" title="Document preview" scrolling="no"></iframe><button type="button" class="rv-fs" aria-label="Full screen">' + EXPAND + '</button></div>' : '<div class="alert bad">No trip was found for this boat.</div>';
        if (rtrip) showDoc('print_crew');
        rnext.disabled = false;
        pbody.innerHTML = d.pdic + '<div class="md-ins"><label for="pd-ins">Name of inspector</label><input id="pd-ins" name="inspector" type="text" maxlength="100" autocomplete="off"></div>';
        started = d.started;
        var dr = loadDraft();
        if (dr) writeFields(pbody, dr.p || {});
        tally(pbody, pcount);
        if (dr && dr.step > 1) goStep(2);
      })
      .catch(function () { rsub.textContent = ''; show(rerr, 'Could not load the checklist.'); });
  }

  function open(f) {
    openBoat(f.querySelector('[name=mid]').value, f.action.replace(/depart\.php.*$/, 'mdsd.php'));
  }
  window.batisDepart = openBoat;

  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.depart') : null;
    if (!b || b.disabled) return;
    e.preventDefault();
    open(b.closest('form'));
  });
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches || !f.matches('form[action$="depart.php"]')) return;
    e.preventDefault();
    open(f);
  }, true);
})();

(function () {
  var box;

  function docBtn(label, kind, off) {
    return '<button type="button" class="btn sm ghost pdfbtn" data-print="" data-kind="' + kind + '"' + (off ? ' disabled' : '') + '><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6M7 17H5a1 1 0 01-1-1v-5a2 2 0 012-2h12a2 2 0 012 2v5a1 1 0 01-1 1h-2M7 14h10v7H7z"/></svg><span>' + label + '</span></button>';
  }
  function build() {
    box = document.createElement('div');
    box.className = 'mdl';
    box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
    box.innerHTML = '<div class="mdl-box wide"><div class="mdl-head"><div class="mdl-title"><h3></h3><p class="sub"></p></div>' +
      '<div class="mdl-tools">' + docBtn('Crew list', 'print_crew', false) + docBtn('Manifest', 'print', false) + docBtn('MDSD', 'admin/mdsd_print', true) + docBtn('PDIC', 'admin/pdic_print', true) +
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
    box.querySelectorAll('[data-print]').forEach(function (b) {
      var k = b.getAttribute('data-kind');
      b.setAttribute('data-print', r.dataset.url.replace('admin/passengers.php', (k.indexOf('/') < 0 ? 'operator/' : '') + k + '.php') + '&print=1');
    });
    var mb = box.querySelector('[data-kind="admin/mdsd_print"]'), pb = box.querySelector('[data-kind="admin/pdic_print"]');
    mb.disabled = true; mb.title = ''; pb.disabled = true; pb.title = '';
    box.querySelector('.mdl-body').innerHTML = '';
    void box.offsetWidth;
    box.classList.add('open');
    fetch(r.dataset.url, { credentials: 'same-origin' })
      .then(function (x) { return x.ok ? x.json() : Promise.reject(); })
      .then(function (d) {
        box.querySelector('h3').textContent = d.title;
        box.querySelector('.sub').textContent = d.sub;
        box.querySelector('.mdl-body').innerHTML = d.html;
        mb.disabled = !d.mdsd; mb.title = d.mdsd ? '' : 'No MDSD was made for this trip';
        pb.disabled = !d.pdic; pb.title = d.pdic ? '' : 'No PDIC was made for this trip';
      })
      .catch(function () { box.querySelector('.sub').textContent = 'Could not load the passengers.'; });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box) box.classList.remove('open'); });
})();

(function () {
  var m = document.getElementById('edit-mdl'); if (!m) return;
  var f = document.getElementById('edit-form'), err = document.getElementById('edit-err'), sub = document.getElementById('edit-sub'), busy = false;
  function close() { m.classList.remove('open'); }
  function el(n) { return f.elements[n]; }
  document.addEventListener('dblclick', function (e) {
    var r = e.target.closest ? e.target.closest('.mb-row') : null; if (!r) return;
    if (window.getSelection) window.getSelection().removeAllRanges();
    el('mid').value = r.dataset.id; el('name').value = r.dataset.name; el('owner').value = r.dataset.owner;
    el('address').value = r.dataset.address || ''; el('contact').value = r.dataset.contact || '';
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
    var r = e.target.closest ? e.target.closest('.mb-row,.trip-row,.th-row[data-hint]') : null;
    if (!r) { hide(); lr = null; return; }
    if (shown && Math.abs(e.clientX - ax) < 6 && Math.abs(e.clientY - ay) < 6) return;
    hide();
    lr = r; lt = e.target; lx = e.clientX; ly = e.clientY;
    timer = setTimeout(show, 450);
  });
  document.addEventListener('mouseleave', function () { hide(); lr = null; });
  document.addEventListener('dblclick', function () { hide(); lr = null; });
  document.addEventListener('mousedown', hide);
  window.addEventListener('scroll', function () { hide(); lr = null; }, true);
})();

(function () {
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-pdf]') : null;
    if (!b || b.disabled || !b.getAttribute('data-pdf')) return;
    var u = b.getAttribute('data-pdf'), sp = b.querySelector('span'), label = sp.textContent, f = document.createElement('iframe'), t, fname = u.indexOf('print_crew') >= 0 ? 'Crew List.pdf' : u.indexOf('mdsd_print') >= 0 ? 'MDSD.pdf' : u.indexOf('pdic_print') >= 0 ? 'PDIC.pdf' : 'Manifest.pdf';
    var finish = function (ok) {
      clearTimeout(t); window.removeEventListener('message', on); f.remove();
      b.disabled = false; sp.textContent = label;
      if (!ok) window.open(u, '_blank');
    };
    var on = function (ev) {
      if (ev.source !== f.contentWindow || !ev.data || !ev.data.pdf) return;
      if (ev.data.pdf === 'done' && ev.data.blob) {
        var url = URL.createObjectURL(ev.data.blob), a = document.createElement('a');
        var d = new Date(), z = function (n) { return (n < 10 ? '0' : '') + n; };
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

document.addEventListener('click', function (e) {
  var a = e.target.closest ? e.target.closest('.btabs a') : null;
  if (!a || a.classList.contains('lo') || a.classList.contains('on') || e.defaultPrevented || e.ctrlKey || e.metaKey) return;
  e.preventDefault();
  a.classList.add('pop');
  setTimeout(function () { location.href = a.href; }, 230);
});
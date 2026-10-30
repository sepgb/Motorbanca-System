// Track my ride: check the manifest, choose a destination barangay, follow the live location on a map.
// The page remembers where you are (on this device only), so a refresh does not start over.
(function () {
    var root = document.getElementById('trk'); if (!root) return;
    var $ = function (id) { return document.getElementById(id); };
    var s1 = $('s1'), s2 = $('s2'), s3 = $('s3'), KEY = 'trkState', TTL = 6 * 3600 * 1000;
    var data = null, who = null, map, me, meAcc, dst, line, watch = null, dest = null, here = null, fitted = false, moved = false, follow = false, destName = '', hiAcc = true, route = [], stops = null, token = 0;

    if (!window.isSecureContext) { $('secgo').href = 'https://' + location.hostname + location.pathname; $('sec').hidden = false; }
    function show(el, msg) { el.textContent = msg; el.hidden = !msg; }
    // a notice with a bold title and a short explanation under it
    function note(el, title, text) {
        el.textContent = '';
        var b = document.createElement('b'), t = document.createElement('span');
        b.textContent = title; t.textContent = text;
        el.appendChild(b); if (text) el.appendChild(t); el.hidden = false;
    }
    function step(n) { s1.hidden = n !== 1; s2.hidden = n !== 2; s3.hidden = n !== 3; window.scrollTo(0, 0); }
    function save(st) { try { localStorage.setItem(KEY, JSON.stringify({ t: Date.now(), who: who, dest: destName, step: st })); } catch (x) { } }
    function load() { try { var o = JSON.parse(localStorage.getItem(KEY)); return o && Date.now() - o.t < TTL ? o : null; } catch (x) { return null; } }
    function clear() { try { localStorage.removeItem(KEY); } catch (x) { } }

    function check(w) {
        var f = new FormData(); f.append('name', w.name); f.append('age', w.age); f.append('barangay', w.barangay);
        return fetch(root.dataset.api, { method: 'POST', body: f }).then(function (r) { return r.json(); });
    }
    function fillStep2(j, pick) {
        data = j; $('fb').textContent = 'You are on ' + j.boat; $('fc').textContent = j.cluster; $('cancel').hidden = false;   // Cancel trip is shown to everyone; only people who joined by scanning can really cancel (the others are told to ask the operator)
        var sel = $('dest'); sel.innerHTML = '';
        j.barangays.forEach(function (x, i) { var o = document.createElement('option'); o.value = i; o.textContent = x.name; if (pick && x.name === pick) o.selected = true; sel.appendChild(o); });
        show($('e2'), '');
    }

    // ---- 1: check the manifest
    s1.addEventListener('submit', function (ev) {
        ev.preventDefault(); show($('e1'), ''); show($('ok1'), '');
        var b = $('b1'); b.disabled = true; b.textContent = 'Checking…';
        var fd = new FormData(s1); var w = { name: fd.get('name'), age: fd.get('age'), barangay: fd.get('barangay') };
        check(w).then(function (j) {
            b.disabled = false; b.textContent = 'Check';
            if (!j.ok) return show($('e1'), j.error || 'Something went wrong. Try again.');
            if (!j.found) return note($('e1'), 'We could not find you on a boat that is on the queue', '');
            who = w; destName = ''; fillStep2(j); save(2); step(2);
        }).catch(function () { b.disabled = false; b.textContent = 'Check'; show($('e1'), 'Could not reach the server. Check your connection and try again.'); });
    });
    $('back1').addEventListener('click', function () { clear(); step(1); });

    // ---- 2: pick destination, ask for location (the tap lets the browser show its permission question)
    $('go').addEventListener('click', function () {
        show($('e2'), '');
        if (!window.isSecureContext || !navigator.geolocation) return show($('e2'), 'Your browser only shares location on a secure (https) page. Use the https link at the top of this page.');
        startTrip(data.barangays[+$('dest').value]);
    });

    function geocode(name) {
        return fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(name + ', Binangonan, Rizal, Philippines'))
            .then(function (r) { return r.json(); }).then(function (a) { return a && a[0] ? [parseFloat(a[0].lat), parseFloat(a[0].lon)] : null; }).catch(function () { return null; });
    }
    function km(a, b) {
        var R = 6371, t = Math.PI / 180, dLa = (b[0] - a[0]) * t, dLo = (b[1] - a[1]) * t;
        var h = Math.sin(dLa / 2) * Math.sin(dLa / 2) + Math.cos(a[0] * t) * Math.cos(b[0] * t) * Math.sin(dLo / 2) * Math.sin(dLo / 2);
        return 2 * R * Math.asin(Math.sqrt(h));
    }
    function fmt(d) { return d < 1 ? Math.round(d * 1000) + ' m' : d.toFixed(1) + ' km'; }
    function status(msg, bad) { var t = $('ts'); t.textContent = msg; t.hidden = !msg; t.className = 'alert' + (bad ? ' bad' : ''); }

    // ---- 3: live map
    function startTrip(d) {
        destName = d.name; save(3); fitted = false; moved = false; follow = false; $('center').classList.remove('on'); here = null; hiAcc = true; route = []; token++; if (stops) stops.clearLayers();
        $('td').textContent = d.name + (d.pier ? ' pier' : ''); $('tk').textContent = '…'; show($('e3'), ''); $('retry').hidden = true;
        step(3); status('Finding your location…');
        if (!map) {
            map = L.map('map').setView([14.4647, 121.192], 12);   // Binangonan, until we know more
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
            // the map fills the screen on phones: keep Leaflet in step whenever the box changes size, so the tiles cover all of it
            var resized = function () { map.invalidateSize(); if (!moved && !follow && here && dest) fitAll(); };   // re-frame both points once the map has its real size
            if (window.ResizeObserver) new ResizeObserver(resized).observe($('map'));
            window.addEventListener('resize', resized);
            $('map').addEventListener('pointerdown', function (e) {   // the passenger is looking around: stop re-framing and following
                if (e.target.closest && e.target.closest('.leaflet-control')) return;
                moved = true; follow = false; $('center').classList.remove('on');
            });
        }
        [80, 300, 800].forEach(function (ms) { setTimeout(function () { map.invalidateSize(); if (!moved && !follow && here && dest) fitAll(); }, ms); });
        var set = function (pos) {
            dest = pos;
            if (!pos) { show($('e3'), 'The map spot for ' + d.name + ' is not available yet, so only your location is shown.'); $('tk').textContent = '—'; return; }
            if (dst) map.removeLayer(dst);
            dst = L.marker(pos, { icon: L.divIcon({ className: 'trk-pin', html: '<svg viewBox="0 0 24 32" width="30" height="40"><path d="M12 0C5.4 0 0 5.2 0 11.7 0 20.3 12 32 12 32s12-11.7 12-20.3C24 5.2 18.6 0 12 0z" fill="#f26b1d"/><circle cx="12" cy="11.5" r="4.5" fill="#fff"/></svg>', iconSize: [30, 40], iconAnchor: [15, 40] }) }).addTo(map).bindTooltip(d.name, { direction: 'top', offset: [0, -36] });
            if (!here) map.setView(pos, 14);
            refresh();
        };
        if (d.pier) set(d.pier); else geocode(d.name).then(set);
        buildRoute(d);
        begin();
    }
    // The route only runs through the barangays of this cluster, in the order they are listed, up to the chosen destination.
    function cached(name) { try { return (JSON.parse(localStorage.getItem('trkGeo')) || {})[name] || null; } catch (x) { return null; } }
    function remember(name, pos) { try { var o = JSON.parse(localStorage.getItem('trkGeo')) || {}; o[name] = pos; localStorage.setItem('trkGeo', JSON.stringify(o)); } catch (x) { } }
    function where(b) {
        if (b.pier) return Promise.resolve(b.pier);
        var c = cached(b.name); if (c) return Promise.resolve(c);
        return new Promise(function (ok) { setTimeout(ok, 1100); }).then(function () { return geocode(b.name); }).then(function (pos) { if (pos) remember(b.name, pos); return pos; });
    }
    function buildRoute(d) {
        var my = token, list = data.barangays.slice(0, data.barangays.indexOf(d) + 1), pts = [], i = 0;
        if (!stops) stops = L.layerGroup().addTo(map);
        (function next() {
            if (my !== token) return;
            if (i >= list.length) { route = pts.map(function (x) { return x.pos; }); refresh(); return; }
            var b = list[i++];
            where(b).then(function (pos) {
                if (my !== token) return;
                if (pos) { pts.push({ name: b.name, pos: pos }); L.circleMarker(pos, { radius: 5, color: '#f26b1d', weight: 2, fillColor: '#fff', fillOpacity: 1 }).addTo(stops).bindTooltip(b.name); route = pts.map(function (x) { return x.pos; }); refresh(); }
                next();
            });
        })();
    }
    function begin() {
        if (watch !== null) navigator.geolocation.clearWatch(watch);
        $('retry').hidden = true;
        watch = navigator.geolocation.watchPosition(function (p) { status(''); moveMe(p); refresh(); }, onErr,
            { enableHighAccuracy: hiAcc, maximumAge: hiAcc ? 0 : 15000, timeout: hiAcc ? 25000 : 20000 });
    }
    function onErr(err) {
        if (err.code === 1) {   // refused
            status('Location is blocked for this page. Tap the lock icon in the address bar, allow Location, then tap Try again.', true);
        } else if (hiAcc) {     // GPS is slow or unavailable: use the quicker network location instead
            hiAcc = false; status('Still looking for your location…'); return begin();
        } else {
            status(err.code === 2 ? 'Your phone could not work out where you are. Turn on Location (GPS) in the phone settings, then tap Try again.' : 'Finding your location is taking too long. Move near a window or outdoors, then tap Try again.', true);
        }
        if (watch !== null) navigator.geolocation.clearWatch(watch); watch = null; $('retry').hidden = false;
    }
    $('retry').addEventListener('click', function () { hiAcc = true; status('Finding your location…'); begin(); });
    function moveMe(p) {
        here = [p.coords.latitude, p.coords.longitude];
        if (!me) {
            meAcc = L.circle(here, { radius: p.coords.accuracy || 20, color: '#2563eb', weight: 1, fillOpacity: .12 }).addTo(map);
            me = L.circleMarker(here, { radius: 8, color: '#fff', weight: 3, fillColor: '#2563eb', fillOpacity: 1 }).addTo(map).bindTooltip('You');
        } else { me.setLatLng(here); meAcc.setLatLng(here).setRadius(p.coords.accuracy || 20); }
        if (follow) map.panTo(here);   // "Your location" is on: the map stays on the passenger as they move
    }
    function path() {   // from you, along the cluster's barangays, to the destination
        var r = route.length ? route.slice() : (dest ? [dest] : []);
        if (!here || !r.length) return [];
        var n = 0; r.forEach(function (p, k) { if (km(here, p) < km(here, r[n])) n = k; });
        return [here].concat(r.slice(n));
    }
    function refresh() {
        if (!here) return;
        var pth = path();
        if (pth.length > 1) {
            var tot = 0; for (var k = 1; k < pth.length; k++) tot += km(pth[k - 1], pth[k]);
            $('tk').textContent = fmt(tot);
            if (line) line.setLatLngs(pth); else line = L.polyline(pth, { color: '#f26b1d', weight: 5, opacity: 1, lineJoin: 'round' }).addTo(map);
            if (!fitted) { fitted = true; fitAll(); }
        } else if (!fitted) { fitted = true; map.setView(here, 16); }
    }
    // frame your location and the destination together; on phones the cards and buttons float over the map, so keep both points clear of them
    function fitAll() {
        var p = path();
        if (p.length > 1) {
            var phone = window.matchMedia('(max-width:999px)').matches;
            map.fitBounds(L.latLngBounds(p), phone ? { paddingTopLeft: [40, 170], paddingBottomRight: [40, 190], maxZoom: 17 } : { padding: [60, 60], maxZoom: 17 });
        } else if (here) map.setView(here, 16);
    }
    // "Your location": focus on the passenger and keep following until they move the map themselves
    $('center').addEventListener('click', function () {
        follow = true; moved = false; $('center').classList.add('on');
        if (here) map.setView(here, 17);
    });
    $('stop').addEventListener('click', function () {
        if (watch !== null) navigator.geolocation.clearWatch(watch); watch = null;
        if (me) { map.removeLayer(me); map.removeLayer(meAcc); me = meAcc = null; }
        if (dst) { map.removeLayer(dst); dst = null; } if (line) { map.removeLayer(line); line = null; }
        here = dest = null; route = []; token++; if (stops) stops.clearLayers(); destName = ''; save(2); step(2);
    });

    // ---- cancel trip: takes this passenger off the boat's list (only offered to passengers who joined by scanning the QR code)
    var cm = $('cx-mdl');
    function cxClose() { cm.classList.remove('open'); }
    $('cancel').addEventListener('click', function () {
        if (!data || !data.scanned) return show($('e2'), 'You did not join by scanning the QR code. Please ask the operator to remove you from the passenger list.');
        show($('e2'), ''); cm.classList.add('open'); cm.querySelector('.no').focus();
    });
    cm.querySelector('.no').addEventListener('click', cxClose);
    cm.addEventListener('click', function (e) { if (e.target === cm) cxClose(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cxClose(); });
    cm.querySelector('.go').addEventListener('click', function () {
        var b = this; b.disabled = true; b.textContent = 'Cancelling…';
        function done() { b.disabled = false; b.textContent = 'Cancel trip'; cxClose(); }
        fetch(root.dataset.cancel, { method: 'POST', credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
            done();
            if (!r.ok) return show($('e2'), r.error || 'Could not cancel your trip. Try again.');
            clear(); who = null; destName = '';
            show($('ok1'), 'Your trip was cancelled. You are no longer on the boat\u2019s passenger list.'); step(1);
        }).catch(function () { done(); show($('e2'), 'Could not reach the server. Check your connection and try again.'); });
    });

    // ---- after a refresh: go back to where the passenger was
    var saved = load();
    if (saved && saved.who) {
        s1.hidden = true; who = saved.who;
        check(who).then(function (j) {
            if (!j.ok || !j.found) { clear(); step(1); show($('e1'), 'Your boat is no longer on the queue, so please check again.'); return; }
            fillStep2(j, saved.dest); step(2);
            var d = j.barangays.filter(function (x) { return x.name === saved.dest; })[0];
            if (saved.step === 3 && d && window.isSecureContext && navigator.geolocation) {
                var go = function () { startTrip(d); };
                if (navigator.permissions && navigator.permissions.query) navigator.permissions.query({ name: 'geolocation' }).then(function (r) { if (r.state === 'granted') go(); }, function () { });
            }
        }).catch(function () { step(1); });
    }
})();
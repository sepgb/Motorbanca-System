<?php
require_once __DIR__ . '/live.php';
require_once __DIR__ . '/announcements.php';
// URL of a css/js file with its last-modified time, so phones and browsers never keep an old copy after an update.
function asset($path)
{
    $f = dirname(__DIR__) . '/' . $path;
    return BASE . '/' . $path . '?v=' . (is_file($f) ? filemtime($f) : 0);
}

function page_head($title, $role = 'public')
{
    $b = BASE;
    $extra = in_array($role, ['admin', 'operator', 'public'], true)
        ? "<link rel='preconnect' href='https://fonts.googleapis.com'><link rel='preconnect' href='https://fonts.gstatic.com' crossorigin><link rel='stylesheet' href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap' media='print' onload=\"this.media='all'\"><link rel='stylesheet' href='" . asset('css/admin.css') . "'>" : '';
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='utf-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<title>" . e($title) . " | Motorbanca Monitor</title><link rel='stylesheet' href='" . asset('css/style.css') . "'>$extra</head>
<body class='" . ($role === 'public' ? 'r-admin r-public enter' : ($role === 'operator' ? 'r-admin r-operator enter' : 'r-' . $role . ($role === 'admin' ? ' enter' : ''))) . "'>";
    if ($role === 'admin' || $role === 'operator') {
        $me = basename($_SERVER['PHP_SELF']);
        $dir = $role === 'operator' ? 'operator' : 'admin';
        $links = $role === 'operator' ? ['dashboard.php' => 'Home', 'manifest.php' => 'Passengers', 'trips.php' => 'Trip History', 'announcements.php' => 'Announcements']
            : ['dashboard.php' => 'Dashboard', 'motorbancas.php' => 'Register', 'queue.php' => 'Queue', 'trip' . 's.php' => 'Trip History', 'announcements.php' => 'Announcements'];
        $logo = "<svg viewBox='0 0 40 40' width='40' height='40' fill='none' stroke='#f26b1d' stroke-width='5' stroke-linecap='round'><circle cx='20' cy='20' r='15' stroke-dasharray='19 12.42' transform='rotate(-80 20 20)'/></svg>";
        echo "<header class='atop'><a class='logo' href='$b/$dir/dashboard.php'>$logo<span>Port Control</span></a>
<button class='ibtn a-burger' aria-label='Menu' onclick=\"document.body.classList.toggle('nav-open')\">" . ico('<path d="M4 7h16M4 12h16M4 17h16"/>') . "</button>
<nav class='pills'><span class='pill-bg'></span>";
        foreach ($links as $f => $l) echo "<a href='$b/$dir/$f'" . ($f === $me ? " class='on'" : '') . ">$l</a>";
        $pillJs = <<<'JS'
<script>(function(){var n=document.querySelector('.pills'),b=n&&n.querySelector('.pill-bg'),c=n&&n.querySelector('a.on');if(!b||!c)return;
function p(a,t){b.style.transition=t?'':'none';b.style.width=a.offsetWidth+'px';b.style.transform='translateX('+a.offsetLeft+'px)';if(!t){void b.offsetWidth;b.style.transition='';}}
n.classList.add('has-bg');var f=null;try{f=JSON.parse(sessionStorage.getItem('pillFrom'));sessionStorage.removeItem('pillFrom');}catch(e){}
if(f&&f.w){b.style.transition='none';b.style.width=f.w+'px';b.style.transform='translateX('+f.l+'px)';void b.offsetWidth;b.style.transition='';requestAnimationFrame(function(){p(c,true)});}else p(c,false);
window.addEventListener('resize',function(){p(c,false)});
n.addEventListener('click',function(e){var a=e.target.closest('a');if(a&&a!==c)try{sessionStorage.setItem('pillFrom',JSON.stringify({l:c.offsetLeft,w:c.offsetWidth}))}catch(x){}});
if(document.fonts&&document.fonts.ready)document.fonts.ready.then(function(){setTimeout(function(){p(c,false)},500)});
})();</script>
JS;
        echo "</nav>$pillJs<div class='tools'><span class='datechip'>" . ico('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>') . "<span class='dt-date'><span id='clk-date'>" . date('l, j F Y') . "</span> - </span><b id='clk-time'>" . date('g:i A') . "</b></span>
<a class='ibtn' href='$b/logout.php' title='Log out' aria-label='Log out'>" . ico('<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>') . "</a></div></header>";
        // phone / tablet: sticky bottom tab bar (the top pills are hidden below 1000px)
        $tabIco = [
            'dashboard.php' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'motorbancas.php' => '<path d="M12 5v14M5 12h14"/>',
            'manifest.php' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M16 4.6a3.5 3.5 0 010 6.8M18 14.3c2.2.7 3.5 2.6 3.5 5.2"/>',
            'queue.php' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
            'trips.php' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'announcements.php' => '<path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4"/>'
        ];
        $short = ['announcements.php' => 'Alerts', 'trips.php' => 'History'];
        if ($role === 'operator') {
            // operator phone bar: icons only (the name stays as aria-label), Home in the middle and bigger, same spacing as the passenger bar
            $tabIco['dashboard.php'] = '<path d="M15 21v-8a1 1 0 00-1-1h-4a1 1 0 00-1 1v8"/><path d="M3 10a2 2 0 01.709-1.528l7-5.999a2 2 0 012.582 0l7 5.999A2 2 0 0121 10v9a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>';
            echo "<nav class='btabs btabs-pub' aria-label='Main'>";
            foreach (['manifest.php', 'trips.php', 'dashboard.php', 'announcements.php'] as $f) echo "<a href='$b/$dir/$f' aria-label='" . e($links[$f]) . "'" . ($f === 'dashboard.php' ? " data-q='1'" : '') . ($f === $me ? " class='on' aria-current='page'" : '') . "><i>" . ico($tabIco[$f]) . "</i></a>";
            echo "<a href='$b/logout.php' class='lo' aria-label='Log out'><i>" . ico('<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>') . "</i></a></nav>";
        } else {
            echo "<nav class='btabs' aria-label='Main'>";
            foreach ($links as $f => $l) echo "<a href='$b/$dir/$f'" . ($f === $me ? " class='on' aria-current='page'" : '') . "><i>" . ico($tabIco[$f]) . "</i><span>" . ($short[$f] ?? $l) . "</span></a>";
            echo "<a href='$b/logout.php' class='lo'><i>" . ico('<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>') . "</i><span>Log out</span></a></nav>";
        }
        // confirm before logging out (top button and bottom tab)
        echo "<div class='mdl' id='lo-mdl' role='dialog' aria-modal='true' aria-labelledby='lo-t'><div class='mdl-box'><div class='mdl-ic red'>" .
            ico('<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>') . "</div><h3 id='lo-t'>Log out?</h3><p>You will need to log in again to use the admin pages.</p>" .
            "<div class='mdl-act'><button type='button' class='no'>Stay</button><button type='button' class='go red'>Log out</button></div></div></div>";
        echo <<<'JS'
<script>(function(){var m=document.getElementById('lo-mdl'),url=null,from=null;if(!m)return;
function close(){m.classList.remove('open');if(from)from.focus()}
document.addEventListener('click',function(e){var a=e.target.closest('a[href$="logout.php"]');if(a){e.preventDefault();url=a.href;from=a;m.classList.add('open');m.querySelector('.no').focus()}});
m.querySelector('.no').addEventListener('click',close);
m.querySelector('.go').addEventListener('click',function(){location.href=url});
m.addEventListener('click',function(e){if(e.target===m)close()});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&m.classList.contains('open'))close()});
})();</script>
JS;
        echo "<main class='main'>";
        if ($role === 'operator' && $me === 'dashboard.php') echo ann_strip($b . '/operator/announcements.php');
    } else {
        $me = basename($_SERVER['PHP_SELF']);
        $plinks = ['index.php' => 'Live Queue', 'fares.php' => 'Fares', 'track.php' => 'Track Ride', 'announcements.php' => 'Notifications', 'profile.php' => 'Profile'];
        $ptab = ['index.php' => '<path d="M15 21v-8a1 1 0 00-1-1h-4a1 1 0 00-1 1v8"/><path d="M3 10a2 2 0 01.709-1.528l7-5.999a2 2 0 012.582 0l7 5.999A2 2 0 0121 10v9a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>', 'fares.php' => '<path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1 .6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M19.38 20A11.6 11.6 0 0021 14l-9-4-9 4c0 2.9.94 5.34 2.81 7.76"/><path d="M19 13V7a2 2 0 00-2-2H7a2 2 0 00-2 2v6"/><path d="M12 10v4"/><path d="M12 2v3"/>', 'track.php' => '<path d="M12 21s7-6.2 7-11.5A7 7 0 005 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>', 'announcements.php' => '<path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4"/>', 'profile.php' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>'];
        echo "<header class='atop'><a class='logo' href='$b/passenger/index.php'>" . "<svg viewBox='0 0 40 40' width='40' height='40' fill='none' stroke='#f26b1d' stroke-width='5' stroke-linecap='round'><circle cx='20' cy='20' r='15' stroke-dasharray='19 12.42' transform='rotate(-80 20 20)'/></svg>" . "<span>Port Control</span></a><nav class='pills'>";
        foreach ($plinks as $f => $l) echo "<a href='$b/passenger/$f'" . ($f === $me ? " class='on'" : '') . ">$l</a>";
        echo "</nav><div class='tools'><span class='datechip'>" . ico('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>') . "<span class='dt-date'><span id='clk-date'>" . date('l, j F Y') . "</span> - </span><b id='clk-time'>" . date('g:i A') . "</b></span></div></header>";
        // phone bar: icons only (the name stays as aria-label), Live Queue in the middle and bigger
        echo "<nav class='btabs btabs-pub' aria-label='Main'>";
        foreach (['fares.php', 'track.php', 'index.php', 'announcements.php', 'profile.php'] as $f) echo "<a href='$b/passenger/$f' aria-label='" . e($plinks[$f]) . "'" . ($f === 'index.php' ? " data-q='1'" : '') . ($f === $me ? " class='on' aria-current='page'" : '') . "><i>" . ico($ptab[$f]) . "</i></a>";
        echo "</nav><main class='main'>";
        if ($me === 'index.php') echo ann_strip($b . '/passenger/announcements.php');
        if ($me === 'index.php') echo "<h1>" . (($h = (int)date('G')) < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening')) . ", passenger</h1><p class='lead'>Here is what is happening at the port right now.</p><h2 class='pub-h2'><button type='button' class='scan-btn' id='scan-open' aria-label='Scan QR code' title='Scan QR code'>" . ico('<path d="M4 8V6a2 2 0 012-2h2M16 4h2a2 2 0 012 2v2M20 16v2a2 2 0 01-2 2h-2M8 20H6a2 2 0 01-2-2v-2M7 12h10"/>') . "</button><i class='dot'></i>Live queue</h2>";
    }
}
function page_foot($role = 'public')
{
    $b = BASE;
    echo "</main>";
    if ($role === 'public' && basename($_SERVER['PHP_SELF']) === 'index.php') {
        require_once __DIR__ . '/profile.php';
        $hasP = pax_profile() !== null;
        echo "<div class='mdl' id='scan-mdl' role='dialog' aria-modal='true' aria-labelledby='scan-t'><div class='mdl-box scn-box'><h3 id='scan-t'>Scan trip QR</h3>" .
            "<p>Point your camera at the QR code shown by the boat operator to add your profile details to the manifest.</p>" .
            ($hasP ? '' : "<div class='alert'><b>No profile yet.</b> <a href='$b/passenger/profile.php'>Set up your profile</a> first.</div>") .
            "<div class='scn-cam' id='cam' hidden><video id='vid' playsinline muted></video><i class='scn-frame'></i></div>" .
            "<div class='alert bad' id='serr' hidden></div>" .
            "<div class='alert trk-sec' id='sec' hidden>The camera needs a secure page. <a id='secgo' href='#'>Open the secure (https) page</a>. If your browser warns about the certificate, tap Advanced, then Proceed.</div>" .
            "<button type='button' class='btn' id='start' hidden>Start camera</button>" .
            "<div class='mdl-act'><button type='button' class='no' id='scan-close'>Close</button></div></div></div>" .
            "<script src='https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.min.js'></script><script>" . <<<'JS'
(function(){
var $=function(i){return document.getElementById(i)},m=$('scan-mdl'),vid=$('vid'),cam=$('cam'),err=$('serr'),btn=$('start'),stream=null,done=false,det=null,busy=false,run=false;
if(!m)return;
try{if('BarcodeDetector' in window)det=new BarcodeDetector({formats:['qr_code']})}catch(e){}
if(!window.isSecureContext){$('secgo').href='https://'+location.hostname+location.pathname;$('sec').hidden=false}
function show(t){err.textContent=t;err.hidden=!t}
function stop(){run=false;if(stream)stream.getTracks().forEach(function(t){t.stop()});stream=null;cam.hidden=true}
function found(txt){var x=String(txt).match(/[?&]t=([a-f0-9]{16})/i)||String(txt).trim().match(/^([a-f0-9]{16})$/i);if(!x||done)return false;done=true;stop();location.href=location.pathname.replace(/[^\/]*$/,'')+'join.php?t='+x[1].toLowerCase();return true}
function decode(c){var g=c.getContext('2d'),w=c.width,h=c.height;
  var js=function(){if(!window.jsQR)return null;var r=jsQR(g.getImageData(0,0,w,h).data,w,h,{inversionAttempts:'attemptBoth'});return r&&r.data};
  if(det)return det.detect(c).then(function(r){return (r&&r[0]&&r[0].rawValue)||js()},function(){return js()});
  return Promise.resolve(js())}
function tick(){if(!run||done)return;
  if(!busy&&vid.readyState===vid.HAVE_ENOUGH_DATA){busy=true;var c=document.createElement('canvas');c.width=vid.videoWidth;c.height=vid.videoHeight;c.getContext('2d').drawImage(vid,0,0,c.width,c.height);
    decode(c).then(function(t){busy=false;if(t)found(t)},function(){busy=false})}
  requestAnimationFrame(tick)}
function start(){show('');btn.hidden=true;
  if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){show('The camera is blocked on this page. Open the secure (https) page to scan.');btn.hidden=false;return}
  navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false}).then(function(s){stream=s;vid.srcObject=s;vid.play();cam.hidden=false;run=true;requestAnimationFrame(tick)},
    function(e){show(e&&e.name==='NotAllowedError'?'Camera permission was denied. Allow the camera for this site, then try again.':'Could not start the camera.');btn.hidden=false;btn.textContent='Try again'})}
function open(auto){m.classList.add('open');done=false;auto?start():(btn.hidden=false)}
function close(){m.classList.remove('open');stop();show('')}
$('scan-open').addEventListener('click',function(){open(true)});
btn.addEventListener('click',start);$('scan-close').addEventListener('click',close);
m.addEventListener('click',function(e){if(e.target===m)close()});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&m.classList.contains('open'))close()});
if(/[?&]scan=1/.test(location.search))open(false);
})();
JS . "</script>";
    }
    echo "<script src='" . asset('js/script.js') . "'></script></body></html>";
}

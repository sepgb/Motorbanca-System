<?php
require_once __DIR__ . '/live.php';
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
        $links = $role === 'operator' ? ['dashboard.php' => 'Home', 'manifest.php' => 'Passengers', 'trips.php' => 'Trip History']
            : ['dashboard.php' => 'Dashboard', 'motorbancas.php' => 'Register', 'queue.php' => 'Queue', 'trip' . 's.php' => 'Trip History'];
        $logo = "<img src='$b/admin/logo.webp' alt='Philippine Coast Guard' width='44' height='44'>";
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
            'trips.php' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'
        ];
        echo "<nav class='btabs' aria-label='Main'>";
        foreach ($links as $f => $l) echo "<a href='$b/$dir/$f'" . ($f === $me ? " class='on' aria-current='page'" : '') . "><i>" . ico($tabIco[$f]) . "</i><span>$l</span></a>";
        echo "<a href='$b/logout.php' class='lo'><i>" . ico('<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>') . "</i><span>Log out</span></a></nav>";
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
    } else {
        echo "<header class='atop'><a class='logo' href='$b/passenger/index.php'><img src='$b/admin/logo.webp' alt='Philippine Coast Guard' width='44' height='44'><span>BATIS</span></a>
<div class='tools'><span class='datechip'>" . ico('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>') . "<span class='dt-date'><span id='clk-date'>" . date('l, j F Y') . "</span> - </span><b id='clk-time'>" . date('g:i A') . "</b></span></div></header><main class='main'><h1>" . (($h = (int)date('G')) < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening')) . ", passenger</h1><p class='lead'>Here is what is happening at the port right now.</p><h2 class='pub-h2'><i class='dot'></i>Live queue</h2>";
    }
}
function page_foot($role = 'public')
{
    $b = BASE;
    echo "</main>";
    echo "<script src='" . asset('js/script.js') . "'></script></body></html>";
}

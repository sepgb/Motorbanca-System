<?php
require_once __DIR__ . '/live.php';

function page_head($title, $role = 'public')
{
    $b = BASE;
    $extra = $role === 'admin'
        ? "<link rel='preconnect' href='https://fonts.googleapis.com'><link rel='preconnect' href='https://fonts.gstatic.com' crossorigin><link rel='stylesheet' href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap' media='print' onload=\"this.media='all'\"><link rel='stylesheet' href='$b/css/admin.css'>" : '';
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='utf-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<title>" . e($title) . " | Motorbanca Monitor</title><link rel='stylesheet' href='$b/css/style.css'>$extra</head>
<body class='r-$role" . ($role === 'admin' ? ' enter' : '') . "'>";
    if ($role === 'admin') {
        $me = basename($_SERVER['PHP_SELF']);
        $links = [
            'dashboard.php' => 'Dashboard',
            'motorbancas.php' => 'Register',
            'queue.php' => 'Queue',
            'trip' . 's.php' => 'Trip History'
        ];
        $logo = "<img src='$b/admin/logo.webp' alt='Philippine Coast Guard' width='44' height='44'>";
        echo "<header class='atop'><a class='logo' href='$b/admin/dashboard.php'>$logo<span>Port Control</span></a>
<button class='ibtn a-burger' aria-label='Menu' onclick=\"document.body.classList.toggle('nav-open')\">" . ico('<path d="M4 7h16M4 12h16M4 17h16"/>') . "</button>
<nav class='pills'><span class='pill-bg'></span>";
        foreach ($links as $f => $l) echo "<a href='$b/admin/$f'" . ($f === $me ? " class='on'" : '') . ">$l</a>";
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
            'queue.php' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
            'trips.php' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'
        ];
        echo "<nav class='btabs' aria-label='Main'>";
        foreach ($links as $f => $l) echo "<a href='$b/admin/$f'" . ($f === $me ? " class='on' aria-current='page'" : '') . "><i>" . ico($tabIco[$f]) . "</i><span>$l</span></a>";
        echo "</nav><main class='main'>";
    } elseif ($role === 'operator') {
        echo "<header class='topbar'><b>🚤 Operator</b><a href='$b/logout.php'>Logout</a></header><main class='main'>";
    } else {
        echo "<header class='topbar'><b>⚓ Binangonan Port — Live Boat Board</b><a href='$b/login.php'>Staff login</a></header><main class='main'>";
    }
}
function page_foot($role = 'public')
{
    $b = BASE;
    echo "</main>";
    if ($role === 'operator')
        echo "<nav class='bottomnav'><a href='$b/operator/dashboard.php'>🏠<br>Home</a>
              <a href='$b/operator/manifest.php'>📋<br>Passengers</a><a href='$b/logout.php'>🚪<br>Logout</a></nav>";
    echo "<script src='$b/js/script.js'></script></body></html>";
}

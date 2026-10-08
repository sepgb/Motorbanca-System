<?php
require_once __DIR__ . '/live.php';

function page_head($title, $role = 'public')
{
    $b = BASE;
    $extra = $role === 'admin'
        ? "<link rel='preconnect' href='https://fonts.googleapis.com'><link rel='stylesheet' href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap'><link rel='stylesheet' href='$b/css/admin.css'>" : '';
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
        $logo = "<svg viewBox='0 0 40 40'><circle cx='20' cy='20' r='14' fill='none' stroke='#f26b1d' stroke-width='8' stroke-dasharray='17 5' transform='rotate(-80 20 20)'/><circle cx='20' cy='20' r='5' fill='#f26b1d'/></svg>";
        echo "<header class='atop'><a class='logo' href='$b/admin/dashboard.php'>$logo<span>Port Control</span></a>
<button class='ibtn a-burger' aria-label='Menu' onclick=\"document.body.classList.toggle('nav-open')\">" . ico('<path d="M4 7h16M4 12h16M4 17h16"/>') . "</button>
<nav class='pills'><span class='pill-bg'></span>";
        foreach ($links as $f => $l) echo "<a href='$b/admin/$f'" . ($f === $me ? " class='on'" : '') . ">$l</a>";
        echo "</nav><div class='tools'><span class='datechip'>" . ico('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>') . "<span class='dt-date'><span id='clk-date'>" . date('l, j F Y') . "</span> - </span><b id='clk-time'>" . date('g.i A') . "</b></span>
<a class='ibtn' href='$b/logout.php' title='Log out' aria-label='Log out'>" . ico('<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>') . "</a><span class='avatar'>PC</span></div></header><main class='main'>";
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

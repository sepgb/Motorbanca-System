<?php
// Public: a passenger checks that they are on a queued boat, picks their destination barangay and follows their live location.
require_once '../config/layout.php';
require_once '../config/profile.php';
pax_device();
$pf = pax_profile();   // details saved on this device's Profile page
$all = [];
try {
    $all = db()->query("SELECT DISTINCT name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $x) {
}
page_head('Track my ride', 'public');
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
<h1>Track my ride</h1>

<div class="trk" id="trk" data-api="<?= BASE ?>/api/track.php" data-cancel="<?= BASE ?>/api/cancel_trip.php">
    <div class="alert trk-sec" id="sec" hidden><b>Location needs a secure page.</b> This page was opened with plain http, so the browser will block your location. <a id="secgo" href="#">Open the secure (https) page</a>. If your browser warns about the certificate, tap Advanced, then Proceed.</div>
    <!-- step 1 -->
    <form class="card trk-card<?= $pf ? ' trk-lock' : '' ?>" id="s1" autocomplete="off">
        <h3>1. Find my boat</h3>
        <p class="muted"><?= $pf ? 'Your details come from your <a href="profile.php">Profile</a>.' : 'Enter your details exactly as they were given to the boat operator, or <a href="profile.php">set up your Profile</a> to fill this in automatically.' ?></p>
        <label>Full name<input name="name" maxlength="80" required<?= $pf ? ' readonly tabindex="-1"' : '' ?> value="<?= e($pf['full_name'] ?? '') ?>"></label>
        <div class="trk-row">
            <label>Age<input name="age" type="number" inputmode="numeric" min="0" max="120" required<?= $pf ? ' readonly tabindex="-1"' : '' ?> value="<?= e($pf['age'] ?? '') ?>"></label>
            <label>Barangay<input name="barangay" list="brgys" maxlength="60" required<?= $pf ? ' readonly tabindex="-1"' : '' ?> value="<?= e($pf['barangay'] ?? '') ?>"></label>
        </div>
        <datalist id="brgys"><?php foreach ($all as $n) echo '<option value="' . e($n) . '">'; ?></datalist>
        <div class="alert" id="ok1" hidden></div>
        <div class="alert bad" id="e1" hidden></div>
        <button class="btn" id="b1">Check</button>
    </form>

    <!-- step 2 -->
    <section class="card trk-card" id="s2" hidden>
        <h3>2. Where are you going?</h3>
        <div class="trk-found"><i class="dot"></i>
            <div><b id="fb"></b><span id="fc"></span></div>
        </div>
        <label>Destination barangay<select id="dest"></select></label>
        <div class="alert bad" id="e2" hidden></div>
        <button class="btn" id="go">Share my location</button>
        <button class="btn ghost trk-cancel" id="cancel" type="button" hidden>Cancel trip</button>
        <button class="trk-link" id="back1" type="button">Not you? Go back</button>
    </section>

    <!-- step 3 -->
    <section class="trk-live" id="s3" hidden>
        <div class="card trk-box">
            <div class="trk-info">
                <div><small>Going to</small><b id="td"></b></div>
                <div><small>Distance</small><b id="tk">&hellip;</b></div>
            </div>
            <div class="alert" id="ts">Finding your location&hellip;</div>
            <div id="map" class="trk-map"></div>
        </div>
        <div class="alert" id="e3" hidden></div>
        <div class="trk-act">
            <button class="btn sm" id="retry" type="button" hidden>Try again</button>
            <button class="btn sm ghost trk-loc" id="center" type="button"><span class="trk-loc-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="3" />
                        <circle cx="12" cy="12" r="7" />
                        <path d="M12 2v3M12 19v3M2 12h3M19 12h3" />
                    </svg></span>Your location</button>
            <button class="btn sm ghost" id="stop" type="button">Stop</button>
        </div>
    </section>
</div>
<div class="mdl" id="cx-mdl" role="dialog" aria-modal="true" aria-labelledby="cx-t">
    <div class="mdl-box">
        <div class="mdl-ic red"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="8" r="3.5" />
                <path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M17 8h5" />
            </svg></div>
        <h3 id="cx-t">Cancel your trip?</h3>
        <p>You will be taken off this boat's passenger list. To ride again, scan the trip QR code again.</p>
        <div class="mdl-act"><button type="button" class="no">Keep my seat</button><button type="button" class="go red">Cancel trip</button></div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script src="<?= asset('js/track.js') ?>"></script>
<?php page_foot();

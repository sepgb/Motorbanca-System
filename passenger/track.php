<?php
// Public: a passenger checks that they are on a queued boat, picks their destination barangay and follows their live location.
require_once '../config/layout.php';
$all = [];
try {
    $all = db()->query("SELECT DISTINCT name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $x) {
}
page_head('Track my ride', 'public');
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
<h1>Track my ride</h1>
<p class="lead">Check that you are on a boat, then follow your trip to your barangay.</p>

<div class="trk" id="trk" data-api="<?= BASE ?>/api/track.php">
    <div class="alert trk-sec" id="sec" hidden><b>Location needs a secure page.</b> This page was opened with plain http, so the browser will block your location. <a id="secgo" href="#">Open the secure (https) page</a>. If your browser warns about the certificate, tap Advanced, then Proceed.</div>
    <!-- step 1 -->
    <form class="card trk-card" id="s1" autocomplete="off">
        <h3>1. Find my boat</h3>
        <p class="muted">Enter your details exactly as they were given to the boat operator.</p>
        <label>Full name<input name="name" maxlength="80" required></label>
        <div class="trk-row">
            <label>Age<input name="age" type="number" inputmode="numeric" min="0" max="120" required></label>
            <label>Barangay<input name="barangay" list="brgys" maxlength="60" required></label>
        </div>
        <datalist id="brgys"><?php foreach ($all as $n) echo '<option value="' . e($n) . '">'; ?></datalist>
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
        <button class="trk-link" id="back1" type="button">Not me? Go back</button>
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
            <button class="btn sm ghost" id="center" type="button">Show both</button>
            <button class="btn sm ghost" id="stop" type="button">Stop</button>
        </div>
        <p class="muted trk-note">Your location is shown only on your own screen. It is not saved or sent to anyone.</p>
    </section>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script src="<?= asset('js/track.js') ?>"></script>
<?php page_foot();

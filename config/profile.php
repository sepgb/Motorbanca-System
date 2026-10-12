<?php
// Passenger profile = the "account" of this device. No log in: the browser keeps a random device token
// (cookie + a copy in localStorage) and the profile is stored in the database under that token.
require_once __DIR__ . '/database.php';

function pax_ensure()
{
    static $d;
    if ($d) return;
    $d = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS passenger_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY, device_token CHAR(32) NOT NULL UNIQUE,
        full_name VARCHAR(80) NOT NULL, age TINYINT UNSIGNED NOT NULL, barangay VARCHAR(60) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    } catch (PDOException $x) {
    }
}
// This device's token (created on first visit). Call before any output.
function pax_device()
{
    static $t;
    if ($t) return $t;
    $c = $_COOKIE['pax_device'] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $c)) $c = bin2hex(random_bytes(16));
    if (!headers_sent()) setcookie('pax_device', $c, ['expires' => time() + 86400 * 730, 'path' => '/', 'samesite' => 'Lax']);
    $_COOKIE['pax_device'] = $c;
    return $t = $c;
}
// The saved profile of this device, or null.
function pax_profile()
{
    pax_ensure();
    try {
        $q = db()->prepare("SELECT full_name, age, barangay FROM passenger_profiles WHERE device_token=?");
        $q->execute([pax_device()]);
        return $q->fetch() ?: null;
    } catch (PDOException $x) {
        return null;
    }
}
function pax_save($name, $age, $brgy)
{
    pax_ensure();
    db()->prepare("INSERT INTO passenger_profiles(device_token,full_name,age,barangay) VALUES(?,?,?,?)
        ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), age=VALUES(age), barangay=VALUES(barangay)")->execute([pax_device(), $name, $age, $brgy]);
}

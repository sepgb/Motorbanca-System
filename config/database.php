<?php
// Database + session setup. Change these if your XAMPP differs.
session_start();
const DB_HOST = 'localhost', DB_NAME = 'motorbanca_db', DB_USER = 'root', DB_PASS = '';
const BASE = '/motorbanca-system';   // folder name inside htdocs

function db() {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]);
    }
    return $pdo;
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
// Role guard: sends anyone without the right role back to login.
function need($role) {
    if (($_SESSION['role'] ?? '') !== $role) { header('Location: ' . BASE . '/login.php'); exit; }
}

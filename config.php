<?php
session_start();

$dbHost = 'localhost';
$dbName = 'trainee_platform';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed');
}

function is_logged_in()
{
    return isset($_SESSION['user']);
}

function current_user()
{
    return $_SESSION['user'] ?? null;
}

function require_role($role)
{
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== $role) {
        header('Location: index.php');
        exit;
    }
}

function e($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect($path)
{
    header("Location: $path");
    exit;
}

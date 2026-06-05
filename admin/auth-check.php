<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id']) || (int)($_SESSION['user_rol'] ?? 1) !== 0) {
    header('Location: login.php');
    exit;
}
// Compatibilidad con las páginas del panel que leen $_SESSION['admin']
$_SESSION['admin'] = [
    'id'     => $_SESSION['user_id'],
    'nombre' => $_SESSION['user_nombre'],
    'email'  => $_SESSION['user_email'],
];

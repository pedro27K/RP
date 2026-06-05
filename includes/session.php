<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function estaLogueado(): bool {
    return !empty($_SESSION['user_id']);
}

function requerirLogin(): void {
    if (!estaLogueado()) {
        header('Location: login.php');
        exit;
    }
}

function usuarioActual(): ?array {
    if (!estaLogueado()) return null;
    return [
        'id'        => $_SESSION['user_id'],
        'nombre'    => $_SESSION['user_nombre']    ?? '',
        'apellidos' => $_SESSION['user_apellidos'] ?? '',
        'email'     => $_SESSION['user_email']     ?? '',
        'rol'       => $_SESSION['user_rol']       ?? 1,
    ];
}

function tokenCSRF(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function verificarCSRF(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(403);
        die('Token de seguridad inválido. Vuelve atrás e inténtalo de nuevo.');
    }
}

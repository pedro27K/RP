<?php

function respuestaJson(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function validarCSRF(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sesion = $_SESSION['csrf_token']       ?? '';
    if (!$token || !$sesion || !hash_equals($sesion, $token)) {
        respuestaJson(['error' => 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.'], 403);
    }
}

function comprobarLimite(PDO $db, string $accion, int $max = 10, int $ventanaSegundos = 300): void {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM login_attempts
        WHERE ip = ? AND action = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$ip, $accion, $ventanaSegundos]);
    if ((int)$stmt->fetchColumn() >= $max) {
        respuestaJson(['error' => 'Demasiados intentos. Espera unos minutos antes de volver a intentarlo.'], 429);
    }
    $db->prepare("INSERT INTO login_attempts (ip, action) VALUES (?, ?)")->execute([$ip, $accion]);
    // Limpiar entradas antiguas ocasionalmente
    if (rand(1, 50) === 1) {
        $db->exec("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    }
}

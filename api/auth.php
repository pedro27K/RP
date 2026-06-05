<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$accion = $_GET['action'] ?? '';
$datos  = json_decode(file_get_contents('php://input'), true) ?? [];

validarCSRF();

try {
    switch ($accion) {

        // ── Comprobar sesión activa ───────────────────────────
        case 'session':
            if (isset($_SESSION['user_id'])) {
                respuestaJson(['loggedIn' => true, 'user' => [
                    'id'        => $_SESSION['user_id'],
                    'nombre'    => $_SESSION['user_nombre'],
                    'apellidos' => $_SESSION['user_apellidos'],
                    'email'     => $_SESSION['user_email'],
                    'rol'       => $_SESSION['user_rol'],
                ]]);
            }
            respuestaJson(['loggedIn' => false]);

        // ── Registro ──────────────────────────────────────────
        case 'register':
            $db        = obtenerBD();
            comprobarLimite($db, 'register', 5, 300);

            $nombre    = trim($datos['nombre']    ?? '');
            $apellidos = trim($datos['apellidos'] ?? '');
            $email     = strtolower(trim($datos['email'] ?? ''));
            $password  = $datos['password'] ?? '';

            if (!$nombre || !$email || !$password) {
                respuestaJson(['error' => 'Nombre, email y contraseña son obligatorios.'], 400);
            }
            if (strlen($nombre) > 100 || strlen($apellidos) > 150) {
                respuestaJson(['error' => 'Nombre o apellidos demasiado largos.'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                respuestaJson(['error' => 'El email introducido no es válido.'], 400);
            }
            if (strlen($password) < 8) {
                respuestaJson(['error' => 'La contraseña debe tener al menos 8 caracteres.'], 400);
            }
            if (!preg_match('/[0-9]/', $password)) {
                respuestaJson(['error' => 'La contraseña debe incluir al menos un número.'], 400);
            }

            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                respuestaJson(['error' => 'Ya existe una cuenta con ese email.'], 409);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO usuarios (nombre, apellidos, email, password_hash) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nombre, $apellidos, $email, $hash]);
            $idUsuario = (int)$db->lastInsertId();

            session_regenerate_id(true);
            $_SESSION['user_id']        = $idUsuario;
            $_SESSION['user_nombre']    = $nombre;
            $_SESSION['user_apellidos'] = $apellidos;
            $_SESSION['user_email']     = $email;
            $_SESSION['user_rol']       = 1; // nuevos usuarios son siempre rol normal

            respuestaJson(['success' => true, 'user' => [
                'id'        => $idUsuario,
                'nombre'    => $nombre,
                'apellidos' => $apellidos,
                'email'     => $email,
                'rol'       => 1,
            ]]);

        // ── Login ─────────────────────────────────────────────
        case 'login':
            $db       = obtenerBD();
            comprobarLimite($db, 'login', 10, 300);

            $email    = strtolower(trim($datos['email']    ?? ''));
            $password = $datos['password'] ?? '';

            if (!$email || !$password) {
                respuestaJson(['error' => 'Introduce tu email y contraseña.'], 400);
            }

            $stmt = $db->prepare("SELECT id, nombre, apellidos, email, password_hash, rol FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
                respuestaJson(['error' => 'Email o contraseña incorrectos.'], 401);
            }

            session_regenerate_id(true);
            $_SESSION['user_id']        = (int)$usuario['id'];
            $_SESSION['user_nombre']    = $usuario['nombre'];
            $_SESSION['user_apellidos'] = $usuario['apellidos'];
            $_SESSION['user_email']     = $usuario['email'];
            $_SESSION['user_rol']       = (int)$usuario['rol'];

            respuestaJson(['success' => true, 'user' => [
                'id'        => (int)$usuario['id'],
                'nombre'    => $usuario['nombre'],
                'apellidos' => $usuario['apellidos'],
                'email'     => $usuario['email'],
                'rol'       => (int)$usuario['rol'],
            ]]);

        // ── Actualizar perfil ─────────────────────────────────
        case 'update-profile':
            if (!isset($_SESSION['user_id'])) {
                respuestaJson(['error' => 'No autenticado'], 401);
            }
            $nombre    = trim($datos['nombre']    ?? '');
            $apellidos = trim($datos['apellidos'] ?? '');
            $telefono  = trim($datos['telefono']  ?? '');

            if (!$nombre) {
                respuestaJson(['error' => 'El nombre es obligatorio.'], 400);
            }
            if (strlen($nombre) > 100 || strlen($apellidos) > 150 || strlen($telefono) > 30) {
                respuestaJson(['error' => 'Datos demasiado largos.'], 400);
            }

            $db   = obtenerBD();
            $stmt = $db->prepare("UPDATE usuarios SET nombre = ?, apellidos = ?, telefono = ? WHERE id = ?");
            $stmt->execute([$nombre, $apellidos, $telefono, $_SESSION['user_id']]);

            $_SESSION['user_nombre']    = $nombre;
            $_SESSION['user_apellidos'] = $apellidos;

            respuestaJson(['success' => true]);

        // ── Logout ────────────────────────────────────────────
        case 'logout':
            session_destroy();
            respuestaJson(['success' => true]);

        // ── Recuperar contraseña (solicitar token) ─────────
        case 'forgot-password':
            $email = strtolower(trim($datos['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                respuestaJson(['error' => 'Email no válido.'], 400);
            }

            $db = obtenerBD();
            comprobarLimite($db, 'forgot', 3, 600);

            // Limpiar tokens caducados
            $db->exec("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");

            $token    = bin2hex(random_bytes(4)); // 8 caracteres legibles
            $expires  = date('Y-m-d H:i:s', time() + 3600);

            // Siempre insertar (no revelamos si el email existe)
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
                   ->execute([$email, $token, $expires]);

                // Intentar enviar email real
                $asunto  = 'Recuperación de contraseña — RP Travels';
                $cuerpo  = "Hola,\n\nTu código de recuperación es: $token\n\nVálido durante 1 hora.\n\nSi no solicitaste este cambio, ignora este mensaje.\n\nRP Travels";
                @mail($email, $asunto, $cuerpo, "From: noreply@rptravels.com\r\nContent-Type: text/plain; charset=UTF-8");
            }

            // Respuesta idéntica exista o no el email (evita user enumeration)
            respuestaJson(['success' => true, 'token_dev' => $token ?? null]);

        // ── Resetear contraseña con token ──────────────────
        case 'reset-password':
            $token    = trim($datos['token']    ?? '');
            $password = $datos['password'] ?? '';

            if (!$token || !$password) {
                respuestaJson(['error' => 'Token y contraseña son obligatorios.'], 400);
            }
            if (strlen($password) < 8) {
                respuestaJson(['error' => 'La contraseña debe tener al menos 8 caracteres.'], 400);
            }
            if (!preg_match('/[0-9]/', $password)) {
                respuestaJson(['error' => 'La contraseña debe incluir al menos un número.'], 400);
            }

            $db   = obtenerBD();
            $stmt = $db->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();
            if (!$reset) {
                respuestaJson(['error' => 'Código inválido o caducado.'], 400);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("UPDATE usuarios SET password_hash = ? WHERE email = ?")->execute([$hash, $reset['email']]);
            $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$token]);

            respuestaJson(['success' => true]);

        default:
            respuestaJson(['error' => 'Acción no válida'], 400);
    }

} catch (PDOException $e) {
    error_log('[RP auth] PDOException: ' . $e->getMessage());
    respuestaJson(['error' => 'Error en la base de datos.'], 500);
} catch (Exception $e) {
    respuestaJson(['error' => $e->getMessage()], 500);
}

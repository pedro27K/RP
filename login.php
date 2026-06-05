<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/db.php';

// Si ya está logueado, redirigir al inicio
if (estaLogueado()) {
    header('Location: index.php');
    exit;
}

$error_login    = '';
$error_registro = '';
$tab_activo     = $_GET['tab'] ?? 'login';

// ── Procesar formulario ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verificarCSRF();
    $accion = $_POST['accion'] ?? '';

    // LOGIN
    if ($accion === 'login') {
        $email    = strtolower(trim($_POST['email']    ?? ''));
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $error_login = 'Introduce tu email y contraseña.';
            $tab_activo  = 'login';
        } else {
            try {
                $db   = obtenerBD();
                $stmt = $db->prepare("SELECT id, nombre, apellidos, email, password_hash, rol FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                $usuario = $stmt->fetch();

                if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
                    $error_login = 'Email o contraseña incorrectos.';
                    $tab_activo  = 'login';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']        = (int)$usuario['id'];
                    $_SESSION['user_nombre']    = $usuario['nombre'];
                    $_SESSION['user_apellidos'] = $usuario['apellidos'];
                    $_SESSION['user_email']     = $usuario['email'];
                    $_SESSION['user_rol']       = (int)$usuario['rol'];
                    header('Location: ' . ($_GET['redir'] ?? 'index.php'));
                    exit;
                }
            } catch (Exception $e) {
                $error_login = 'Error al conectar con la base de datos.';
                $tab_activo  = 'login';
            }
        }
    }

    // REGISTRO
    if ($accion === 'registro') {
        $nombre    = trim($_POST['nombre']    ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $email     = strtolower(trim($_POST['email']    ?? ''));
        $password  = $_POST['password'] ?? '';
        $tab_activo = 'registro';

        if (!$nombre || !$email || !$password) {
            $error_registro = 'Nombre, email y contraseña son obligatorios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_registro = 'El email introducido no es válido.';
        } elseif (strlen($password) < 8) {
            $error_registro = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error_registro = 'La contraseña debe incluir al menos un número.';
        } else {
            try {
                $db   = obtenerBD();
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error_registro = 'Ya existe una cuenta con ese email.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $db->prepare("INSERT INTO usuarios (nombre, apellidos, email, password_hash) VALUES (?, ?, ?, ?)")
                       ->execute([$nombre, $apellidos, $email, $hash]);
                    $idUsuario = (int)$db->lastInsertId();

                    session_regenerate_id(true);
                    $_SESSION['user_id']        = $idUsuario;
                    $_SESSION['user_nombre']    = $nombre;
                    $_SESSION['user_apellidos'] = $apellidos;
                    $_SESSION['user_email']     = $email;
                    $_SESSION['user_rol']       = 1;
                    header('Location: index.php');
                    exit;
                }
            } catch (Exception $e) {
                $error_registro = 'Error al crear la cuenta. Inténtalo de nuevo.';
            }
        }
    }
}

$paginaActiva = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mi cuenta — RP Travels</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>✈</text></svg>">
  <link rel="stylesheet" href="css/fonts.css">
  <link rel="stylesheet" href="css/variables.css">
  <link rel="stylesheet" href="css/animations.css">
  <link rel="stylesheet" href="css/nav.css">
  <link rel="stylesheet" href="css/hero.css">
  <link rel="stylesheet" href="css/home.css">
  <link rel="stylesheet" href="css/results.css">
  <link rel="stylesheet" href="css/detail.css">
  <link rel="stylesheet" href="css/booking.css">
  <link rel="stylesheet" href="css/confirm.css">
  <link rel="stylesheet" href="css/modal.css">
  <link rel="stylesheet" href="css/profile.css">
  <link rel="stylesheet" href="css/car-rental.css">
  <link rel="stylesheet" href="css/footer.css">
  <style>
    .nav-tab { text-decoration: none; }
    .footer-link { text-decoration: none; }
    body { background: var(--n100, #f3f4f6); }
    .login-page { min-height: calc(100vh - 64px); display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
    .login-box { background: white; border-radius: 16px; padding: 40px; max-width: 480px; width: 100%; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/nav.php'; ?>

<div class="login-page">
  <div class="login-box">

    <div class="modal-tabs-row">
      <a class="modal-tab <?= $tab_activo === 'login'   ? 'active' : '' ?>"
         href="login.php?tab=login" style="text-decoration:none">Iniciar sesión</a>
      <a class="modal-tab <?= $tab_activo === 'registro' ? 'active' : '' ?>"
         href="login.php?tab=registro" style="text-decoration:none">Crear cuenta</a>
    </div>

    <?php if ($tab_activo !== 'registro'): ?>
    <!-- PANEL LOGIN -->
    <div class="modal-body">
      <div class="modal-title">¡Bienvenido de vuelta!</div>
      <div class="modal-subtitle">Accede a tus reservas y viajes favoritos.</div>

      <?php if ($error_login): ?>
      <div class="modal-error"><?= htmlspecialchars($error_login) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf"   value="<?= tokenCSRF() ?>">
        <input type="hidden" name="accion" value="login">

        <div class="modal-field">
          <label class="modal-label">Email</label>
          <input class="modal-input" type="email" name="email" placeholder="tu@email.com" required
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="modal-field">
          <label class="modal-label">Contraseña</label>
          <input class="modal-input" type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-modal-submit">Iniciar sesión</button>
      </form>
      <p class="modal-switch-text">
        ¿No tienes cuenta? <a href="login.php?tab=registro">Regístrate gratis</a>
      </p>
    </div>

    <?php else: ?>
    <!-- PANEL REGISTRO -->
    <div class="modal-body">
      <div class="modal-title">Crea tu cuenta</div>
      <div class="modal-subtitle">Únete y descubre las mejores ofertas de viaje.</div>

      <?php if ($error_registro): ?>
      <div class="modal-error"><?= htmlspecialchars($error_registro) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf"   value="<?= tokenCSRF() ?>">
        <input type="hidden" name="accion" value="registro">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="modal-field">
            <label class="modal-label">Nombre</label>
            <input class="modal-input" type="text" name="nombre" placeholder="Ana" required
              value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
          </div>
          <div class="modal-field">
            <label class="modal-label">Apellidos</label>
            <input class="modal-input" type="text" name="apellidos" placeholder="García"
              value="<?= htmlspecialchars($_POST['apellidos'] ?? '') ?>">
          </div>
        </div>
        <div class="modal-field">
          <label class="modal-label">Email</label>
          <input class="modal-input" type="email" name="email" placeholder="tu@email.com" required
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="modal-field">
          <label class="modal-label">Contraseña</label>
          <input class="modal-input" type="password" name="password"
            placeholder="Mínimo 8 caracteres, incluye un número" required>
        </div>
        <button type="submit" class="btn-modal-submit">Crear cuenta gratuita</button>
      </form>
      <p class="modal-switch-text">
        ¿Ya tienes cuenta? <a href="login.php?tab=login">Inicia sesión</a>
      </p>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>

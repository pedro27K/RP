<?php
session_start();

// Si ya está logueado como admin en la web principal, acceso directo
if (!empty($_SESSION['user_id']) && (int)($_SESSION['user_rol'] ?? 1) === 0) {
    header('Location: dashboard.php');
    exit;
}

require_once '../api/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    if ($email && $password) {
        $db   = obtenerBD();
        $stmt = $db->prepare('SELECT id, nombre, apellidos, email, password_hash FROM usuarios WHERE email = ? AND rol = 0 LIMIT 1');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']        = (int)$usuario['id'];
            $_SESSION['user_nombre']    = $usuario['nombre'];
            $_SESSION['user_apellidos'] = $usuario['apellidos'];
            $_SESSION['user_email']     = $usuario['email'];
            $_SESSION['user_rol']       = 0;
            header('Location: dashboard.php');
            exit;
        }
    }
    $error = 'Email o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — RP Travels</title>
  <link rel="stylesheet" href="../css/fonts.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body class="login-body">

  <div class="login-card">
    <div class="login-logo">
      <div class="login-logo-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
          <g transform="translate(12,12) rotate(-45)">
            <rect x="-1.8" y="-9" width="3.6" height="18" rx="1.8" fill="white"/>
            <polygon points="1.8,-1.5 12,4.5 12,6 -1.8,1.5" fill="white"/>
            <polygon points="-1.8,-1.5 -12,4.5 -12,6 1.8,1.5" fill="white"/>
          </g>
        </svg>
      </div>
      <div>
        <div class="login-logo-rp">RP TRAVELS</div>
        <div class="login-logo-sub">Panel de Administración</div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="login-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="login-field">
        <label>Email</label>
        <input type="email" name="email" placeholder="admin@rptravels.com" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="login-field">
        <label>Contraseña</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-login">Entrar al panel</button>
    </form>

    <p class="login-back"><a href="../index.php">← Volver a la web</a></p>
  </div>

</body>
</html>

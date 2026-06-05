<?php
require_once 'auth-check.php';
require_once '../api/db.php';

$db = obtenerBD();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: users.php'); exit; }

$stmt = $db->prepare("SELECT id, nombre, apellidos, email, telefono FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) { header('Location: users.php'); exit; }

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre    = trim($_POST['nombre']    ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $telefono  = trim($_POST['telefono']  ?? '');
    $newPass   = $_POST['nueva_password'] ?? '';

    if ($nombre === ''   || strlen($nombre)    > 100) $errors[] = 'El nombre es obligatorio (máx. 100 caracteres).';
    if ($apellidos === '' || strlen($apellidos) > 150) $errors[] = 'Los apellidos son obligatorios (máx. 150 caracteres).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'Email inválido.';
    if ($newPass !== '' && strlen($newPass) < 8)       $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.';

    // Chequea si el email ya está en uso.
    if (!$errors) {
        $dup = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $dup->execute([$email, $id]);
        if ($dup->fetch()) $errors[] = 'Ese email ya está en uso por otro usuario.';
    }

    if (!$errors) {
        if ($newPass !== '') {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $db->prepare("UPDATE usuarios SET nombre=?, apellidos=?, email=?, telefono=?, password_hash=? WHERE id=?")
               ->execute([$nombre, $apellidos, $email, $telefono ?: null, $hash, $id]);
        } else {
            $db->prepare("UPDATE usuarios SET nombre=?, apellidos=?, email=?, telefono=? WHERE id=?")
               ->execute([$nombre, $apellidos, $email, $telefono ?: null, $id]);
        }
        // Reload
        $stmt = $db->prepare("SELECT id, nombre, apellidos, email, telefono FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar usuario — RP Travels Admin</title>
  <link rel="stylesheet" href="../css/fonts.css">
  <link rel="stylesheet" href="css/admin.css">
  <style>
    .form-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-grid .full { grid-column: 1 / -1; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label { font-size: 13px; font-weight: 600; color: #374151; }
    .form-group input { padding: 9px 12px; border: 1.5px solid #E5E7EB; border-radius: 8px;
      font-size: 14px; font-family: inherit; color: #111827; transition: border-color 160ms; }
    .form-group input:focus { outline: none; border-color: #2563EB; }
    .form-actions { display: flex; gap: 12px; margin-top: 8px; }
    .form-errors { background: #FEE2E2; border: 1px solid #FECACA; border-radius: 10px;
      padding: 14px 18px; margin-bottom: 24px; }
    .form-errors li { color: #B91C1C; font-size: 13px; }
    .form-success { background: #DCFCE7; border: 1px solid #86EFAC; border-radius: 10px;
      padding: 12px 18px; margin-bottom: 20px; font-size: 14px; font-weight: 600; color: #15803D; }
    .form-hint { font-size: 12px; color: #9CA3AF; }
  </style>
</head>
<body>
<div class="admin-wrap">
  <?php include 'partials/sidebar.php'; ?>

  <div class="admin-main">
    <div class="admin-topbar">
      <h1>
        <a href="users.php" style="color:inherit;text-decoration:none;opacity:0.5">Usuarios</a>
        <span style="opacity:0.35; margin:0 6px">/</span>
        Editar usuario #<?= $id ?>
      </h1>
      <div class="admin-user">
        <div class="admin-user-avatar"><?= strtoupper(substr($_SESSION['admin']['nombre'], 0, 1)) ?></div>
        <?= htmlspecialchars($_SESSION['admin']['nombre']) ?>
      </div>
    </div>

    <div class="admin-content">
      <div class="admin-card" style="max-width:700px">

        <?php if ($success): ?>
        <div class="form-success">Datos del usuario actualizados correctamente.</div>
        <?php endif; ?>

        <?php if ($errors): ?>
        <div class="form-errors"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST">
          <div class="form-grid" style="margin-bottom:24px">
            <div class="form-group">
              <label>Nombre <span style="color:#EF4444">*</span></label>
              <input type="text" name="nombre" maxlength="100" required
                     value="<?= htmlspecialchars($u['nombre']) ?>">
            </div>
            <div class="form-group">
              <label>Apellidos <span style="color:#EF4444">*</span></label>
              <input type="text" name="apellidos" maxlength="150" required
                     value="<?= htmlspecialchars($u['apellidos']) ?>">
            </div>
            <div class="form-group">
              <label>Email <span style="color:#EF4444">*</span></label>
              <input type="email" name="email" maxlength="150" required
                     value="<?= htmlspecialchars($u['email']) ?>">
            </div>
            <div class="form-group">
              <label>Teléfono</label>
              <input type="text" name="telefono" maxlength="30"
                     value="<?= htmlspecialchars($u['telefono'] ?? '') ?>">
            </div>
            <div class="form-group full">
              <label>Nueva contraseña (dejar en blanco para no cambiar)</label>
              <input type="password" name="nueva_password" autocomplete="new-password">
              <span class="form-hint">Mínimo 8 caracteres si se rellena.</span>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="users.php" class="btn btn-ghost">Cancelar</a>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>
</body>
</html>

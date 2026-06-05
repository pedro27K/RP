<?php
require_once 'auth-check.php';
require_once '../api/db.php';

$db = obtenerBD();

$buscar = trim($_GET['q'] ?? '');
$where  = [];
$params = [];

if ($buscar !== '') {
    $like     = '%' . $buscar . '%';
    $where[]  = '(u.nombre LIKE ? OR u.apellidos LIKE ? OR u.email LIKE ? OR u.telefono LIKE ?)';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$per_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$countStmt = $db->prepare("SELECT COUNT(*) FROM usuarios u $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $per_page));

$stmt = $db->prepare("
    SELECT u.id, u.nombre, u.apellidos, u.email, u.telefono, u.created_at,
           COUNT(r.id) AS num_reservas,
           COALESCE(SUM(CASE WHEN r.estado != 'cancelada' THEN r.precio_total ELSE 0 END), 0) AS total_gastado
    FROM usuarios u
    LEFT JOIN reservas r ON r.usuario_id = u.id
    $whereSql
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Usuarios — RP Travels Admin</title>
  <link rel="stylesheet" href="../css/fonts.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-wrap">
  <?php include 'partials/sidebar.php'; ?>

  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Usuarios</h1>
      <div class="admin-user">
        <div class="admin-user-avatar"><?= strtoupper(substr($_SESSION['admin']['nombre'], 0, 1)) ?></div>
        <?= htmlspecialchars($_SESSION['admin']['nombre']) ?>
      </div>
    </div>

    <div class="admin-content">
      <div class="admin-card">

        <!-- Filtros -->
        <form method="GET" class="filter-bar">
          <input type="text" name="q" placeholder="Buscar por nombre, email o teléfono…"
                 value="<?= htmlspecialchars($buscar) ?>" style="min-width:280px">
          <button type="submit" class="btn btn-primary">Buscar</button>
          <?php if ($buscar): ?>
            <a href="users.php" class="btn btn-ghost">Limpiar</a>
          <?php endif; ?>
          <span class="text-muted text-sm" style="margin-left:auto"><?= $total ?> usuario<?= $total !== 1 ? 's' : '' ?></span>
        </form>

        <!-- Tabla -->
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Nombre</th>
              <th>Email</th>
              <th>Teléfono</th>
              <th>Reservas</th>
              <th>Total gastado</th>
              <th>Registro</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($usuarios): ?>
              <?php foreach ($usuarios as $u): ?>
              <tr>
                <td class="font-mono"><?= $u['id'] ?></td>
                <td>
                  <div style="font-weight:600"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?></div>
                </td>
                <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                <td class="text-muted text-sm"><?= $u['telefono'] ? htmlspecialchars($u['telefono']) : '—' ?></td>
                <td style="text-align:center">
                  <?php if ($u['num_reservas'] > 0): ?>
                    <a href="bookings.php?q=<?= urlencode($u['email']) ?>" style="font-weight:700;color:#2563eb">
                      <?= (int)$u['num_reservas'] ?>
                    </a>
                  <?php else: ?>
                    <span class="text-muted">0</span>
                  <?php endif; ?>
                </td>
                <td style="font-weight:<?= $u['total_gastado'] > 0 ? '700' : '400' ?>">
                  <?= $u['total_gastado'] > 0 ? number_format($u['total_gastado'], 0, ',', '.') . ' €' : '<span class="text-muted">—</span>' ?>
                </td>
                <td class="text-muted text-sm"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                <td>
                  <a href="user-edit.php?id=<?= $u['id'] ?>" class="btn btn-ghost" style="font-size:11px;padding:5px 10px">Editar</a>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="8">
                <div class="empty-state">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                  <p>No se encontraron usuarios.</p>
                </div>
              </td></tr>
            <?php endif; ?>
          </tbody>
        </table>

        <!-- Paginación -->
        <?php if ($pages > 1): ?>
        <div class="pagination">
          <span>Página <?= $page ?> de <?= $pages ?></span>
          <div class="pagination-links">
            <?php $qs = $buscar ? '&q=' . urlencode($buscar) : ''; ?>
            <button class="page-btn" onclick="location.href='?page=<?= max(1,$page-1) ?><?= $qs ?>'"
              <?= $page <= 1 ? 'disabled' : '' ?>>← Ant.</button>
            <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
              <button class="page-btn <?= $i===$page?'active':'' ?>"
                onclick="location.href='?page=<?= $i ?><?= $qs ?>'"><?= $i ?></button>
            <?php endfor; ?>
            <button class="page-btn" onclick="location.href='?page=<?= min($pages,$page+1) ?><?= $qs ?>'"
              <?= $page >= $pages ? 'disabled' : '' ?>>Sig. →</button>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>
</body>
</html>

<?php
require_once 'auth-check.php';
require_once '../api/db.php';

$db = obtenerBD();

// Toggle activo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paquete_id'], $_POST['activo'])) {
    $stmt = $db->prepare('UPDATE paquetes SET activo = ? WHERE id = ?');
    $stmt->execute([(int)$_POST['activo'], (int)$_POST['paquete_id']]);
    header('Location: packages.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// Delete package (only if no reservations)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delId = (int)$_POST['delete_id'];
    $count = $db->prepare("SELECT COUNT(*) FROM reservas WHERE paquete_id = ?");
    $count->execute([$delId]);
    if ((int)$count->fetchColumn() === 0) {
        $db->prepare("DELETE FROM paquetes WHERE id = ?")->execute([$delId]);
    }
    header('Location: packages.php');
    exit;
}

// Filtros
$filtro_tipo   = $_GET['tipo']   ?? '';
$filtro_activo = $_GET['activo'] ?? '';
$buscar        = trim($_GET['q'] ?? '');

$tipos_validos  = ['vuelo','hotel','paquete','crucero','circuito','finde'];
$where  = [];
$params = [];

if ($filtro_tipo && in_array($filtro_tipo, $tipos_validos, true)) {
    $where[]  = 'p.tipo = ?';
    $params[] = $filtro_tipo;
}
if ($filtro_activo !== '') {
    $where[]  = 'p.activo = ?';
    $params[] = (int)$filtro_activo;
}
if ($buscar !== '') {
    $like     = '%' . $buscar . '%';
    $where[]  = '(p.nombre LIKE ? OR d.nombre LIKE ?)';
    $params[] = $like; $params[] = $like;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$per_page = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$countStmt = $db->prepare("
    SELECT COUNT(*) FROM paquetes p
    LEFT JOIN destinos d ON d.id = p.destino_id
    $whereSql
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $per_page));

$stmt = $db->prepare("
    SELECT p.id, p.nombre, p.tipo, p.precio_persona, p.noches, p.regimen, p.activo,
           d.nombre AS destino,
           (SELECT COUNT(*) FROM reservas r WHERE r.paquete_id = p.id) AS num_reservas
    FROM paquetes p
    LEFT JOIN destinos d ON d.id = p.destino_id
    $whereSql
    ORDER BY p.id DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$paquetes = $stmt->fetchAll();

$tipo_labels = [
    'vuelo'      => 'Vuelo',
    'hotel'      => 'Hotel',
    'paquete'    => 'Paquete',
    'crucero'    => 'Crucero',
    'circuito'   => 'Circuito',
    'finde'      => 'Fin de semana',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paquetes — RP Travels Admin</title>
  <link rel="stylesheet" href="../css/fonts.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-wrap">
  <?php include 'partials/sidebar.php'; ?>

  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Paquetes</h1>
      <div style="display:flex;align-items:center;gap:16px">
        <a href="package-edit.php" class="btn btn-primary" style="font-size:13px">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nuevo paquete
        </a>
      <div class="admin-user">
        <div class="admin-user-avatar"><?= strtoupper(substr($_SESSION['admin']['nombre'], 0, 1)) ?></div>
        <?= htmlspecialchars($_SESSION['admin']['nombre']) ?>
      </div>
      </div>
    </div>

    <div class="admin-content">
      <div class="admin-card">

        <?php if (isset($_GET['saved'])): ?>
        <div style="background:#DCFCE7;border:1px solid #86EFAC;border-radius:10px;padding:12px 18px;
             font-size:14px;font-weight:600;color:#15803D;margin-bottom:20px">
          Paquete guardado correctamente.
        </div>
        <?php endif; ?>

        <!-- Filtros -->
        <form method="GET" class="filter-bar">
          <select name="tipo" onchange="this.form.submit()">
            <option value="">Todos los tipos</option>
            <?php foreach ($tipo_labels as $val => $lab): ?>
              <option value="<?= $val ?>" <?= $filtro_tipo===$val ? 'selected' : '' ?>><?= $lab ?></option>
            <?php endforeach; ?>
          </select>
          <select name="activo" onchange="this.form.submit()">
            <option value="">Activo / Inactivo</option>
            <option value="1" <?= $filtro_activo==='1' ? 'selected' : '' ?>>Activos</option>
            <option value="0" <?= $filtro_activo==='0' ? 'selected' : '' ?>>Inactivos</option>
          </select>
          <input type="text" name="q" placeholder="Buscar nombre o destino…" value="<?= htmlspecialchars($buscar) ?>">
          <button type="submit" class="btn btn-primary">Buscar</button>
          <?php if ($filtro_tipo || $filtro_activo !== '' || $buscar): ?>
            <a href="packages.php" class="btn btn-ghost">Limpiar</a>
          <?php endif; ?>
          <span class="text-muted text-sm" style="margin-left:auto"><?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?></span>
        </form>

        <!-- Tabla -->
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Nombre</th>
              <th>Destino</th>
              <th>Tipo</th>
              <th>Precio / pax</th>
              <th>Noches</th>
              <th>Régimen</th>
              <th>Reservas</th>
              <th>Estado</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($paquetes): ?>
              <?php foreach ($paquetes as $p): ?>
              <tr>
                <td class="font-mono"><?= $p['id'] ?></td>
                <td style="max-width:200px">
                  <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= htmlspecialchars($p['nombre']) ?>
                  </div>
                </td>
                <td class="text-muted"><?= htmlspecialchars($p['destino'] ?? '—') ?></td>
                <td><span class="badge" style="background:#eff6ff;color:#1d4ed8"><?= htmlspecialchars($tipo_labels[$p['tipo']] ?? $p['tipo']) ?></span></td>
                <td style="font-weight:700"><?= number_format($p['precio_persona'], 0, ',', '.') ?> €</td>
                <td style="text-align:center"><?= (int)$p['noches'] ?: '—' ?></td>
                <td class="text-muted text-sm"><?= htmlspecialchars($p['regimen'] ?? '—') ?></td>
                <td style="text-align:center"><?= (int)$p['num_reservas'] ?></td>
                <td>
                  <span class="badge badge-<?= $p['activo'] ? 'activo' : 'inactivo' ?>">
                    <?= $p['activo'] ? 'Activo' : 'Inactivo' ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                    <a href="package-edit.php?id=<?= $p['id'] ?>" class="btn btn-ghost" style="font-size:11px;padding:5px 10px">Editar</a>
                    <form method="POST" style="display:inline">
                      <input type="hidden" name="paquete_id" value="<?= $p['id'] ?>">
                      <input type="hidden" name="activo" value="<?= $p['activo'] ? '0' : '1' ?>">
                      <button type="submit" class="btn <?= $p['activo'] ? 'btn-warning' : 'btn-success' ?>" style="font-size:11px;padding:5px 10px">
                        <?= $p['activo'] ? 'Desactivar' : 'Activar' ?>
                      </button>
                    </form>
                    <?php if ((int)$p['num_reservas'] === 0): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este paquete? Esta acción no se puede deshacer.')">
                      <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                      <button type="submit" class="btn btn-danger" style="font-size:11px;padding:5px 10px">Eliminar</button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="10">
                <div class="empty-state">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/></svg>
                  <p>No hay paquetes que coincidan con los filtros.</p>
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
            <?php
            $qs = http_build_query(array_filter(['tipo'=>$filtro_tipo,'activo'=>$filtro_activo,'q'=>$buscar]));
            $qs = $qs ? '&' . $qs : '';
            ?>
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

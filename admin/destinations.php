<?php
require_once 'auth-check.php';
require_once '../api/db.php';

$db = obtenerBD();

$continentes = ['Europa','América','Asia','África','Oceanía','Oriente Medio'];

$errors  = [];
$success = '';
$editing = null;

// Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $editId  = (int)($_POST['edit_id'] ?? 0);
        $nombre  = trim($_POST['nombre'] ?? '');
        $pais    = trim($_POST['pais'] ?? '');
        $cont    = trim($_POST['continente'] ?? '');
        $desc    = trim($_POST['descripcion'] ?? '');
        $activo  = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '' || strlen($nombre) > 120)   $errors[] = 'El nombre es obligatorio (máx. 120 caracteres).';
        if ($pais === ''   || strlen($pais) > 100)     $errors[] = 'El país es obligatorio (máx. 100 caracteres).';
        if (!in_array($cont, $continentes, true))       $errors[] = 'Selecciona un continente válido.';

        if (!$errors) {
            if ($editId > 0) {
                $db->prepare("UPDATE destinos SET nombre=?, pais=?, continente=?, descripcion=?, activo=? WHERE id=?")
                   ->execute([$nombre, $pais, $cont, $desc ?: null, $activo, $editId]);
            } else {
                $db->prepare("INSERT INTO destinos (nombre, pais, continente, descripcion, activo) VALUES (?,?,?,?,?)")
                   ->execute([$nombre, $pais, $cont, $desc ?: null, $activo]);
            }
            header('Location: destinations.php?saved=1');
            exit;
        }
        // Error formulario
        $editing = ['id'=>$editId,'nombre'=>$nombre,'pais'=>$pais,'continente'=>$cont,'descripcion'=>$desc,'activo'=>$activo];

    } elseif ($action === 'toggle') {
        $togId = (int)($_POST['dest_id'] ?? 0);
        $newVal = (int)($_POST['new_activo'] ?? 0);
        if ($togId > 0) {
            $db->prepare("UPDATE destinos SET activo = ? WHERE id = ?")->execute([$newVal, $togId]);
        }
        header('Location: destinations.php');
        exit;

    } elseif ($action === 'delete') {
        $delId = (int)($_POST['dest_id'] ?? 0);
        if ($delId > 0) {
            $used = $db->prepare("SELECT COUNT(*) FROM paquetes WHERE destino_id = ?");
            $used->execute([$delId]);
            if ((int)$used->fetchColumn() === 0) {
                $db->prepare("DELETE FROM destinos WHERE id = ?")->execute([$delId]);
            }
        }
        header('Location: destinations.php');
        exit;
    }
}

// Cargar la fila con GET
if (!$editing && isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM destinos WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

// Fetch list
$buscar = trim($_GET['q'] ?? '');
$params = [];
$where  = [];
if ($buscar !== '') {
    $like    = '%' . $buscar . '%';
    $where[] = '(nombre LIKE ? OR pais LIKE ? OR continente LIKE ?)';
    $params  = [$like, $like, $like];
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$per_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$total = (int)$db->prepare("SELECT COUNT(*) FROM destinos $whereSql")->execute($params) ? null : null;
$cStmt = $db->prepare("SELECT COUNT(*) FROM destinos $whereSql");
$cStmt->execute($params);
$total = (int)$cStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $per_page));

$stmt = $db->prepare("
    SELECT d.*, (SELECT COUNT(*) FROM paquetes p WHERE p.destino_id = d.id) AS num_paquetes
    FROM destinos d
    $whereSql
    ORDER BY d.nombre
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$destinos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Destinos — RP Travels Admin</title>
  <link rel="stylesheet" href="../css/fonts.css">
  <link rel="stylesheet" href="css/admin.css">
  <style>
    .dest-form-card { background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 12px;
      padding: 24px 28px; margin-bottom: 28px; }
    .dest-form-title { font-size: 14px; font-weight: 700; color: #374151; margin-bottom: 18px; }
    .form-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-grid .full { grid-column: 1 / -1; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group label { font-size: 12px; font-weight: 600; color: #6B7280; }
    .form-group input, .form-group select, .form-group textarea {
      padding: 8px 11px; border: 1.5px solid #E5E7EB; border-radius: 8px;
      font-size: 13px; font-family: inherit; color: #111827; transition: border-color 160ms; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
      outline: none; border-color: #2563EB; }
    .form-group textarea { resize: vertical; min-height: 60px; }
    .form-actions { display: flex; gap: 10px; margin-top: 16px; }
    .checkbox-row { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
    .checkbox-row input { width: 15px; height: 15px; cursor: pointer; }
    .checkbox-row label { font-size: 13px; font-weight: 600; color: #374151; cursor: pointer; margin: 0; }
    .form-errors { background: #FEE2E2; border: 1px solid #FECACA; border-radius: 10px;
      padding: 12px 16px; margin-bottom: 16px; }
    .form-errors li { color: #B91C1C; font-size: 13px; }
  </style>
</head>
<body>
<div class="admin-wrap">
  <?php include 'partials/sidebar.php'; ?>

  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Destinos</h1>
      <div class="admin-user">
        <div class="admin-user-avatar"><?= strtoupper(substr($_SESSION['admin']['nombre'], 0, 1)) ?></div>
        <?= htmlspecialchars($_SESSION['admin']['nombre']) ?>
      </div>
    </div>

    <div class="admin-content">

      <!-- Create / Edit form -->
      <div class="admin-card dest-form-card">
        <div class="dest-form-title">
          <?= $editing && $editing['id'] > 0 ? 'Editar destino' : 'Nuevo destino' ?>
        </div>

        <?php if ($errors): ?>
        <div class="form-errors"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="edit_id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">

          <div class="form-grid">
            <div class="form-group">
              <label>Nombre <span style="color:#EF4444">*</span></label>
              <input type="text" name="nombre" maxlength="120" required
                     value="<?= htmlspecialchars($editing['nombre'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>País <span style="color:#EF4444">*</span></label>
              <input type="text" name="pais" maxlength="100" required
                     value="<?= htmlspecialchars($editing['pais'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Continente <span style="color:#EF4444">*</span></label>
              <select name="continente">
                <option value="">— Seleccionar —</option>
                <?php foreach ($continentes as $c): ?>
                  <option value="<?= $c ?>" <?= ($editing['continente'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="justify-content:flex-end">
              <div class="checkbox-row">
                <input type="checkbox" id="activo_dest" name="activo" value="1"
                       <?= !$editing || $editing['activo'] ? 'checked' : '' ?>>
                <label for="activo_dest">Destino activo</label>
              </div>
            </div>
            <div class="form-group full">
              <label>Descripción</label>
              <textarea name="descripcion" rows="2"><?= htmlspecialchars($editing['descripcion'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary" style="font-size:13px">
              <?= $editing && $editing['id'] > 0 ? 'Guardar cambios' : 'Crear destino' ?>
            </button>
            <?php if ($editing && $editing['id'] > 0): ?>
              <a href="destinations.php" class="btn btn-ghost" style="font-size:13px">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- List -->
      <div class="admin-card">

        <?php if (isset($_GET['saved'])): ?>
        <div style="background:#DCFCE7;border:1px solid #86EFAC;border-radius:10px;padding:12px 18px;
             font-size:14px;font-weight:600;color:#15803D;margin-bottom:20px">
          Destino guardado correctamente.
        </div>
        <?php endif; ?>

        <form method="GET" class="filter-bar">
          <input type="text" name="q" placeholder="Buscar destino, país o continente…"
                 value="<?= htmlspecialchars($buscar) ?>" style="min-width:260px">
          <button type="submit" class="btn btn-primary">Buscar</button>
          <?php if ($buscar): ?>
            <a href="destinations.php" class="btn btn-ghost">Limpiar</a>
          <?php endif; ?>
          <span class="text-muted text-sm" style="margin-left:auto"><?= $total ?> destino<?= $total !== 1 ? 's' : '' ?></span>
        </form>

        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Nombre</th>
              <th>País</th>
              <th>Continente</th>
              <th>Paquetes</th>
              <th>Estado</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($destinos): ?>
              <?php foreach ($destinos as $d): ?>
              <tr>
                <td class="font-mono"><?= $d['id'] ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($d['nombre']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($d['pais']) ?></td>
                <td class="text-muted text-sm"><?= htmlspecialchars($d['continente']) ?></td>
                <td style="text-align:center">
                  <?php if ($d['num_paquetes'] > 0): ?>
                    <a href="packages.php?q=<?= urlencode($d['nombre']) ?>" style="font-weight:700;color:#2563eb"><?= (int)$d['num_paquetes'] ?></a>
                  <?php else: ?>
                    <span class="text-muted">0</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge badge-<?= $d['activo'] ? 'activo' : 'inactivo' ?>">
                    <?= $d['activo'] ? 'Activo' : 'Inactivo' ?>
                  </span>
                </td>
                <td>
                  <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                    <a href="destinations.php?edit=<?= $d['id'] ?>" class="btn btn-ghost" style="font-size:11px;padding:5px 10px">Editar</a>
                    <form method="POST" style="display:inline">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="dest_id" value="<?= $d['id'] ?>">
                      <input type="hidden" name="new_activo" value="<?= $d['activo'] ? '0' : '1' ?>">
                      <button type="submit" class="btn <?= $d['activo'] ? 'btn-warning' : 'btn-success' ?>" style="font-size:11px;padding:5px 10px">
                        <?= $d['activo'] ? 'Desactivar' : 'Activar' ?>
                      </button>
                    </form>
                    <?php if ((int)$d['num_paquetes'] === 0): ?>
                    <form method="POST" style="display:inline"
                          onsubmit="return confirm('¿Eliminar este destino? Esta acción no se puede deshacer.')">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="dest_id" value="<?= $d['id'] ?>">
                      <button type="submit" class="btn btn-danger" style="font-size:11px;padding:5px 10px">Eliminar</button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="7">
                <div class="empty-state">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                  <p>No se encontraron destinos.</p>
                </div>
              </td></tr>
            <?php endif; ?>
          </tbody>
        </table>

        <?php if ($pages > 1): ?>
        <div class="pagination">
          <span>Página <?= $page ?> de <?= $pages ?></span>
          <div class="pagination-links">
            <?php $qs = $buscar ? '&q=' . urlencode($buscar) : ''; ?>
            <button class="page-btn" onclick="location.href='?page=<?= max(1,$page-1) ?><?= $qs ?>'" <?= $page<=1?'disabled':'' ?>>← Ant.</button>
            <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
              <button class="page-btn <?= $i===$page?'active':'' ?>" onclick="location.href='?page=<?= $i ?><?= $qs ?>'"><?= $i ?></button>
            <?php endfor; ?>
            <button class="page-btn" onclick="location.href='?page=<?= min($pages,$page+1) ?><?= $qs ?>'" <?= $page>=$pages?'disabled':'' ?>>Sig. →</button>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>
</body>
</html>

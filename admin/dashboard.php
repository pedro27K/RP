<?php
require_once 'auth-check.php';
require_once '../api/db.php';

$db = obtenerBD();

$total_usuarios = $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$total_reservas = $db->query('SELECT COUNT(*) FROM reservas')->fetchColumn();
$total_paquetes = $db->query('SELECT COUNT(*) FROM paquetes WHERE activo = 1')->fetchColumn();
$total_ingresos = $db->query("SELECT COALESCE(SUM(precio_total), 0) FROM reservas WHERE estado != 'cancelada'")->fetchColumn();

$por_estado = $db->query('SELECT estado, COUNT(*) as total FROM reservas GROUP BY estado')->fetchAll();
$estado_map = [];
foreach ($por_estado as $row) $estado_map[$row['estado']] = $row['total'];

$recientes = $db->query("
    SELECT r.id, r.estado, r.precio_total, r.created_at,
           p.nombre AS paquete,
           COALESCE(CONCAT(u.nombre, ' ', u.apellidos), c.email, 'Invitado') AS cliente
    FROM reservas r
    JOIN paquetes p ON p.id = r.paquete_id
    LEFT JOIN usuarios u ON u.id = r.usuario_id
    LEFT JOIN contactos_reserva c ON c.reserva_id = r.id
    ORDER BY r.created_at DESC
    LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — RP Travels Admin</title>
  <link rel="stylesheet" href="../css/fonts.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-wrap">
  <?php include 'partials/sidebar.php'; ?>

  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Dashboard</h1>
      <div class="admin-user">
        <div class="admin-user-avatar"><?= strtoupper(substr($_SESSION['user_nombre'], 0, 1)) ?></div>
        <?= htmlspecialchars($_SESSION['user_nombre']) ?>
      </div>
    </div>

    <div class="admin-content">

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div>
            <div class="stat-label">Usuarios registrados</div>
            <div class="stat-value"><?= number_format($total_usuarios) ?></div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </div>
          <div>
            <div class="stat-label">Reservas totales</div>
            <div class="stat-value"><?= number_format($total_reservas) ?></div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon amber">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
          </div>
          <div>
            <div class="stat-label">Paquetes activos</div>
            <div class="stat-value"><?= number_format($total_paquetes) ?></div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon purple">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
          <div>
            <div class="stat-label">Ingresos totales</div>
            <div class="stat-value"><?= number_format($total_ingresos, 0, ',', '.') ?> €</div>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 280px;gap:24px">

        <!-- Reservas recientes -->
        <div class="admin-card">
          <div class="card-header">
            <span class="card-title">Reservas recientes</span>
            <a href="bookings.php" class="btn btn-ghost">Ver todas</a>
          </div>
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Paquete</th>
                <th>Importe</th>
                <th>Estado</th>
                <th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($recientes): ?>
                <?php foreach ($recientes as $r): ?>
                <tr>
                  <td class="font-mono"><?= $r['id'] ?></td>
                  <td><?= htmlspecialchars($r['cliente']) ?></td>
                  <td class="text-muted"><?= htmlspecialchars($r['paquete']) ?></td>
                  <td><?= number_format($r['precio_total'], 0, ',', '.') ?> €</td>
                  <td><span class="badge badge-<?= $r['estado'] ?>"><?= ucfirst($r['estado']) ?></span></td>
                  <td class="text-muted text-sm"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" class="empty-state">Sin reservas todavía</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Reservas por estado -->
        <div class="admin-card" style="align-self:start">
          <div class="card-header">
            <span class="card-title">Por estado</span>
          </div>
          <div style="padding:20px;display:flex;flex-direction:column;gap:12px">
            <?php
            $estados = ['pendiente' => 'Pendientes', 'confirmada' => 'Confirmadas', 'cancelada' => 'Canceladas'];
            foreach ($estados as $key => $label):
              $n = $estado_map[$key] ?? 0;
              $pct = $total_reservas > 0 ? round($n / $total_reservas * 100) : 0;
            ?>
            <div>
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px">
                <span class="badge badge-<?= $key ?>"><?= $label ?></span>
                <span style="font-weight:700;color:#0f172a"><?= $n ?></span>
              </div>
              <div style="height:6px;background:#f1f5f9;border-radius:99px;overflow:hidden">
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $key==='confirmada'?'#16a34a':($key==='pendiente'?'#d97706':'#ef4444') ?>;border-radius:99px"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
    </div><!-- /admin-content -->
  </div><!-- /admin-main -->
</div>
</body>
</html>

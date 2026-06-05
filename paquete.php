<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/destino_assets.php';
require_once __DIR__ . '/api/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: resultados.php');
    exit;
}

try {
    $db   = obtenerBD();
    $stmt = $db->prepare("
        SELECT p.*, d.nombre AS destino_nombre, d.pais, d.continente, d.descripcion AS destino_descripcion
        FROM paquetes p
        JOIN destinos d ON d.id = p.destino_id
        WHERE p.id = :id AND p.activo = 1
    ");
    $stmt->execute([':id' => $id]);
    $paquete = $stmt->fetch();

    if (!$paquete) {
        header('Location: resultados.php');
        exit;
    }

    // Coches disponibles
    $coches = $db->query("SELECT id, nombre, categoria, precio_dia, imagen FROM coches WHERE activo = 1 ORDER BY precio_dia ASC")->fetchAll();

    // Servicios incluidos (relación N:M via paquete_servicios)
    $stmtSvc = $db->prepare("
        SELECT s.nombre
        FROM servicios s
        JOIN paquete_servicios ps ON ps.servicio_id = s.id
        WHERE ps.paquete_id = :id AND s.activo = 1
        ORDER BY s.id
    ");
    $stmtSvc->execute([':id' => $id]);
    $servicios_incluidos = $stmtSvc->fetchAll();

    $atributos = json_decode($paquete['atributos'] ?? '[]', true) ?: [];

} catch (Exception $e) {
    header('Location: resultados.php');
    exit;
}

$precio    = (float)$paquete['precio_persona'];
$noches    = (int)$paquete['noches'];

$atributos_meta = [
    'cama_doble'           => ['Cama doble',          '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="2" y1="14" x2="22" y2="14"/>'],
    'camas_separadas'      => ['Camas separadas',     '<rect x="2" y="9" width="8" height="9" rx="2"/><rect x="14" y="9" width="8" height="9" rx="2"/><line x1="2" y1="18" x2="22" y2="18"/>'],
    'cama_king'            => ['King Size',            '<rect x="2" y="8" width="20" height="13" rx="2"/><path d="M2 14h20"/><path d="M9 5l3-3 3 3"/>'],
    'suite'                => ['Suite',                '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'],
    'habitacion_familiar'  => ['Habitación familiar', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    'wifi'                 => ['WiFi gratuito',        '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1"/>'],
    'desayuno_incluido'    => ['Desayuno incluido',    '<path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/>'],
    'piscina'              => ['Piscina',              '<path d="M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 17c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M8 7l4-4 4 4"/>'],
    'parking'              => ['Parking gratuito',     '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>'],
    'aire_acondicionado'   => ['Aire acondicionado',   '<path d="M9.59 4.59A2 2 0 1 1 11 8H2m10.59 11.41A2 2 0 1 0 14 16H2m15.73-8.27A2.5 2.5 0 1 1 19.5 12H2"/>'],
    'gimnasio'             => ['Gimnasio',             '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
    'spa'                  => ['Spa & bienestar',      '<path d="M12 22s6-6 6-10a6 6 0 0 0-12 0c0 4 6 10 6 10z"/><circle cx="12" cy="12" r="2"/>'],
    'restaurante'          => ['Restaurante',          '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>'],
    'bar'                  => ['Bar / pool bar',       '<path d="M8 22h8"/><path d="M7 10h10"/><path d="M12 15v7"/><path d="M12 15a5 5 0 0 0 5-5c0-2-.5-4-2-8H9c-1.5 4-2 6-2 8a5 5 0 0 0 5 5z"/>'],
    'terraza'              => ['Terraza / jardín',     '<circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/>'],
    'primera_linea_playa'  => ['Primera línea playa',  '<path d="M2 7c.6.5 1.2 1 2.5 1C7 8 7 6 9.5 6c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><line x1="12" y1="2" x2="12" y2="4"/>'],
    'acceso_playa'         => ['Cerca de playa',       '<path d="M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 17c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/>'],
    'centro_ciudad'        => ['Centro ciudad',        '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
    'cancelacion_gratuita' => ['Cancelación gratuita', '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
];
$imgSrc    = imagenDestino((int)$paquete['destino_id'], $paquete['imagen_url'], $IMGS_DESTINOS);
$imgStyle  = $imgSrc
    ? "background-image:url('" . htmlspecialchars($imgSrc) . "');background-size:cover;background-position:center"
    : '';
$paginaActiva = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($paquete['destino_nombre']) ?> — RP Travels</title>
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
  <link rel="stylesheet" href="css/footer.css">
  <style>
    .nav-tab { text-decoration: none; }
    .footer-link { text-decoration: none; }
    body { background: var(--n50, #f9fafb); }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/nav.php'; ?>

<div class="detail-layout" style="padding-top:24px">

  <!-- Columna izquierda -->
  <div>
    <a class="btn-back" href="javascript:history.back()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
      </svg>
      Volver atrás
    </a>

    <div class="detail-hero <?= $imgSrc ? '' : 'grad-default' ?>" style="<?= $imgStyle ?>">
      <div class="detail-hero-text">
        <div class="detail-hero-name"><?= htmlspecialchars($paquete['destino_nombre']) ?></div>
        <div class="detail-hero-sub"><?= $noches ?> noches · <?= htmlspecialchars($paquete['regimen']) ?></div>
      </div>
    </div>

    <?php if (!empty($atributos)): ?>
    <h2 class="detail-h2">Características del hotel</h2>
    <div class="attr-pills">
      <?php foreach ($atributos as $key):
        if (!isset($atributos_meta[$key])) continue;
        [$label, $iconPath] = $atributos_meta[$key];
      ?>
      <div class="attr-pill">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <?= $iconPath ?>
        </svg>
        <?= htmlspecialchars($label) ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h2 class="detail-h2">¿Qué incluye este paquete?</h2>
    <div class="inclusions-grid">
      <?php if ($servicios_incluidos): ?>
        <?php foreach ($servicios_incluidos as $svc): ?>
        <div class="inclusion-item">
          <div class="inclusion-check">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <?= htmlspecialchars($svc['nombre']) ?>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="color:#64748b;font-size:14px;grid-column:1/-1">Consulta con nosotros los servicios incluidos en este paquete.</p>
      <?php endif; ?>
    </div>

    <?php if ($paquete['tipo'] === 'circuito'): ?>
    <h2 class="detail-h2">Itinerario</h2>
    <div class="itinerary">
      <?php for ($d = 1; $d <= $noches + 1; $d++): ?>
      <div class="itinerary-item">
        <div class="itinerary-day">D<?= $d ?></div>
        <div class="itinerary-text">
          <?php if ($d === 1): ?>
            Llegada a <?= htmlspecialchars($paquete['destino_nombre']) ?>. Traslado al hotel, bienvenida y orientación del grupo.
          <?php elseif ($d <= ceil(($noches + 1) / 3)): ?>
            Visita a los principales monumentos y lugares de interés.
          <?php elseif ($d <= ceil(($noches + 1) * 2 / 3)): ?>
            Excursión incluida con guía local. Almuerzo tradicional de la región.
          <?php elseif ($d === $noches + 1): ?>
            Check-out. Traslado al aeropuerto. Vuelo de regreso.
          <?php else: ?>
            Tiempo libre para compras y actividades opcionales.
          <?php endif; ?>
        </div>
      </div>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($coches)): ?>
    <div class="car-rental-section">
      <h2 class="detail-h2">¿Necesitas vehículo?</h2>
      <p class="car-rental-intro">Selecciona un coche al hacer la reserva. El coste se añade al precio del viaje.</p>
      <div class="car-grid">
        <?php foreach ($coches as $c):
          $total_c = round((float)$c['precio_dia'] * $noches, 0);
        ?>
        <div class="car-card">
          <div class="car-card-img">
            <img src="assets/<?= htmlspecialchars($c['imagen']) ?>" alt="<?= htmlspecialchars($c['nombre']) ?>">
          </div>
          <div class="car-card-body">
            <div class="car-name"><?= htmlspecialchars($c['nombre']) ?></div>
            <div class="car-cat"><?= htmlspecialchars($c['categoria']) ?></div>
            <div class="car-price"><?= number_format($total_c, 0, ',', '.') ?>€ <span class="car-price-sub">(<?= (int)$c['precio_dia'] ?>€/noche)</span></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Panel de reserva (columna derecha) -->
  <div>
    <div class="booking-panel">
      <div class="booking-price-label">Precio por persona</div>
      <div class="booking-price-amount"><?= number_format($precio, 0, ',', '.') ?>€</div>
      <div class="booking-price-total">1 adulto · Total: <?= number_format($precio, 0, ',', '.') ?>€</div>
      <div class="booking-rows">
        <div class="booking-row">
          <span class="bk-key">Duración</span>
          <span class="bk-val"><?= $noches ?> noches</span>
        </div>
        <div class="booking-row">
          <span class="bk-key">Régimen</span>
          <span class="bk-val"><?= htmlspecialchars($paquete['regimen']) ?></span>
        </div>
        <?php if ($paquete['aerolinea']): ?>
        <div class="booking-row">
          <span class="bk-key">Aerolínea</span>
          <span class="bk-val"><?= htmlspecialchars($paquete['aerolinea']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($paquete['plazas_disponibles'] !== null): ?>
        <div class="booking-row">
          <span class="bk-key">Disponibilidad</span>
          <span class="bk-val">
            <?php if ($paquete['plazas_disponibles'] <= 0): ?>
              <span style="color:var(--red);font-weight:700">Sin plazas</span>
            <?php elseif ($paquete['plazas_disponibles'] <= 5): ?>
              <span style="color:#d97706;font-weight:700">¡Solo quedan <?= (int)$paquete['plazas_disponibles'] ?>!</span>
            <?php else: ?>
              <?= (int)$paquete['plazas_disponibles'] ?> plazas disponibles
            <?php endif; ?>
          </span>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($paquete['plazas_disponibles'] === null || $paquete['plazas_disponibles'] > 0): ?>
      <a class="btn-primary" href="reserva.php?id=<?= $paquete['id'] ?>" style="text-decoration:none;display:block;text-align:center">
        Reservar ahora
      </a>
      <?php else: ?>
      <button class="btn-primary" disabled style="opacity:.5;cursor:not-allowed">Sin disponibilidad</button>
      <?php endif; ?>
      <button class="btn-secondary">Solicitar información</button>
      <p class="booking-note">Cancelación gratuita hasta 30 días antes · Pago seguro · Mejor precio garantizado</p>
    </div>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>

<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/destino_assets.php';
require_once __DIR__ . '/api/db.php';

// Parámetros de búsqueda desde GET
$tipo       = $_GET['tipo']       ?? '';
$destino_id = (int)($_GET['destino_id'] ?? 0);
$precio_max = (float)($_GET['precio_max'] ?? 15000);
$regimen    = $_GET['regimen']    ?? '';
$noches_min = (int)($_GET['noches_min'] ?? 1);
$noches_max = (int)($_GET['noches_max'] ?? 30);
$sort       = $_GET['sort']       ?? 'recomendados';
$destino_texto = $_GET['destino'] ?? '';

$tipo_map = [
    'vuelos'    => 'vuelo',
    'hoteles'   => 'hotel',
    'paquetes'  => 'paquete',
    'cruceros'  => 'crucero',
    'circuitos' => 'circuito',
    'finde'     => 'finde',
];

$paginaActiva = $tipo ?: 'todos';

// Etiqueta de la sección
$etiquetas = [
    'vuelos' => 'Vuelos', 'hoteles' => 'Hoteles', 'paquetes' => 'Paquetes',
    'cruceros' => 'Cruceros', 'circuitos' => 'Circuitos', 'finde' => 'Fin de semana',
];
$seccion = $etiquetas[$tipo] ?? 'Todos los viajes';

try {
    $db = obtenerBD();

    // Construir la consulta con filtros
    $sql = "
        SELECT p.*, d.nombre AS destino_nombre, d.pais, d.continente
        FROM paquetes p
        JOIN destinos d ON d.id = p.destino_id
        WHERE p.activo = 1
          AND p.noches BETWEEN :noches_min AND :noches_max
          AND p.precio_persona <= :precio_max
    ";
    $params = [
        ':noches_min' => $noches_min,
        ':noches_max' => $noches_max,
        ':precio_max' => $precio_max,
    ];

    if ($tipo && isset($tipo_map[$tipo])) {
        $sql .= " AND p.tipo = :tipo";
        $params[':tipo'] = $tipo_map[$tipo];
    }

    if ($destino_id) {
        $sql .= " AND d.id = :destino_id";
        $params[':destino_id'] = $destino_id;
    } elseif ($destino_texto) {
        $sql .= " AND (d.nombre LIKE :dest OR d.pais LIKE :dest2 OR d.continente LIKE :dest3)";
        $params[':dest']  = "%$destino_texto%";
        $params[':dest2'] = "%$destino_texto%";
        $params[':dest3'] = "%$destino_texto%";
    }

    if ($regimen) {
        $sql .= " AND p.regimen = :regimen";
        $params[':regimen'] = $regimen;
    }

    $sql .= match($sort) {
        'price-asc'   => " ORDER BY p.precio_persona ASC",
        'price-desc'  => " ORDER BY p.precio_persona DESC",
        'noches-asc'  => " ORDER BY p.noches ASC",
        'noches-desc' => " ORDER BY p.noches DESC",
        default       => " ORDER BY p.badge_tipo DESC, p.precio_persona ASC",
    };
    $sql .= " LIMIT 50";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $paquetes = $stmt->fetchAll();

    // Destino seleccionado (para mostrar en el título)
    $destino_nombre = '';
    if ($destino_id) {
        $row = $db->prepare("SELECT nombre, pais FROM destinos WHERE id = ?");
        $row->execute([$destino_id]);
        $dest = $row->fetch();
        if ($dest) $destino_nombre = $dest['nombre'] . ', ' . $dest['pais'];
    }

} catch (Exception $e) {
    $paquetes = [];
}

$titulo = $destino_nombre ? "$seccion · $destino_nombre" : "$seccion · Todos los destinos";
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($titulo) ?> — RP Travels</title>
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
    body { background: var(--n50, #f9fafb); }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/nav.php'; ?>

<div class="results-header">
  <div class="results-header-inner">
    <h1><?= htmlspecialchars($titulo) ?></h1>
  </div>
</div>

<div class="results-layout">

  <!-- Panel de filtros (formulario GET) -->
  <aside class="filters-panel">
    <div class="filters-title">Filtrar resultados</div>
    <form action="resultados.php" method="GET">
      <?php if ($tipo): ?>
      <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
      <?php endif; ?>
      <?php if ($destino_id): ?>
      <input type="hidden" name="destino_id" value="<?= $destino_id ?>">
      <?php endif; ?>

      <div class="filter-group">
        <div class="filter-label">Precio máximo por persona</div>
        <input type="range" min="200" max="15000" value="<?= (int)$precio_max ?>" name="precio_max"
          oninput="document.getElementById('precio-val').textContent=this.value+'€'">
        <div style="font-size:13px;color:var(--n500);margin-top:4px">
          Hasta <strong id="precio-val"><?= (int)$precio_max ?>€</strong>
        </div>
      </div>

      <div class="filter-group">
        <div class="filter-label">Duración mínima (noches)</div>
        <select class="field-input" name="noches_min" style="font-size:13px;padding:8px 10px">
          <option value="1"  <?= $noches_min === 1  ? 'selected' : '' ?>>Cualquiera</option>
          <option value="6"  <?= $noches_min === 6  ? 'selected' : '' ?>>6+ noches</option>
          <option value="10" <?= $noches_min === 10 ? 'selected' : '' ?>>10+ noches</option>
        </select>
      </div>

      <div class="filter-group">
        <div class="filter-label">Régimen</div>
        <?php
        $regimenes = ['Todo incluido', 'Media pensión', 'Vuelo + hotel', 'Solo alojamiento'];
        foreach ($regimenes as $r):
        ?>
        <label class="filter-option">
          <input type="radio" name="regimen" value="<?= htmlspecialchars($r) ?>"
            <?= $regimen === $r ? 'checked' : '' ?>>
          <?= htmlspecialchars($r) ?>
        </label>
        <?php endforeach; ?>
        <label class="filter-option">
          <input type="radio" name="regimen" value="" <?= $regimen === '' ? 'checked' : '' ?>>
          Todos los regímenes
        </label>
      </div>

      <div class="filter-group">
        <div class="filter-label">Ordenar por</div>
        <select class="field-input" name="sort" style="font-size:13px;padding:8px 10px">
          <option value="recomendados" <?= $sort === 'recomendados' ? 'selected' : '' ?>>Recomendados</option>
          <option value="price-asc"   <?= $sort === 'price-asc'   ? 'selected' : '' ?>>Precio: menor a mayor</option>
          <option value="price-desc"  <?= $sort === 'price-desc'  ? 'selected' : '' ?>>Precio: mayor a menor</option>
          <option value="noches-asc"  <?= $sort === 'noches-asc'  ? 'selected' : '' ?>>Duración: menor a mayor</option>
          <option value="noches-desc" <?= $sort === 'noches-desc' ? 'selected' : '' ?>>Duración: mayor a menor</option>
        </select>
      </div>

      <button type="submit" class="btn-buscar" style="width:100%;justify-content:center;margin-top:8px">
        Aplicar filtros
      </button>
      <a href="resultados.php<?= $tipo ? '?tipo=' . htmlspecialchars($tipo) : '' ?>"
        class="btn-reset-filters" style="display:block;text-align:center;text-decoration:none;margin-top:8px">
        Limpiar filtros
      </a>
    </form>
  </aside>

  <!-- Resultados -->
  <div class="results-main">
    <div class="results-top">
      <div class="results-count">
        <h2><?= htmlspecialchars($seccion) ?></h2>
        <p><?= count($paquetes) ?> paquete<?= count($paquetes) !== 1 ? 's' : '' ?> disponible<?= count($paquetes) !== 1 ? 's' : '' ?></p>
      </div>
    </div>

    <div id="results-list">
      <?php if (empty($paquetes)): ?>
      <div style="text-align:center;padding:80px 20px;color:var(--n400)">
        <p style="font-size:16px;font-weight:600;color:var(--n600);margin-bottom:8px">No se encontraron paquetes</p>
        <p style="font-size:14px">Prueba a ajustar los filtros o busca otro destino.</p>
        <a href="resultados.php" class="btn-buscar" style="margin:20px auto 0;display:inline-flex">Ver todos los viajes</a>
      </div>
      <?php else: ?>
      <?php foreach ($paquetes as $p):
        $badgeClass = $p['badge_tipo'] === 'oferta' ? 'oferta' : ($p['badge_tipo'] === 'urgente' ? 'urgente' : '');
        $precio     = (float)$p['precio_persona'];
        $estrellas  = (int)($p['estrellas'] ?? 4);
        $esHotel    = $p['tipo'] === 'hotel';
        $imgSrc     = imagenDestino((int)$p['destino_id'], $p['imagen_url'], $IMGS_DESTINOS);
        $imgStyle   = $imgSrc ? "style='background-image:url(" . htmlspecialchars($imgSrc) . ");background-size:cover;background-position:center'" : '';
      ?>
      <a class="result-card" href="paquete.php?id=<?= $p['id'] ?>" style="text-decoration:none;color:inherit">
        <div class="result-img <?= $p['imagen_url'] ? '' : 'grad-default' ?>" <?= $imgStyle ?>>
          <?php if ($p['badge']): ?>
          <span class="dest-badge <?= htmlspecialchars($badgeClass) ?>" style="position:absolute;top:10px;left:10px">
            <?= htmlspecialchars($p['badge']) ?>
          </span>
          <?php endif; ?>
          <?php if ($esHotel): ?>
          <span class="hotel-stars-badge"><?= str_repeat('★', $estrellas) ?> <?= $estrellas ?> estrellas</span>
          <?php endif; ?>
        </div>
        <div class="result-body">
          <div class="result-title">
            <?= htmlspecialchars($p['destino_nombre']) ?> · <?= (int)$p['noches'] ?> noches
          </div>
          <div class="result-meta">
            <span class="result-meta-item">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 22V8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14"/><path d="M2 22h20"/><path d="M9 22V12h6v10"/></svg>
              <?= htmlspecialchars($p['regimen']) ?>
            </span>
            <?php if ($p['aerolinea'] && !$esHotel): ?>
            <span class="result-meta-item">
              <?= htmlspecialchars($p['aerolinea']) ?>
            </span>
            <?php endif; ?>
            <?php if ($p['pais']): ?>
            <span class="result-meta-item"><?= htmlspecialchars($p['pais']) ?></span>
            <?php endif; ?>
          </div>
          <div class="result-tags">
            <?php if ($esHotel): ?>
            <span class="result-tag blue">Hotel</span>
            <?php else: ?>
            <span class="result-tag blue">Vuelo incluido</span>
            <?php endif; ?>
            <span class="result-tag gray">Cancelación gratuita</span>
          </div>
        </div>
        <div class="result-price-panel">
          <div class="result-price-label">Desde</div>
          <div class="result-price-amount"><?= number_format($precio, 0, ',', '.') ?>€</div>
          <div class="result-price-unit">por persona</div>
          <span class="btn-buscar" style="font-size:14px;padding:10px 20px">Ver oferta</span>
        </div>
      </a>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /results-layout -->

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>

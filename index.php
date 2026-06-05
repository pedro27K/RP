<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/destino_assets.php';
require_once __DIR__ . '/api/db.php';

$paginaActiva = '';

// Cargar destinos y orígenes para los selects del buscador
try {
    $db      = obtenerBD();
    $destinos = $db->query("SELECT id, nombre, pais FROM destinos WHERE activo = 1 ORDER BY nombre")->fetchAll();
    $origenes = $db->query("SELECT id, nombre, codigo FROM ciudades_origen WHERE activo = 1 ORDER BY nombre")->fetchAll();

    // Destinos disponibles agrupados por tipo de paquete
    $destinosPorTipoRaw = $db->query("
        SELECT DISTINCT d.id, d.nombre, d.pais, p.tipo
        FROM destinos d
        JOIN paquetes p ON p.destino_id = d.id
        WHERE p.activo = 1 AND d.activo = 1
        ORDER BY p.tipo, d.nombre
    ")->fetchAll();
    $destinosPorTipo = [];
    foreach ($destinosPorTipoRaw as $row) {
        $destinosPorTipo[$row['tipo']][] = ['id' => (int)$row['id'], 'nombre' => $row['nombre'], 'pais' => $row['pais']];
    }

    // Destinos populares: primer paquete de cada destino (subquery para MySQL 8)
    $populares = $db->query("
        SELECT p.id, p.destino_id, p.precio_persona, p.noches, p.regimen, p.imagen_url, p.badge, p.badge_tipo,
               d.nombre AS destino_nombre, d.pais
        FROM paquetes p
        JOIN destinos d ON d.id = p.destino_id
        WHERE p.activo = 1
          AND p.id IN (
              SELECT MIN(id) FROM paquetes WHERE activo = 1 GROUP BY destino_id
          )
        ORDER BY p.badge_tipo DESC, p.precio_persona ASC
        LIMIT 8
    ")->fetchAll();

    // Oferta flash: paquete más económico con badge de oferta o urgente
    $ofertaFlash = $db->query("
        SELECT p.id, p.nombre, p.precio_persona, p.precio_original, p.noches, p.regimen, p.badge_tipo,
               d.nombre AS destino_nombre, d.pais
        FROM paquetes p
        JOIN destinos d ON d.id = p.destino_id
        WHERE p.activo = 1 AND p.badge_tipo IN ('oferta', 'urgente')
        ORDER BY p.precio_persona ASC
        LIMIT 1
    ")->fetch();

} catch (Exception $e) {
    $destinos = $origenes = $populares = [];
    $ofertaFlash = null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RP Travels — Descubre el mundo</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>✈</text></svg>">
  <meta name="description" content="Las mejores ofertas en vuelos, hoteles y paquetes vacacionales.">
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
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/nav.php'; ?>

<!-- HERO -->
<section class="hero">
  <video class="hero-video" autoplay muted loop playsinline preload="auto">
    <source src="assets/fondo.webm" type="video/webm">
  </video>
  <div class="hero-inner">
    <p class="hero-tag">Tu próxima aventura comienza aquí</p>
    <h1 class="hero-title">Descubre el mundo con RP Travels</h1>

    <!-- Buscador -->
    <form class="search-card" action="resultados.php" method="GET" id="search-form">
      <input type="hidden" name="tipo" id="campo-tipo" value="vuelos">

      <!-- Pestañas -->
      <div class="search-tabs">
        <button type="button" class="search-tab active" data-tipo="vuelos" onclick="cambiarTab(this)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="11" y="2" width="2" height="20" rx="1" fill="currentColor"/>
            <polygon points="11,10 3,13 3,16 11,13" fill="currentColor"/>
            <polygon points="13,10 21,13 21,16 13,13" fill="currentColor"/>
          </svg>
          Vuelos
        </button>
        <button type="button" class="search-tab" data-tipo="hoteles" onclick="cambiarTab(this)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 22V8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14"/><path d="M2 22h20"/><path d="M9 22V12h6v10"/>
          </svg>
          Hoteles
        </button>
        <button type="button" class="search-tab" data-tipo="paquetes" onclick="cambiarTab(this)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m7.5 4.27 9 5.15"/>
            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
            <path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>
          </svg>
          Paquetes
        </button>
        <button type="button" class="search-tab" data-tipo="cruceros" onclick="cambiarTab(this)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1 .6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/>
            <path d="M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.2.7 4.3 1.62 6"/>
            <path d="M10 3.3 8 2v6l3 4 3-4V2l-2 1.3"/>
          </svg>
          Cruceros
        </button>
        <button type="button" class="search-tab" data-tipo="circuitos" onclick="cambiarTab(this)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>
          </svg>
          Circuitos
        </button>
        <button type="button" class="search-tab" data-tipo="finde" onclick="cambiarTab(this)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
          </svg>
          Fin de semana
        </button>
      </div>

      <!-- Campos de búsqueda -->
      <div class="search-fields vuelos" id="sf-vuelos">
        <div class="field-group">
          <label class="field-label">Origen</label>
          <select class="field-input" name="origen_id">
            <option value="">Selecciona origen</option>
            <?php foreach ($origenes as $o): ?>
            <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['nombre']) ?> (<?= htmlspecialchars($o['codigo']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field-group">
          <label class="field-label">Destino</label>
          <select class="field-input" name="destino_id">
            <option value="">¿A dónde quieres ir?</option>
          </select>
        </div>
        <button type="submit" class="btn-buscar">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          Buscar
        </button>
      </div>

      <div class="search-fields hoteles hidden" id="sf-hoteles">
        <div class="field-group">
          <label class="field-label">Destino</label>
          <select class="field-input" name="destino_id">
            <option value="">¿A dónde quieres ir?</option>
          </select>
        </div>
        <button type="submit" class="btn-buscar">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          Buscar
        </button>
      </div>

      <div class="search-fields paquetes hidden" id="sf-paquetes">
        <div class="field-group">
          <label class="field-label">Destino</label>
          <select class="field-input" name="destino_id">
            <option value="">¿A dónde quieres ir?</option>
          </select>
        </div>
        <button type="submit" class="btn-buscar">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          Buscar
        </button>
      </div>

      <div class="search-fields cruceros hidden" id="sf-cruceros" style="grid-template-columns:1fr auto">
        <div class="field-group">
          <label class="field-label">Destino</label>
          <select class="field-input" name="destino_id">
            <option value="">Selecciona destino</option>
          </select>
        </div>
        <button type="submit" class="btn-buscar">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          Buscar
        </button>
      </div>

      <div class="search-fields circuitos hidden" id="sf-circuitos" style="grid-template-columns:1fr auto">
        <div class="field-group">
          <label class="field-label">Destino o región</label>
          <select class="field-input" name="destino_id">
            <option value="">¿Qué zona quieres recorrer?</option>
          </select>
        </div>
        <button type="submit" class="btn-buscar">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          Buscar
        </button>
      </div>

      <div class="search-fields finde hidden" id="sf-finde" style="grid-template-columns:1fr auto">
        <div class="field-group">
          <label class="field-label">Ciudad de origen</label>
          <select class="field-input" name="origen_id">
            <?php foreach ($origenes as $o): ?>
            <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn-buscar">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          Buscar
        </button>
      </div>

    </form>
  </div>
</section>

<!-- Trust badges -->
<section class="trust-bar">
  <div class="trust-grid">
    <div class="trust-item">
      <div class="trust-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div>
        <div class="trust-title">Mejor precio garantizado</div>
        <div class="trust-desc">Encontramos la mejor tarifa para ti</div>
      </div>
    </div>
    <div class="trust-item">
      <div class="trust-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.64 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.29 6.29l.75-.76a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
      </div>
      <div>
        <div class="trust-title">Atención 24/7</div>
        <div class="trust-desc">Asistencia en todo el mundo</div>
      </div>
    </div>
    <div class="trust-item">
      <div class="trust-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
      </div>
      <div>
        <div class="trust-title">+20 años de experiencia</div>
        <div class="trust-desc">Expertos en viajes a tu servicio</div>
      </div>
    </div>
    <div class="trust-item">
      <div class="trust-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
          <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
        </svg>
      </div>
      <div>
        <div class="trust-title">Pago seguro SSL</div>
        <div class="trust-desc">Todas las transacciones encriptadas</div>
      </div>
    </div>
  </div>
</section>

<!-- Destinos populares -->
<div class="section" style="padding-top:40px">
  <div class="section-header">
    <div>
      <h2 class="section-title">Destinos más populares</h2>
      <p class="section-subtitle">Los favoritos de nuestros viajeros esta temporada</p>
    </div>
    <a class="section-link" href="resultados.php">Ver todos
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>
  <div class="dest-grid">
    <?php foreach ($populares as $p):
      $badgeClass = $p['badge_tipo'] === 'oferta' ? 'oferta' : ($p['badge_tipo'] === 'urgente' ? 'urgente' : '');
      $imgSrc     = imagenDestino((int)$p['destino_id'], $p['imagen_url'], $IMGS_DESTINOS);
      $imgStyle   = $imgSrc ? "style='background-image:url(" . htmlspecialchars($imgSrc) . ");background-size:cover;background-position:center'" : '';
    ?>
    <a class="dest-card" href="paquete.php?id=<?= $p['id'] ?>" style="text-decoration:none;color:inherit">
      <div class="dest-img <?= $p['imagen_url'] ? '' : 'grad-default' ?>" <?= $imgStyle ?>>
        <?php if ($p['badge']): ?>
        <span class="dest-badge <?= htmlspecialchars($badgeClass) ?>"><?= htmlspecialchars($p['badge']) ?></span>
        <?php endif; ?>
      </div>
      <div class="dest-body">
        <div class="dest-meta"><?= (int)$p['noches'] ?> noches · <?= htmlspecialchars($p['regimen']) ?></div>
        <div class="dest-name"><?= htmlspecialchars($p['destino_nombre']) ?></div>
        <div class="dest-footer">
          <div>
            <div class="dest-desde">Desde</div>
            <div class="dest-price"><?= number_format((float)$p['precio_persona'], 0, ',', '.') ?>€ <span>p.p.</span></div>
          </div>
          <span class="dest-cta">Ver oferta →</span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($ofertaFlash): ?>
<!-- Banner oferta flash (dinámico desde BD) -->
<div style="padding:0 40px 60px;max-width:1320px;margin-inline:auto">
  <div class="offer-inner">
    <div class="offer-content">
      <span class="offer-tag"><?= $ofertaFlash['badge_tipo'] === 'urgente' ? 'OFERTA URGENTE · PLAZAS LIMITADAS' : 'OFERTA ESPECIAL DESTACADA' ?></span>
      <h2 class="offer-title">
        <?= htmlspecialchars($ofertaFlash['destino_nombre']) ?> desde <?= number_format((float)$ofertaFlash['precio_persona'], 0, ',', '.') ?>€
        <br><span style="font-weight:400;font-size:22px;opacity:0.85">
          <?= htmlspecialchars($ofertaFlash['regimen']) ?> · <?= (int)$ofertaFlash['noches'] ?> noches
        </span>
      </h2>
      <?php if ($ofertaFlash['precio_original']): ?>
      <p class="offer-subtitle">
        Antes: <s><?= number_format((float)$ofertaFlash['precio_original'], 0, ',', '.') ?>€</s>
        · Ahorra <?= number_format((float)$ofertaFlash['precio_original'] - (float)$ofertaFlash['precio_persona'], 0, ',', '.') ?>€ por persona
      </p>
      <?php else: ?>
      <p class="offer-subtitle">Consulta disponibilidad y fechas al reservar</p>
      <?php endif; ?>
    </div>
    <div class="offer-price-panel">
      <div class="offer-price-label">Precio especial</div>
      <div class="offer-price-amount"><?= number_format((float)$ofertaFlash['precio_persona'], 0, ',', '.') ?>€</div>
      <div class="offer-price-unit">por persona</div>
      <a class="btn-offer" href="paquete.php?id=<?= (int)$ofertaFlash['id'] ?>">Reservar ahora</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
const destinosPorTipo = <?= json_encode($destinosPorTipo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

const tabTipoMap = { vuelos:'vuelo', hoteles:'hotel', paquetes:'paquete', cruceros:'crucero', circuitos:'circuito' };

const tabPlaceholders = {
  vuelos: '¿A dónde quieres ir?',
  hoteles: '¿A dónde quieres ir?',
  paquetes: '¿A dónde quieres ir?',
  cruceros: 'Selecciona destino',
  circuitos: '¿Qué zona quieres recorrer?'
};

function actualizarDestinos(tab) {
  if (tab === 'finde') return;
  const tipo = tabTipoMap[tab];
  const destinos = destinosPorTipo[tipo] || [];
  const panel = document.getElementById('sf-' + tab);
  if (!panel) return;
  const select = panel.querySelector('select[name="destino_id"]');
  if (!select) return;
  const placeholder = tabPlaceholders[tab] || '¿A dónde quieres ir?';
  select.innerHTML = '<option value="">' + placeholder + '</option>';
  destinos.forEach(function(d) {
    const opt = document.createElement('option');
    opt.value = d.id;
    opt.textContent = d.nombre + ', ' + d.pais;
    select.appendChild(opt);
  });
}

function cambiarTab(btn) {
  document.querySelectorAll('.search-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const tipo = btn.dataset.tipo;
  document.getElementById('campo-tipo').value = tipo;
  ['vuelos','hoteles','paquetes','cruceros','circuitos','finde'].forEach(t => {
    const el = document.getElementById('sf-' + t);
    if (el) el.classList.toggle('hidden', t !== tipo);
  });
  actualizarDestinos(tipo);
}

// Inicializar destinos del tab activo al cargar la página
actualizarDestinos('vuelos');

// Deshabilitar campos de pestañas ocultas antes de enviar el formulario
// para que no sobreescriban los valores de la pestaña activa
document.getElementById('search-form').addEventListener('submit', function() {
  document.querySelectorAll('.search-fields.hidden input, .search-fields.hidden select').forEach(function(el) {
    el.disabled = true;
  });
});
</script>

</body>
</html>

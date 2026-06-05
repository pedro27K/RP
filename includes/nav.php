<?php
$usuario = usuarioActual();
$paginaActiva = $paginaActiva ?? '';
?>
<nav class="nav" id="main-nav">
  <a class="nav-logo" href="index.php" style="text-decoration:none;cursor:pointer">
    <div class="nav-logo-icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
        <g transform="translate(12,12) rotate(-45)">
          <rect x="-1.8" y="-9" width="3.6" height="18" rx="1.8" fill="white" />
          <polygon points="1.8,-1.5 12,4.5 12,6 -1.8,1.5" fill="white" />
          <polygon points="-1.8,-1.5 -12,4.5 -12,6 1.8,1.5" fill="white" />
          <polygon points="-1.8,6.5 -6,11.5 -5.5,12.5 1.8,9" fill="white" />
          <polygon points="1.8,6.5 6,11.5 5.5,12.5 -1.8,9" fill="white" />
        </g>
      </svg>
    </div>
    <div class="nav-logo-text">
      <span class="nav-logo-rp">RP</span>
      <span class="nav-logo-travels">TRAVELS</span>
    </div>
  </a>

  <div class="nav-tabs">
    <a class="nav-tab <?= $paginaActiva === 'vuelos'    ? 'active' : '' ?>" href="resultados.php?tipo=vuelos">Vuelos</a>
    <a class="nav-tab <?= $paginaActiva === 'hoteles'   ? 'active' : '' ?>" href="resultados.php?tipo=hoteles">Hoteles</a>
    <a class="nav-tab <?= $paginaActiva === 'paquetes'  ? 'active' : '' ?>" href="resultados.php?tipo=paquetes">Paquetes</a>
    <a class="nav-tab <?= $paginaActiva === 'cruceros'  ? 'active' : '' ?>" href="resultados.php?tipo=cruceros">Cruceros</a>
    <a class="nav-tab <?= $paginaActiva === 'circuitos' ? 'active' : '' ?>" href="resultados.php?tipo=circuitos">Circuitos</a>
    <a class="nav-tab <?= $paginaActiva === 'finde'     ? 'active' : '' ?>" href="resultados.php?tipo=finde">Fin de semana</a>
    <a class="nav-tab nav-tab-todos <?= $paginaActiva === 'todos'  ? 'active' : '' ?>" href="resultados.php">Todos los viajes</a>
  </div>

  <div class="nav-actions">
    <?php if ($usuario && $usuario['rol'] === 0): ?>
    <a class="btn-admin" href="admin/dashboard.php">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
        stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px">
        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
      </svg>
      Panel de administrador
    </a>
    <?php endif; ?>
    <span class="nav-phone">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
        stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.64 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.29 6.29l.75-.76a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
      </svg>
      900 123 456
    </span>
    <?php if ($usuario): ?>
    <a class="btn-cuenta" href="perfil.php" style="background:var(--green);color:white;text-decoration:none">
      <?= htmlspecialchars($usuario['nombre']) ?>
    </a>
    <?php else: ?>
    <a class="btn-cuenta" href="login.php" style="text-decoration:none">Mi cuenta</a>
    <?php endif; ?>
  </div>
</nav>

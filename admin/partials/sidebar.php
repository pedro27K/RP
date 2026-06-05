<?php
$current = basename($_SERVER['PHP_SELF']);
function navLink(string $file, string $label, string $icon, string $current): string {
    $active = ($current === $file) ? ' active' : '';
    return "<a href=\"{$file}\" class=\"nav-link{$active}\">{$icon}<span>{$label}</span></a>";
}
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
        <g transform="translate(12,12) rotate(-45)">
          <rect x="-1.8" y="-9" width="3.6" height="18" rx="1.8" fill="white"/>
          <polygon points="1.8,-1.5 12,4.5 12,6 -1.8,1.5" fill="white"/>
          <polygon points="-1.8,-1.5 -12,4.5 -12,6 1.8,1.5" fill="white"/>
        </g>
      </svg>
    </div>
    <div>
      <div class="sidebar-rp">RP TRAVELS</div>
      <div class="sidebar-sub">Administración</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">General</div>
    <?= navLink('dashboard.php', 'Dashboard', '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>', $current) ?>

    <div class="nav-section-label" style="margin-top:16px">Gestión</div>
    <?= navLink('bookings.php', 'Reservas', '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>', $current) ?>
    <?= navLink('packages.php', 'Paquetes', '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>', $current) ?>
    <?= navLink('destinations.php', 'Destinos', '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>', $current) ?>
    <?= navLink('users.php', 'Usuarios', '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>', $current) ?>
  </nav>

  <div class="sidebar-footer">
    <a href="../index.php" class="nav-link" target="_blank">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      <span>Ver la web</span>
    </a>
    <a href="logout.php" class="nav-link nav-link-danger">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span>Cerrar sesión</span>
    </a>
  </div>
</aside>

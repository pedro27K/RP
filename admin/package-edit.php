<?php
require_once 'auth-check.php';
require_once '../api/db.php';

$db = obtenerBD();

$id     = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$errors = [];
$ok     = false;

// Cargar destinos select
$destinos = $db->query("SELECT id, nombre, pais FROM destinos WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Cargar servicios
$todosServicios = $db->query("SELECT id, nombre FROM servicios WHERE activo = 1 ORDER BY id")->fetchAll();
$serviciosAsignados = [];

$tipos    = ['vuelo','hotel','paquete','crucero','circuito','finde'];
$regimenes = ['Todo incluido','Media pensión','Solo alojamiento','Vuelo + hotel','Vuelo + traslados'];
$badge_tipos = ['info','oferta','popular','urgente'];

$assetsDir     = __DIR__ . '/../assets/paquetes/';
$assetsUrlBase = '../assets/paquetes/';

// Cargar los existenses al editar.
$p = [
    'nombre' => '', 'destino_id' => '', 'tipo' => 'paquete',
    'precio_persona' => '', 'precio_original' => '',
    'noches' => '', 'regimen' => 'Todo incluido',
    'aerolinea' => '', 'estrellas' => 4,
    'badge' => '', 'badge_tipo' => 'info',
    'plazas_disponibles' => 20, 'activo' => 1,
    'descripcion' => '', 'imagen_url' => '',
    'atributos' => [],
];

if ($isEdit) {
    $stmt = $db->prepare("SELECT * FROM paquetes WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { header('Location: packages.php'); exit; }
    $p = array_merge($p, $row);
    $p['atributos'] = json_decode($p['atributos'] ?? '[]', true) ?: [];

    $stmt2 = $db->prepare("SELECT servicio_id FROM paquete_servicios WHERE paquete_id = ?");
    $stmt2->execute([$id]);
    $serviciosAsignados = $stmt2->fetchAll(PDO::FETCH_COLUMN, 0);
}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p['nombre']             = trim($_POST['nombre'] ?? '');
    $p['destino_id']         = (int)($_POST['destino_id'] ?? 0);
    $p['tipo']               = in_array($_POST['tipo'] ?? '', $tipos, true) ? $_POST['tipo'] : 'paquete';
    $p['precio_persona']     = $_POST['precio_persona'] ?? '';
    $p['precio_original']    = $_POST['precio_original'] ?? '';
    $p['noches']             = (int)($_POST['noches'] ?? 0);
    $p['regimen']            = in_array($_POST['regimen'] ?? '', $regimenes, true) ? $_POST['regimen'] : 'Todo incluido';
    $p['aerolinea']          = trim($_POST['aerolinea'] ?? '');
    $p['estrellas']          = (int)($_POST['estrellas'] ?? 4);
    $p['badge']              = trim($_POST['badge'] ?? '');
    $p['badge_tipo']         = in_array($_POST['badge_tipo'] ?? '', $badge_tipos, true) ? $_POST['badge_tipo'] : 'info';
    $p['plazas_disponibles'] = $_POST['plazas_disponibles'] !== '' ? (int)$_POST['plazas_disponibles'] : null;
    $p['activo']             = isset($_POST['activo']) ? 1 : 0;
    $p['descripcion']        = trim($_POST['descripcion'] ?? '');
    $rawAttrs = $_POST['attr'] ?? [];
    $p['atributos'] = is_array($rawAttrs)
        ? array_values(array_filter($rawAttrs, fn($v) => is_string($v) && preg_match('/^[a-z_]+$/', $v) && strlen($v) <= 50))
        : [];

    // Subir imagen
    $nuevaImagen = null;
    if (!empty($_FILES['imagen']['name'])) {
        $file     = $_FILES['imagen'];
        $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxBytes = 5 * 1024 * 1024; // 5 MB
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mime     = $finfo->file($file['tmp_name']);

        if ($file['error'] !== UPLOAD_ERR_OK)       $errors[] = 'Error al subir el archivo.';
        elseif (!in_array($mime, $allowed, true))    $errors[] = 'Formato no permitido. Usa JPG, PNG, WebP o GIF.';
        elseif ($file['size'] > $maxBytes)           $errors[] = 'La imagen no puede superar 5 MB.';
        else {
            $ext        = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime];
            $filename   = 'paquete_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath   = $assetsDir . $filename;
            if (!is_dir($assetsDir)) mkdir($assetsDir, 0755, true);
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $errors[] = 'No se pudo guardar la imagen.';
            } else {
                // Delete old image if replacing
                if ($isEdit && !empty($p['imagen_url'])) {
                    $old = $assetsDir . basename($p['imagen_url']);
                    if (file_exists($old)) unlink($old);
                }
                $nuevaImagen = 'assets/paquetes/' . $filename;
            }
        }
    }

    // Validar
    if ($p['nombre'] === '' || strlen($p['nombre']) > 200) $errors[] = 'El nombre es obligatorio (máx. 200 caracteres).';
    if ($p['destino_id'] <= 0)                             $errors[] = 'Selecciona un destino.';
    if (!is_numeric($p['precio_persona']) || (float)$p['precio_persona'] <= 0) $errors[] = 'El precio por persona debe ser un número positivo.';
    if ($p['noches'] < 0 || $p['noches'] > 365)           $errors[] = 'Las noches deben estar entre 0 y 365.';

    if (!$errors) {
        $precioOrig = $p['precio_original'] !== '' && is_numeric($p['precio_original']) ? (float)$p['precio_original'] : null;
        $imgVal = $nuevaImagen ?? ($isEdit ? ($p['imagen_url'] ?: null) : null);

        if ($isEdit) {
            $stmt = $db->prepare("
                UPDATE paquetes SET
                    destino_id=?, nombre=?, descripcion=?, imagen_url=?, noches=?, regimen=?, tipo=?,
                    precio_persona=?, precio_original=?, estrellas=?, aerolinea=?,
                    badge=?, badge_tipo=?, activo=?, plazas_disponibles=?, atributos=?
                WHERE id=?
            ");
            $stmt->execute([
                $p['destino_id'], $p['nombre'], $p['descripcion'] ?: null, $imgVal, $p['noches'],
                $p['regimen'], $p['tipo'], (float)$p['precio_persona'], $precioOrig,
                $p['estrellas'], $p['aerolinea'] ?: null,
                $p['badge'] ?: null, $p['badge_tipo'], $p['activo'], $p['plazas_disponibles'],
                json_encode($p['atributos']) ?: '[]',
                $id,
            ]);
            $pkgId = $id;
        } else {
            $stmt = $db->prepare("
                INSERT INTO paquetes (destino_id, nombre, descripcion, imagen_url, noches, regimen, tipo,
                    precio_persona, precio_original, estrellas, aerolinea,
                    badge, badge_tipo, activo, plazas_disponibles, atributos)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $p['destino_id'], $p['nombre'], $p['descripcion'] ?: null, $imgVal, $p['noches'],
                $p['regimen'], $p['tipo'], (float)$p['precio_persona'], $precioOrig,
                $p['estrellas'], $p['aerolinea'] ?: null,
                $p['badge'] ?: null, $p['badge_tipo'], $p['activo'], $p['plazas_disponibles'],
                json_encode($p['atributos']) ?: '[]',
            ]);
            $pkgId = (int)$db->lastInsertId();
        }

        // Guardar relación N:M: servicios incluidos
        $db->prepare("DELETE FROM paquete_servicios WHERE paquete_id = ?")->execute([$pkgId]);
        $serviciosPost = array_filter(array_map('intval', $_POST['servicios'] ?? []), fn($v) => $v > 0);
        if ($serviciosPost) {
            $placeholders = implode(',', array_fill(0, count($serviciosPost), '(?,?)'));
            $vals = [];
            foreach ($serviciosPost as $sid) { $vals[] = $pkgId; $vals[] = $sid; }
            $db->prepare("INSERT INTO paquete_servicios (paquete_id, servicio_id) VALUES $placeholders")->execute($vals);
        }

        header('Location: packages.php?saved=1');
        exit;
    }
}

function hotelAttrIcon(string $key): string {
    static $icons = [
        'cama_doble'           => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="2" y1="14" x2="22" y2="14"/>',
        'camas_separadas'      => '<rect x="2" y="9" width="8" height="9" rx="2"/><rect x="14" y="9" width="8" height="9" rx="2"/><line x1="2" y1="18" x2="22" y2="18"/>',
        'cama_king'            => '<rect x="2" y="8" width="20" height="13" rx="2"/><path d="M2 14h20"/><path d="M9 5l3-3 3 3"/>',
        'suite'                => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'habitacion_familiar'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'wifi'                 => '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1"/>',
        'desayuno_incluido'    => '<path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/>',
        'piscina'              => '<path d="M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 17c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M8 7l4-4 4 4"/>',
        'parking'              => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>',
        'aire_acondicionado'   => '<path d="M9.59 4.59A2 2 0 1 1 11 8H2m10.59 11.41A2 2 0 1 0 14 16H2m15.73-8.27A2.5 2.5 0 1 1 19.5 12H2"/>',
        'gimnasio'             => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'spa'                  => '<path d="M12 22s6-6 6-10a6 6 0 0 0-12 0c0 4 6 10 6 10z"/><circle cx="12" cy="12" r="2"/>',
        'restaurante'          => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
        'bar'                  => '<path d="M8 22h8"/><path d="M7 10h10"/><path d="M12 15v7"/><path d="M12 15a5 5 0 0 0 5-5c0-2-.5-4-2-8H9c-1.5 4-2 6-2 8a5 5 0 0 0 5 5z"/>',
        'terraza'              => '<circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/>',
        'primera_linea_playa'  => '<path d="M2 7c.6.5 1.2 1 2.5 1C7 8 7 6 9.5 6c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><line x1="12" y1="2" x2="12" y2="4"/>',
        'acceso_playa'         => '<path d="M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 17c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/>',
        'centro_ciudad'        => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'cancelacion_gratuita' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'admite_mascotas'      => '<circle cx="7.5" cy="4" r="1.5"/><circle cx="16.5" cy="4" r="1.5"/><circle cx="4" cy="9.5" r="1.5"/><circle cx="20" cy="9.5" r="1.5"/><path d="M12 17c-2 0-4 1-5 3l-1 2h12l-1-2c-1-2-3-3-5-3z"/>',
        'accesible'            => '<circle cx="12" cy="4" r="2"/><path d="M12 6v7l-3 5"/><path d="M9 12h6"/>',
        'animacion'            => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
    ];
    return $icons[$key] ?? '<circle cx="12" cy="12" r="5"/>';
}

$hotelAttrGroups = [
    'Tipo de habitación' => [
        ['cama_doble',          'Cama doble'],
        ['camas_separadas',     'Camas separadas'],
        ['cama_king',           'King Size'],
        ['suite',               'Suite'],
        ['habitacion_familiar', 'Habitación familiar'],
    ],
    'Servicios' => [
        ['wifi',                'WiFi gratuito'],
        ['desayuno_incluido',   'Desayuno incluido'],
        ['piscina',             'Piscina'],
        ['parking',             'Parking gratuito'],
        ['aire_acondicionado',  'Aire acondicionado'],
        ['gimnasio',            'Gimnasio'],
        ['spa',                 'Spa & bienestar'],
        ['restaurante',         'Restaurante'],
        ['bar',                 'Bar / pool bar'],
        ['terraza',             'Terraza / jardín'],
    ],
    'Ubicación' => [
        ['primera_linea_playa', 'Primera línea playa'],
        ['acceso_playa',        'Cerca de playa'],
        ['centro_ciudad',       'Centro ciudad'],
    ],
    'Políticas' => [
        ['cancelacion_gratuita', 'Cancelación gratuita'],
        ['admite_mascotas',      'Admite mascotas'],
        ['accesible',            'Accesible'],
        ['animacion',            'Animación incluida'],
    ],
];

$pageTitle = $isEdit ? 'Editar paquete' : 'Nuevo paquete';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> — RP Travels Admin</title>
  <link rel="stylesheet" href="../css/fonts.css">
  <link rel="stylesheet" href="css/admin.css">
  <style>
    /* Layout principal del formulario */
    .pkg-layout {
      display: grid;
      grid-template-columns: 1fr 320px;
      gap: 24px;
      align-items: start;
    }

    /* Secciones del formulario */
    .pkg-section {
      background: white;
      border-radius: 14px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      margin-bottom: 20px;
      overflow: hidden;
    }
    .pkg-section:last-child { margin-bottom: 0; }

    .pkg-section-header {
      padding: 16px 24px;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .pkg-section-header h3 {
      font-size: 13px;
      font-weight: 700;
      color: #0f172a;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .pkg-section-icon {
      width: 28px;
      height: 28px;
      border-radius: 7px;
      background: #eff6ff;
      color: #2563eb;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .pkg-section-body { padding: 20px 24px; }

    /* Grupos de campos */
    .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 16px; }
    .form-group:last-child { margin-bottom: 0; }
    .form-group label { font-size: 12px; font-weight: 600; color: #475569; }
    .form-group input,
    .form-group select,
    .form-group textarea {
      padding: 9px 12px;
      border: 1.5px solid #e2e8f0;
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
      color: #1e293b;
      background: white;
      transition: border-color 150ms;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus { outline: none; border-color: #2563eb; }
    .form-group textarea { resize: vertical; min-height: 120px; }
    .form-hint { font-size: 11px; color: #94a3b8; margin-top: 3px; }

    /* Grids internos */
    .fg-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .fg-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }

    /* Checkbox activo */
    .toggle-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 14px;
      background: #f8fafc;
      border-radius: 10px;
      border: 1.5px solid #e2e8f0;
      cursor: pointer;
      transition: border-color 150ms;
    }
    .toggle-row:has(input:checked) { border-color: #2563eb; background: #eff6ff; }
    .toggle-row-label { font-size: 13px; font-weight: 600; color: #1e293b; }
    .toggle-row-sub { font-size: 11px; color: #64748b; margin-top: 2px; }
    .toggle-switch {
      width: 40px; height: 22px;
      background: #cbd5e1;
      border-radius: 11px;
      position: relative;
      flex-shrink: 0;
      transition: background 150ms;
    }
    .toggle-switch::after {
      content: '';
      position: absolute;
      top: 3px; left: 3px;
      width: 16px; height: 16px;
      background: white;
      border-radius: 50%;
      transition: transform 150ms;
      box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .toggle-row:has(input:checked) .toggle-switch { background: #2563eb; }
    .toggle-row:has(input:checked) .toggle-switch::after { transform: translateX(18px); }
    .toggle-row input[type=checkbox] { display: none; }

    /* Errores */
    .form-errors {
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-radius: 10px;
      padding: 14px 18px;
      margin-bottom: 20px;
    }
    .form-errors strong { display: block; color: #991b1b; font-size: 13px; margin-bottom: 6px; }
    .form-errors ul { margin: 0; padding-left: 18px; }
    .form-errors li { color: #b91c1c; font-size: 13px; line-height: 1.6; }

    /* Upload imagen */
    .img-preview-wrap {
      position: relative;
      display: inline-block;
      margin-bottom: 12px;
    }
    .img-preview-wrap img {
      display: block;
      height: 160px;
      width: 100%;
      object-fit: cover;
      border-radius: 10px;
      border: 1.5px solid #e2e8f0;
    }
    .img-preview-label {
      font-size: 11px;
      color: #64748b;
      margin-top: 6px;
    }
    .file-input-label {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 16px;
      border: 2px dashed #cbd5e1;
      border-radius: 10px;
      background: #f8fafc;
      font-size: 13px;
      font-weight: 500;
      color: #64748b;
      cursor: pointer;
      transition: all 150ms;
    }
    .file-input-label:hover { border-color: #2563eb; color: #2563eb; background: #eff6ff; }
    .file-input-label input[type=file] { display: none; }

    /* Badge preview */
    .badge-preview {
      display: inline-flex;
      align-items: center;
      padding: 3px 10px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 700;
    }
    .badge-preview.info    { background: #dbeafe; color: #1d4ed8; }
    .badge-preview.oferta  { background: #fef3c7; color: #92400e; }
    .badge-preview.popular { background: #d1fae5; color: #065f46; }
    .badge-preview.urgente { background: #fee2e2; color: #991b1b; }

    /* Barra de acciones fija abajo */
    .pkg-actions-bar {
      background: white;
      border-top: 1px solid #e2e8f0;
      padding: 16px 32px;
      display: flex;
      align-items: center;
      gap: 12px;
      position: sticky;
      bottom: 0;
      z-index: 10;
      box-shadow: 0 -4px 12px rgba(0,0,0,0.05);
    }
    .pkg-actions-bar .btn { padding: 9px 20px; font-size: 13px; }
    .pkg-actions-bar .btn-primary { padding: 9px 24px; }

    /* ── Atributos del hotel (chips estilo Trivago) ── */
    .attr-group { margin-bottom: 20px; }
    .attr-group:last-child { margin-bottom: 0; }
    .attr-group-title {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #94a3b8;
      margin-bottom: 10px;
    }
    .attr-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .attr-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 13px;
      border: 1.5px solid #e2e8f0;
      border-radius: 999px;
      font-size: 12.5px;
      font-weight: 500;
      color: #475569;
      cursor: pointer;
      transition: border-color 150ms, background 150ms, color 150ms;
      user-select: none;
      white-space: nowrap;
      background: #fff;
    }
    .attr-chip:hover { border-color: #93c5fd; background: #f0f9ff; color: #1e40af; }
    .attr-chip:has(input:checked) {
      border-color: #2563eb;
      background: #eff6ff;
      color: #1d4ed8;
      font-weight: 600;
    }
    .attr-chip input[type=checkbox] { display: none; }
    .attr-chip svg { flex-shrink: 0; opacity: 0.65; }
    .attr-chip:has(input:checked) svg { opacity: 1; }
    .attr-chip:has(input:checked)::before {
      content: '✓';
      font-size: 11px;
      font-weight: 700;
      margin-right: -2px;
    }
  </style>
</head>
<body>
<div class="admin-wrap">
  <?php include 'partials/sidebar.php'; ?>

  <div class="admin-main">
    <div class="admin-topbar">
      <h1>
        <a href="packages.php" style="color:inherit;text-decoration:none;opacity:0.5">Paquetes</a>
        <span style="opacity:0.35;margin:0 6px">/</span>
        <?= $pageTitle ?>
      </h1>
      <div class="admin-user">
        <div class="admin-user-avatar"><?= strtoupper(substr($_SESSION['admin']['nombre'], 0, 1)) ?></div>
        <?= htmlspecialchars($_SESSION['admin']['nombre']) ?>
      </div>
    </div>

    <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;flex:1;min-height:0">
      <div class="admin-content" style="flex:1">

        <?php if ($errors): ?>
        <div class="form-errors">
          <strong>Por favor corrige los siguientes errores:</strong>
          <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <div class="pkg-layout">

          <!-- ── Columna principal ── -->
          <div>

            <!-- Información del paquete -->
            <div class="pkg-section">
              <div class="pkg-section-header">
                <div class="pkg-section-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                  </svg>
                </div>
                <h3>Información del paquete</h3>
              </div>
              <div class="pkg-section-body">
                <div class="form-group">
                  <label for="nombre">Nombre del paquete <span style="color:#ef4444">*</span></label>
                  <input type="text" id="nombre" name="nombre" maxlength="200" required
                         placeholder="Ej: Hotel Barceló Bávaro Beach Resort 5*"
                         value="<?= htmlspecialchars($p['nombre']) ?>">
                </div>

                <div class="fg-2">
                  <div class="form-group">
                    <label for="destino_id">Destino <span style="color:#ef4444">*</span></label>
                    <select id="destino_id" name="destino_id" required>
                      <option value="">— Seleccionar —</option>
                      <?php foreach ($destinos as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= (int)$p['destino_id'] === $d['id'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars($d['nombre'] . ', ' . $d['pais']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="tipo">Tipo de viaje <span style="color:#ef4444">*</span></label>
                    <select id="tipo" name="tipo">
                      <?php foreach ($tipos as $t): ?>
                        <option value="<?= $t ?>" <?= $p['tipo'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="fg-3">
                  <div class="form-group">
                    <label for="precio_persona">Precio / persona (€) <span style="color:#ef4444">*</span></label>
                    <input type="number" id="precio_persona" name="precio_persona" min="0" step="0.01"
                           placeholder="0.00"
                           value="<?= htmlspecialchars($p['precio_persona']) ?>">
                  </div>
                  <div class="form-group">
                    <label for="precio_original">Precio original / tachado (€)</label>
                    <input type="number" id="precio_original" name="precio_original" min="0" step="0.01"
                           placeholder="0.00"
                           value="<?= htmlspecialchars($p['precio_original'] ?? '') ?>">
                    <span class="form-hint">Opcional — aparece tachado</span>
                  </div>
                  <div class="form-group">
                    <label for="noches">Noches</label>
                    <input type="number" id="noches" name="noches" min="0" max="365"
                           value="<?= (int)$p['noches'] ?>">
                  </div>
                </div>

                <div class="fg-3">
                  <div class="form-group">
                    <label for="regimen">Régimen</label>
                    <select id="regimen" name="regimen">
                      <?php foreach ($regimenes as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>" <?= $p['regimen'] === $r ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="aerolinea">Aerolínea</label>
                    <input type="text" id="aerolinea" name="aerolinea" maxlength="100"
                           placeholder="Ej: Iberia"
                           value="<?= htmlspecialchars($p['aerolinea'] ?? '') ?>">
                  </div>
                  <div class="form-group">
                    <label for="estrellas">Categoría hotel</label>
                    <select id="estrellas" name="estrellas">
                      <?php for ($s = 1; $s <= 5; $s++): ?>
                        <option value="<?= $s ?>" <?= (int)$p['estrellas'] === $s ? 'selected' : '' ?>><?= $s ?> estrellas</option>
                      <?php endfor; ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- Descripción -->
            <div class="pkg-section">
              <div class="pkg-section-header">
                <div class="pkg-section-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                  </svg>
                </div>
                <h3>Descripción</h3>
              </div>
              <div class="pkg-section-body">
                <div class="form-group" style="margin-bottom:0">
                  <label for="descripcion">Descripción del paquete <span style="color:#94a3b8;font-weight:400">(opcional)</span></label>
                  <textarea id="descripcion" name="descripcion" rows="5"
                            placeholder="Describe el alojamiento, servicios incluidos, actividades…"><?= htmlspecialchars($p['descripcion'] ?? '') ?></textarea>
                </div>
              </div>
            </div>

            <!-- Servicios incluidos (N:M) -->
            <div class="pkg-section">
              <div class="pkg-section-header">
                <div class="pkg-section-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                  </svg>
                </div>
                <h3>Servicios incluidos</h3>
              </div>
              <div class="pkg-section-body">
                <p class="form-hint" style="margin-bottom:12px">Selecciona los servicios que incluye este paquete</p>
                <div class="attr-chips">
                  <?php foreach ($todosServicios as $svc): ?>
                  <label class="attr-chip">
                    <input type="checkbox" name="servicios[]" value="<?= $svc['id'] ?>"
                           <?= in_array((int)$svc['id'], array_map('intval', $serviciosAsignados), true) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($svc['nombre']) ?>
                  </label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- Atributos del hotel -->
            <div class="pkg-section" id="section-hotel-attrs" style="display:none">
              <div class="pkg-section-header">
                <div class="pkg-section-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="2" y1="14" x2="22" y2="14"/>
                  </svg>
                </div>
                <h3>Atributos del hotel</h3>
              </div>
              <div class="pkg-section-body">
                <?php foreach ($hotelAttrGroups as $groupName => $attrs): ?>
                <div class="attr-group">
                  <div class="attr-group-title"><?= htmlspecialchars($groupName) ?></div>
                  <div class="attr-chips">
                    <?php foreach ($attrs as [$key, $label]): ?>
                    <label class="attr-chip">
                      <input type="checkbox" name="attr[]" value="<?= htmlspecialchars($key) ?>"
                             <?= in_array($key, $p['atributos'], true) ? 'checked' : '' ?>>
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <?= hotelAttrIcon($key) ?>
                      </svg>
                      <?= htmlspecialchars($label) ?>
                    </label>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

          </div>

          <!-- ── Columna lateral ── -->
          <div>

            <!-- Imagen -->
            <div class="pkg-section">
              <div class="pkg-section-header">
                <div class="pkg-section-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                  </svg>
                </div>
                <h3>Imagen</h3>
              </div>
              <div class="pkg-section-body">
                <?php if (!empty($p['imagen_url'])): ?>
                <div class="img-preview-wrap" style="width:100%">
                  <img src="<?= htmlspecialchars('../' . $p['imagen_url']) ?>" alt="Imagen actual">
                  <p class="img-preview-label">Imagen actual — sube una nueva para reemplazarla</p>
                </div>
                <?php endif; ?>
                <label class="file-input-label">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/>
                    <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                  </svg>
                  Subir imagen
                  <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp,image/gif"
                         onchange="this.closest('label').querySelector('span') && (this.closest('label').querySelector('span').textContent = this.files[0]?.name || 'Subir imagen')">
                </label>
                <p class="form-hint" style="margin-top:8px">JPG, PNG o WebP · Máx. 5 MB</p>
              </div>
            </div>

            <!-- Badge -->
            <div class="pkg-section">
              <div class="pkg-section-header">
                <div class="pkg-section-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                  </svg>
                </div>
                <h3>Badge</h3>
              </div>
              <div class="pkg-section-body">
                <div class="form-group">
                  <label for="badge">Texto del badge</label>
                  <input type="text" id="badge" name="badge" maxlength="50"
                         placeholder='Ej: Oferta, Más vendido…'
                         value="<?= htmlspecialchars($p['badge'] ?? '') ?>">
                </div>
                <div class="form-group" style="margin-bottom:0">
                  <label for="badge_tipo">Estilo</label>
                  <select id="badge_tipo" name="badge_tipo">
                    <?php foreach ($badge_tipos as $bt): ?>
                      <option value="<?= $bt ?>" <?= $p['badge_tipo'] === $bt ? 'selected' : '' ?>><?= ucfirst($bt) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <!-- Disponibilidad -->
            <div class="pkg-section">
              <div class="pkg-section-header">
                <div class="pkg-section-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                  </svg>
                </div>
                <h3>Disponibilidad</h3>
              </div>
              <div class="pkg-section-body">
                <div class="form-group">
                  <label for="plazas_disponibles">Plazas disponibles</label>
                  <input type="number" id="plazas_disponibles" name="plazas_disponibles" min="0"
                         placeholder="Sin límite"
                         value="<?= $p['plazas_disponibles'] !== null ? (int)$p['plazas_disponibles'] : '' ?>">
                  <span class="form-hint">Deja vacío para no limitar plazas</span>
                </div>
                <label class="toggle-row">
                  <div>
                    <div class="toggle-row-label">Paquete activo</div>
                    <div class="toggle-row-sub">Visible para los usuarios en la web</div>
                  </div>
                  <div class="toggle-switch"></div>
                  <input type="checkbox" id="activo" name="activo" value="1" <?= $p['activo'] ? 'checked' : '' ?>>
                </label>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- Barra de acciones fija -->
      <div class="pkg-actions-bar">
        <button type="submit" class="btn btn-primary">
          <?= $isEdit ? 'Guardar cambios' : 'Crear paquete' ?>
        </button>
        <a href="packages.php" class="btn btn-ghost">Cancelar</a>
      </div>
    </form>

  </div>
</div>
<script>
(function () {
  var tipoSel    = document.getElementById('tipo');
  var hotelSection = document.getElementById('section-hotel-attrs');
  function syncHotelSection() {
    hotelSection.style.display = tipoSel.value === 'hotel' ? '' : 'none';
  }
  tipoSel.addEventListener('change', syncHotelSection);
  syncHotelSection();
})();
</script>
</body>
</html>

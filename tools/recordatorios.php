<?php
/**
 * ------------------------------------------------------------
 * ------------------------------------------------------------
 *    Busca las reservas con salida en los próximos N días y envía
 *    a cada viajero un correo recordatorio. El envío se reparte
 *    entre varios procesos para acelerar el lote.
 *
 *  USO (sólo línea de comandos, dentro del contenedor):
 *    docker exec rp_app php tools/recordatorios.php            # simulacro (no envía)
 *    docker exec rp_app php tools/recordatorios.php --days=10 --workers=4
 *    docker exec rp_app php tools/recordatorios.php --send     # envía de verdad
 *
 *  Requisitos: extensión pcntl (Dockerfile).
 * ============================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script sólo se ejecuta por línea de comandos.\n");
    exit(1);
}
if (!function_exists('pcntl_fork')) {
    fwrite(STDERR, "ERROR: falta la extensión 'pcntl'. Reconstruye la imagen con el Dockerfile actualizado.\n");
    exit(1);
}

require_once __DIR__ . '/../api/config.php';   // define DB_HOST, DB_USER, DB_PASS, DB_NAME

// ── Argumentos ──────────────────────────────────────────────
$opts    = getopt('', ['days::', 'workers::', 'send']);
$days    = isset($opts['days'])    ? max(1, (int)$opts['days'])    : 7;
$workers = isset($opts['workers']) ? max(1, (int)$opts['workers']) : 4;
$send    = isset($opts['send']);   // sin --send => simulacro (dry-run)

/** Crea SIEMPRE una conexión nueva (no se puede compartir un PDO entre procesos tras fork). */
function connect(): PDO {
    return new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

// ── 1) El PADRE obtiene la lista de destinatarios ───────────
$db = connect();
$sql = "SELECT v.nombre, v.apellidos,
               COALESCE(c.email, u.email) AS email,
               r.referencia, r.fecha_salida,
               p.nombre AS paquete, d.nombre AS destino
        FROM reservas r
        JOIN viajeros v            ON v.reserva_id = r.id
        JOIN paquetes p            ON p.id = r.paquete_id
        JOIN destinos d            ON d.id = p.destino_id
        LEFT JOIN contactos_reserva c ON c.reserva_id = r.id
        LEFT JOIN usuarios u           ON u.id = r.usuario_id
        WHERE r.estado <> 'cancelada'
          AND r.fecha_salida BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :d DAY)
          AND COALESCE(c.email, u.email) IS NOT NULL
        ORDER BY r.fecha_salida";
$stmt = $db->prepare($sql);
$stmt->execute([':d' => $days]);
$destinatarios = $stmt->fetchAll();
$db = null;                                   // cerrar antes de hacer fork

$total = count($destinatarios);
echo "Recordatorios para los próximos {$days} días: {$total} destinatario(s).\n";
echo $send ? "Modo: ENVÍO REAL\n" : "Modo: SIMULACRO (usa --send para enviar)\n";

if ($total === 0) { echo "Nada que enviar.\n"; exit(0); }

// ── 2) Repartir el trabajo en 'workers' bloques ─────────────
$workers = min($workers, $total);
$bloques = array_chunk($destinatarios, (int)ceil($total / $workers));
$tmp     = sys_get_temp_dir();
$hijos   = [];

// ── 3) Lanzar un proceso hijo por bloque (pcntl_fork) ───────
foreach ($bloques as $i => $bloque) {
    $pid = pcntl_fork();

    if ($pid === -1) {
        fwrite(STDERR, "No se pudo crear el proceso hijo {$i}\n");
        exit(1);
    }

    if ($pid === 0) {
        // ===== CÓDIGO DEL HIJO (se ejecuta en paralelo) =====
        $enviados = 0;
        foreach ($bloque as $r) {
            $asunto = "Tu viaje a {$r['destino']} está cerca ✈";
            $fechaFormateada = date('d/m/Y', strtotime($r['fecha_salida']));
            $cuerpo = "Hola {$r['nombre']} {$r['apellidos']},\n\n"
                    . "Te recordamos tu reserva {$r['referencia']} ({$r['paquete']}).\n"
                    . "Fecha de salida: {$fechaFormateada}.\n\n¡Buen viaje!\nRP Travels";

            if ($send) {
                $ok = mail($r['email'], $asunto, $cuerpo,
                           "From: RP Travels <rptravels101@gmail.com>");
            } else {
                $ok = true;  // simulacro
            }
            if ($ok) { $enviados++; }
            echo "  [worker {$i} · pid " . getmypid() . "] -> {$r['email']}\n";
        }
        // Guardar el resultado del hijo para que el padre lo sume
        file_put_contents("{$tmp}/rp_worker_{$i}.cnt", (string)$enviados);
        exit(0);  // termina el hijo
    }

    // ===== El PADRE guarda el PID y sigue lanzando hijos =====
    $hijos[$pid] = $i;
}

// ── 4) El padre ESPERA a que terminen todos los hijos ───────
$totalEnviados = 0;
while (count($hijos) > 0) {
    $pid = pcntl_wait($status);
    if (isset($hijos[$pid])) {
        $i = $hijos[$pid];
        $f = "{$tmp}/rp_worker_{$i}.cnt";
        if (is_file($f)) { $totalEnviados += (int)file_get_contents($f); @unlink($f); }
        unset($hijos[$pid]);
    }
}

echo "\nHecho. Procesos usados: {$workers}. Recordatorios "
   . ($send ? "enviados" : "simulados") . ": {$totalEnviados}/{$total}.\n";
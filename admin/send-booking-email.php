<?php

/**
 * Envía un correo al cliente cuando hace una nueva reserva (estado: pendiente).
 *
 * $datos debe contener:
 *   email, referencia, destino, pais, paquete, noches, regimen,
 *   fecha_salida, fecha_regreso, num_adultos, num_ninos,
 *   seguro_cancelacion, precio_total
 *   Opcional: coche_nombre, precio_coche
 */
function enviarCorreoNuevaReserva(array $datos): bool
{
    $salida  = date('d/m/Y', strtotime($datos['fecha_salida']));
    $regreso = date('d/m/Y', strtotime($datos['fecha_regreso']));
    $viajeros = _formatViajeros((int)$datos['num_adultos'], (int)$datos['num_ninos']);
    $extras  = _buildCarExtras($datos['coche_nombre'] ?? null, $datos['precio_coche'] ?? 0);

    $subject = "Hemos recibido tu reserva {$datos['referencia']} – RP Travels";
    $html = _buildEmailHtml(
        titulo:      'Reserva recibida',
        intro:       'Hemos recibido tu solicitud de reserva. Nuestro equipo la revisará y recibirás un correo de confirmación en breve.',
        referencia:  $datos['referencia'],
        destino:     $datos['destino'] . ', ' . $datos['pais'],
        paquete:     $datos['paquete'],
        salida:      $salida,
        regreso:     $regreso,
        viajeros:    $viajeros,
        seguro:      $datos['seguro_cancelacion'] ? 'Sí (+10%)' : 'No',
        total:       number_format((float)$datos['precio_total'], 2, ',', '.') . ' €',
        badgeColor:  '#f59e0b',
        badgeTexto:  'Pendiente de confirmación',
        extras:      $extras
    );

    return _sendMail($datos['email'], $subject, $html);
}

/**
 * Envía un correo al cliente cuando el administrador confirma su reserva.
 *
 * $datos debe contener los campos devueltos por la consulta en bookings.php:
 *   cli_email, cli_nombre, referencia, paquete, destino, pais,
 *   fecha_salida, fecha_regreso, num_adultos, num_ninos,
 *   precio_total, seguro_cancelacion
 *   Opcional: coche_nombre, precio_coche
 */
function enviarCorreoConfirmacion(array $datos): bool
{
    $nombre  = trim($datos['cli_nombre'] ?? '');
    $saludo  = $nombre ? ", $nombre" : '';
    $salida  = date('d/m/Y', strtotime($datos['fecha_salida']));
    $regreso = date('d/m/Y', strtotime($datos['fecha_regreso']));
    $viajeros = _formatViajeros((int)$datos['num_adultos'], (int)$datos['num_ninos']);
    $extras  = _buildCarExtras($datos['coche_nombre'] ?? null, $datos['precio_coche'] ?? 0);

    $subject = "¡Tu reserva {$datos['referencia']} ha sido confirmada! – RP Travels";
    $html = _buildEmailHtml(
        titulo:      '¡Reserva confirmada!',
        intro:       "¡Buenas noticias{$saludo}! Tu reserva ha sido confirmada. Ya puedes empezar a preparar tu viaje.",
        referencia:  $datos['referencia'],
        destino:     $datos['destino'] . ', ' . $datos['pais'],
        paquete:     $datos['paquete'],
        salida:      $salida,
        regreso:     $regreso,
        viajeros:    $viajeros,
        seguro:      $datos['seguro_cancelacion'] ? 'Sí (+10%)' : 'No',
        total:       number_format((float)$datos['precio_total'], 2, ',', '.') . ' €',
        badgeColor:  '#22c55e',
        badgeTexto:  'Confirmada',
        extras:      $extras
    );

    return _sendMail($datos['cli_email'], $subject, $html);
}

/**
 * Envía un correo al cliente cuando su reserva es cancelada (por él mismo o por un admin).
 *
 * $datos debe contener los mismos campos que enviarCorreoConfirmacion().
 */
function enviarCorreoCancelacion(array $datos): bool
{
    $nombre  = trim($datos['cli_nombre'] ?? '');
    $saludo  = $nombre ? ", $nombre" : '';
    $salida  = date('d/m/Y', strtotime($datos['fecha_salida']));
    $regreso = date('d/m/Y', strtotime($datos['fecha_regreso']));
    $viajeros = _formatViajeros((int)$datos['num_adultos'], (int)$datos['num_ninos']);
    $extras  = _buildCarExtras($datos['coche_nombre'] ?? null, $datos['precio_coche'] ?? 0);

    $subject = "Tu reserva {$datos['referencia']} ha sido cancelada – RP Travels";
    $html = _buildEmailHtml(
        titulo:      'Reserva cancelada',
        intro:       "Hola{$saludo}. Te informamos de que tu reserva ha sido cancelada. Si tienes alguna duda, no dudes en ponerte en contacto con nosotros.",
        referencia:  $datos['referencia'],
        destino:     $datos['destino'] . ', ' . $datos['pais'],
        paquete:     $datos['paquete'],
        salida:      $salida,
        regreso:     $regreso,
        viajeros:    $viajeros,
        seguro:      $datos['seguro_cancelacion'] ? 'Sí (+10%)' : 'No',
        total:       number_format((float)$datos['precio_total'], 2, ',', '.') . ' €',
        badgeColor:  '#ef4444',
        badgeTexto:  'Cancelada',
        extras:      $extras
    );

    return _sendMail($datos['cli_email'], $subject, $html);
}

// ── Helpers privados ──────────────────────────────────────────────────────────

function _buildCarExtras(?string $cocheNombre, float $precioCoche): array
{
    if (!$cocheNombre) return [];
    return [['key' => 'Vehículo de alquiler', 'value' => $cocheNombre . ' · ' . number_format($precioCoche, 2, ',', '.') . ' €']];
}

function _formatViajeros(int $adultos, int $ninos): string
{
    $str = $adultos . ' adulto' . ($adultos !== 1 ? 's' : '');
    if ($ninos > 0) {
        $str .= ' + ' . $ninos . ' niño' . ($ninos !== 1 ? 's' : '');
    }
    return $str;
}

function _sendMail(string $to, string $subject, string $html): bool
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('[RP mail] vendor/autoload.php no encontrado');
        return false;
    }
    require_once $autoload;

    $mailUser = getenv('MAIL_USER') ?: '';
    $mailPass = getenv('MAIL_PASS') ?: '';

    if (!$mailUser || !$mailPass) {
        error_log('[RP mail] MAIL_USER o MAIL_PASS no configurados');
        return false;
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailUser;
        $mail->Password   = $mailPass;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($mailUser, 'RP Travels');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;

        $mail->send();
        error_log('[RP mail] Enviado a ' . $to . ' | asunto: ' . $subject);
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('[RP mail] FALLÓ el envío a ' . $to . ' | ' . $mail->ErrorInfo);
        return false;
    }
}

function _buildEmailHtml(
    string $titulo,
    string $intro,
    string $referencia,
    string $destino,
    string $paquete,
    string $salida,
    string $regreso,
    string $viajeros,
    string $seguro,
    string $total,
    string $badgeColor,
    string $badgeTexto,
    array  $extras = []
): string {
    $referencia = htmlspecialchars($referencia);
    $destino    = htmlspecialchars($destino);
    $paquete    = htmlspecialchars($paquete);
    $titulo     = htmlspecialchars($titulo);
    $intro      = htmlspecialchars($intro);
    $viajeros   = htmlspecialchars($viajeros);
    $seguro     = htmlspecialchars($seguro);
    $total      = htmlspecialchars($total);
    $badgeTexto = htmlspecialchars($badgeTexto);

    // Filas extra (ej. vehículo de alquiler)
    $extrasHtml = '';
    foreach ($extras as $extra) {
        $k = htmlspecialchars($extra['key']);
        $v = htmlspecialchars($extra['value']);
        $extrasHtml .= '
            <tr>
              <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0">
                <span style="display:block;color:#94a3b8;font-size:12px;text-transform:uppercase;
                             letter-spacing:.5px;margin-bottom:2px">' . $k . '</span>
                <span style="color:#0f172a;font-size:15px">' . $v . '</span>
              </td>
            </tr>';
    }

    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 16px">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0"
           style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:600px;width:100%">

      <!-- Cabecera -->
      <tr>
        <td style="background:#0f172a;padding:28px 40px;text-align:center">
          <span style="color:#ffffff;font-size:26px;font-weight:700;letter-spacing:-0.5px">RP Travels</span>
        </td>
      </tr>

      <!-- Cuerpo -->
      <tr>
        <td style="padding:40px">

          <h1 style="margin:0 0 8px;font-size:22px;color:#0f172a">' . $titulo . '</h1>
          <p style="margin:0 0 24px;color:#64748b;font-size:15px;line-height:1.6">' . $intro . '</p>

          <div style="margin-bottom:28px">
            <span style="display:inline-block;background:' . $badgeColor . ';color:#ffffff;
                         font-size:13px;font-weight:700;padding:5px 16px;border-radius:20px">' . $badgeTexto . '</span>
          </div>

          <!-- Tabla de detalles -->
          <table width="100%" cellpadding="0" cellspacing="0"
                 style="border-radius:8px;overflow:hidden;border:1px solid #e2e8f0">
            <tr>
              <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;background:#f8fafc">
                <span style="display:block;color:#94a3b8;font-size:12px;text-transform:uppercase;
                             letter-spacing:.5px;margin-bottom:2px">Referencia</span>
                <span style="color:#0f172a;font-size:15px;font-weight:700;font-family:monospace">' . $referencia . '</span>
              </td>
            </tr>
            <tr>
              <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0">
                <span style="display:block;color:#94a3b8;font-size:12px;text-transform:uppercase;
                             letter-spacing:.5px;margin-bottom:2px">Destino</span>
                <span style="color:#0f172a;font-size:15px;font-weight:600">' . $destino . '</span>
              </td>
            </tr>
            <tr>
              <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;background:#f8fafc">
                <span style="display:block;color:#94a3b8;font-size:12px;text-transform:uppercase;
                             letter-spacing:.5px;margin-bottom:2px">Paquete</span>
                <span style="color:#0f172a;font-size:15px">' . $paquete . '</span>
              </td>
            </tr>
            <tr>
              <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0">
                <span style="display:block;color:#94a3b8;font-size:12px;text-transform:uppercase;
                             letter-spacing:.5px;margin-bottom:2px">Fecha de salida</span>
                <span style="color:#0f172a;font-size:15px">' . $salida . '</span>
              </td>
            </tr>
            <tr>
              <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;background:#f8fafc">
                <span style="display:block;color:#94a3b8;font-size:12px;text-transform:uppercase;
                             letter-spacing:.5px;margin-bottom:2px">Fecha de regreso</span>
                <span style="color:#0f172a;font-size:15px">' . $regreso . '</span>
              </td>
            </tr>
            <tr>
              <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0">
                <span style="display:block;color:#94a3b8;font-size:12px;text-transform:uppercase;
                             letter-spacing:.5px;margin-bottom:2px">Viajeros</span>
                <span style="color:#0f172a;font-size:15px">' . $viajeros . '</span>
              </td>
            </tr>
            <tr>
              <td style="padding:14px 20px;border-bottom:1px solid #e2e8f0;background:#f8fafc">
                <span style="display:block;color:#94a3b8;font-size:12px;text-transform:uppercase;
                             letter-spacing:.5px;margin-bottom:2px">Seguro de cancelación</span>
                <span style="color:#0f172a;font-size:15px">' . $seguro . '</span>
              </td>
            </tr>
            ' . $extrasHtml . '
            <tr>
              <td style="padding:18px 20px;background:#0f172a">
                <span style="display:block;color:#94a3b8;font-size:12px;text-transform:uppercase;
                             letter-spacing:.5px;margin-bottom:4px">Precio total</span>
                <span style="color:#ffffff;font-size:22px;font-weight:700">' . $total . '</span>
              </td>
            </tr>
          </table>

        </td>
      </tr>

      <!-- Pie -->
      <tr>
        <td style="background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e2e8f0">
          <p style="margin:0;color:#94a3b8;font-size:12px;line-height:1.6">
            RP Travels &bull; Este mensaje se ha generado automáticamente, por favor no respondas a este correo.
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';
}
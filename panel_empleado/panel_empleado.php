<?php
session_start();

if (empty($_SESSION['rol']) || $_SESSION['rol'] !== 'empleado') {
  header("Location: login.php");
  exit;
}

$conn = new mysqli("localhost", "root", "", "turnostv");
$conn->set_charset("utf8mb4");
if ($conn->connect_error)
  die("Error de conexión: " . $conn->connect_error);

$usuario_id = $_SESSION['usuario_id'];

$stmt = $conn->prepare("SELECT id, vacaciones_total AS dias_vacaciones, vacaciones_gastadas, puesto FROM empleados WHERE usuario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$empleado = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$empleado)
  die("No se encontró el perfil de empleado.");

$empleado_id = $empleado['id'];
$dias_vacaciones = $empleado['dias_vacaciones'];
$puesto = $empleado['puesto'];

/* Procesar formulario */
$mensaje_ok = "";
$mensaje_error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['accion'])) {
  $fecha_inicio = $_POST['fecha_inicio'] ?? '';
  $fecha_fin = $_POST['fecha_fin'] ?? '';
  $motivo = trim($_POST['motivo'] ?? '');
  $tipo_solicitud = $_POST['tipo_solicitud'] ?? 'vacaciones';

  if (empty($fecha_inicio) || empty($fecha_fin)) {
    $mensaje_error = "Las fechas son obligatorias.";
  } elseif ($fecha_fin < $fecha_inicio) {
    $mensaje_error = "La fecha de fin no puede ser anterior a la de inicio.";
  } else {
    $dias = (new DateTime($fecha_inicio))->diff(new DateTime($fecha_fin))->days + 1;
    $motivo = match ($tipo_solicitud) {
      'asunto' => 'Asunto propio' . ($motivo ? ': ' . $motivo : ''),
      'medico' => 'Médico' . ($motivo ? ': ' . $motivo : ''),
      'otro' => 'Otro' . ($motivo ? ': ' . $motivo : ''),
      default => $motivo,
    };

    $stmt = $conn->prepare("INSERT INTO vacaciones (empleado_id, fecha_inicio, fecha_fin, dias, motivo, estado) VALUES (?, ?, ?, ?, ?, 'pendiente')");
    $stmt->bind_param("issis", $empleado_id, $fecha_inicio, $fecha_fin, $dias, $motivo);
    $stmt->execute() ? $mensaje_ok = "Solicitud enviada correctamente." : $mensaje_error = "Error al guardar.";
    $stmt->close();
  }
}

/* Turno de hoy */
$stmt = $conn->prepare("
    SELECT t.hora_inicio, t.hora_fin, t.control_nombre, t.plato, p.nombre AS programa
    FROM turnos t
    LEFT JOIN programas p ON p.id = t.programa_id
    WHERE t.empleado_id = ? AND t.fecha = CURDATE() AND t.estado = 'cubierto'
    LIMIT 1
");
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$turno_hoy = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* Turnos esta semana */
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM turnos WHERE empleado_id = ? AND YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1) AND estado = 'cubierto'");
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$turnos_semana = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

/* Solicitudes pendientes */
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM vacaciones WHERE empleado_id = ? AND estado = 'pendiente'");
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$solicitudes_pendientes = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

/* Próximo turno */
$stmt = $conn->prepare("
    SELECT t.fecha, t.hora_inicio, t.hora_fin, t.control_nombre, t.plato, p.nombre AS programa
    FROM turnos t
    LEFT JOIN programas p ON p.id = t.programa_id
    WHERE t.empleado_id = ? AND t.fecha > CURDATE() AND t.estado = 'cubierto'
    ORDER BY t.fecha ASC LIMIT 1
");
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$proximo_turno = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* Próximos turnos (lista) */
$stmt = $conn->prepare("
    SELECT t.fecha, t.hora_inicio, t.hora_fin, t.control_nombre, t.plato, p.nombre AS programa
    FROM turnos t
    LEFT JOIN programas p ON p.id = t.programa_id
    WHERE t.empleado_id = ? AND t.fecha >= CURDATE() AND t.estado = 'cubierto'
    ORDER BY t.fecha ASC
    LIMIT 20
");
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$proximos_turnos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Vacaciones */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(dias), 0) AS dias_usados
    FROM vacaciones
    WHERE empleado_id = ? AND estado = 'aprobada' AND fecha_inicio >= CURDATE()
");
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$dias_usados_solicitudes = (int) $stmt->get_result()->fetch_assoc()['dias_usados'];
$stmt->close();

$dias_gastados_total = $empleado['vacaciones_gastadas'] + $dias_usados_solicitudes;
$dias_reales = max(0, $dias_vacaciones - $dias_gastados_total);

/* Últimas solicitudes */
$stmt = $conn->prepare("SELECT fecha_inicio, fecha_fin, dias, motivo, estado FROM vacaciones WHERE empleado_id = ? ORDER BY fecha_peticion DESC LIMIT 9");
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$ultimas_solicitudes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Total solicitudes */
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM vacaciones WHERE empleado_id = ?");
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$total_solicitudes = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$conn->close();

$dias_map = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses_map = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$titulo_pagina = 'Mi panel';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TurnosTV — Empleado</title>
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="../fragmentos/styles/base.css">
  <link rel="stylesheet" href="styles/panel_empleado.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>

<body>

  <?php include '../fragmentos/sidebar.php'; ?>

  <div class="content">
    <?php include '../fragmentos/header.php'; ?>
    <div class="body">

      <!-- TURNO DE HOY -->
      <?php if ($turno_hoy): ?>
        <div class="turno-actual">
          <h2><?= htmlspecialchars($turno_hoy['programa'] ?? 'Turno asignado') ?></h2>
          <p>
            Hoy · <?= substr($turno_hoy['hora_inicio'], 0, 5) ?>–<?= substr($turno_hoy['hora_fin'], 0, 5) ?>
            · <?= htmlspecialchars($puesto) ?>
            <?php if ($turno_hoy['control_nombre']): ?>
              · <?= htmlspecialchars($turno_hoy['control_nombre']) ?>
            <?php endif; ?>
          </p>
        </div>
      <?php else: ?>
        <div class="turno-actual turno-libre">
          <h2>Hoy libras 🎉</h2>
          <?php if ($proximo_turno):
            $fecha_obj = new DateTime($proximo_turno['fecha']);
            $dias_para = (new DateTime('today'))->diff($fecha_obj)->days;
            $cuando = $dias_para === 1 ? 'mañana' : 'el ' . $dias_map[(int) $fecha_obj->format('w')] . ' ' . (int) $fecha_obj->format('j') . ' de ' . $meses_map[(int) $fecha_obj->format('n') - 1];
            ?>
            <p>Tu próximo turno es <?= $cuando ?> ·
              <?= substr($proximo_turno['hora_inicio'], 0, 5) ?>–<?= substr($proximo_turno['hora_fin'], 0, 5) ?> ·
              <?= htmlspecialchars($proximo_turno['programa'] ?? 'Sin programa') ?>
            </p>
          <?php else: ?>
            <p>No tienes turnos próximos asignados.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Resumen -->
      <div class="stats-grid">
        <div class="stat">
          <div class="stat-num"><?= $turnos_semana ?></div>
          <div class="stat-label">Turnos esta semana</div>
        </div>
        <div class="stat">
          <div class="stat-num"><?= $dias_reales ?></div>
          <div class="stat-label">Días de vacaciones disponibles</div>
          <div style="font-size:11px;color:var(--suave);margin-top:3px"><?= $empleado['vacaciones_gastadas'] ?> gastados
            · <?= $dias_vacaciones ?> totales</div>
        </div>
        <div class="stat">
          <div class="stat-num"><?= $solicitudes_pendientes ?></div>
          <div class="stat-label">Solicitudes pendientes</div>
        </div>
      </div>

      <!-- Próximos turnos -->
      <div class="card" style="margin-bottom:16px">
        <div class="card-header">
          <span class="card-title">Mis próximos turnos</span>
        </div>
        <?php if (empty($proximos_turnos)): ?>
          <p style="color:var(--suave);font-size:13px;padding:12px 0">No tienes turnos próximos asignados.</p>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Horario</th>
                <th>Programa</th>
                <th>Control</th>
                <th>Puesto</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($proximos_turnos as $t):
                $fobj = new DateTime($t['fecha']);
                $dow = (int) $fobj->format('w');
                $es_hoy = $t['fecha'] === date('Y-m-d');
                ?>
                <tr <?= $es_hoy ? 'style="background:#EEF3FB"' : '' ?>>
                  <td style="font-weight:<?= $es_hoy ? '700' : '400' ?>;white-space:nowrap">
                    <?= $es_hoy ? '🔵 Hoy' : ucfirst($dias_map[$dow]) . ' ' . $fobj->format('j') . ' ' . $meses_map[(int) $fobj->format('n') - 1] ?>
                  </td>
                  <td style="white-space:nowrap"><?= substr($t['hora_inicio'], 0, 5) ?>–<?= substr($t['hora_fin'], 0, 5) ?>
                  </td>
                  <td><?= htmlspecialchars($t['programa'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($t['control_nombre'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($puesto) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- FORMULARIO + HISTORIAL -->
      <div class="two-col">

        <!-- Formulario nueva solicitud -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Nueva solicitud</span>
          </div>

          <?php if ($mensaje_ok):
            echo "<p class='mensaje-ok'>{$mensaje_ok}</p>";
          endif; ?>
          <?php if ($mensaje_error):
            echo "<p class='mensaje-error'>{$mensaje_error}</p>";
          endif; ?>

          <form method="POST" action="panel_empleado.php">
            <input type="hidden" name="accion" value="solicitud">
            <div class="form-field">
              <label>Tipo de solicitud</label>
              <select name="tipo_solicitud">
                <option value="vacaciones">Vacaciones</option>
                <option value="asunto">Asunto propio</option>
                <option value="medico">Médico</option>
                <option value="otro">Otro</option>
              </select>
            </div>
            <div class="form-field">
              <label>Fechas <span class="dias-badge" id="dias-badge-panel"></span></label>
              <input type="text" id="campo-rango-panel" placeholder="Selecciona un rango..." readonly required>
              <input type="hidden" name="fecha_inicio" id="panel-inicio">
              <input type="hidden" name="fecha_fin" id="panel-fin">
            </div>
            <div class="form-field">
              <label>Motivo (opcional)</label>
              <textarea name="motivo" placeholder="Describe el motivo si lo deseas..." rows="1"
                style="resize:none;overflow:hidden;line-height:1.5"
                oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"></textarea>
            </div>
            <button type="submit" class="btn-send">Enviar solicitud</button>
          </form>
        </div>

        <!-- Últimas solicitudes -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">
              Últimas solicitudes
              <?php if ($solicitudes_pendientes > 0): ?>
                <span class="badge badge-aviso" style="margin-left:8px"><?= $solicitudes_pendientes ?>
                  pendiente<?= $solicitudes_pendientes > 1 ? 's' : '' ?></span>
              <?php endif; ?>
            </span>
          </div>

          <?php if (empty($ultimas_solicitudes)): ?>
            <p style="color:var(--suave);font-size:13px;padding:12px 0">Todavía no tienes solicitudes registradas.</p>
          <?php else: ?>
            <table class="table">
              <thead>
                <tr>
                  <th>Tipo</th>
                  <th>Inicio</th>
                  <th>Días</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ultimas_solicitudes as $sol):
                  $clase = match ($sol['estado']) {
                    'aprobada' => 'badge-ok',
                    'rechazada' => 'badge-peligro',
                    'en_proceso' => 'badge-neutro',
                    default => 'badge-aviso'
                  };
                  $label = match ($sol['estado']) {
                    'aprobada' => 'Aprobada',
                    'rechazada' => 'Rechazada',
                    'en_proceso' => 'En proceso',
                    default => 'Pendiente'
                  };
                  $tipo_label = match (true) {
                    str_starts_with($sol['motivo'] ?? '', 'Asunto propio') => 'Asunto propio',
                    str_starts_with($sol['motivo'] ?? '', 'Médico') => 'Médico',
                    str_starts_with($sol['motivo'] ?? '', 'Otro') => 'Otro',
                    default => 'Vacaciones',
                  };
                  ?>
                  <tr>
                    <td><?= $tipo_label ?></td>
                    <td><?= date('d/m/Y', strtotime($sol['fecha_inicio'])) ?></td>
                    <td><?= $sol['dias'] ?></td>
                    <td><span class="badge <?= $clase ?>"><?= $label ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div style="text-align:right;margin-top:12px;padding-top:10px;border-top:1px solid var(--borde)">
              <a href="mis_solicitudes.php" style="font-size:13px;color:var(--suave);text-decoration:none">
                <?= $total_solicitudes > 9 ? "Ver todas las solicitudes ($total_solicitudes) →" : "Gestionar solicitudes →" ?>
              </a>
            </div>
          <?php endif; ?>
        </div>

      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
  <script src="js/panel_empleado.js"></script>
</body>

</html>
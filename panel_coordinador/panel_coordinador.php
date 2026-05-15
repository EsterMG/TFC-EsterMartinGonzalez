<?php
session_start();

if (empty($_SESSION['rol']) || $_SESSION['rol'] !== 'coordinador') {
  header("Location: login.php");
  exit;
}

$conn = new mysqli("localhost", "root", "", "turnostv");
$conn->set_charset("utf8mb4");

/* Vacaciones pendientes */
$stmt = $conn->prepare("
    SELECT v.id, v.fecha_inicio, v.fecha_fin, v.dias, v.motivo, v.estado,
           u.nombre AS empleado_nombre,
           e.puesto
    FROM vacaciones v
    JOIN empleados e ON e.id = v.empleado_id
    JOIN usuarios u  ON u.id = e.usuario_id
    WHERE v.estado = 'pendiente'
    ORDER BY v.fecha_peticion ASC
");
$stmt->execute();
$vacaciones_pendientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Peticiones de directores pendientes */
$stmt = $conn->prepare("
    SELECT p.id, p.tipo, p.descripcion, p.fecha_pedida, p.fecha_peticion,
           u.nombre AS director_nombre,
           pr.nombre AS programa_nombre
    FROM peticiones p
    JOIN directores d ON d.id = p.director_id
    JOIN usuarios u ON u.id = d.usuario_id
    LEFT JOIN programas pr ON pr.id = p.programa_id
    WHERE p.estado = 'pendiente'
    ORDER BY p.fecha_peticion ASC
");
$stmt->execute();
$peticiones_pendientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Solicitudes de equipo pendientes */
$stmt = $conn->prepare("
    SELECT s.id, s.control_nombre, s.plato, s.hora_inicio, s.hora_fin,
           s.estado, s.notas, s.fecha_peticion,
           p.nombre AS programa_nombre,
           u.nombre AS director_nombre,
           COUNT(DISTINCT sf.fecha) AS num_dias,
           COUNT(DISTINCT sp.id) AS num_puestos,
           SUM(CASE WHEN sp.estado = 'cubierto' THEN 1 ELSE 0 END) AS puestos_cubiertos,
           MIN(sf.fecha) AS primera_fecha
    FROM solicitudes s
    JOIN programas p ON p.id = s.programa_id
    JOIN directores d ON d.id = s.director_id
    JOIN usuarios u ON u.id = d.usuario_id
    JOIN solicitud_fechas sf ON sf.solicitud_id = s.id
    JOIN solicitud_puestos sp ON sp.solicitud_id = s.id
    WHERE s.estado = 'pendiente'
    GROUP BY s.id
    ORDER BY s.fecha_peticion ASC
    LIMIT 4
");
$stmt->execute();
$solicitudes_pendientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Total solicitudes pendientes para el contador */
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM solicitudes WHERE estado = 'pendiente'");
$stmt->execute();
$total_solicitudes_pend = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

/* Turnos sin cubrir hoy y mañana */
$stmt = $conn->prepare("
    SELECT t.fecha, t.hora_inicio, t.hora_fin, t.puesto_solicitado,
           t.control_nombre, t.plato,
           p.nombre AS programa_nombre
    FROM turnos t
    LEFT JOIN programas p ON p.id = t.programa_id
    WHERE t.estado = 'sin_cubrir'
      AND t.fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ORDER BY t.fecha ASC, t.hora_inicio ASC
    LIMIT 10
");
$stmt->execute();
$turnos_sin_cubrir = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$titulo_pagina = 'Panel de coordinación';
$dias_es = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses_es = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TurnosTV — Coordinador</title>
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="../fragmentos/styles/base.css">
  <link rel="stylesheet" href="styles/panel_coordinador.css">
</head>

<body>
  <?php include '../fragmentos/sidebar.php'; ?>
  <div class="content">
    <?php include '../fragmentos/header.php'; ?>
    <div class="body">

      <!-- Accesos rápidos -->
      <div class="panel-grid">
        <a href="programas.php" class="panel-card">
          <div class="panel-name">Programas</div>
          <div class="panel-desc">Turnos por programa</div>
        </a>
        <a href="horarios.php" class="panel-card">
          <div class="panel-name">Horarios</div>
          <div class="panel-desc">Planning semanal</div>
        </a>
        <a href="empleados.php" class="panel-card">
          <div class="panel-name">Empleados</div>
          <div class="panel-desc">Vacaciones y permisos</div>
        </a>
        <a href="peticiones.php" class="panel-card">
          <div class="panel-name">Peticiones</div>
          <div class="panel-desc">Solicitudes de directores</div>
        </a>
      </div>

      <!-- Resumen rápido 4 columnas -->
      <div class="stats-grid" style="margin-bottom:16px">
        <div class="card" style="margin-bottom:0;text-align:center">
          <div
            style="font-size:28px;font-weight:700;color:<?= count($vacaciones_pendientes) > 0 ? 'var(--aviso)' : 'var(--ok)' ?>">
            <?= count($vacaciones_pendientes) ?>
          </div>
          <div style="font-size:12px;color:var(--suave)">Vacaciones por aprobar</div>
        </div>
        <div class="card" style="margin-bottom:0;text-align:center">
          <div
            style="font-size:28px;font-weight:700;color:<?= count($peticiones_pendientes) > 0 ? 'var(--aviso)' : 'var(--ok)' ?>">
            <?= count($peticiones_pendientes) ?>
          </div>
          <div style="font-size:12px;color:var(--suave)">Peticiones de directores</div>
        </div>
        <div class="card" style="margin-bottom:0;text-align:center">
          <div
            style="font-size:28px;font-weight:700;color:<?= $total_solicitudes_pend > 0 ? 'var(--aviso)' : 'var(--ok)' ?>">
            <?= $total_solicitudes_pend ?>
          </div>
          <div style="font-size:12px;color:var(--suave)">Solicitudes de equipo</div>
        </div>
        <div class="card" style="margin-bottom:0;text-align:center">
          <div
            style="font-size:28px;font-weight:700;color:<?= count($turnos_sin_cubrir) > 0 ? 'var(--peligro)' : 'var(--ok)' ?>">
            <?= count($turnos_sin_cubrir) ?>
          </div>
          <div style="font-size:12px;color:var(--suave)">Turnos sin cubrir (2 días)</div>
        </div>
      </div>

      <!-- Vacaciones pendientes -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Vacaciones por aprobar</span>
          <a href="peticiones.php" style="font-size:12px;color:var(--suave);text-decoration:none">Ver todas →</a>
        </div>
        <?php if (empty($vacaciones_pendientes)): ?>
          <p style="color:var(--suave);font-size:13px;padding:8px 0">No hay solicitudes pendientes.</p>
        <?php else: ?>
          <?php foreach ($vacaciones_pendientes as $v):
            $fi = date('d/m/Y', strtotime($v['fecha_inicio']));
            $ff = date('d/m/Y', strtotime($v['fecha_fin']));
            $tipo_label = match (true) {
              str_starts_with($v['motivo'] ?? '', 'Asunto propio') => 'Asunto propio',
              str_starts_with($v['motivo'] ?? '', 'Médico') => 'Médico',
              str_starts_with($v['motivo'] ?? '', 'Otro') => 'Otro',
              default => 'Vacaciones',
            };
            ?>
            <div class="item">
              <div style="flex:1">
                <div class="item-title">
                  <?= htmlspecialchars($v['empleado_nombre']) ?>
                  <span class="badge badge-neutro"
                    style="margin-left:6px;font-size:10px"><?= htmlspecialchars($v['puesto']) ?></span>
                  <span class="badge badge-aviso" style="margin-left:4px;font-size:10px"><?= $tipo_label ?></span>
                </div>
                <div class="item-meta">
                  <?= $fi ?> – <?= $ff ?> · <?= $v['dias'] ?> día<?= $v['dias'] > 1 ? 's' : '' ?>
                  <?php if (!empty($v['motivo']) && $tipo_label !== 'Vacaciones'): ?>
                    · <?= htmlspecialchars($v['motivo']) ?>
                  <?php endif; ?>
                </div>
              </div>
              <div style="display:flex;gap:6px;align-items:center;flex-shrink:0">
                <form method="POST" action="peticiones.php" style="display:inline">
                  <input type="hidden" name="accion" value="aprobar_vacacion">
                  <input type="hidden" name="vacacion_id" value="<?= $v['id'] ?>">
                  <button type="submit" class="btn btn-ok" style="padding:5px 12px;font-size:12px">Aprobar</button>
                </form>
                <form method="POST" action="peticiones.php" style="display:inline">
                  <input type="hidden" name="accion" value="rechazar_vacacion">
                  <input type="hidden" name="vacacion_id" value="<?= $v['id'] ?>">
                  <button type="submit" class="btn btn-peligro" style="padding:5px 12px;font-size:12px">Rechazar</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Peticiones de directores -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Peticiones de directores</span>
          <a href="peticiones.php" style="font-size:12px;color:var(--suave);text-decoration:none">Ver todas →</a>
        </div>
        <?php if (empty($peticiones_pendientes)): ?>
          <p style="color:var(--suave);font-size:13px;padding:8px 0">No hay peticiones pendientes.</p>
        <?php else: ?>
          <?php foreach ($peticiones_pendientes as $p): ?>
            <div class="item">
              <div style="flex:1">
                <div class="item-title">
                  <?= htmlspecialchars($p['tipo']) ?>
                  <?php if ($p['programa_nombre']): ?>
                    <span class="badge badge-neutro"
                      style="margin-left:6px;font-size:10px"><?= htmlspecialchars($p['programa_nombre']) ?></span>
                  <?php endif; ?>
                </div>
                <div class="item-meta">
                  <?= htmlspecialchars($p['director_nombre']) ?> ·
                  Enviada el <?= date('d/m/Y', strtotime($p['fecha_peticion'])) ?>
                  <?php if ($p['fecha_pedida']): ?>
                    · Necesaria para el <?= date('d/m/Y', strtotime($p['fecha_pedida'])) ?>
                  <?php endif; ?>
                </div>
                <?php if (!empty($p['descripcion'])): ?>
                  <div style="font-size:11px;color:var(--suave);margin-top:3px;font-style:italic">
                    "<?= htmlspecialchars($p['descripcion']) ?>"
                  </div>
                <?php endif; ?>
              </div>
              <a href="peticiones.php" class="btn btn-outline"
                style="padding:5px 12px;font-size:12px;flex-shrink:0">Gestionar</a>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Solicitudes de equipo -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Solicitudes de equipo pendientes</span>
          <a href="peticiones.php?tab=solicitudes" style="font-size:12px;color:var(--suave);text-decoration:none">Ver
            todas →</a>
        </div>
        <?php if (empty($solicitudes_pendientes)): ?>
          <p style="color:var(--suave);font-size:13px;padding:8px 0">No hay solicitudes de equipo pendientes.</p>
        <?php else: ?>
          <?php foreach ($solicitudes_pendientes as $s): ?>
            <div class="item">
              <div style="flex:1">
                <div class="item-title">
                  <?= htmlspecialchars($s['programa_nombre']) ?>
                  <span class="badge badge-neutro" style="margin-left:6px;font-size:10px">
                    <?= htmlspecialchars($s['control_nombre']) ?> · <?= htmlspecialchars($s['plato']) ?>
                  </span>
                </div>
                <div class="item-meta">
                  <?= htmlspecialchars($s['director_nombre']) ?> ·
                  <?= substr($s['hora_inicio'], 0, 5) ?>–<?= substr($s['hora_fin'], 0, 5) ?> ·
                  <?= $s['num_dias'] ?> día<?= $s['num_dias'] > 1 ? 's' : '' ?> ·
                  <?= $s['num_puestos'] ?> puesto<?= $s['num_puestos'] > 1 ? 's' : '' ?> ·
                  Enviada el <?= date('d/m/Y', strtotime($s['fecha_peticion'])) ?>
                </div>
                <?php if (!empty($s['notas'])): ?>
                  <div style="font-size:11px;color:var(--suave);margin-top:3px;font-style:italic">
                    "<?= htmlspecialchars($s['notas']) ?>"
                  </div>
                <?php endif; ?>
              </div>
              <a href="peticiones.php?tab=solicitudes" class="btn btn-outline"
                style="padding:5px 12px;font-size:12px;flex-shrink:0">Gestionar</a>
            </div>
          <?php endforeach; ?>
          <?php if ($total_solicitudes_pend > 4): ?>
            <div style="text-align:right;margin-top:10px;padding-top:10px;border-top:1px solid var(--borde)">
              <a href="peticiones.php?tab=solicitudes" style="font-size:12px;color:var(--suave);text-decoration:none">
                Ver <?= $total_solicitudes_pend - 4 ?> más →
              </a>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- Turnos sin cubrir -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Turnos sin cubrir (próximos 2 días)</span>
          <a href="horarios.php" style="font-size:12px;color:var(--suave);text-decoration:none">Ver horarios →</a>
        </div>
        <?php if (empty($turnos_sin_cubrir)): ?>
          <p style="color:var(--suave);font-size:13px;padding:8px 0">✓ Todos los turnos están cubiertos.</p>
        <?php else: ?>
          <?php foreach ($turnos_sin_cubrir as $t):
            $fecha_obj = new DateTime($t['fecha']);
            $hoy = new DateTime('today');
            $diff = (int) $hoy->diff($fecha_obj)->days;
            $cuando = $diff === 0 ? 'Hoy' : ($diff === 1 ? 'Mañana' : date('d/m', strtotime($t['fecha'])));
            ?>
            <div class="item">
              <div>
                <div class="item-title">
                  <?= htmlspecialchars($t['puesto_solicitado'] ?? 'Sin puesto') ?>
                  <?php if (!empty($t['control_nombre'])): ?>
                    <span class="badge badge-neutro" style="margin-left:6px;font-size:10px">
                      <?= htmlspecialchars($t['control_nombre']) ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div class="item-meta">
                  <?= $cuando ?> · <?= substr($t['hora_inicio'], 0, 5) ?>–<?= substr($t['hora_fin'], 0, 5) ?>
                  <?php if (!empty($t['programa_nombre'])): ?>
                    · <?= htmlspecialchars($t['programa_nombre']) ?>
                  <?php endif; ?>
                </div>
              </div>
              <a href="horarios.php" class="btn" style="padding:5px 12px;font-size:12px;flex-shrink:0">Asignar</a>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>
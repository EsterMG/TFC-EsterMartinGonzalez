<?php
session_start();

if (empty($_SESSION['rol']) || $_SESSION['rol'] !== 'director') {
  header("Location: login.php");
  exit;
}

$conn = new mysqli("localhost", "root", "", "turnostv");
$conn->set_charset("utf8mb4");
if ($conn->connect_error)
  die("Error de conexión: " . $conn->connect_error);

$usuario_id = $_SESSION['usuario_id'];

$stmt = $conn->prepare("SELECT d.id AS director_id FROM directores d WHERE d.usuario_id = ? LIMIT 1");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$director = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$director)
  die("No se encontró el perfil de director.");
$director_id = $director['director_id'];

$mensaje_ok = "";
$mensaje_error = "";

if (($_POST['accion'] ?? '') === 'nueva') {
  $tipo = trim($_POST['tipo'] ?? '');
  $descripcion = trim($_POST['descripcion'] ?? '');
  $fecha_pedida = $_POST['fecha_pedida'] ?? null;

  if (empty($tipo) || empty($descripcion)) {
    $mensaje_error = "El tipo y la descripción son obligatorios.";
  } else {
    $stmt = $conn->prepare("INSERT INTO peticiones (director_id, tipo, descripcion, fecha_pedida, estado) VALUES (?, ?, ?, ?, 'pendiente')");
    $stmt->bind_param("isss", $director_id, $tipo, $descripcion, $fecha_pedida);
    $stmt->execute() ? $mensaje_ok = "Petición enviada correctamente." : $mensaje_error = "Error al guardar.";
    $stmt->close();
  }
}

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM solicitudes WHERE director_id = ? AND estado = 'pendiente'");
$stmt->bind_param("i", $director_id);
$stmt->execute();
$solicitudes_pendientes = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM solicitudes WHERE director_id = ? AND estado IN ('pendiente','en_proceso')");
$stmt->bind_param("i", $director_id);
$stmt->execute();
$solicitudes_activas = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM peticiones WHERE director_id = ? AND estado = 'pendiente'");
$stmt->bind_param("i", $director_id);
$stmt->execute();
$peticiones_pendientes = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM programas");
$stmt->execute();
$total_programas = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("
    SELECT s.id, s.control_nombre, s.plato, s.estado, s.fecha_peticion,
           p.nombre AS programa_nombre,
           COUNT(DISTINCT sf.fecha) AS num_dias,
           COUNT(DISTINCT sp.id) AS num_puestos,
           SUM(CASE WHEN sp.estado='cubierto' THEN 1 ELSE 0 END) AS cubiertos
    FROM solicitudes s
    JOIN programas p ON p.id = s.programa_id
    JOIN solicitud_fechas sf ON sf.solicitud_id = s.id
    JOIN solicitud_puestos sp ON sp.solicitud_id = s.id
    WHERE s.director_id = ?
    GROUP BY s.id
    ORDER BY s.fecha_peticion DESC LIMIT 4
");
$stmt->bind_param("i", $director_id);
$stmt->execute();
$ultimas_solicitudes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT id, tipo, descripcion, estado, fecha_peticion
    FROM peticiones WHERE director_id = ?
    ORDER BY fecha_peticion DESC LIMIT 9
");
$stmt->bind_param("i", $director_id);
$stmt->execute();
$ultimas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM peticiones WHERE director_id = ?");
$stmt->bind_param("i", $director_id);
$stmt->execute();
$total_peticiones = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$conn->close();

$titulo_pagina = 'Panel de Director';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TurnosTV — Director</title>
  <link rel="stylesheet" href="../fragmentos/styles/base.css">
  <link rel="stylesheet" href="styles/panel_director.css">
</head>

<body>

  <?php include '../fragmentos/sidebar.php'; ?>

  <div class="content">
    <?php include '../fragmentos/header.php'; ?>

    <div class="body">

      <?php if ($mensaje_ok):
        echo "<p class='mensaje-ok'>{$mensaje_ok}</p>";
      endif; ?>
      <?php if ($mensaje_error):
        echo "<p class='mensaje-error'>{$mensaje_error}</p>";
      endif; ?>

      <div class="turno-actual">
        <h2>Panel de director</h2>
        <p>Gestiona solicitudes de equipo técnico para cualquier programa ·
          <a href="turnos_programa.php" style="color:inherit;font-weight:700;text-decoration:underline">Nueva solicitud
            →</a>
        </p>
      </div>

      <div class="stats-grid">
        <div class="stat">
          <div class="stat-num"><?= $total_programas ?></div>
          <div class="stat-label">Programas disponibles</div>
        </div>
        <div class="stat">
          <div class="stat-num <?= $solicitudes_activas > 0 ? 'stat-alerta' : '' ?>"><?= $solicitudes_activas ?></div>
          <div class="stat-label">Solicitudes de equipo activas</div>
        </div>
        <div class="stat">
          <div class="stat-num <?= $solicitudes_pendientes > 0 ? 'stat-alerta' : '' ?>"><?= $solicitudes_pendientes ?>
          </div>
          <div class="stat-label">Solicitudes pendientes</div>
        </div>
        <div class="stat">
          <div class="stat-num"><?= $peticiones_pendientes ?></div>
          <div class="stat-label">Peticiones de cambio pendientes</div>
        </div>
      </div>

      <div class="two-col">

        <div class="card">
          <div class="card-header">
            <span class="card-title">Solicitudes de equipo recientes</span>
            <a href="mis_peticiones.php" style="font-size:12px;color:var(--suave);text-decoration:none">Ver todas →</a>
          </div>
          <?php if (empty($ultimas_solicitudes)): ?>
            <p style="color:var(--suave);font-size:13px;padding:8px 0">No hay solicitudes recientes.</p>
            <a href="turnos_programa.php" class="btn-outline" style="font-size:12px;margin-top:8px;display:inline-block">+
              Nueva solicitud</a>
          <?php else: ?>
            <table class="table">
              <thead>
                <tr>
                  <th>Programa · Control</th>
                  <th>Días</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ultimas_solicitudes as $s):
                  $clase = match ($s['estado']) { 'aprobada' => 'badge-ok', 'rechazada' => 'badge-peligro', 'en_proceso' => 'badge-neutro', default => 'badge-aviso'};
                  $label = match ($s['estado']) { 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada', 'en_proceso' => 'En proceso', default => 'Pendiente'};
                  ?>
                  <tr>
                    <td>
                      <div style="font-weight:600;font-size:12px"><?= htmlspecialchars($s['programa_nombre']) ?></div>
                      <div style="color:var(--suave);font-size:11px">
                        <?= htmlspecialchars($s['control_nombre'] . ' · ' . $s['plato']) ?>
                      </div>
                    </td>
                    <td style="color:var(--suave)"><?= $s['num_dias'] ?> día<?= $s['num_dias'] > 1 ? 's' : '' ?></td>
                    <td><span class="badge <?= $clase ?>"><?= $label ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div style="text-align:right;margin-top:12px;padding-top:10px;border-top:1px solid var(--borde)">
              <a href="turnos_programa.php" style="font-size:13px;color:var(--suave);text-decoration:none">+ Nueva
                solicitud →</a>
            </div>
          <?php endif; ?>
        </div>

        <div class="card">
          <div class="card-header">
            <span class="card-title">Peticiones de cambio</span>
            <a href="mis_peticiones.php#peticiones" style="font-size:12px;color:var(--suave);text-decoration:none">Ver
              todas →</a>
          </div>
          <form method="POST" action="panel_director.php">
            <input type="hidden" name="accion" value="nueva">
            <div style="display:grid;grid-template-columns:1fr 120px;gap:8px;margin-bottom:10px">
              <div class="form-field" style="margin-bottom:0">
                <label>Tipo</label>
                <select name="tipo">
                  <option value="Técnico extra">Técnico extra</option>
                  <option value="Ampliar horario">Ampliar horario</option>
                  <option value="Cambio de turno">Cambio de turno</option>
                  <option value="Refuerzo fin semana">Refuerzo fin de semana</option>
                  <option value="Otro">Otro</option>
                </select>
              </div>
              <div class="form-field" style="margin-bottom:0">
                <label>Fecha</label>
                <input type="date" name="fecha_pedida" min="<?= date('Y-m-d') ?>">
              </div>
            </div>
            <div class="form-field">
              <label>Descripción</label>
              <textarea name="descripcion" placeholder="Describe lo que necesitas..." rows="2"
                style="resize:none;overflow:hidden;line-height:1.5"
                oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"></textarea>
            </div>
            <button type="submit" class="btn-send">Enviar petición</button>
          </form>

          <?php if (!empty($ultimas)): ?>
            <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--borde)">
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--suave);margin-bottom:8px">
                Últimas peticiones</div>
              <table class="table">
                <thead>
                  <tr>
                    <th>Tipo</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($ultimas as $p):
                    $clase = match ($p['estado']) { 'aprobada' => 'badge-ok', 'rechazada' => 'badge-peligro', 'en proceso' => 'badge-neutro', default => 'badge-aviso'};
                    $label = match ($p['estado']) { 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada', 'en proceso' => 'En proceso', default => 'Pendiente'};
                    ?>
                    <tr>
                      <td><?= htmlspecialchars($p['tipo']) ?></td>
                      <td style="color:var(--suave)"><?= date('d/m/Y', strtotime($p['fecha_peticion'])) ?></td>
                      <td><span class="badge <?= $clase ?>"><?= $label ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>

    </div>
  </div>

</body>

</html>
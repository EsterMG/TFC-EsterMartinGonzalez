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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'nueva_peticion') {
  $tipo = trim($_POST['tipo'] ?? '');
  $descripcion = trim($_POST['descripcion'] ?? '');
  $fecha_ped = $_POST['fecha_pedida'] ?? null;

  if (empty($tipo) || empty($descripcion)) {
    $mensaje_error = "El tipo y la descripción son obligatorios.";
  } else {
    $stmt = $conn->prepare("INSERT INTO peticiones (director_id, tipo, descripcion, fecha_pedida, estado) VALUES (?,?,?,?,'pendiente')");
    $stmt->bind_param("isss", $director_id, $tipo, $descripcion, $fecha_ped);
    $stmt->execute() ? $mensaje_ok = "Petición enviada correctamente." : $mensaje_error = "Error al guardar.";
    $stmt->close();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'borrar_peticion') {
  $pet_id = (int) ($_POST['peticion_id'] ?? 0);
  $stmt = $conn->prepare("DELETE FROM peticiones WHERE id=? AND director_id=? AND estado='pendiente'");
  $stmt->bind_param("ii", $pet_id, $director_id);
  $stmt->execute() ? $mensaje_ok = "Petición eliminada." : $mensaje_error = "Error al eliminar.";
  $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'borrar_solicitud') {
  $sol_id = (int) ($_POST['solicitud_id'] ?? 0);
  $stmt = $conn->prepare("DELETE FROM solicitudes WHERE id=? AND director_id=? AND estado='pendiente'");
  $stmt->bind_param("ii", $sol_id, $director_id);
  $stmt->execute() ? $mensaje_ok = "Solicitud eliminada." : $mensaje_error = "Error al eliminar.";
  $stmt->close();
}

$stmt = $conn->prepare("
    SELECT s.id, s.control_nombre, s.plato, s.hora_inicio, s.hora_fin,
           s.estado, s.notas, s.fecha_peticion,
           p.nombre AS programa_nombre,
           COUNT(DISTINCT sf.fecha) AS num_dias,
           COUNT(DISTINCT sp.id) AS num_puestos,
           SUM(CASE WHEN sp.estado='cubierto' THEN 1 ELSE 0 END) AS puestos_cubiertos,
           MIN(sf.fecha) AS primera_fecha,
           MAX(sf.fecha) AS ultima_fecha
    FROM solicitudes s
    JOIN programas p ON p.id = s.programa_id
    JOIN solicitud_fechas sf ON sf.solicitud_id = s.id
    JOIN solicitud_puestos sp ON sp.solicitud_id = s.id
    WHERE s.director_id = ?
    GROUP BY s.id
    ORDER BY s.fecha_peticion DESC
");
$stmt->bind_param("i", $director_id);
$stmt->execute();
$solicitudes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT id, tipo, descripcion, fecha_pedida, estado, respuesta, fecha_peticion
    FROM peticiones
    WHERE director_id = ?
    ORDER BY fecha_peticion DESC
");
$stmt->bind_param("i", $director_id);
$stmt->execute();
$peticiones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$sol_pend = count(array_filter($solicitudes, fn($s) => $s['estado'] === 'pendiente'));
$sol_proc = count(array_filter($solicitudes, fn($s) => $s['estado'] === 'en_proceso'));
$pet_pend = count(array_filter($peticiones, fn($p) => $p['estado'] === 'pendiente'));
$pet_apro = count(array_filter($peticiones, fn($p) => $p['estado'] === 'aprobada'));

$titulo_pagina = 'Mis peticiones';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TurnosTV — Mis peticiones</title>
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="../fragmentos/styles/base.css">
  <link rel="stylesheet" href="styles/panel_director.css">
</head>

<body>

  <?php include '../fragmentos/sidebar.php'; ?>

  <div class="content">
    <?php include '../fragmentos/header.php'; ?>

    <div class="body">

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
        <div style="font-size:13px;color:var(--suave)">Mis peticiones</div>
        <a href="panel_director.php" style="font-size:13px;color:var(--suave);text-decoration:none">← Volver al
          inicio</a>
      </div>

      <?php if ($mensaje_ok):
        echo "<p class='mensaje-ok'>{$mensaje_ok}</p>";
      endif; ?>
      <?php if ($mensaje_error):
        echo "<p class='mensaje-error'>{$mensaje_error}</p>";
      endif; ?>

      <!-- Resumen -->
      <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
        <div class="stat">
          <div class="stat-num <?= $sol_pend > 0 ? 'stat-alerta' : '' ?>"><?= $sol_pend ?></div>
          <div class="stat-label">Solicitudes de equipo pendientes</div>
        </div>
        <div class="stat">
          <div class="stat-num"><?= $sol_proc ?></div>
          <div class="stat-label">Solicitudes en proceso</div>
        </div>
        <div class="stat">
          <div class="stat-num <?= $pet_pend > 0 ? 'stat-alerta' : '' ?>"><?= $pet_pend ?></div>
          <div class="stat-label">Peticiones de cambio pendientes</div>
        </div>
        <div class="stat">
          <div class="stat-num"><?= $pet_apro ?></div>
          <div class="stat-label">Peticiones aprobadas</div>
        </div>
      </div>

      <!-- PESTAÑAS -->
      <div class="tabs">
        <button class="tab-btn activa" onclick="cambiarTab('solicitudes', this)">
          Solicitudes de equipo
          <?php if ($sol_pend > 0): ?><span class="badge badge-aviso"
              style="margin-left:6px;font-size:10px"><?= $sol_pend ?></span><?php endif; ?>
        </button>
        <button class="tab-btn" onclick="cambiarTab('peticiones', this)">
          Peticiones de cambio
          <?php if ($pet_pend > 0): ?><span class="badge badge-aviso"
              style="margin-left:6px;font-size:10px"><?= $pet_pend ?></span><?php endif; ?>
        </button>
      </div>

      <!-- PESTAÑA 1: SOLICITUDES DE EQUIPO -->
      <div class="tab-panel visible" id="tab-solicitudes">
        <!-- Ir a nueva solicitud -->
        <div id="btn-nueva" style="margin-bottom:16px">
          <a href="turnos_programa.php" class="btn"
            style="font-size:14px;padding:10px 20px;display:inline-block;text-decoration:none">
            + Nueva solicitud
          </a>
        </div>

        <?php if (empty($solicitudes)): ?>
          <div class="empty-state">
            <strong>Sin solicitudes</strong>
            Todavía no has enviado ninguna solicitud de equipo.
            <div style="margin-top:12px">
              <a href="turnos_programa.php" class="btn" style="font-size:13px;padding:8px 18px">Ir a nueva solicitud →</a>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($solicitudes as $s):
            $clase_e = match ($s['estado']) { 'aprobada' => 'badge-ok', 'rechazada' => 'badge-peligro', 'en_proceso' => 'badge-neutro', default => 'badge-aviso'};
            $label_e = match ($s['estado']) { 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada', 'en_proceso' => 'En proceso', default => 'Pendiente'};
            $fecha_ini_fmt = date('d/m/Y', strtotime($s['primera_fecha']));
            $fecha_fin_fmt = $s['primera_fecha'] !== $s['ultima_fecha'] ? ' – ' . date('d/m/Y', strtotime($s['ultima_fecha'])) : '';
            $pct = $s['num_puestos'] > 0 ? round($s['puestos_cubiertos'] / $s['num_puestos'] * 100) : 0;
            ?>
            <div class="sol-card">
              <div class="sol-header">
                <div>
                  <div class="prog-badge"><?= htmlspecialchars($s['programa_nombre']) ?></div>
                  <div class="sol-titulo"><?= htmlspecialchars($s['control_nombre']) ?> ·
                    <?= htmlspecialchars($s['plato']) ?>
                  </div>
                  <div class="sol-meta">
                    <?= substr($s['hora_inicio'], 0, 5) ?>–<?= substr($s['hora_fin'], 0, 5) ?> ·
                    <?= $fecha_ini_fmt . $fecha_fin_fmt ?> (<?= $s['num_dias'] ?> día<?= $s['num_dias'] > 1 ? 's' : '' ?>) ·
                    <?= $s['puestos_cubiertos'] ?>/<?= $s['num_puestos'] ?> puestos cubiertos ·
                    Enviada el <?= date('d/m/Y', strtotime($s['fecha_peticion'])) ?>
                  </div>
                  <?php if (!empty($s['notas'])): ?>
                    <div style="font-size:11px;color:var(--suave);margin-top:4px;font-style:italic">
                      "<?= htmlspecialchars($s['notas']) ?>"</div>
                  <?php endif; ?>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                  <span class="badge <?= $clase_e ?>"><?= $label_e ?></span>
                  <?php if ($s['estado'] === 'pendiente'): ?>
                    <form method="POST" id="del-sol-<?= $s['id'] ?>" style="display:inline">
                      <input type="hidden" name="accion" value="borrar_solicitud">
                      <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                      <button type="button" onclick="abrirConfirm('¿Eliminar esta solicitud?','del-sol-<?= $s['id'] ?>')"
                        style="font-size:11px;color:var(--peligro);background:none;border:1px solid rgba(185,28,28,0.25);border-radius:5px;padding:3px 10px;cursor:pointer">
                        Borrar
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
              <div style="margin-top:6px">
                <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--suave);margin-bottom:3px">
                  <span>Puestos cubiertos</span>
                  <span><?= $s['puestos_cubiertos'] ?>/<?= $s['num_puestos'] ?></span>
                </div>
                <div style="height:5px;background:var(--fondo);border-radius:3px;overflow:hidden">
                  <div
                    style="height:100%;width:<?= $pct ?>%;background:<?= $pct === 100 ? '#16A34A' : '#1e40af' ?>;border-radius:3px;transition:width 0.3s">
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

      </div>

      <!-- PESTAÑA 2: PETICIONES DE CAMBIO -->
      <div class="tab-panel" id="tab-peticiones">

        <div class="form-card">
          <div style="font-size:14px;font-weight:700;margin-bottom:14px">Nueva petición de cambio</div>
          <form method="POST" action="mis_peticiones.php">
            <input type="hidden" name="accion" value="nueva_peticion">
            <div style="display:grid;grid-template-columns:1fr 160px;gap:10px;margin-bottom:12px">
              <div class="form-field" style="margin-bottom:0">
                <label>Tipo</label>
                <select name="tipo">
                  <option value="Técnico extra">Técnico extra</option>
                  <option value="Ampliar horario">Ampliar horario</option>
                  <option value="Cambio de turno">Cambio de turno</option>
                  <option value="Refuerzo fin de semana">Refuerzo fin de semana</option>
                  <option value="Otro">Otro</option>
                </select>
              </div>
              <div class="form-field" style="margin-bottom:0">
                <label>Fecha necesaria</label>
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
        </div>

        <?php if (empty($peticiones)): ?>
          <div class="empty-state">
            <strong>Sin peticiones</strong>
            Todavía no has enviado ninguna petición de cambio.
          </div>
        <?php else: ?>
          <?php foreach ($peticiones as $p):
            $clase_e = match ($p['estado']) { 'aprobada' => 'badge-ok', 'rechazada' => 'badge-peligro', 'en proceso' => 'badge-neutro', default => 'badge-aviso'};
            $label_e = match ($p['estado']) { 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada', 'en proceso' => 'En proceso', default => 'Pendiente'};
            ?>
            <div class="pet-card">
              <div class="pet-header">
                <div style="flex:1;min-width:0">
                  <div class="pet-tipo"><?= htmlspecialchars($p['tipo']) ?></div>
                  <div class="pet-desc"><?= htmlspecialchars($p['descripcion']) ?></div>
                  <div class="pet-meta">
                    Enviada el <?= date('d/m/Y', strtotime($p['fecha_peticion'])) ?>
                    <?php if ($p['fecha_pedida']): ?>
                      · Necesaria para el <?= date('d/m/Y', strtotime($p['fecha_pedida'])) ?>
                    <?php endif; ?>
                  </div>
                </div>
                <div style="display:flex;align-items:flex-start;gap:8px;flex-shrink:0">
                  <span class="badge <?= $clase_e ?>"><?= $label_e ?></span>
                  <?php if ($p['estado'] === 'pendiente'): ?>
                    <form method="POST" id="del-pet-<?= $p['id'] ?>" style="display:inline">
                      <input type="hidden" name="accion" value="borrar_peticion">
                      <input type="hidden" name="peticion_id" value="<?= $p['id'] ?>">
                      <button type="button" onclick="abrirConfirm('¿Eliminar esta petición?','del-pet-<?= $p['id'] ?>')"
                        style="font-size:11px;color:var(--peligro);background:none;border:1px solid rgba(185,28,28,0.25);border-radius:5px;padding:3px 10px;cursor:pointer">
                        Borrar
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
              <?php if (!empty($p['respuesta'])): ?>
                <div class="pet-respuesta">
                  <strong style="font-size:10px;text-transform:uppercase;letter-spacing:0.5px;color:var(--suave)">Respuesta
                    del coordinador</strong><br>
                  <?= htmlspecialchars($p['respuesta']) ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

      </div>

    </div>
  </div>

  <!-- MODAL CONFIRMACIÓN -->
  <div id="modal-confirm"
    style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:300;align-items:center;justify-content:center">
    <div
      style="background:var(--blanco);border-radius:12px;padding:28px;width:100%;max-width:340px;margin:20px;box-shadow:0 8px 32px rgba(0,0,0,0.18)">
      <div style="font-size:16px;font-weight:700;margin-bottom:8px">Confirmar eliminación</div>
      <div id="modal-confirm-msg" style="font-size:13px;color:var(--suave);margin-bottom:24px;line-height:1.5"></div>
      <div style="display:flex;gap:8px">
        <button type="button" id="modal-confirm-ok"
          style="flex:1;background:var(--peligro);color:#fff;border:none;border-radius:8px;padding:10px;font-size:14px;font-weight:500;cursor:pointer">Eliminar</button>
        <button type="button" onclick="cerrarConfirm()"
          style="flex:1;background:none;border:1px solid var(--borde);border-radius:8px;padding:10px;font-size:14px;cursor:pointer">Cancelar</button>
      </div>
    </div>
  </div>
  <!-- /* Comparten mismo js.*/ -->
  <script src="js/turnos_programa.js"></script>

</body>

</html>
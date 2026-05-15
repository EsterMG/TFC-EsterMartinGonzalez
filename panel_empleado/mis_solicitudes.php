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

$stmt = $conn->prepare("SELECT id FROM empleados WHERE usuario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$empleado = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$empleado)
  die("No se encontró el perfil de empleado.");
$empleado_id = $empleado['id'];

$mensaje_ok = "";
$mensaje_error = "";
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// BORRAR
if ($accion === 'borrar' && isset($_GET['id'])) {
  $id = (int) $_GET['id'];
  $stmt = $conn->prepare("SELECT id FROM vacaciones WHERE id = ? AND empleado_id = ? AND estado = 'pendiente'");
  $stmt->bind_param("ii", $id, $empleado_id);
  $stmt->execute();
  $existe = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($existe) {
    $stmt = $conn->prepare("DELETE FROM vacaciones WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute() ? $mensaje_ok = "Solicitud eliminada correctamente." : $mensaje_error = "Error al eliminar.";
    $stmt->close();
  } else {
    $mensaje_error = "No se puede eliminar esta solicitud.";
  }
}

// EDITAR: cargar datos
$editando = null;
if ($accion === 'editar' && isset($_GET['id'])) {
  $id = (int) $_GET['id'];
  $stmt = $conn->prepare("SELECT * FROM vacaciones WHERE id = ? AND empleado_id = ? AND estado = 'pendiente'");
  $stmt->bind_param("ii", $id, $empleado_id);
  $stmt->execute();
  $editando = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$editando)
    $mensaje_error = "No se puede editar esta solicitud.";
}

// GUARDAR EDICIÓN
if ($accion === 'guardar_edicion' && isset($_POST['id'])) {
  $id = (int) $_POST['id'];
  $fecha_inicio = $_POST['fecha_inicio'] ?? '';
  $fecha_fin = $_POST['fecha_fin'] ?? '';
  $motivo = trim($_POST['motivo'] ?? '');
  $tipo = $_POST['tipo_solicitud'] ?? 'vacaciones';

  if (empty($fecha_inicio) || empty($fecha_fin)) {
    $mensaje_error = "Las fechas son obligatorias.";
  } elseif ($fecha_fin < $fecha_inicio) {
    $mensaje_error = "La fecha de fin no puede ser anterior a la de inicio.";
  } else {
    $dias = (new DateTime($fecha_inicio))->diff(new DateTime($fecha_fin))->days + 1;
    $motivo = match ($tipo) {
      'asunto' => 'Asunto propio' . ($motivo ? ': ' . $motivo : ''),
      'medico' => 'Médico' . ($motivo ? ': ' . $motivo : ''),
      'otro' => 'Otro' . ($motivo ? ': ' . $motivo : ''),
      default => $motivo,
    };
    $stmt = $conn->prepare("SELECT id FROM vacaciones WHERE id = ? AND empleado_id = ? AND estado = 'pendiente'");
    $stmt->bind_param("ii", $id, $empleado_id);
    $stmt->execute();
    $existe = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existe) {
      $stmt = $conn->prepare("UPDATE vacaciones SET fecha_inicio=?, fecha_fin=?, dias=?, motivo=? WHERE id=?");
      $stmt->bind_param("ssisi", $fecha_inicio, $fecha_fin, $dias, $motivo, $id);
      $stmt->execute() ? $mensaje_ok = "Solicitud actualizada correctamente." : $mensaje_error = "Error al actualizar.";
      $stmt->close();
      $editando = null;
    } else {
      $mensaje_error = "No se puede editar esta solicitud.";
    }
  }
}

// NUEVA
if ($accion === 'nueva') {
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

// Resumen de estados
$stmt = $conn->prepare("
    SELECT estado, COUNT(*) AS total
    FROM vacaciones WHERE empleado_id = ?
    GROUP BY estado
");
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$resumen_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$resumen = ['pendiente' => 0, 'en_proceso' => 0, 'aprobada' => 0, 'rechazada' => 0];
foreach ($resumen_raw as $r) {
  if (isset($resumen[$r['estado']]))
    $resumen[$r['estado']] = (int) $r['total'];
}

// HISTORIAL CON FILTROS
$where = ["empleado_id = ?"];
$params = [$empleado_id];
$types = "i";

$f_peticion = $_GET['f_peticion'] ?? '';
$f_tipo = $_GET['f_tipo'] ?? '';
$f_inicio = $_GET['f_inicio'] ?? '';
$f_fin = $_GET['f_fin'] ?? '';
$f_estado = $_GET['f_estado'] ?? '';

if ($f_peticion) {
  $where[] = "DATE(fecha_peticion) = ?";
  $params[] = $f_peticion;
  $types .= "s";
}
if ($f_tipo) {
  if ($f_tipo === 'vacaciones') {
    $where[] = "(motivo NOT LIKE 'Asunto propio%' AND motivo NOT LIKE 'Médico%' AND motivo NOT LIKE 'Otro%')";
  } else {
    $prefijos = ['asunto' => 'Asunto propio%', 'medico' => 'Médico%', 'otro' => 'Otro%'];
    $where[] = "motivo LIKE ?";
    $params[] = $prefijos[$f_tipo];
    $types .= "s";
  }
}
if ($f_inicio) {
  $where[] = "fecha_inicio >= ?";
  $params[] = $f_inicio;
  $types .= "s";
}
if ($f_fin) {
  $where[] = "fecha_fin <= ?";
  $params[] = $f_fin;
  $types .= "s";
}
if ($f_estado) {
  $where[] = "estado = ?";
  $params[] = $f_estado;
  $types .= "s";
}

// Ordenación 
$columnas_validas = ['fecha_peticion', 'fecha_inicio', 'fecha_fin', 'estado'];
$orden_col = in_array($_GET['orden'] ?? '', $columnas_validas) ? $_GET['orden'] : 'fecha_peticion';
$orden_dir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$sql = "SELECT id, fecha_inicio, fecha_fin, dias, motivo, estado, fecha_peticion FROM vacaciones WHERE " . implode(" AND ", $where) . " ORDER BY {$orden_col} {$orden_dir}";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$titulo_pagina = 'Mis solicitudes';
$boton_cabecera = ['texto' => '+ Nueva solicitud', 'onclick' => 'abrirModal()'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TurnosTV — Mis solicitudes</title>
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

      <?php if ($mensaje_ok):
        echo "<p class='mensaje-ok'>{$mensaje_ok}</p>";
      endif; ?>
      <?php if ($mensaje_error):
        echo "<p class='mensaje-error'>{$mensaje_error}</p>";
      endif; ?>


      <!-- Volver -->
      <div style="margin-bottom:16px">
        <a href="panel_empleado.php" style="font-size:13px; color:var(--suave); text-decoration:none">← Volver al
          inicio</a>
      </div>

      <!-- RESUMEN DE ESTADOS -->
      <div class="stats-grid" style="margin-bottom:16px">
        <div class="stat" style="cursor:pointer; border-left:3px solid #D97706"
          onclick="window.location='mis_solicitudes.php?f_estado=pendiente'">
          <div class="stat-num" style="color:#D97706"><?= $resumen['pendiente'] ?></div>
          <div class="stat-label">Pendientes</div>
        </div>
        <div class="stat" style="cursor:pointer; border-left:3px solid #888"
          onclick="window.location='mis_solicitudes.php?f_estado=en_proceso'">
          <div class="stat-num" style="color:#888"><?= $resumen['en_proceso'] ?></div>
          <div class="stat-label">En proceso</div>
        </div>
        <div class="stat" style="cursor:pointer; border-left:3px solid #16A34A"
          onclick="window.location='mis_solicitudes.php?f_estado=aprobada'">
          <div class="stat-num" style="color:#16A34A"><?= $resumen['aprobada'] ?></div>
          <div class="stat-label">Aprobadas</div>
        </div>
        <div class="stat" style="cursor:pointer; border-left:3px solid #B91C1C"
          onclick="window.location='mis_solicitudes.php?f_estado=rechazada'">
          <div class="stat-num" style="color:#B91C1C"><?= $resumen['rechazada'] ?></div>
          <div class="stat-label">Rechazadas</div>
        </div>
      </div>

      <!-- FILTRO -->
      <div class="card">
        <form method="GET" action="mis_solicitudes.php">
          <div style="display:grid; grid-template-columns: 1fr 1fr 130px 130px 130px auto; gap:10px; align-items:end">
            <div class="form-field" style="margin-bottom:0">
              <label>Fecha de petición</label>
              <input type="date" name="f_peticion" value="<?= htmlspecialchars($f_peticion) ?>"
                style="font-size:13px; padding:8px 10px">
            </div>
            <div class="form-field" style="margin-bottom:0">
              <label>Tipo</label>
              <select name="f_tipo" style="font-size:13px; padding:8px 10px">
                <option value="">Todos</option>
                <option value="vacaciones" <?= $f_tipo === 'vacaciones' ? 'selected' : '' ?>>Vacaciones</option>
                <option value="asunto" <?= $f_tipo === 'asunto' ? 'selected' : '' ?>>Asunto propio</option>
                <option value="medico" <?= $f_tipo === 'medico' ? 'selected' : '' ?>>Médico</option>
                <option value="otro" <?= $f_tipo === 'otro' ? 'selected' : '' ?>>Otro</option>
              </select>
            </div>
            <div class="form-field" style="margin-bottom:0">
              <label>Inicio desde</label>
              <input type="date" name="f_inicio" value="<?= htmlspecialchars($f_inicio) ?>"
                style="font-size:13px; padding:8px 10px">
            </div>
            <div class="form-field" style="margin-bottom:0">
              <label>Fin hasta</label>
              <input type="date" name="f_fin" value="<?= htmlspecialchars($f_fin) ?>"
                style="font-size:13px; padding:8px 10px">
            </div>
            <div class="form-field" style="margin-bottom:0">
              <label>Estado</label>
              <select name="f_estado" style="font-size:13px; padding:8px 10px">
                <option value="">Todos</option>
                <option value="pendiente" <?= $f_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="en_proceso" <?= $f_estado === 'en_proceso' ? 'selected' : '' ?>>En proceso</option>
                <option value="aprobada" <?= $f_estado === 'aprobada' ? 'selected' : '' ?>>Aprobada</option>
                <option value="rechazada" <?= $f_estado === 'rechazada' ? 'selected' : '' ?>>Rechazada</option>
              </select>
            </div>
            <div style="display:flex; gap:6px">
              <button type="submit" class="btn" style="white-space:nowrap">Filtrar</button>
              <a href="mis_solicitudes.php" class="btn-outline" style="white-space:nowrap">Limpiar</a>
            </div>
          </div>
        </form>
      </div>

      <!-- HISTORIAL -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Historial de solicitudes</span>
          <?php if ($f_peticion || $f_tipo || $f_inicio || $f_fin || $f_estado): ?>
            <span class="badge badge-aviso"><?= count($historial) ?>
              resultado<?= count($historial) !== 1 ? 's' : '' ?></span>
          <?php endif; ?>
        </div>

        <?php if (empty($historial)): ?>
          <p style="color:var(--suave); font-size:13px; padding:12px 0;">No hay solicitudes que coincidan.</p>
        <?php else: ?>
          <table class="table">
            <?php
            // Construir URL base manteniendo filtros activos
            $params_url = array_filter([
              'f_peticion' => $f_peticion,
              'f_tipo' => $f_tipo,
              'f_inicio' => $f_inicio,
              'f_fin' => $f_fin,
              'f_estado' => $f_estado,
            ]);

            function th_ordenable($label, $col, $orden_col, $orden_dir, $params_url)
            {
              $es_activo = $orden_col === $col;
              $nueva_dir = ($es_activo && $orden_dir === 'ASC') ? 'DESC' : 'ASC';
              $url = 'mis_solicitudes.php?' . http_build_query(array_merge($params_url, ['orden' => $col, 'dir' => $nueva_dir]));
              $icono = $es_activo ? ($orden_dir === 'ASC' ? ' ↑' : ' ↓') : ' ⇅';

              if ($es_activo) {
                return "<th style='background:#EBF4FF; padding:8px 10px; border-radius:6px 6px 0 0'>
                  <a href='{$url}' style='text-decoration:none; color:#1a5fa8; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.5px'>
              {$label}{$icono}
               </a></th>";
              } else {
                return "<th> <a href='{$url}' style='text-decoration:none; color:inherit'>
              {$label}<span style='color:#ccc; font-size:11px'>{$icono}</span> </a> </th>";
              }
            }
            ?>
            <thead>
              <tr>
                <?= th_ordenable('Fecha petición', 'fecha_peticion', $orden_col, $orden_dir, $params_url) ?>
                <th>Tipo</th>
                <?= th_ordenable('Inicio', 'fecha_inicio', $orden_col, $orden_dir, $params_url) ?>
                <?= th_ordenable('Fin', 'fecha_fin', $orden_col, $orden_dir, $params_url) ?>
                <th>Días</th>
                <th>Motivo</th>
                <?= th_ordenable('Estado', 'estado', $orden_col, $orden_dir, $params_url) ?>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($historial as $sol):
                $clase = match ($sol['estado']) { 'aprobada' => 'badge-ok', 'rechazada' => 'badge-peligro', 'en_proceso' => 'badge-neutro', default => 'badge-aviso'};
                $label = match ($sol['estado']) { 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada', 'en_proceso' => 'En proceso', default => 'Pendiente'};
                $tipo_label = match (true) {
                  strpos($sol['motivo'] ?? '', 'Asunto propio') === 0 => 'Asunto propio',
                  strpos($sol['motivo'] ?? '', 'Médico') === 0 => 'Médico',
                  strpos($sol['motivo'] ?? '', 'Otro') === 0 => 'Otro',
                  default => 'Vacaciones',
                };
                $motivo_limpio = preg_replace('/^(Asunto propio|Médico|Otro): ?/', '', $sol['motivo'] ?? '');
                $es_pendiente = $sol['estado'] === 'pendiente';
                $es_editando = $editando && $editando['id'] === $sol['id'];
                ?>
                <tr <?= $es_editando ? 'style="background:rgba(0,0,0,0.03)"' : '' ?>>
                  <td style="color:var(--suave)"><?= date('d/m/Y H:i', strtotime($sol['fecha_peticion'])) ?></td>
                  <td><?= $tipo_label ?></td>
                  <td><?= date('d/m/Y', strtotime($sol['fecha_inicio'])) ?></td>
                  <td><?= date('d/m/Y', strtotime($sol['fecha_fin'])) ?></td>
                  <td><?= $sol['dias'] ?></td>
                  <td style="color:var(--suave)"><?= $motivo_limpio ? htmlspecialchars($motivo_limpio) : '—' ?></td>
                  <td><span class="badge <?= $clase ?>"><?= $label ?></span></td>
                  <td>
                    <?php if ($es_pendiente): ?>
                      <div style="display:flex; gap:6px">
                        <button onclick="abrirModalEditar(
                          '<?= $sol['id'] ?>',
                          '<?= $tipo_label === 'Vacaciones' ? 'vacaciones' : (strpos($sol['motivo'], 'Asunto') === 0 ? 'asunto' : (strpos($sol['motivo'], 'Médico') === 0 ? 'medico' : 'otro')) ?>',
                          '<?= $sol['fecha_inicio'] ?>',
                          '<?= $sol['fecha_fin'] ?>',
                          '<?= htmlspecialchars(preg_replace('/^(Asunto propio|Médico|Otro): ?/', '', $sol['motivo'] ?? ''), ENT_QUOTES) ?>'
                        )" class="btn-outline" style="font-size:11px; padding:4px 10px">Editar</button>
                        <a href="mis_solicitudes.php?accion=borrar&id=<?= $sol['id'] ?>" class="btn-outline"
                          style="font-size:11px; padding:4px 10px; color:var(--peligro); border-color:var(--peligro)"
                          onclick="return confirm('¿Seguro que quieres eliminar esta solicitud?')">Borrar</a>
                      </div>
                    <?php else: ?>
                      <span style="color:var(--suave); font-size:11px">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- MODAL NUEVA / EDITAR SOLICITUD -->
  <div id="modal-overlay"
    style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:100; align-items:center; justify-content:center">
    <div
      style="background:var(--blanco); border-radius:12px; padding:28px; width:100%; max-width:520px; margin:20px; position:relative">

      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px">
        <span style="font-size:15px; font-weight:700" id="modal-titulo">Nueva solicitud</span>
        <button onclick="cerrarModal()"
          style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--suave); line-height:1">×</button>
      </div>

      <?php if ($mensaje_ok):
        echo "<p class='mensaje-ok'>{$mensaje_ok}</p>";
      endif; ?>
      <?php if ($mensaje_error):
        echo "<p class='mensaje-error'>{$mensaje_error}</p>";
      endif; ?>

      <form method="POST" action="mis_solicitudes.php" id="form-solicitud">
        <input type="hidden" name="accion" id="form-accion" value="nueva">
        <input type="hidden" name="id" id="form-id" value="">

        <?php
        $tipo_actual = '';
        if ($editando) {
          if (strpos($editando['motivo'] ?? '', 'Asunto propio') === 0)
            $tipo_actual = 'asunto';
          elseif (strpos($editando['motivo'] ?? '', 'Médico') === 0)
            $tipo_actual = 'medico';
          elseif (strpos($editando['motivo'] ?? '', 'Otro') === 0)
            $tipo_actual = 'otro';
          else
            $tipo_actual = 'vacaciones';
        }
        ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; align-items:end; margin-bottom:14px">
          <div class="form-field" style="margin-bottom:0">
            <label>Tipo</label>
            <select name="tipo_solicitud" id="modal-tipo">
              <option value="vacaciones" <?= $tipo_actual === 'vacaciones' || !$editando ? 'selected' : '' ?>>Vacaciones
              </option>
              <option value="asunto" <?= $tipo_actual === 'asunto' ? 'selected' : '' ?>>Asunto propio</option>
              <option value="medico" <?= $tipo_actual === 'medico' ? 'selected' : '' ?>>Médico</option>
              <option value="otro" <?= $tipo_actual === 'otro' ? 'selected' : '' ?>>Otro</option>
            </select>
          </div>
          <div class="form-field" style="margin-bottom:0">
            <label>
              Fechas
              <span class="dias-badge" id="dias-badge"></span>
            </label>
            <input type="text" id="campo-rango" placeholder="Selecciona un rango..." readonly required>
            <!-- Inputs ocultos que recibe PHP -->
            <input type="hidden" name="fecha_inicio" id="modal-inicio" value="<?= $editando['fecha_inicio'] ?? '' ?>">
            <input type="hidden" name="fecha_fin" id="modal-fin" value="<?= $editando['fecha_fin'] ?? '' ?>">
          </div>
        </div>

        <div class="form-field" style="margin-bottom:16px">
          <label>Motivo (opcional)</label>
          <textarea name="motivo" id="modal-motivo" placeholder="Describe el motivo..." rows="2"
            style="resize:none; line-height:1.5"><?php
            if ($editando)
              echo htmlspecialchars(preg_replace('/^(Asunto propio|Médico|Otro): ?/', '', $editando['motivo'] ?? ''));
            ?></textarea>
        </div>

        <div style="display:flex; gap:8px">
          <button type="submit" class="btn" style="flex:1" id="modal-btn-enviar">Enviar solicitud</button>
          <button type="button" class="btn-outline" onclick="cerrarModal()">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

  <script>
    window.appState = <?= json_encode([
      'mensajeError' => !empty($mensaje_error),
      'editando' => $editando ?? null
    ]) ?>;
  </script>
  <script src="js/mis_solicitudes.js"></script>
</body>

</html>
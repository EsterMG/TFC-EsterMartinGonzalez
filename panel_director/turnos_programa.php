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
$hora_ini_prog = '08:00';
$hora_fin_prog = '22:00';

$stmt = $conn->prepare("SELECT id, nombre, hora_inicio, hora_fin FROM programas ORDER BY nombre ASC");
$stmt->execute();
$todos_programas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$mensaje_ok = "";
$mensaje_error = "";
$form_error_fields = [];
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $accion = $_POST['accion'] ?? '';

  if ($accion === 'nueva_solicitud') {
    $programa_id = (int) ($_POST['programa_id'] ?? 0);
    $control = trim($_POST['control_nombre'] ?? '');
    $plato = trim($_POST['plato'] ?? '');
    $ctrl_ini = $_POST['control_inicio'] ?? '';
    $ctrl_fin = $_POST['control_fin'] ?? '';
    $fechas_raw = $_POST['fechas_seleccionadas'] ?? '';
    $puestos = $_POST['puestos'] ?? [];
    $notas = trim($_POST['notas'] ?? '');

    $fechas_arr = array_values(array_filter(array_map('trim', explode(',', $fechas_raw))));
    $form_data = compact('programa_id', 'control', 'plato', 'ctrl_ini', 'ctrl_fin', 'fechas_raw', 'notas');

    if (!$programa_id) {
      $mensaje_error = "Debes seleccionar un programa.";
      $form_error_fields = ['programa_id'];
    } elseif (empty($control)) {
      $mensaje_error = "Debes seleccionar un control.";
      $form_error_fields = ['control_nombre'];
    } elseif (empty($plato)) {
      $mensaje_error = "Debes seleccionar un plato.";
      $form_error_fields = ['plato'];
    } elseif (empty($ctrl_ini) || empty($ctrl_fin)) {
      $mensaje_error = "Indica el horario del control.";
      $form_error_fields = ['horario_ctrl'];
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $ctrl_ini) || !preg_match('/^\d{2}:\d{2}$/', $ctrl_fin)) {
      $mensaje_error = "Formato de hora inválido (usa HH:MM).";
      $form_error_fields = ['horario_ctrl'];
    } elseif (empty($fechas_arr)) {
      $mensaje_error = "Selecciona al menos un día.";
      $form_error_fields = ['fechas'];
    } elseif (empty($puestos)) {
      $mensaje_error = "Añade al menos un puesto.";
      $form_error_fields = ['puestos'];
    } else {
      $conn->begin_transaction();
      try {
        $stmt = $conn->prepare("INSERT INTO solicitudes (programa_id, director_id, control_nombre, plato, hora_inicio, hora_fin, notas) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("iisssss", $programa_id, $director_id, $control, $plato, $ctrl_ini, $ctrl_fin, $notas);
        $stmt->execute();
        $sol_id = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("INSERT IGNORE INTO solicitud_fechas (solicitud_id, fecha) VALUES (?,?)");
        foreach ($fechas_arr as $f) {
          $stmt->bind_param("is", $sol_id, $f);
          $stmt->execute();
        }
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO solicitud_puestos (solicitud_id, puesto_solicitado, hora_inicio, hora_fin) VALUES (?,?,?,?)");
        foreach ($puestos as $puesto) {
          $stmt->bind_param("isss", $sol_id, $puesto, $ctrl_ini, $ctrl_fin);
          $stmt->execute();
        }
        $stmt->close();

        $conn->commit();
        $mensaje_ok = "Solicitud creada: " . count($fechas_arr) . " día(s), " . count($puestos) . " puesto(s).";
        $form_data = [];
      } catch (Exception $e) {
        $conn->rollback();
        $mensaje_error = "Error al guardar: " . $e->getMessage();
      }
    }
  }

  if ($accion === 'borrar_solicitud') {
    $sol_id = (int) ($_POST['solicitud_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM solicitudes WHERE id=? AND director_id=? AND estado='pendiente'");
    $stmt->bind_param("ii", $sol_id, $director_id);
    $stmt->execute() ? $mensaje_ok = "Solicitud eliminada." : $mensaje_error = "Error al eliminar.";
    $stmt->close();
  }
}

$mes_offset = (int) ($_GET['mes'] ?? 0);
$primer_dia = new DateTime("first day of this month");
$primer_dia->modify("{$mes_offset} months");
$ultimo_dia = clone $primer_dia;
$ultimo_dia->modify("last day of this month");

$fecha_ini_mes = $primer_dia->format('Y-m-d');
$fecha_fin_mes = $ultimo_dia->format('Y-m-d');

$stmt = $conn->prepare("
    SELECT s.id, s.programa_id, s.control_nombre, s.plato, s.hora_inicio, s.hora_fin,
           s.estado, s.fecha_peticion, s.notas,
           p.nombre AS programa_nombre,
           COUNT(DISTINCT sp.id) AS num_puestos,
           SUM(CASE WHEN sp.estado='cubierto' THEN 1 ELSE 0 END) AS puestos_cubiertos
    FROM solicitudes s
    JOIN programas p ON p.id = s.programa_id
    JOIN solicitud_fechas sf ON sf.solicitud_id = s.id
    JOIN solicitud_puestos sp ON sp.solicitud_id = s.id
    WHERE s.director_id = ? AND sf.fecha BETWEEN ? AND ?
    GROUP BY s.id
    ORDER BY s.fecha_peticion DESC
");
$stmt->bind_param("iss", $director_id, $fecha_ini_mes, $fecha_fin_mes);
$stmt->execute();
$solicitudes_mes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sol_ids = array_column($solicitudes_mes, 'id');
$fechas_por_sol = [];
if (!empty($sol_ids)) {
  $ph = implode(',', array_fill(0, count($sol_ids), '?'));
  $ty = str_repeat('i', count($sol_ids));
  $stmt = $conn->prepare("SELECT solicitud_id, fecha FROM solicitud_fechas WHERE solicitud_id IN ($ph) ORDER BY fecha ASC");
  $stmt->bind_param($ty, ...$sol_ids);
  $stmt->execute();
  foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $fechas_por_sol[$r['solicitud_id']][] = $r['fecha'];
  }
  $stmt->close();
}

$solicitudes_por_dia = [];
foreach ($solicitudes_mes as $s) {
  foreach ($fechas_por_sol[$s['id']] ?? [] as $f) {
    if ($f >= $fecha_ini_mes && $f <= $fecha_fin_mes)
      $solicitudes_por_dia[$f][$s['id']] = $s;
  }
}

$stmt = $conn->prepare("
    SELECT t.fecha, t.hora_inicio, t.hora_fin,
           t.puesto_solicitado, e.puesto AS puesto_real, u.nombre
    FROM turnos t
    JOIN empleados e ON e.id  = t.empleado_id
    JOIN usuarios u ON u.id  = e.usuario_id
    JOIN solicitudes s ON s.programa_id = t.programa_id
    WHERE s.director_id = ? AND t.fecha BETWEEN ? AND ? AND t.estado = 'cubierto'
    GROUP BY t.id
    ORDER BY t.fecha ASC, t.hora_inicio ASC
");
$stmt->bind_param("iss", $director_id, $fecha_ini_mes, $fecha_fin_mes);
$stmt->execute();
$turnos_cubiertos = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $t) {
  $turnos_cubiertos[$t['fecha']][] = $t;
}
$stmt->close();

$puestos_ordenados = ['JEFE', 'MEZCLA', 'SONIDO', 'CCU', 'ILUMINA', 'EVS', 'MULTIPLAY', 'ROTULO', 'PROMPT', 'PRIMERA', 'CAMARA', 'AUXILIAR'];

$conn->close();

$orden_claves = $puestos_ordenados;
$mapa_acen = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'ñ' => 'n', 'Ñ' => 'N'];
$norm = fn($s) => strtoupper(strtr($s, $mapa_acen));
$orden_fn = function ($p) use ($orden_claves, $norm) {
  $n = $norm($p);
  foreach ($orden_claves as $i => $c) {
    if (str_contains($n, $c))
      return $i;
  }
  return 99;
};

$meses_es = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$titulo_pagina = 'Turnos del programa';
$reabrir_form = !empty($mensaje_error) && !empty($form_data);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TurnosTV — Turnos del programa</title>
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="../fragmentos/styles/base.css">
  <link rel="stylesheet" href="styles/panel_director.css">
  <link rel="stylesheet" href="styles/turnos_programa.css">
</head>

<body>

  <?php include '../fragmentos/sidebar.php'; ?>

  <div class="content">
    <?php include '../fragmentos/header.php'; ?>

    <div class="body">

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
        <div style="font-size:13px;color:var(--suave)">Solicitudes de equipo</div>
        <a href="panel_director.php" style="font-size:13px;color:var(--suave);text-decoration:none">← Volver al
          inicio</a>
      </div>

      <?php if ($mensaje_ok):
        echo "<p class='mensaje-ok'>{$mensaje_ok}</p>";
      endif; ?>

      <!-- BOTÓN NUEVA SOLICITUD -->
      <div id="btn-nueva" style="margin-bottom:16px">
        <button type="button" class="btn" onclick="mostrarFormulario()" style="font-size:14px;padding:10px 20px">
          + Nueva solicitud
        </button>
      </div>

      <!-- FORMULARIO -->
      <div class="reserva-card" id="reserva-card" style="display:none">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <div style="font-size:14px;font-weight:700">Nueva solicitud</div>
          <button type="button" onclick="ocultarFormulario()"
            style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--suave)">×</button>
        </div>

        <div id="error-inline" class="error-inline" style="display:none"></div>

        <form method="POST" action="turnos_programa.php?mes=<?= $mes_offset ?>" id="form-solicitud">
          <input type="hidden" name="accion" value="nueva_solicitud">
          <input type="hidden" name="fechas_seleccionadas" id="fechas-hidden"
            value="<?= htmlspecialchars($form_data['fechas_raw'] ?? '') ?>">

          <!-- PROGRAMA -->
          <div class="reserva-seccion">0. Programa</div>
          <div class="form-field" id="wrap-programa" style="margin-bottom:16px">
            <label>Programa</label>
            <select name="programa_id" id="sel-programa" onchange="cambiarPrograma(this)">
              <option value="">— Selecciona un programa —</option>
              <?php foreach ($todos_programas as $prog): ?>
                <option value="<?= $prog['id'] ?>" data-ini="<?= substr($prog['hora_inicio'], 0, 5) ?>"
                  data-fin="<?= substr($prog['hora_fin'], 0, 5) ?>" <?= (($form_data['programa_id'] ?? 0) == $prog['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($prog['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- CONTROL Y PLATO -->
          <div class="reserva-seccion">1. Control y plato</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div class="form-field" id="wrap-control" style="margin-bottom:0">
              <label>Control</label>
              <select name="control_nombre">
                <option value="">— Selecciona —</option>
                <?php for ($cn = 1; $cn <= 10; $cn++): ?>
                  <option value="Control <?= $cn ?>" <?= (($form_data['control'] ?? '') === "Control $cn") ? 'selected' : '' ?>>
                    Control <?= $cn ?>
                  </option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="form-field" id="wrap-plato" style="margin-bottom:0">
              <label>Plato</label>
              <select name="plato">
                <option value="">— Selecciona —</option>
                <?php for ($pn = 1; $pn <= 7; $pn++): ?>
                  <option value="Plato <?= $pn ?>" <?= (($form_data['plato'] ?? '') === "Plato $pn") ? 'selected' : '' ?>>
                    Plato <?= $pn ?>
                  </option>
                <?php endfor; ?>
              </select>
            </div>
          </div>

          <!-- Horario del control -->
          <div id="wrap-horario-ctrl">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">
              <div>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                  <div style="flex:1">
                    <div
                      style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--suave);margin-bottom:4px">
                      Entrada control</div>
                    <input id="inp-ini" type="text" placeholder="00:00" maxlength="5"
                      value="<?= htmlspecialchars($form_data['ctrl_ini'] ?? '') ?>"
                      style="width:100%;font-size:28px;font-weight:700;border:none;border-bottom:2px solid var(--borde);background:none;outline:none;padding:4px 0;text-align:center;letter-spacing:2px;font-family:sans-serif">
                    <input type="hidden" name="control_inicio" id="ctrl-ini"
                      value="<?= htmlspecialchars($form_data['ctrl_ini'] ?? '') ?>">
                  </div>
                  <div style="font-size:18px;color:var(--suave);margin-top:14px">→</div>
                  <div style="flex:1">
                    <div
                      style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--suave);margin-bottom:4px">
                      Salida control</div>
                    <input id="inp-fin" type="text" placeholder="00:00" maxlength="5"
                      value="<?= htmlspecialchars($form_data['ctrl_fin'] ?? '') ?>"
                      style="width:100%;font-size:28px;font-weight:700;border:none;border-bottom:2px solid var(--borde);background:none;outline:none;padding:4px 0;text-align:center;letter-spacing:2px;font-family:sans-serif">
                    <input type="hidden" name="control_fin" id="ctrl-fin"
                      value="<?= htmlspecialchars($form_data['ctrl_fin'] ?? '') ?>">
                  </div>
                </div>
                <div id="dur-ctrl" style="font-size:12px;color:var(--suave);margin-bottom:6px;min-height:16px"></div>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <?php foreach ([['05:30', '13:30'], ['08:00', '14:00'], ['14:00', '22:00'], ['22:00', '24:00']] as $fp): ?>
                    <button type="button" class="btn-todos hora-pill-ctrl" data-ini="<?= $fp[0] ?>"
                      data-fin="<?= $fp[1] ?>" style="font-size:11px;padding:4px 8px" onclick="aplicarPillCtrl(this)">
                      <?= $fp[0] ?>–<?= $fp[1] ?>
                    </button>
                  <?php endforeach; ?>
                  <button type="button" class="btn-todos" style="font-size:11px;padding:4px 8px;margin-left:auto"
                    onclick="limpiarCtrl()">Limpiar</button>
                </div>
              </div>
              <div>
                <div
                  style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--suave);margin-bottom:6px">
                  Seleccionar arrastrando</div>
                <div id="grid-ctrl"
                  style="display:grid;grid-template-columns:repeat(25,1fr);gap:2px;user-select:none;align-items:end">
                </div>
              </div>
            </div>
          </div>

          <hr class="seccion-sep">

          <!-- DÍAS -->
          <div class="reserva-seccion">2. Días</div>
          <div id="wrap-fechas">
            <div class="carr-wrap">
              <button type="button" class="carr-arrow" id="carr-ant" onclick="carruselMover(-7)">←</button>
              <div class="carr-dias" id="carr-dias"></div>
              <button type="button" class="carr-arrow" id="carr-sig" onclick="carruselMover(7)">→</button>
            </div>
            <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
              <button type="button" class="btn-todos" onclick="seleccionarSemana()">Semana completa</button>
              <button type="button" class="btn-todos" onclick="limpiarDias()">Limpiar</button>
              <span class="dias-resumen" id="dias-resumen">Ningún día seleccionado</span>
            </div>
          </div>

          <hr class="seccion-sep">

          <!-- PUESTOS -->
          <div class="reserva-seccion">3. Puestos necesarios</div>
          <div id="wrap-puestos">
            <div style="display:flex;gap:6px;margin-bottom:8px">
              <button type="button" class="btn-todos" onclick="marcarTodos()">Todos</button>
              <button type="button" class="btn-todos" onclick="desmarcarTodos()">Limpiar selección</button>
            </div>
            <div class="puestos-grid" id="puestos-grid">
              <?php foreach ($puestos_ordenados as $p): ?>
                <button type="button" class="puesto-item" data-puesto="<?= htmlspecialchars($p, ENT_QUOTES) ?>"
                  onclick="toggleSeleccion(this)">
                  <span class="puesto-nombre"><?= htmlspecialchars($p) ?></span>
                </button>
              <?php endforeach; ?>
            </div>
            <div style="margin-top:10px">
              <button type="button" class="btn-todos" onclick="añadirSeleccionados()"
                style="border-color:#1a1a1a;font-weight:600;color:#1a1a1a;width:100%">
                + Añadir a la solicitud →
              </button>
            </div>
          </div>

          <!-- Lista puestos añadidos -->
          <div id="tabla-wrap" style="display:none;margin-top:16px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <span style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--suave)">Puestos en
                solicitud</span>
              <button type="button" onclick="limpiarPuestos()"
                style="font-size:11px;color:var(--peligro);background:none;border:none;cursor:pointer">Limpiar
                todo</button>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:13px">
              <tbody id="tabla-body"></tbody>
            </table>
          </div>

          <div class="form-field" style="margin-top:16px;margin-bottom:0">
            <label>Notas para el coordinador (opcional)</label>
            <textarea name="notas" rows="2" placeholder="Cualquier información adicional..."
              style="resize:none;overflow:hidden;line-height:1.5"
              oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"><?= htmlspecialchars($form_data['notas'] ?? '') ?></textarea>
          </div>

          <div class="reserva-footer">
            <span class="dias-resumen" id="dias-resumen2">Ningún día seleccionado</span>
            <button type="button" onclick="validarYEnviar()" class="btn" style="padding:10px 24px;font-size:14px">
              Enviar solicitud al coordinador →
            </button>
          </div>
        </form>
      </div>

      <!-- CALENDARIO -->
      <div class="cal-card" id="calendario">
        <div class="cal-nav">
          <a href="turnos_programa.php?mes=<?= $mes_offset - 1 ?>">←</a>
          <span class="cal-mes"><?= $meses_es[(int) $primer_dia->format('n') - 1] ?>
            <?= $primer_dia->format('Y') ?></span>
          <a href="turnos_programa.php?mes=<?= $mes_offset + 1 ?>">→</a>
        </div>
        <div class="cal-grid">
          <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $d): ?>
            <div class="cal-dia-nombre"><?= $d ?></div>
          <?php endforeach; ?>

          <?php
          $inicio_dow = (int) $primer_dia->format('N') - 1;
          $dias_mes = (int) $ultimo_dia->format('j');
          $hoy_str = date('Y-m-d');
          $dia_abierto = $_GET['dia'] ?? null;
          for ($v = 0; $v < $inicio_dow; $v++): ?>
            <div class="cal-dia vacio">
              <div class="cal-num"></div>
            </div>
          <?php endfor; ?>

          <?php for ($nd = 1; $nd <= $dias_mes; $nd++):
            $mes_pad = str_pad((int) $primer_dia->format('m'), 2, '0', STR_PAD_LEFT);
            $fecha_dia = $primer_dia->format('Y-') . $mes_pad . '-' . str_pad($nd, 2, '0', STR_PAD_LEFT);
            $solis_dia = $solicitudes_por_dia[$fecha_dia] ?? [];
            $turn_conf = $turnos_cubiertos[$fecha_dia] ?? [];
            $es_hoy = $fecha_dia === $hoy_str;
            $expandido = $dia_abierto === $fecha_dia;
            $dia_obj = new DateTime($fecha_dia);
            $dow = (int) $dia_obj->format('N');
            $tiene_datos = !empty($solis_dia) || !empty($turn_conf);
            ?>
            <div class="cal-dia <?= $es_hoy ? 'hoy' : '' ?> <?= !$tiene_datos ? 'sin-datos' : '' ?>" <?= $tiene_datos ? 'onclick="toggleDia(\'' . $fecha_dia . '\')"' : '' ?> style="<?= $dow === 7 ? 'border-right:none' : '' ?>">
              <div class="cal-num"><?= $nd ?></div>
              <div class="cal-tags">
                <?php foreach ($solis_dia as $s):
                  $lbl = match ($s['estado']) {
                    'pendiente' => '⏳ ' . $s['programa_nombre'],
                    'en_proceso' => '🔄 ' . $s['programa_nombre'],
                    'aprobada' => '✓ ' . $s['programa_nombre'],
                    'rechazada' => '✗ ' . $s['programa_nombre'],
                    default => $s['programa_nombre'],
                  };
                  ?>
                  <span class="cal-tag <?= $s['estado'] ?>"><?= htmlspecialchars($lbl) ?></span>
                <?php endforeach; ?>
                <?php if (!empty($turn_conf)): ?>
                  <span class="cal-tag cubierto">Equipo · <?= count($turn_conf) ?>
                    confirmado<?= count($turn_conf) > 1 ? 's' : '' ?></span>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($expandido && $tiene_datos): ?>
              <div class="detalle-sol visible">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                  <span style="font-size:13px;font-weight:700">
                    <?= $dia_obj->format('j') ?> de <?= strtolower($meses_es[(int) $dia_obj->format('n') - 1]) ?>
                  </span>
                  <a href="turnos_programa.php?mes=<?= $mes_offset ?>"
                    style="font-size:12px;color:var(--suave);text-decoration:none">Cerrar ×</a>
                </div>

                <?php foreach ($solis_dia as $s):
                  $clase_e = match ($s['estado']) { 'aprobada' => 'badge-ok', 'rechazada' => 'badge-peligro', 'en_proceso' => 'badge-neutro', default => 'badge-aviso'};
                  $label_e = match ($s['estado']) { 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada', 'en_proceso' => 'En proceso', default => 'Pendiente'};

                  $conn2 = new mysqli("localhost", "root", "", "turnostv");
                  $conn2->set_charset("utf8mb4");
                  $st = $conn2->prepare("SELECT sp.*, u.nombre AS emp_nombre FROM solicitud_puestos sp LEFT JOIN empleados e ON e.id=sp.empleado_id LEFT JOIN usuarios u ON u.id=e.usuario_id WHERE sp.solicitud_id=? ORDER BY sp.id ASC");
                  $st->bind_param("i", $s['id']);
                  $st->execute();
                  $puestos_sol = $st->get_result()->fetch_all(MYSQLI_ASSOC);
                  $st->close();
                  $conn2->close();
                  usort($puestos_sol, fn($a, $b) => $orden_fn($a['puesto_solicitado']) <=> $orden_fn($b['puesto_solicitado']));
                  ?>
                  <div class="sol-card">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
                      <div>
                        <div style="font-size:13px;font-weight:700">
                          <?= htmlspecialchars($s['programa_nombre']) ?> · <?= htmlspecialchars($s['control_nombre']) ?> ·
                          <?= htmlspecialchars($s['plato']) ?>
                        </div>
                        <div style="font-size:11px;color:var(--suave);margin-top:3px">
                          <?= substr($s['hora_inicio'], 0, 5) ?>–<?= substr($s['hora_fin'], 0, 5) ?> ·
                          <?= $s['puestos_cubiertos'] ?>/<?= $s['num_puestos'] ?> puestos cubiertos ·
                          Solicitado <?= date('d/m/Y', strtotime($s['fecha_peticion'])) ?>
                        </div>
                        <?php if (!empty($s['notas'])): ?>
                          <div style="font-size:11px;color:var(--suave);margin-top:4px;font-style:italic">
                            "<?= htmlspecialchars($s['notas']) ?>"</div>
                        <?php endif; ?>
                      </div>
                      <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                        <span class="badge <?= $clase_e ?>"><?= $label_e ?></span>
                        <?php if ($s['estado'] === 'pendiente'): ?>
                          <form method="POST" id="form-del-<?= $s['id'] ?>" style="display:inline">
                            <input type="hidden" name="accion" value="borrar_solicitud">
                            <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                            <button type="button"
                              onclick="abrirConfirm('¿Eliminar esta solicitud completa?','form-del-<?= $s['id'] ?>')"
                              style="font-size:11px;color:var(--peligro);background:none;border:1px solid rgba(185,28,28,0.25);border-radius:5px;padding:3px 10px;cursor:pointer">
                              Borrar solicitud
                            </button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </div>
                    <?php if (!empty($puestos_sol)): ?>
                      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">
                        <?php foreach ($puestos_sol as $sp):
                          [$bg, $tc] = match ($sp['estado']) { 'cubierto' => ['#DBEAFE', '#1e40af'], default => ['#FEF3C7', '#92400e']};
                          $txt = $sp['estado'] === 'cubierto' ? htmlspecialchars($sp['emp_nombre']) : 'Sin asignar';
                          ?>
                          <div
                            style="background:<?= $bg ?>;color:<?= $tc ?>;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:600">
                            <?= htmlspecialchars($sp['puesto_solicitado']) ?>
                            <span style="font-weight:400;opacity:0.8"> — <?= $txt ?></span>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>

                <?php if (!empty($turn_conf)):
                  usort($turn_conf, fn($a, $b) => $orden_fn($a['puesto_solicitado'] ?: $a['puesto_real']) <=> $orden_fn($b['puesto_solicitado'] ?: $b['puesto_real']));
                  ?>
                  <div class="detalle-seccion-titulo">Equipo confirmado ese día</div>
                  <div class="equipo-grid">
                    <?php foreach ($turn_conf as $tc):
                      $puesto_show = $tc['puesto_solicitado'] ?: $tc['puesto_real'];
                      $partes = explode(' ', trim($tc['nombre']));
                      $nombre_corto = $partes[0] . (isset($partes[1]) ? ' ' . strtoupper($partes[1][0]) . '.' : '');
                      ?>
                      <div class="emp-card">
                        <div class="emp-nombre"><?= htmlspecialchars($nombre_corto) ?></div>
                        <div class="emp-puesto"><?= htmlspecialchars($puesto_show) ?></div>
                        <div class="emp-horario"><?= substr($tc['hora_inicio'], 0, 5) ?> – <?= substr($tc['hora_fin'], 0, 5) ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

              </div>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
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

  <script>
    window.APP_CONFIG = {
      horaIniProg: '<?= $hora_ini_prog ?>',
      horaFinProg: '<?= $hora_fin_prog ?>',
      mesOffset: <?= (int) $mes_offset ?>,
      reabrirForm: <?= $reabrir_form ? 'true' : 'false' ?>,
      errorMsg: <?= json_encode($mensaje_error) ?>,
      errorFields: <?= json_encode($form_error_fields) ?>
    };
  </script>
  <script src="js/turnos_programa.js"></script>

</body>

</html>
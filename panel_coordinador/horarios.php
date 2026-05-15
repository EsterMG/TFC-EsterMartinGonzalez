<?php
/**
 * horarios.php * 
 * Cuando recibe ?solicitud_id=X muestra un panel lateral con los puestos
 * pendientes de esa solicitud para que el coordinador los cubra sin salir.
 */

session_start();
if (empty($_SESSION['rol']) || $_SESSION['rol'] !== 'coordinador') {
  header("Location: ../login.php");
  exit;
}

$conn = new mysqli("localhost", "root", "", "turnostv");
$conn->set_charset("utf8mb4");
if ($conn->connect_error)
  die("Error de conexión: " . $conn->connect_error);

$stmt = $conn->prepare("SELECT id FROM coordinadores WHERE usuario_id = ?");
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$coord = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$coord)
  die("Coordinador no encontrado.");
$coordinador_id = $coord['id'];

require_once 'horarios_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $accion = $_POST['accion'] ?? '';
  $fecha_ctx = $_POST['fecha_ctx'] ?? date('Y-m-d');
  $mes_ctx = (int) ($_POST['mes_ctx'] ?? 0);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_ctx))
    $fecha_ctx = date('Y-m-d');
  $msg_ok = '';
  $msg_err = '';

  require 'horarios_actions.php';

  $ctrl_activo = $_POST['ctrl_activo'] ?? '';
  $solicitud_id = (int) ($_POST['solicitud_id_ctx'] ?? 0);
  $redir = "horarios.php?fecha={$fecha_ctx}&mes={$mes_ctx}"
    . ($ctrl_activo ? "&ctrl=" . urlencode($ctrl_activo) : '')
    . ($solicitud_id ? "&solicitud_id=" . $solicitud_id : '');

  if ($msg_ok) {
    header("Location: {$redir}&ok=" . urlencode($msg_ok));
    exit;
  }
  if ($msg_err) {
    header("Location: {$redir}&err=" . urlencode($msg_err));
    exit;
  }
  header("Location: {$redir}");
  exit;
}

require 'horarios_data.php';

// Panel lateral: cargar solicitud si viene en la URL
$solicitud_id = (int) ($_GET['solicitud_id'] ?? 0);
$solicitud_panel = null;
$puestos_panel = [];
$fechas_panel = [];

if ($solicitud_id > 0) {
  $st = $conn->prepare("
        SELECT s.id, s.control_nombre, s.plato, s.hora_inicio, s.hora_fin,
               s.estado, s.notas, s.fecha_peticion,
               p.nombre AS programa_nombre, u.nombre AS director_nombre
        FROM solicitudes s
        JOIN programas p  ON p.id  = s.programa_id
        JOIN directores d ON d.id  = s.director_id
        JOIN usuarios u   ON u.id  = d.usuario_id
        WHERE s.id = ?
    ");
  $st->bind_param("i", $solicitud_id);
  $st->execute();
  $solicitud_panel = $st->get_result()->fetch_assoc();
  $st->close();

  if ($solicitud_panel) {
    $st = $conn->prepare("
            SELECT sp.id, sp.puesto_solicitado, sp.hora_inicio, sp.hora_fin, sp.estado,
                   u.nombre AS emp_nombre
            FROM solicitud_puestos sp
            LEFT JOIN empleados e ON e.id = sp.empleado_id
            LEFT JOIN usuarios  u ON u.id = e.usuario_id
            WHERE sp.solicitud_id = ?
            ORDER BY sp.id ASC
        ");
    $st->bind_param("i", $solicitud_id);
    $st->execute();
    $puestos_panel = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    $st = $conn->prepare("SELECT fecha FROM solicitud_fechas WHERE solicitud_id = ? ORDER BY fecha ASC");
    $st->bind_param("i", $solicitud_id);
    $st->execute();
    $fechas_panel = array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), 'fecha');
    $st->close();
  }
}

$conn->close();

$titulo_pagina = 'Horarios';
$opHora = opcionesHora();

// Si viene ctrl en la URL, se usa como control activo por defecto
$ctrl_url = $_GET['ctrl'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TurnosTV — Horarios</title>
  <link rel="icon" type="image/png" href="../img/favicon.png">
  <link rel="stylesheet" href="../fragmentos/styles/base.css">
  <link rel="stylesheet" href="styles/horarios.css">
  <?php if ($solicitud_panel): ?>
    <style>

    </style>
  <?php endif; ?>
</head>

<body>

  <?php include '../fragmentos/sidebar.php'; ?>

  <div class="content">
    <?php include '../fragmentos/header.php'; ?>
    <div class="body">

      <!-- COLUMNA PRINCIPAL -->
      <div class="hor-main">

        <?php if ($msg_ok):
          echo "<p class='mensaje-ok'>" . htmlspecialchars($msg_ok) . "</p>";
        endif; ?>
        <?php if ($msg_err):
          echo "<p class='mensaje-error'>" . htmlspecialchars($msg_err) . "</p>";
        endif; ?>

        <?php if ($solicitud_panel): ?>
          <!-- Aviso encima del Gantt -->
          <div
            style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;color:#1e40af;display:flex;align-items:center;justify-content:space-between;gap:12px">
            <span>
              📋 Asignando equipo para <strong><?= htmlspecialchars($solicitud_panel['programa_nombre']) ?></strong> —
              <?= htmlspecialchars($solicitud_panel['control_nombre']) ?> ·
              <?= htmlspecialchars($solicitud_panel['plato']) ?> ·
              <?= substr($solicitud_panel['hora_inicio'], 0, 5) ?>–<?= substr($solicitud_panel['hora_fin'], 0, 5) ?>
            </span>
            <a href="peticiones.php?tab=solicitudes"
              style="font-size:11px;color:#3B82F6;text-decoration:none;flex-shrink:0">← Volver a peticiones</a>
          </div>
        <?php endif; ?>

        <!-- BARRA DE NAVEGACIÓN -->
        <div class="hor-nav">
          <button class="cal-btn cal-btn-grande" onclick="abrirCalendario()">
            📅 <?= $dias_es[(int) $fecha_obj->format('w')] ?>,
            <?= $fecha_obj->format('j') ?> <?= $meses_es[(int) $fecha_obj->format('n') - 1] ?>
            <?= $fecha_obj->format('Y') ?>
            <?php if ($festivo_hoy): ?>
              <span class="badge-festivo <?= $festivo_hoy['tipo'] === 'festivo' ? 'badge-fs' : 'badge-fd' ?>">
                <?= $festivo_hoy['tipo'] === 'festivo' ? '🎉 Festivo' : '📅 Fin semana' ?>
              </span>
            <?php endif; ?>
          </button>
          <a href="horarios.php?fecha=<?= $fecha_ant ?>&mes=<?= $mes_offset ?><?= $solicitud_id ? '&solicitud_id=' . $solicitud_id : '' ?>"
            class="nav-arrow">←</a>
          <a href="horarios.php?fecha=<?= $fecha_sig ?>&mes=<?= $mes_offset ?><?= $solicitud_id ? '&solicitud_id=' . $solicitud_id : '' ?>"
            class="nav-arrow">→</a>
          <?php if ($fecha_sel !== $hoy_str): ?>
            <a href="horarios.php?mes=<?= $mes_offset ?><?= $solicitud_id ? '&solicitud_id=' . $solicitud_id : '' ?>"
              class="nav-hoy">Hoy</a>
          <?php endif; ?>
          <button class="btn" onclick="abrirNuevo()">+ Nuevo turno</button>
        </div>

        <!-- GANTT idéntico al original -->
        <div class="gantt-card">
          <div class="gantt-head" style="padding:8px 14px">
            <div class="leyenda">
              <span><i style="background:#DBEAFE;border:1px solid #93C5FD"></i>Cubierto</span>
              <span><i style="background:#e5e7eb;border:1px solid #d1d5db"></i>Sin asignar</span>
              <span style="font-size:10px;color:var(--suave);margin-left:8px">← Arrastra los extremos de una barra para
                cambiar horario</span>
            </div>
          </div>

          <div class="ctrl-tabs">
            <?php for ($cn = 1; $cn <= 10; $cn++):
              $ck = "Control {$cn}";
              $tiene = !empty($por_ctrl[$ck]['turnos']) || !empty($por_ctrl[$ck]['solis']) || !empty($por_ctrl[$ck]['bloques']);
              ?>
              <div class="ctrl-tab<?= $tiene ? ' ctrl-tab-con-datos' : '' ?>" data-ctrl="Control <?= $cn ?>"
                onclick="activarTab('Control <?= $cn ?>')">C<?= $cn ?></div>
            <?php endfor; ?>
          </div>

          <div class="gantt-scroll">
            <div class="g-inner">

              <!-- Cabecera de horas -->
              <div class="g-hdr">
                <div class="g-col"></div>
                <div class="g-horas">
                  <?php foreach ($slots as $idx_s => $slot):
                    if ($idx_s % 4 === 0): ?>
                      <span
                        style="position:absolute;left:<?= ($idx_s / $ns * 100) ?>%;font-size:9px;font-weight:700;color:#1a5fa8;transform:translateX(-50%);top:4px;white-space:nowrap"><?= $slot ?></span>
                    <?php endif; endforeach; ?>
                </div>
              </div>

              <?php foreach ($por_ctrl as $ctrl_nombre => $dc):
                $es_vacio = empty($dc['turnos']) && empty($dc['solis']) && empty($dc['bloques']);
                $alto_ctrl = 28;
                $filas_b_pre = [];
                foreach ($dc['bloques'] as $bloque) {
                  $ini_m = $hhmm($bloque['hora_inicio']);
                  $fin_m = $hhmm($bloque['hora_fin']);
                  if ($fin_m <= $ini_m)
                    $fin_m += 1440;
                  $fila_ok = -1;
                  foreach ($filas_b_pre as $fi => $ocs) {
                    $solapa = false;
                    foreach ($ocs as $oc) {
                      if ($ini_m < $oc[1] && $fin_m > $oc[0]) {
                        $solapa = true;
                        break;
                      }
                    }
                    if (!$solapa) {
                      $fila_ok = $fi;
                      break;
                    }
                  }
                  if ($fila_ok === -1) {
                    $fila_ok = count($filas_b_pre);
                    $filas_b_pre[] = [];
                  }
                  $filas_b_pre[$fila_ok][] = [$ini_m, $fin_m];
                }
                if (!empty($filas_b_pre))
                  $alto_ctrl = max(28, count($filas_b_pre) * 28);

                $puestos_ctrl = [];
                foreach ($dc['turnos'] as $t)
                  $puestos_ctrl[$t['puesto_solicitado']][] = ['tipo' => 'turno', 'data' => $t];
                foreach ($dc['solis'] as $sol_id => $entradas)
                  foreach ($entradas as $e)
                    $puestos_ctrl[$e['puesto_solicitado']][] = ['tipo' => 'sol', 'data' => $e];
                uksort($puestos_ctrl, fn($a, $b) => ordenPuesto($a) <=> ordenPuesto($b));
                ?>
                <div class="g-ctrl-bloque" data-ctrl="<?= htmlspecialchars($ctrl_nombre, ENT_QUOTES) ?>">

                  <div class="g-fila-ctrl">
                    <div class="glbl" style="display:flex;align-items:center;justify-content:space-between;gap:4px">
                      <span><?= htmlspecialchars($ctrl_nombre) ?></span>
                      <?php if (!$es_vacio): ?>
                        <button type="button" onclick="limpiarControl('<?= htmlspecialchars($ctrl_nombre, ENT_QUOTES) ?>')"
                          class="btn-limpiar">🗑</button>
                      <?php endif; ?>
                    </div>
                    <div class="gcel" style="min-height:<?= $alto_ctrl ?>px">
                      <?php foreach ($slots as $idx_s => $slot): ?>
                        <div class="gv"
                          style="left:<?= ($idx_s / $ns * 100) ?>%;border-color:<?= $idx_s % 4 === 0 ? '#c8d8f0' : ($idx_s % 2 === 0 ? '#dde4f0' : '#eef3fb') ?>;height:<?= $alto_ctrl ?>px">
                        </div>
                      <?php endforeach; ?>

                      <?php if (!empty($dc['bloques'])):
                        $filas_b = [];
                        $fila_bloque = [];
                        foreach ($dc['bloques'] as $ib => $bloque) {
                          $ini_m = $hhmm($bloque['hora_inicio']);
                          $fin_m = $hhmm($bloque['hora_fin']);
                          if ($fin_m <= $ini_m)
                            $fin_m += 1440;
                          $fila_ok = -1;
                          foreach ($filas_b as $fi => $ocs) {
                            $solapa = false;
                            foreach ($ocs as $oc) {
                              if ($ini_m < $oc[1] && $fin_m > $oc[0]) {
                                $solapa = true;
                                break;
                              }
                            }
                            if (!$solapa) {
                              $fila_ok = $fi;
                              break;
                            }
                          }
                          if ($fila_ok === -1) {
                            $fila_ok = count($filas_b);
                            $filas_b[] = [];
                          }
                          $filas_b[$fila_ok][] = [$ini_m, $fin_m];
                          $fila_bloque[$ib] = $fila_ok;
                        }
                        $altura_bloque = 28;
                        ?>
                        <?php foreach ($dc['bloques'] as $ib => $bloque):
                          [$lp, $wp] = $gpos($bloque['hora_inicio'], $bloque['hora_fin']);
                          $top_b = $fila_bloque[$ib] * $altura_bloque + 2;
                          ?>
                          <div class="barra b-prog"
                            style="left:<?= $lp ?>%;width:<?= $wp ?>%;top:<?= $top_b ?>px;bottom:auto;height:<?= $altura_bloque - 4 ?>px;cursor:pointer"
                            data-bloque-id="<?= $bloque['bloque_id'] ?>"
                            data-ini="<?= substr($bloque['hora_inicio'], 0, 5) ?>"
                            data-fin="<?= substr($bloque['hora_fin'], 0, 5) ?>"
                            onclick="abrirEditBloque(<?= $bloque['bloque_id'] ?>,'<?= htmlspecialchars($ctrl_nombre, ENT_QUOTES) ?>','<?= substr($bloque['hora_inicio'], 0, 5) ?>','<?= substr($bloque['hora_fin'], 0, 5) ?>',<?= $bloque['programa_id'] ?? 0 ?>)">
                            <span class="barra-handle barra-handle-ini" data-lado="ini" data-tipo="bloque"></span>
                            <?= substr($bloque['hora_inicio'], 0, 5) ?> —
                            <?= htmlspecialchars($bloque['prog_nombre'] ?: 'Sin programa') ?> —
                            <?= substr($bloque['hora_fin'], 0, 5) ?>
                            <span class="barra-handle barra-handle-fin" data-lado="fin" data-tipo="bloque"></span>
                          </div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div class="gcel-vacio"
                          onclick="abrirNuevoConCtrl('<?= htmlspecialchars($ctrl_nombre, ENT_QUOTES) ?>')">Clic para crear
                          turno</div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <?php foreach ($puestos_todos as $puesto_nombre):
                    $entradas_p = $puestos_ctrl[$puesto_nombre] ?? [];
                    $altura_barra = 36;
                    $filas = [];
                    $fila_asignada = [];
                    foreach ($entradas_p as $idx_e => $ent) {
                      $r = $ent['data'];
                      $ini_m = $hhmm($ent['tipo'] === 'turno' ? $r['hora_inicio'] : $r['p_ini']);
                      $fin_m = $hhmm($ent['tipo'] === 'turno' ? $r['hora_fin'] : $r['p_fin']);
                      if ($fin_m <= $ini_m)
                        $fin_m += 1440;
                      $fila_ok = -1;
                      foreach ($filas as $fi => $ocs) {
                        $solapa = false;
                        foreach ($ocs as $oc) {
                          if ($ini_m < $oc[1] && $fin_m > $oc[0]) {
                            $solapa = true;
                            break;
                          }
                        }
                        if (!$solapa) {
                          $fila_ok = $fi;
                          break;
                        }
                      }
                      if ($fila_ok === -1) {
                        $fila_ok = count($filas);
                        $filas[] = [];
                      }
                      $filas[$fila_ok][] = [$ini_m, $fin_m];
                      $fila_asignada[$idx_e] = $fila_ok;
                    }
                    $alto_celda = max(56, max(1, count($filas)) * $altura_barra);
                    $pidx = indicePuesto($puesto_nombre);
                    ?>
                    <div class="g-fila-p">
                      <div class="glbl"><?= htmlspecialchars($puesto_nombre) ?></div>
                      <div class="gcel" style="min-height:<?= $alto_celda ?>px"
                        data-puesto="<?= htmlspecialchars($puesto_nombre, ENT_QUOTES) ?>"
                        data-ctrl="<?= htmlspecialchars($ctrl_nombre, ENT_QUOTES) ?>" data-slot-ini="<?= $slot_ini ?>"
                        data-ventana="<?= $ventana ?>">

                        <?php foreach ($slots as $idx_s => $slot): ?>
                          <div class="gv"
                            style="left:<?= ($idx_s / $ns * 100) ?>%;border-color:<?= $idx_s % 4 === 0 ? '#e0e0e0' : ($idx_s % 2 === 0 ? '#ebebeb' : '#f5f5f5') ?>;height:<?= $alto_celda ?>px">
                          </div>
                        <?php endforeach; ?>

                        <?php foreach ($entradas_p as $idx_e => $ent):
                          $r = $ent['data'];
                          if ($ent['tipo'] === 'turno') {
                            $ini_b = $r['hora_inicio'];
                            $fin_b = $r['hora_fin'];
                            $cub = $r['estado'] === 'cubierto';
                            $txt = $cub ? (htmlspecialchars($r['emp_nombre'] ?? '—') . ' ' . substr($ini_b, 0, 5) . '–' . substr($fin_b, 0, 5)) : 'Sin asignar ' . substr($ini_b, 0, 5) . '–' . substr($fin_b, 0, 5);
                            $cls_b = $cub ? 'b-cub' : 'b-sin';
                            $tid = $r['id'];
                            $sid = 0;
                            $pid = 0;
                          } else {
                            $ini_b = $r['p_ini'];
                            $fin_b = $r['p_fin'];
                            $cub = $r['puesto_estado'] === 'cubierto';
                            $txt = $cub ? (htmlspecialchars($r['emp_nombre'] ?? '—') . ' ' . substr($ini_b, 0, 5) . '–' . substr($fin_b, 0, 5)) : 'Sin asignar ' . substr($ini_b, 0, 5) . '–' . substr($fin_b, 0, 5);
                            $cls_b = $cub ? 'b-cub' : 'b-sin';
                            $tid = 0;
                            $sid = $r['sol_id'];
                            $pid = $r['puesto_sol_id'];
                          }
                          [$lp_b, $wp_b] = $gpos($ini_b, $fin_b);
                          $top_b = $fila_asignada[$idx_e] * $altura_barra + 3;
                          $emp_nombre_esc = $cub ? htmlspecialchars($r['emp_nombre'] ?? '', ENT_QUOTES) : '';
                          $emp_id_b = $cub ? ($r['emp_id'] ?? 0) : 0;
                          ?>
                          <div class="barra <?= $cls_b ?>" data-puesto-idx="<?= $pidx ?>" data-emp-id="<?= $emp_id_b ?>"
                            data-turno-id="<?= $tid ?>" data-sol-id="<?= $sid ?>" data-ini="<?= substr($ini_b, 0, 5) ?>"
                            data-fin="<?= substr($fin_b, 0, 5) ?>"
                            style="left:<?= $lp_b ?>%;width:<?= $wp_b ?>%;top:<?= $top_b ?>px;bottom:auto;height:<?= $altura_barra - 6 ?>px"
                            onclick="event.stopPropagation();abrirAsignar('<?= htmlspecialchars($puesto_nombre, ENT_QUOTES) ?>','<?= htmlspecialchars($ctrl_nombre, ENT_QUOTES) ?>',<?= $tid ?>,<?= $sid ?>,<?= $pid ?>,'<?= substr($ini_b, 0, 5) ?>','<?= substr($fin_b, 0, 5) ?>',<?= $cub ? 1 : 0 ?>,'<?= $emp_nombre_esc ?>')">
                            <span class="barra-txt"><?= $txt ?></span>
                            <span class="barra-handle barra-handle-ini" data-lado="ini"></span>
                            <span class="barra-handle barra-handle-fin" data-lado="fin"></span>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>

                </div>
              <?php endforeach; ?>

            </div>
          </div>
        </div><!-- fin gantt-card -->

      </div><!-- fin hor-main -->

      <!-- Panel lateral de solicitud -->
      <?php if ($solicitud_panel):
        $total_p = count($puestos_panel);
        $cubiertos = count(array_filter($puestos_panel, fn($p) => $p['estado'] === 'cubierto'));
        $pct_panel = $total_p > 0 ? round($cubiertos / $total_p * 100) : 0;
        ?>
        <div class="sol-panel">
          <div class="sol-panel-head">
            <h3>📋 <?= htmlspecialchars($solicitud_panel['programa_nombre']) ?></h3>
            <p><?= htmlspecialchars($solicitud_panel['control_nombre']) ?> ·
              <?= htmlspecialchars($solicitud_panel['plato']) ?> ·
              <?= substr($solicitud_panel['hora_inicio'], 0, 5) ?>–<?= substr($solicitud_panel['hora_fin'], 0, 5) ?>
            </p>
            <p style="margin-top:2px">Director: <?= htmlspecialchars($solicitud_panel['director_nombre']) ?></p>
          </div>

          <!-- Progreso -->
          <div class="sol-progreso">
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--suave);margin-bottom:3px">
              <span>Puestos cubiertos</span>
              <span><?= $cubiertos ?>/<?= $total_p ?></span>
            </div>
            <div class="sol-progreso-bar">
              <div class="sol-progreso-fill"
                style="width:<?= $pct_panel ?>%;background:<?= $pct_panel === 100 ? '#16A34A' : '#1e40af' ?>"></div>
            </div>
          </div>

          <!-- Puestos -->
          <div class="sol-panel-body">
            <?php foreach ($puestos_panel as $pp):
              $cub_p = $pp['estado'] === 'cubierto';
              [$bg, $tc] = $cub_p ? ['#DBEAFE', '#1e40af'] : ['#FEF3C7', '#92400e'];
              ?>
              <div class="sol-puesto-row">
                <div>
                  <div class="sol-puesto-nombre"><?= htmlspecialchars($pp['puesto_solicitado']) ?></div>
                  <div class="sol-puesto-emp" style="color:<?= $tc ?>">
                    <?= $cub_p ? htmlspecialchars($pp['emp_nombre']) : 'Sin asignar' ?>
                  </div>
                </div>
                <span
                  style="width:8px;height:8px;border-radius:50%;background:<?= $cub_p ? '#16A34A' : '#D97706' ?>;flex-shrink:0;display:inline-block"></span>
              </div>
            <?php endforeach; ?>

            <?php if (!empty($solicitud_panel['notas'])): ?>
              <div
                style="margin-top:10px;padding-top:10px;border-top:1px solid var(--borde);font-size:11px;color:var(--suave);font-style:italic">
                "<?= htmlspecialchars($solicitud_panel['notas']) ?>"
              </div>
            <?php endif; ?>
          </div>

          <!-- Días de la solicitud -->
          <?php if (!empty($fechas_panel)): ?>
            <div class="sol-fechas">
              <span style="font-size:10px;color:var(--suave);width:100%;margin-bottom:2px">Días de la solicitud:</span>
              <?php foreach ($fechas_panel as $f):
                $es_activa = $f === $fecha_sel;
                $url_f = "horarios.php?fecha=" . urlencode($f)
                  . "&mes=" . $mes_offset
                  . "&ctrl=" . urlencode($solicitud_panel['control_nombre'])
                  . "&solicitud_id=" . $solicitud_id;
                ?>
                <a href="<?= htmlspecialchars($url_f) ?>" class="sol-fecha-btn <?= $es_activa ? 'activa' : '' ?>">
                  <?= date('d/m', strtotime($f)) ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div style="padding:10px 14px;border-top:1px solid var(--borde)">
            <a href="peticiones.php?tab=solicitudes" class="btn-outline"
              style="display:block;text-align:center;font-size:12px;padding:6px">← Volver a peticiones</a>
          </div>
        </div>
      <?php endif; ?>

    </div><!-- fin body grid -->
  </div><!-- fin content -->

  <!-- POPUP: calendario -->
  <div class="ov" id="ovCalendario">
    <div class="pop-cal" onclick="event.stopPropagation()">
      <div class="pop-cal-header">
        <button onclick="cambiarMes(-1)" class="cal-mes-btn">←</button>
        <span id="cal-titulo-mes" class="cal-mes-titulo"><?= $meses_es[(int) $primer_dia->format('n') - 1] ?>
          <?= $primer_dia->format('Y') ?></span>
        <button onclick="cambiarMes(1)" class="cal-mes-btn">→</button>
      </div>
      <div class="cal-modo-btns">
        <button class="cal-modo-btn" id="btn-modo-fs" onclick="toggleModo('fs')">🎉 FS — Festivo</button>
        <button class="cal-modo-btn" id="btn-modo-fd" onclick="toggleModo('fd')">📅 FD — Fin de semana</button>
      </div>
      <div id="cal-modo-info" class="cal-modo-info"></div>
      <div class="cal-grid">
        <?php foreach (['L', 'M', 'X', 'J', 'V', 'S', 'D'] as $d): ?>
          <div class="cal-dow"><?= $d ?></div><?php endforeach; ?>
        <?php
        $idow = (int) $primer_dia->format('N') - 1;
        $dmes = (int) $ultimo_dia->format('j');
        for ($v = 0; $v < $idow; $v++)
          echo '<div class="cal-dia vacio"></div>';
        for ($nd = 1; $nd <= $dmes; $nd++) {
          $fnd = $primer_dia->format('Y-m-') . str_pad($nd, 2, '0', STR_PAD_LEFT);
          $dow_nd = (int) (new DateTime($fnd))->format('N');
          $dat = $cal_data[$fnd] ?? null;
          $tipo_e = $cal_data[$fnd]['tipo_especial'] ?? null;
          $cls_dia = 'cal-dia'
            . ($fnd === $hoy_str ? ' hoy' : '')
            . ($fnd === $fecha_sel ? ' sel' : '')
            . ($tipo_e === 'festivo' ? ' dia-fs' : '')
            . ($tipo_e === 'fin_de_semana' ? ' dia-fd' : '');
          $cubiertos = (int) ($dat['cubiertos'] ?? 0);
          $total = (int) ($dat['total'] ?? 0);
          $progs_dia = $progs_por_dia[$fnd] ?? [];
          $progs_html = '';
          foreach (array_unique($progs_dia) as $pn)
            $progs_html .= "<div class='cal-prog-nombre'>" . htmlspecialchars($pn) . "</div>";
          if ($tipo_e === 'festivo')
            $tag = "<div class='cal-mini cal-fest'>🎉" . ($total > 0 ? " {$cubiertos}/{$total}" : "") . "</div>" . $progs_html;
          elseif ($tipo_e === 'fin_de_semana')
            $tag = "<div class='cal-mini cal-fd'>📅" . ($total > 0 ? " {$cubiertos}/{$total}" : "") . "</div>" . $progs_html;
          elseif ($total > 0) {
            $pct = $cubiertos / $total;
            $tc = $pct >= 1 ? 'ok' : ($pct > 0 ? 'pend' : 'vacio-t');
            $tag = "<div class='cal-mini {$tc}'>{$cubiertos}/{$total}</div>" . $progs_html;
          } else {
            $tag = $progs_html;
          }

          // Mantener solicitud_id al navegar desde el calendario
          $extra_cal = $solicitud_id ? "&solicitud_id={$solicitud_id}" : '';
          echo "<div class='{$cls_dia}' data-fecha='{$fnd}' data-extra='" . htmlspecialchars($extra_cal) . "' onclick=\"calDiaClick('{$fnd}')\" style='" . ($dow_nd === 7 ? 'border-right:none' : '') . "'><div class='cal-num'>{$nd}</div>{$tag}</div>";
        }
        ?>
      </div>
    </div>
  </div>

  <!-- POPUP: nuevo turno -->
  <div class="ov" id="ovNuevo">
    <div class="pop" onclick="event.stopPropagation()" style="max-width:460px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <div class="pop-tit">Nuevo turno</div>
        <button onclick="cerrarNuevo()"
          style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--suave);line-height:1">×</button>
      </div>
      <div style="display:flex;gap:0;border-bottom:2px solid var(--borde);margin-bottom:16px">
        <button type="button" id="nt-tab-normal" onclick="ntCambiarModo('normal')"
          style="background:none;border:none;border-bottom:2px solid var(--texto);margin-bottom:-2px;padding:8px 16px;font-size:13px;font-weight:600;color:var(--texto);cursor:pointer">Normal</button>
        <button type="button" id="nt-tab-avanzado" onclick="ntCambiarModo('avanzado')"
          style="background:none;border:none;border-bottom:2px solid transparent;margin-bottom:-2px;padding:8px 16px;font-size:13px;font-weight:600;color:var(--suave);cursor:pointer">
          Por programa</button>
      </div>
      <div id="nt-panel-normal">
        <form method="POST" action="horarios.php">
          <input type="hidden" name="accion" value="crear_turno">
          <input type="hidden" name="fechas_seleccionadas" id="nt-fechas-hidden">
          <input type="hidden" name="fecha_ctx" value="<?= $fecha_sel ?>">
          <input type="hidden" name="mes_ctx" value="<?= $mes_offset ?>">
          <input type="hidden" name="ctrl_activo" id="nt-ctrl-activo">
          <input type="hidden" name="solicitud_id_ctx" value="<?= $solicitud_id ?>">
          <div class="pop-f">
            <label>Días *</label>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px">
              <button type="button" id="nt-carr-ant" onclick="ntCarrusel(-7)"
                style="flex-shrink:0;width:26px;height:36px;border:1px solid var(--borde);border-radius:6px;background:var(--fondo);cursor:pointer;font-size:13px">←</button>
              <div id="nt-carr-dias" style="display:flex;gap:3px;flex:1;min-width:0;overflow:hidden"></div>
              <button type="button" id="nt-carr-sig" onclick="ntCarrusel(7)"
                style="flex-shrink:0;width:26px;height:36px;border:1px solid var(--borde);border-radius:6px;background:var(--fondo);cursor:pointer;font-size:13px">→</button>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <button type="button" onclick="ntSelSemana()"
                style="font-size:11px;padding:3px 10px;background:none;border:1px solid var(--borde);border-radius:6px;cursor:pointer">Semana</button>
              <button type="button" onclick="ntLimpiarDias()"
                style="font-size:11px;padding:3px 10px;background:none;border:1px solid var(--borde);border-radius:6px;cursor:pointer">Limpiar</button>
              <span id="nt-resumen-dias" style="font-size:11px;color:var(--suave)">Ningún día</span>
            </div>
          </div>
          <div class="pop-f">
            <label>Programa <span style="font-weight:400;opacity:.6">(opcional)</span></label>
            <select name="programa_id">
              <option value="0">— Sin programa —</option>
              <?php foreach ($programas_lista as $pg): ?>
                <option value="<?= $pg['id'] ?>"><?= htmlspecialchars($pg['nombre']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="pop-r2">
            <div class="pop-f"><label>Control *</label><select name="control_nombre" id="nt-control"
                onchange="ntAutoPlato()" required>
                <option value="">— —</option><?php for ($cn = 1; $cn <= 10; $cn++): ?>
                  <option>Control <?= $cn ?></option><?php endfor; ?>
              </select></div>
            <div class="pop-f"><label>Plato</label><select name="plato" id="nt-plato">
                <option value="">— —</option><?php for ($pn = 1; $pn <= 7; $pn++): ?>
                  <option>Plato <?= $pn ?></option><?php endfor; ?>
              </select></div>
          </div>
          <div class="pop-r2">
            <div class="pop-f"><label>Hora inicio *</label><select name="hora_inicio" class="pop-time-sel"
                required><?= $opHora ?></select></div>
            <div class="pop-f"><label>Hora fin *</label><select name="hora_fin" class="pop-time-sel"
                required><?= $opHora ?></select></div>
          </div>
          <div class="pop-btns"><button type="button" class="pop-c" onclick="cerrarNuevo()">Cancelar</button><button
              type="button" class="pop-s" onclick="confirmarCrearTurno()">Crear turnos →</button></div>
        </form>
      </div>
      <div id="nt-panel-avanzado" style="display:none">
        <form method="POST" action="horarios.php">
          <input type="hidden" name="accion" value="crear_turno_programa">
          <input type="hidden" name="fechas_seleccionadas" id="nt-av-fechas-hidden">
          <input type="hidden" name="fecha_ctx" value="<?= $fecha_sel ?>">
          <input type="hidden" name="mes_ctx" value="<?= $mes_offset ?>">
          <input type="hidden" name="ctrl_activo" id="nt-av-ctrl-activo">
          <input type="hidden" name="empleados_seleccionados" id="nt-av-empleados-hidden">
          <input type="hidden" name="solicitud_id_ctx" value="<?= $solicitud_id ?>">
          <div
            style="background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.2);border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:12px;color:#1e40af">
            Crea turnos automáticamente.</div>
          <div class="pop-f"><label>Programa *</label><select name="programa_id" id="nt-av-prog"
              onchange="ntAvCargarInfo()" required>
              <option value="">— Selecciona —</option><?php foreach ($programas_lista as $pg): ?>
                <option value="<?= $pg['id'] ?>" data-ini="<?= substr($pg['hora_inicio'] ?? '00:00', 0, 5) ?>"
                  data-fin="<?= substr($pg['hora_fin'] ?? '00:00', 0, 5) ?>"><?= htmlspecialchars($pg['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select></div>
          <div class="pop-r2">
            <div class="pop-f"><label>Hora inicio *</label><select name="hora_inicio" id="nt-av-ini"
                class="pop-time-sel" required><?= $opHora ?></select></div>
            <div class="pop-f"><label>Hora fin *</label><select name="hora_fin" id="nt-av-fin" class="pop-time-sel"
                required><?= $opHora ?></select></div>
          </div>
          <div class="pop-f"><label>Control *</label><select name="control_nombre" id="nt-av-control" required>
              <option value="">— —</option><?php for ($cn = 1; $cn <= 10; $cn++): ?>
                <option>Control <?= $cn ?></option><?php endfor; ?>
            </select></div>
          <div class="pop-f">
            <label>Empleados *</label>
            <div id="nt-av-lista-empleados"
              style="max-height:180px;overflow-y:auto;border:1px solid var(--borde);border-radius:8px;padding:6px 8px;background:var(--fondo)">
              <?php
              $emps_por_puesto = [];
              foreach ($empleados_dia as $emp)
                $emps_por_puesto[$emp['puesto']][] = $emp;
              uksort($emps_por_puesto, fn($a, $b) => ordenPuesto($a) <=> ordenPuesto($b));
              foreach ($emps_por_puesto as $puesto_nom => $emps):
                ?>
                <div
                  style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--suave);padding:6px 4px 3px">
                  <?= htmlspecialchars($puesto_nom) ?>
                </div>
                <?php foreach ($emps as $emp): ?>
                  <div class="nt-av-emp-opt" data-id="<?= $emp['id'] ?>"
                    data-puesto="<?= htmlspecialchars($emp['puesto'], ENT_QUOTES) ?>" onclick="ntAvToggleEmp(this)"
                    style="display:flex;align-items:center;gap:8px;padding:5px 8px;border-radius:6px;cursor:pointer;font-size:12px;margin-bottom:2px;transition:background 0.1s">
                    <span class="nt-av-emp-check"
                      style="width:16px;height:16px;border:1.5px solid var(--borde);border-radius:4px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:10px;background:var(--blanco)"></span>
                    <span><?= htmlspecialchars($emp['nombre']) ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:8px;margin-top:6px">
              <button type="button" onclick="ntAvSelTodos()"
                style="font-size:11px;padding:3px 10px;background:none;border:1px solid var(--borde);border-radius:6px;cursor:pointer">Todos</button>
              <button type="button" onclick="ntAvDeselTodos()"
                style="font-size:11px;padding:3px 10px;background:none;border:1px solid var(--borde);border-radius:6px;cursor:pointer">Limpiar</button>
              <span id="nt-av-emp-resumen" style="font-size:11px;color:var(--suave);align-self:center">0
                seleccionados</span>
            </div>
          </div>
          <div class="pop-f">
            <label>Días *</label>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px">
              <button type="button" onclick="ntAvCarrusel(-7)"
                style="flex-shrink:0;width:26px;height:36px;border:1px solid var(--borde);border-radius:6px;background:var(--fondo);cursor:pointer;font-size:13px">←</button>
              <div id="nt-av-carr-dias" style="display:flex;gap:3px;flex:1;min-width:0;overflow:hidden"></div>
              <button type="button" onclick="ntAvCarrusel(7)"
                style="flex-shrink:0;width:26px;height:36px;border:1px solid var(--borde);border-radius:6px;background:var(--fondo);cursor:pointer;font-size:13px">→</button>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <button type="button" onclick="ntAvSelSemana()"
                style="font-size:11px;padding:3px 10px;background:none;border:1px solid var(--borde);border-radius:6px;cursor:pointer">Semana</button>
              <button type="button" onclick="ntAvLimpiarDias()"
                style="font-size:11px;padding:3px 10px;background:none;border:1px solid var(--borde);border-radius:6px;cursor:pointer">Limpiar</button>
              <span id="nt-av-resumen-dias" style="font-size:11px;color:var(--suave)">Ningún día</span>
            </div>
          </div>
          <div class="pop-btns"><button type="button" class="pop-c" onclick="cerrarNuevo()">Cancelar</button><button
              type="button" class="pop-s" onclick="confirmarCrearTurnoAvanzado()"> Crear y asignar →</button></div>
        </form>
      </div>
    </div>
  </div>

  <!-- POPUP: asignar / editar turno -->
  <div class="ov" id="ovAsignar">
    <div class="pop" onclick="event.stopPropagation()">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px">
        <div>
          <div class="pop-tit" id="as-tit"></div>
          <div class="pop-sub" id="as-sub"></div>
        </div>
        <button onclick="cerrarAsignar()"
          style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--suave);line-height:1;margin-top:-2px">×</button>
      </div>
      <div id="as-add">
        <div class="pop-f"><label>Trabajador</label>
          <div class="wk-list" id="as-lista"></div>
        </div>
        <div class="pop-r2">
          <div class="pop-f"><label>Hora inicio</label><select id="as-ini" onchange="buildLista(ctxAsignar.puesto)"
              class="pop-time-sel"><?= $opHora ?></select></div>
          <div class="pop-f"><label>Hora fin</label><select id="as-fin" onchange="buildLista(ctxAsignar.puesto)"
              class="pop-time-sel"><?= $opHora ?></select></div>
        </div>
        <div id="as-add-borrar" style="display:none;margin-bottom:8px">
          <button type="button" class="pop-del" style="width:100%" onclick="borrarTurnoCtx()">🗑 Borrar este
            turno</button>
        </div>
        <div class="pop-btns"><button class="pop-c" onclick="cerrarAsignar()">Cancelar</button><button class="pop-s"
            onclick="confirmarAsignacion()">Asignar →</button></div>
      </div>
      <div id="as-edit" style="display:none">
        <div class="turno-info">
          <div class="turno-info-row"><strong id="as-edit-nombre" style="font-size:13px"></strong><button class="pop-c"
              style="padding:4px 10px;font-size:11px;flex:none" onclick="editarCampo('nombre')">Editar</button></div>
          <div class="turno-info-row">
            <div>
              <div id="as-edit-puesto" style="font-size:11px;color:var(--suave)"></div>
              <div id="as-edit-horas" style="font-size:11px;color:var(--suave);margin-top:2px"></div>
            </div><button class="pop-c" style="padding:4px 10px;font-size:11px;flex:none"
              onclick="editarCampo('horario')">Editar</button>
          </div>
        </div>
        <div id="as-edit-trabajador" style="display:none;margin-bottom:10px">
          <div class="pop-f"><label>Cambiar trabajador</label>
            <div class="wk-list" id="as-lista-edit"></div>
          </div>
        </div>
        <div id="as-edit-horario" style="display:none;margin-bottom:10px">
          <div class="pop-r2">
            <div class="pop-f"><label>Hora inicio</label><select id="as-edit-ini"
                class="pop-time-sel"><?= $opHora ?></select></div>
            <div class="pop-f"><label>Hora fin</label><select id="as-edit-fin"
                class="pop-time-sel"><?= $opHora ?></select></div>
          </div>
        </div>
        <div class="pop-btns"><button class="pop-del" onclick="borrarTurnoCtx()">Borrar turno</button><button
            class="pop-s" onclick="guardarCambios()">Confirmar cambios</button></div>
      </div>
    </div>
  </div>

  <!-- POPUP: contexto drag -->
  <div class="ov" id="ovContexto">
    <div class="pop" onclick="event.stopPropagation()" style="max-width:320px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <div class="pop-tit">Completar datos</div><button onclick="cerrarContexto()"
          style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--suave)">×</button>
      </div>
      <div class="pop-f"><label>Programa *</label><select id="ctx-prog">
          <option value="">— Selecciona —</option><?php foreach ($programas_lista as $pg): ?>
            <option value="<?= $pg['id'] ?>"><?= htmlspecialchars($pg['nombre']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="pop-f"><label>Plato *</label><select id="ctx-plato">
          <option value="">— —</option><?php for ($pn = 1; $pn <= 7; $pn++): ?>
            <option>Plato <?= $pn ?></option><?php endfor; ?>
        </select></div>
      <div class="pop-btns"><button class="pop-c" onclick="cerrarContexto()">Cancelar</button><button class="pop-s"
          onclick="confirmarContexto()">Confirmar →</button></div>
    </div>
  </div>

  <!-- POPUP: confirmación -->
  <div class="ov" id="ovConfirmar">
    <div class="pop" onclick="event.stopPropagation()" style="max-width:320px;text-align:center">
      <div id="ovConfirmar-ico" style="font-size:36px;margin-bottom:8px">🗑️</div>
      <div id="ovConfirmar-tit" class="pop-tit" style="margin-bottom:6px">¿Quitar este turno?</div>
      <div id="ovConfirmar-txt" style="font-size:12px;color:var(--suave);margin-bottom:16px"></div>
      <div class="pop-btns" style="justify-content:center"><button class="pop-c"
          onclick="_cerrarConfirmar()">Cancelar</button><button class="pop-del" id="ovConfirmar-btn">Sí, borrar</button>
      </div>
    </div>
  </div>

  <!-- POPUP: editar bloque -->
  <div class="ov" id="ovEditBloque">
    <div class="pop" onclick="event.stopPropagation()" style="max-width:380px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <div class="pop-tit" id="eb-titulo">Editar bloque</div><button onclick="cerrarPopup('ovEditBloque')"
          style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--suave);line-height:1">×</button>
      </div>
      <form method="POST" action="horarios.php">
        <input type="hidden" name="accion" value="editar_bloque">
        <input type="hidden" name="fecha_ctx" value="<?= $fecha_sel ?>">
        <input type="hidden" name="mes_ctx" value="<?= $mes_offset ?>">
        <input type="hidden" name="ctrl_activo" id="eb-ctrl-activo">
        <input type="hidden" name="bloque_id" id="eb-bloque-id">
        <input type="hidden" name="solicitud_id_ctx" value="<?= $solicitud_id ?>">
        <div class="pop-f"><label>Programa</label><select name="programa_id" id="eb-prog">
            <option value="0">— Sin programa —</option><?php foreach ($programas_lista as $pg): ?>
              <option value="<?= $pg['id'] ?>"><?= htmlspecialchars($pg['nombre']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="pop-r2">
          <div class="pop-f"><label>Hora inicio</label><select name="hora_inicio" id="eb-ini"
              class="pop-time-sel"><?= $opHora ?></select></div>
          <div class="pop-f"><label>Hora fin</label><select name="hora_fin" id="eb-fin"
              class="pop-time-sel"><?= $opHora ?></select></div>
        </div>
        <p style="font-size:11px;color:var(--suave);margin-bottom:12px">El horario solo cambiará los turnos sin asignar.
        </p>
        <div class="pop-btns"><button type="button" class="pop-del" onclick="borrarBloqueCtx()">Borrar
            bloque</button><button type="submit" class="pop-s">Guardar →</button></div>
      </form>
    </div>
  </div>

  <!-- POPUP: confirmar borrar bloque -->
  <div class="ov" id="ovConfirmarBloque">
    <div class="pop" onclick="event.stopPropagation()" style="max-width:320px;text-align:center">
      <div style="font-size:36px;margin-bottom:8px">🗑️</div>
      <div class="pop-tit" style="margin-bottom:6px">¿Borrar este bloque?</div>
      <div style="font-size:12px;color:var(--suave);margin-bottom:16px">Se borrarán todos los turnos del bloque.</div>
      <form method="POST" action="horarios.php">
        <input type="hidden" name="accion" value="borrar_bloque">
        <input type="hidden" name="fecha_ctx" value="<?= $fecha_sel ?>">
        <input type="hidden" name="mes_ctx" value="<?= $mes_offset ?>">
        <input type="hidden" name="ctrl_activo" id="cb-ctrl-activo">
        <input type="hidden" name="bloque_id" id="cb-bloque-id">
        <input type="hidden" name="solicitud_id_ctx" value="<?= $solicitud_id ?>">
        <div class="pop-btns" style="justify-content:center"><button type="button" class="pop-c"
            onclick="cerrarPopup('ovConfirmarBloque')">Cancelar</button><button type="submit" class="pop-del">Sí,
            borrar</button></div>
      </form>
    </div>
  </div>


  <script>
    const HOR = {
      mesOffset: <?= $mes_offset ?>,
      fechaSel: '<?= $fecha_sel ?>',
      empleados: <?= json_encode($empleados_dia ?? []) ?>,
      solicitudId: <?= $solicitud_id ?>
    };

    const diasEspeciales = {};
    <?php foreach ($dias_especiales_js as $de): ?>
      diasEspeciales['<?= $de['fecha'] ?>'] = '<?= $de['tipo'] ?>';
    <?php endforeach; ?>

    let calModo = null;
    function toggleModo(modo) {
      calModo = calModo === modo ? null : modo;
      document.getElementById('btn-modo-fs').className = 'cal-modo-btn' + (calModo === 'fs' ? ' activo-fs' : '');
      document.getElementById('btn-modo-fd').className = 'cal-modo-btn' + (calModo === 'fd' ? ' activo-fd' : '');
      const info = document.getElementById('cal-modo-info');
      if (calModo) { info.style.display = 'block'; info.textContent = 'Modo ' + (calModo === 'fs' ? 'FESTIVO' : 'FIN DE SEMANA') + ' activo — haz clic en un día para marcarlo/desmarcarlo'; }
      else { info.style.display = 'none'; }
    }

    async function calDiaClick(fecha) {
      if (!calModo) {
        cerrarCalendario();
        // Mantener solicitud_id al navegar desde el calendario
        const extra = HOR.solicitudId ? '&solicitud_id=' + HOR.solicitudId : '';
        window.location.href = 'horarios.php?fecha=' + fecha + '&mes=' + HOR.mesOffset + extra;
        return;
      }
      const tipoBD = calModo === 'fs' ? 'festivo' : 'fin_de_semana';
      const yaExiste = diasEspeciales[fecha] === tipoBD;
      const fd = new FormData();
      fd.append('accion', 'toggle_dia_especial'); fd.append('tipo_dia', tipoBD);
      fd.append('fecha_ctx', fecha); fd.append('mes_ctx', HOR.mesOffset); fd.append('ctrl_activo', '');
      try { await fetch('horarios_ajax.php', { method: 'POST', body: fd }); } catch (e) { return; }
      const celda = document.querySelector(`.cal-dia[data-fecha="${fecha}"]`);
      if (!celda) return;
      if (yaExiste) {
        delete diasEspeciales[fecha]; celda.classList.remove('dia-fs', 'dia-fd');
        const mini = celda.querySelector('.cal-mini.cal-fest,.cal-mini.cal-fd');
        if (mini) { const txt = mini.textContent.replace(/🎉|📅/g, '').trim(); if (txt) { mini.className = 'cal-mini pend'; mini.textContent = txt; } else mini.remove(); }
      } else {
        diasEspeciales[fecha] = tipoBD; celda.classList.remove('dia-fs', 'dia-fd');
        celda.classList.add(calModo === 'fs' ? 'dia-fs' : 'dia-fd');
        const icono = calModo === 'fs' ? '🎉' : '📅'; const clsMini = calModo === 'fs' ? 'cal-fest' : 'cal-fd';
        let mini = celda.querySelector('.cal-mini');
        if (mini) { const txt = mini.textContent.replace(/🎉|📅/g, '').trim(); mini.className = 'cal-mini ' + clsMini; mini.textContent = icono + (txt ? ' ' + txt : ''); }
        else { mini = document.createElement('div'); mini.className = 'cal-mini ' + clsMini; mini.textContent = icono; celda.appendChild(mini); }
      }
    }

    // Activar el control que viene en la URL al cargar
    window.addEventListener('DOMContentLoaded', () => {
      const ctrlUrl = <?= json_encode($ctrl_url) ?>;
      if (ctrlUrl) {
        setTimeout(() => activarTab(ctrlUrl), 100);
      }

      // Recargar el panel lateral cada 30s para reflejar asignaciones
      <?php if ($solicitud_id): ?>
        setInterval(() => {
          fetch('horarios_panel_solicitud.php?solicitud_id=<?= $solicitud_id ?>')
            .then(r => r.text())
            .then(html => {
              const panel = document.querySelector('.sol-panel-body');
              const progreso = document.querySelector('.sol-progreso');
              if (panel && html) {
                const tmp = document.createElement('div');
                tmp.innerHTML = html;
                const newBody = tmp.querySelector('.sol-panel-body');
                const newProg = tmp.querySelector('.sol-progreso');
                if (newBody) panel.innerHTML = newBody.innerHTML;
                if (newProg && progreso) progreso.innerHTML = newProg.innerHTML;
              }
            })
            .catch(() => { });
        }, 30000);
      <?php endif; ?>
    });
  </script>
  <script src="js/horarios.js"></script>

</body>

</html>
<?php
session_start();

if (empty($_SESSION['rol']) || $_SESSION['rol'] !== 'coordinador') {
    header("Location: ../login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "turnostv");
$conn->set_charset("utf8mb4");

/* ACCIONES POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'aprobar_vacacion') {
        $vid = (int) $_POST['vacacion_id'];
        $stmt = $conn->prepare("SELECT empleado_id, dias FROM vacaciones WHERE id = ?");
        $stmt->bind_param("i", $vid);
        $stmt->execute();
        $vac = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($vac) {
            $stmt = $conn->prepare("UPDATE vacaciones SET estado='aprobada' WHERE id=?");
            $stmt->bind_param("i", $vid);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("UPDATE empleados SET vacaciones_gastadas=vacaciones_gastadas+? WHERE id=?");
            $stmt->bind_param("ii", $vac['dias'], $vac['empleado_id']);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: peticiones.php?msg=aprobada");
        exit;
    }
    if ($accion === 'rechazar_vacacion') {
        $vid = (int) $_POST['vacacion_id'];
        $stmt = $conn->prepare("UPDATE vacaciones SET estado='rechazada' WHERE id=?");
        $stmt->bind_param("i", $vid);
        $stmt->execute();
        $stmt->close();
        header("Location: peticiones.php?msg=rechazada");
        exit;
    }
    if ($accion === 'en_proceso_vacacion') {
        $vid = (int) $_POST['vacacion_id'];
        $stmt = $conn->prepare("UPDATE vacaciones SET estado='en_proceso' WHERE id=?");
        $stmt->bind_param("i", $vid);
        $stmt->execute();
        $stmt->close();
        header("Location: peticiones.php?msg=en_proceso");
        exit;
    }
    if ($accion === 'responder_peticion') {
        $pid = (int) $_POST['peticion_id'];
        $estado = $_POST['estado_peticion'];
        $respuesta = trim($_POST['respuesta'] ?? '');
        $stmt = $conn->prepare("UPDATE peticiones SET estado=?, respuesta=? WHERE id=?");
        $stmt->bind_param("ssi", $estado, $respuesta, $pid);
        $stmt->execute();
        $stmt->close();
        header("Location: peticiones.php?msg=respondida&tab=peticiones");
        exit;
    }
    if ($accion === 'en_proceso_peticion') {
        $pid = (int) $_POST['peticion_id'];
        $stmt = $conn->prepare("UPDATE peticiones SET estado='en_proceso' WHERE id=?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $stmt->close();
        header("Location: peticiones.php?msg=en_proceso&tab=peticiones");
        exit;
    }
    if ($accion === 'rechazar_solicitud') {
        $sid = (int) $_POST['solicitud_id'];
        $respuesta = trim($_POST['respuesta_solicitud'] ?? '');
        $stmt = $conn->prepare("UPDATE solicitudes SET estado='rechazada', notas=? WHERE id=?");
        $stmt->bind_param("si", $respuesta, $sid);
        $stmt->execute();
        $stmt->close();
        header("Location: peticiones.php?msg=sol_rechazada&tab=solicitudes");
        exit;
    }
    if ($accion === 'en_proceso_solicitud') {
        $sid = (int) $_POST['solicitud_id'];
        $stmt = $conn->prepare("UPDATE solicitudes SET estado='en_proceso' WHERE id=?");
        $stmt->bind_param("i", $sid);
        $stmt->execute();
        $stmt->close();
        header("Location: peticiones.php?msg=en_proceso&tab=solicitudes");
        exit;
    }

    if ($accion === 'asignar_puestos_solicitud') {
        $sol_id     = (int) $_POST['solicitud_id'];
        $asignacion = $_POST['asignacion'] ?? [];

        foreach ($asignacion as $puesto_id => $empleado_id) {
            $puesto_id   = (int) $puesto_id;
            $empleado_id = (int) $empleado_id;

            if ($empleado_id > 0) {
                $stmt = $conn->prepare("UPDATE solicitud_puestos SET empleado_id=?, estado='cubierto' WHERE id=? AND solicitud_id=?");
                $stmt->bind_param("iii", $empleado_id, $puesto_id, $sol_id);
            } else {
                $stmt = $conn->prepare("UPDATE solicitud_puestos SET empleado_id=NULL, estado='pendiente' WHERE id=? AND solicitud_id=?");
                $stmt->bind_param("ii", $puesto_id, $sol_id);
            }
            $stmt->execute();
            $stmt->close();
        }

        // Marcar en_proceso si sigue pendiente
        $stmt = $conn->prepare("UPDATE solicitudes SET estado='en_proceso' WHERE id=? AND estado='pendiente'");
        $stmt->bind_param("i", $sol_id);
        $stmt->execute();
        $stmt->close();

        // ¿Todos cubiertos?
        $stmt = $conn->prepare("SELECT COUNT(*) AS t, SUM(CASE WHEN estado='cubierto' THEN 1 ELSE 0 END) AS c FROM solicitud_puestos WHERE solicitud_id=?");
        $stmt->bind_param("i", $sol_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res['t'] > 0 && $res['t'] == $res['c']) {

            // Obtener coordinador_id
            $stmt = $conn->prepare("SELECT id FROM coordinadores WHERE usuario_id=?");
            $stmt->bind_param("i", $_SESSION['usuario_id']);
            $stmt->execute();
            $coord = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $coord_id = $coord['id'] ?? 0;

            // Datos completos de la solicitud
            $stmt = $conn->prepare("SELECT * FROM solicitudes WHERE id=?");
            $stmt->bind_param("i", $sol_id);
            $stmt->execute();
            $sol = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $pid_sol = (int) ($sol['programa_id'] ?? 0);

            // Fechas
            $stmt = $conn->prepare("SELECT fecha FROM solicitud_fechas WHERE solicitud_id=? ORDER BY fecha ASC");
            $stmt->bind_param("i", $sol_id);
            $stmt->execute();
            $fechas_sol = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'fecha');
            $stmt->close();

            // Puestos con empleados asignados
            $stmt = $conn->prepare("SELECT * FROM solicitud_puestos WHERE solicitud_id=? AND empleado_id IS NOT NULL");
            $stmt->bind_param("i", $sol_id);
            $stmt->execute();
            $puestos_sol = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($fechas_sol as $f) {

                // Primero borrar turnos anteriores de esta solicitud en esta fecha
                // para evitar duplicados si se guarda dos veces
                $stmt = $conn->prepare("
                    DELETE FROM turnos 
                    WHERE fecha=? 
                    AND control_nombre=? 
                    AND programa_id=? 
                    AND coordinador_id=?
                    AND bloque_id IS NULL
                ");
                $stmt->bind_param("ssii", $f, $sol['control_nombre'], $pid_sol, $coord_id);
                $stmt->execute();
                $stmt->close();

                // Crear o recuperar bloque para este día
                $stmt = $conn->prepare("
                    SELECT id FROM turnos_bloque 
                    WHERE control_nombre=? AND fecha=? AND coordinador_id=? AND programa_id=?
                ");
                $stmt->bind_param("ssii", $sol['control_nombre'], $f, $coord_id, $pid_sol);
                $stmt->execute();
                $bloque = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($bloque) {
                    $bloque_id = $bloque['id'];
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO turnos_bloque 
                        (control_nombre, fecha, programa_id, hora_inicio, hora_fin, coordinador_id) 
                        VALUES (?,?,?,?,?,?)
                    ");
                    $stmt->bind_param("ssissi", $sol['control_nombre'], $f, $pid_sol, $sol['hora_inicio'], $sol['hora_fin'], $coord_id);
                    $stmt->execute();
                    $bloque_id = $conn->insert_id;
                    $stmt->close();
                }

                // Insertar cada turno vinculado al bloque
                foreach ($puestos_sol as $p) {
                    // Comprobar que no existe ya para evitar duplicados
                    $stmt = $conn->prepare("
                        SELECT id FROM turnos 
                        WHERE bloque_id=? AND puesto_solicitado=? AND fecha=?
                    ");
                    $stmt->bind_param("iss", $bloque_id, $p['puesto_solicitado'], $f);
                    $stmt->execute();
                    $existe = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($existe) {
                        // Actualizar el existente
                        $stmt = $conn->prepare("
                            UPDATE turnos SET empleado_id=?, programa_id=?, estado='cubierto'
                            WHERE id=?
                        ");
                        $stmt->bind_param("iii", $p['empleado_id'], $pid_sol, $existe['id']);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        // Insertar nuevo
                        $stmt = $conn->prepare("
                            INSERT INTO turnos 
                            (bloque_id, empleado_id, programa_id, coordinador_id, puesto_solicitado, control_nombre, plato, fecha, hora_inicio, hora_fin, estado) 
                            VALUES (?,?,?,?,?,?,?,?,?,?,'cubierto')
                        ");
                        $stmt->bind_param("iiiissssss", $bloque_id, $p['empleado_id'], $pid_sol, $coord_id, $p['puesto_solicitado'], $sol['control_nombre'], $sol['plato'], $f, $p['hora_inicio'], $p['hora_fin']);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            // Aprobar solicitud
            $stmt = $conn->prepare("UPDATE solicitudes SET estado='aprobada' WHERE id=?");
            $stmt->bind_param("i", $sol_id);
            $stmt->execute();
            $stmt->close();

            header("Location: peticiones.php?msg=sol_aprobada&tab=solicitudes");
            exit;
        }

        header("Location: peticiones.php?msg=puesto_asignado&tab=solicitudes");
        exit;
    }
}

/* CARGAR DATOS */
$stmt = $conn->prepare("
    SELECT v.id, v.fecha_inicio, v.fecha_fin, v.dias, v.motivo, v.estado, v.fecha_peticion,
           u.nombre AS empleado_nombre, e.puesto, e.vacaciones_total, e.vacaciones_gastadas,
           (e.vacaciones_total - e.vacaciones_gastadas) AS vacaciones_disponibles
    FROM vacaciones v JOIN empleados e ON e.id=v.empleado_id JOIN usuarios u ON u.id=e.usuario_id
    WHERE v.estado IN ('pendiente','en_proceso')
    ORDER BY CASE v.estado WHEN 'pendiente' THEN 0 ELSE 1 END, v.fecha_peticion ASC
");
$stmt->execute();
$vacaciones_activas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT p.id, p.tipo, p.descripcion, p.fecha_pedida, p.fecha_peticion, p.estado, p.respuesta,
           u.nombre AS director_nombre, pr.nombre AS programa_nombre
    FROM peticiones p JOIN directores d ON d.id=p.director_id JOIN usuarios u ON u.id=d.usuario_id
    LEFT JOIN programas pr ON pr.id=p.programa_id
    WHERE p.estado IN ('pendiente','en_proceso')
    ORDER BY CASE p.estado WHEN 'pendiente' THEN 0 ELSE 1 END, p.fecha_peticion ASC
");
$stmt->execute();
$peticiones_activas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT s.id, s.control_nombre, s.plato, s.hora_inicio, s.hora_fin,
           s.estado, s.notas, s.fecha_peticion,
           p.nombre AS programa_nombre, u.nombre AS director_nombre,
           COUNT(DISTINCT sf.fecha) AS num_dias,
           COUNT(DISTINCT sp.id) AS num_puestos,
           SUM(CASE WHEN sp.estado='cubierto' THEN 1 ELSE 0 END) AS puestos_cubiertos,
           MIN(sf.fecha) AS primera_fecha,
           MAX(sf.fecha) AS ultima_fecha
    FROM solicitudes s
    JOIN programas p ON p.id=s.programa_id
    JOIN directores d ON d.id=s.director_id JOIN usuarios u ON u.id=d.usuario_id
    JOIN solicitud_fechas sf ON sf.solicitud_id=s.id
    JOIN solicitud_puestos sp ON sp.solicitud_id=s.id
    WHERE s.estado IN ('pendiente','en_proceso')
    GROUP BY s.id
    ORDER BY CASE s.estado WHEN 'pendiente' THEN 0 ELSE 1 END, s.fecha_peticion ASC
");
$stmt->execute();
$solicitudes_activas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT s.id, s.control_nombre, s.plato, s.hora_inicio, s.hora_fin,
           s.estado, s.notas, s.fecha_peticion,
           p.nombre AS programa_nombre, u.nombre AS director_nombre,
           COUNT(DISTINCT sf.fecha) AS num_dias, COUNT(DISTINCT sp.id) AS num_puestos,
           SUM(CASE WHEN sp.estado='cubierto' THEN 1 ELSE 0 END) AS puestos_cubiertos
    FROM solicitudes s
    JOIN programas p ON p.id=s.programa_id
    JOIN directores d ON d.id=s.director_id JOIN usuarios u ON u.id=d.usuario_id
    JOIN solicitud_fechas sf ON sf.solicitud_id=s.id
    JOIN solicitud_puestos sp ON sp.solicitud_id=s.id
    WHERE s.estado IN ('aprobada','rechazada')
    GROUP BY s.id ORDER BY s.fecha_peticion DESC
");
$stmt->execute();
$solicitudes_finalizadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT v.id, v.fecha_inicio, v.fecha_fin, v.dias, v.motivo, v.estado, v.fecha_peticion,
           u.nombre AS empleado_nombre, e.puesto, e.vacaciones_total, e.vacaciones_gastadas,
           (e.vacaciones_total - e.vacaciones_gastadas) AS vacaciones_disponibles
    FROM vacaciones v JOIN empleados e ON e.id=v.empleado_id JOIN usuarios u ON u.id=e.usuario_id
    WHERE v.estado IN ('aprobada','rechazada') ORDER BY v.fecha_peticion DESC
");
$stmt->execute();
$vac_finalizadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT p.id, p.tipo, p.descripcion, p.fecha_pedida, p.fecha_peticion, p.estado, p.respuesta,
           u.nombre AS director_nombre, pr.nombre AS programa_nombre
    FROM peticiones p JOIN directores d ON d.id=p.director_id JOIN usuarios u ON u.id=d.usuario_id
    LEFT JOIN programas pr ON pr.id=p.programa_id
    WHERE p.estado IN ('aprobada','rechazada') ORDER BY p.fecha_peticion DESC
");
$stmt->execute();
$pet_finalizadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$msg = $_GET['msg'] ?? '';
$tab_activa = $_GET['tab'] ?? 'vacaciones';
$titulo_pagina = 'Peticiones';

$vac_pend = count(array_filter($vacaciones_activas, fn($v) => $v['estado'] === 'pendiente'));
$pet_pend = count(array_filter($peticiones_activas, fn($p) => $p['estado'] === 'pendiente'));
$sol_pend = count(array_filter($solicitudes_activas, fn($s) => $s['estado'] === 'pendiente'));
$total_fin = count($vac_finalizadas) + count($pet_finalizadas) + count($solicitudes_finalizadas);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peticiones — TurnosTV</title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="../fragmentos/styles/base.css">
    <link rel="stylesheet" href="styles/peticiones.css">
</head>

<body>
    <?php include '../fragmentos/sidebar.php'; ?>
    <div class="content">
        <?php include '../fragmentos/header.php'; ?>
        <div class="body">

            <?php if ($msg === 'aprobada'):
                echo "<p class='mensaje-ok'>Petición aprobada correctamente.</p>";
            endif; ?>
            <?php if ($msg === 'rechazada'):
                echo "<p class='mensaje-ok'>Petición rechazada.</p>";
            endif; ?>
            <?php if ($msg === 'en_proceso'):
                echo "<p class='mensaje-ok'>Petición marcada como en proceso.</p>";
            endif; ?>
            <?php if ($msg === 'respondida'):
                echo "<p class='mensaje-ok'>Petición respondida.</p>";
            endif; ?>
            <?php if ($msg === 'sol_rechazada'):
                echo "<p class='mensaje-ok'>Solicitud de equipo rechazada.</p>";
            endif; ?>
            <?php if ($msg === 'puesto_asignado'):
                echo "<p class='mensaje-ok'>Empleado asignado al puesto.</p>";
            endif; ?>
            <?php if ($msg === 'sol_aprobada'):
                echo "<p class='mensaje-ok'>✓ Solicitud completada y turnos generados.</p>";
            endif; ?>

            <!-- Resumen -->
            <div class="stats-grid" style="margin-bottom:20px">
                <div class="card" style="margin-bottom:0;text-align:center">
                    <div
                        style="font-size:28px;font-weight:700;color:<?= count($vacaciones_activas) > 0 ? 'var(--aviso)' : 'var(--ok)' ?>">
                        <?= count($vacaciones_activas) ?>
                    </div>
                    <div style="font-size:12px;color:var(--suave)">Vacaciones activas</div>
                </div>
                <div class="card" style="margin-bottom:0;text-align:center">
                    <div
                        style="font-size:28px;font-weight:700;color:<?= count($peticiones_activas) > 0 ? 'var(--aviso)' : 'var(--ok)' ?>">
                        <?= count($peticiones_activas) ?>
                    </div>
                    <div style="font-size:12px;color:var(--suave)">Peticiones activas</div>
                </div>
                <div class="card" style="margin-bottom:0;text-align:center">
                    <div
                        style="font-size:28px;font-weight:700;color:<?= count($solicitudes_activas) > 0 ? 'var(--aviso)' : 'var(--ok)' ?>">
                        <?= count($solicitudes_activas) ?>
                    </div>
                    <div style="font-size:12px;color:var(--suave)">Solicitudes de equipo</div>
                </div>
                <div class="card" style="margin-bottom:0;text-align:center">
                    <div style="font-size:28px;font-weight:700;color:var(--suave)"><?= $total_fin ?></div>
                    <div style="font-size:12px;color:var(--suave)">Finalizadas</div>
                </div>
            </div>

            <!-- Pestañas -->
            <div class="tabs">
                <button class="tab-btn <?= $tab_activa === 'vacaciones' ? 'activa' : '' ?>"
                    onclick="cambiarTab('vacaciones',this)">
                    Vacaciones
                    <?php if ($vac_pend > 0): ?><span class="badge badge-aviso"
                            style="margin-left:6px;font-size:10px"><?= $vac_pend ?></span><?php endif; ?>
                </button>
                <button class="tab-btn <?= $tab_activa === 'peticiones' ? 'activa' : '' ?>"
                    onclick="cambiarTab('peticiones',this)">
                    Peticiones directores
                    <?php if ($pet_pend > 0): ?><span class="badge badge-aviso"
                            style="margin-left:6px;font-size:10px"><?= $pet_pend ?></span><?php endif; ?>
                </button>
                <button class="tab-btn <?= $tab_activa === 'solicitudes' ? 'activa' : '' ?>"
                    onclick="cambiarTab('solicitudes',this)">
                    Solicitudes de equipo
                    <?php if ($sol_pend > 0): ?><span class="badge badge-aviso"
                            style="margin-left:6px;font-size:10px"><?= $sol_pend ?></span><?php endif; ?>
                </button>
                <button class="tab-btn <?= $tab_activa === 'finalizadas' ? 'activa' : '' ?>"
                    onclick="cambiarTab('finalizadas',this)">
                    Finalizadas
                    <?php if ($total_fin > 0): ?><span class="badge badge-neutro"
                            style="margin-left:6px;font-size:10px"><?= $total_fin ?></span><?php endif; ?>
                </button>
            </div>

            <!-- Vacaciones -->
            <div class="tab-panel <?= $tab_activa === 'vacaciones' ? 'visible' : '' ?>" id="tab-vacaciones">
                <?php if (empty($vacaciones_activas)): ?>
                    <p style="color:var(--suave);font-size:13px">No hay solicitudes de vacaciones activas.</p>
                <?php else: ?>
                    <?php foreach ($vacaciones_activas as $v):
                        $fi = date('d/m/Y', strtotime($v['fecha_inicio']));
                        $ff = date('d/m/Y', strtotime($v['fecha_fin']));
                        $tipo_label = match (true) {
                            str_starts_with($v['motivo'] ?? '', 'Asunto propio') => 'Asunto propio',
                            str_starts_with($v['motivo'] ?? '', 'Médico') => 'Médico',
                            str_starts_with($v['motivo'] ?? '', 'Otro') => 'Otro',
                            default => 'Vacaciones',
                        };
                        ?>
                        <div class="vac-card <?= $v['estado'] ?>">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                                <div style="flex:1">
                                    <div class="vac-nombre">
                                        <?= htmlspecialchars($v['empleado_nombre']) ?>
                                        <span class="badge badge-neutro"
                                            style="margin-left:6px;font-size:10px"><?= htmlspecialchars($v['puesto']) ?></span>
                                        <span class="badge badge-aviso"
                                            style="margin-left:4px;font-size:10px"><?= $v['estado'] ?></span>
                                    </div>
                                    <div class="vac-meta">
                                        <?= $tipo_label ?> · <?= $fi ?> – <?= $ff ?> · <?= $v['dias'] ?>
                                        día<?= $v['dias'] > 1 ? 's' : '' ?> ·
                                        Solicitado el <?= date('d/m/Y', strtotime($v['fecha_peticion'])) ?>
                                    </div>
                                    <div class="vac-dias">
                                        Vacaciones: <strong><?= $v['vacaciones_disponibles'] ?></strong> disponibles de
                                        <strong><?= $v['vacaciones_total'] ?></strong> totales (<?= $v['vacaciones_gastadas'] ?>
                                gastados)
                            </div>
                        </div>
                                <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;align-items:stretch">
                                    <?php if ($v['estado'] === 'pendiente'): ?>
                                <form method="POST">
                                    <input type="hidden" name="accion" value="en_proceso_vacacion">
                                    <input type="hidden" name="vacacion_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn btn-outline" style="padding:6px 14px;font-size:12px;width:100%">⏳ En
                                        proceso</button>
                                </form>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline" style="padding:6px 14px;font-size:12px"
                                onclick="toggleVacRespuesta(<?= $v['id'] ?>)">Responder</button>
                        </div>
                        </div>
                        <div class="respuesta-form" id="vac-form-<?= $v['id'] ?>">
                            <form method="POST">
                                <input type="hidden" name="vacacion_id" value="<?= $v['id'] ?>">
                                <div style="display:grid;grid-template-columns:1fr 140px;gap:10px;margin-bottom:10px">
                                    <div class="form-field" style="margin-bottom:0">
                                        <label>Nota (opcional)</label>
                                        <textarea name="nota" rows="2" placeholder="Motivo de la decisión..." style="resize:none"></textarea>
                                    </div>
                                    <div class="form-field" style="margin-bottom:0">
                                        <label>Decisión</label>
                                        <select name="accion">
                                            <option value="aprobar_vacacion">Aprobar</option>
                                            <option value="rechazar_vacacion">Rechazar</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn" style="padding:6px 16px;font-size:12px">Enviar</button>
                                <button type="button" class="btn-outline" style="padding:6px 16px;font-size:12px;margin-left:6px"
                                    onclick="toggleVacRespuesta(<?= $v['id'] ?>)">Cancelar</button>
                            </form>
                        </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </div>

            <!-- Peticiones de directores -->
            <div class="tab-panel <?= $tab_activa === 'peticiones' ? 'visible' : '' ?>" id="tab-peticiones">
                <?php if (empty($peticiones_activas)): ?>
                    <p style="color:var(--suave);font-size:13px">No hay peticiones de directores activas.</p>
                <?php else: ?>
                    <?php foreach ($peticiones_activas as $p): ?>
                        <div class="pet-card <?= $p['estado'] ?>">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                                <div style="flex:1">
                                    <div style="font-size:14px;font-weight:600">
                                        <?= htmlspecialchars($p['tipo']) ?>
                                        <?php if ($p['programa_nombre']): ?>
                                            <span class="badge badge-neutro"
                                                style="margin-left:6px;font-size:10px"><?= htmlspecialchars($p['programa_nombre']) ?></span>
                                        <?php endif; ?>
                                        <span class="badge badge-aviso"
                                            style="margin-left:4px;font-size:10px"><?= $p['estado'] ?></span>
                                    </div>
                                    <div style="font-size:12px;color:var(--suave);margin-top:3px">
                                        <?= htmlspecialchars($p['director_nombre']) ?> ·
                                        Enviada el <?= date('d/m/Y', strtotime($p['fecha_peticion'])) ?>
                                        <?php if ($p['fecha_pedida']): ?> · Necesaria para el
                                            <?= date('d/m/Y', strtotime($p['fecha_pedida'])) ?>        <?php endif; ?>
                                    </div>
                                    <div style="font-size:12px;color:var(--texto);margin-top:6px">
                                        <?= htmlspecialchars($p['descripcion']) ?></div>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;align-items:stretch">
                                    <?php if ($p['estado'] === 'pendiente'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="accion" value="en_proceso_peticion">
                                            <input type="hidden" name="peticion_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-outline"
                                                style="padding:6px 14px;font-size:12px;width:100%">⏳ En proceso</button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-outline" style="padding:6px 14px;font-size:12px"
                                        onclick="togglePetRespuesta(<?= $p['id'] ?>)">Responder</button>
                                </div>
                            </div>
                            <div class="respuesta-form" id="pet-form-<?= $p['id'] ?>">
                                <form method="POST">
                                    <input type="hidden" name="accion" value="responder_peticion">
                                    <input type="hidden" name="peticion_id" value="<?= $p['id'] ?>">
                                    <div style="display:grid;grid-template-columns:1fr 140px;gap:10px;margin-bottom:10px">
                                        <div class="form-field" style="margin-bottom:0">
                                            <label>Respuesta</label>
                                            <textarea name="respuesta" rows="2" placeholder="Escribe tu respuesta..."
                                                style="resize:none"></textarea>
                                        </div>
                                        <div class="form-field" style="margin-bottom:0">
                                            <label>Decisión</label>
                                            <select name="estado_peticion">
                                                <option value="aprobada">Aprobar</option>
                                                <option value="rechazada">Rechazar</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn" style="padding:6px 16px;font-size:12px">Enviar
                                        respuesta</button>
                                    <button type="button" class="btn-outline"
                                        style="padding:6px 16px;font-size:12px;margin-left:6px"
                                        onclick="togglePetRespuesta(<?= $p['id'] ?>)">Cancelar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Solicitudes de equipo -->
            <div class="tab-panel <?= $tab_activa === 'solicitudes' ? 'visible' : '' ?>" id="tab-solicitudes">
                <?php if (empty($solicitudes_activas)): ?>
                    <p style="color:var(--suave);font-size:13px">No hay solicitudes de equipo activas.</p>
                <?php else: ?>
                    <?php foreach ($solicitudes_activas as $s):
                        $clase_e = match ($s['estado']) { 'aprobada' => 'badge-ok', 'rechazada' => 'badge-peligro', 'en_proceso' => 'badge-neutro', default => 'badge-aviso'};

                        // Puestos y fechas de la solicitud
                        $conn2 = new mysqli("localhost", "root", "", "turnostv");
                        $conn2->set_charset("utf8mb4");
                        $st = $conn2->prepare("SELECT sp.id, sp.puesto_solicitado, sp.hora_inicio, sp.hora_fin, sp.estado, sp.empleado_id AS emp_id, u.nombre AS emp_nombre FROM solicitud_puestos sp LEFT JOIN empleados e ON e.id=sp.empleado_id LEFT JOIN usuarios u ON u.id=e.usuario_id WHERE sp.solicitud_id=? ORDER BY sp.id ASC");
                        $st->bind_param("i", $s['id']);
                        $st->execute();
                        $puestos_s = $st->get_result()->fetch_all(MYSQLI_ASSOC);
                        $st->close();
                        $st = $conn2->prepare("SELECT fecha FROM solicitud_fechas WHERE solicitud_id=? ORDER BY fecha ASC");
                        $st->bind_param("i", $s['id']);
                        $st->execute();
                        $fechas_s = array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), 'fecha');
                        $st->close();
                        $conn2->close();

                        $fecha_ini_fmt = !empty($fechas_s) ? date('d/m/Y', strtotime($fechas_s[0])) : '—';
                        $fecha_fin_fmt = count($fechas_s) > 1 ? ' – ' . date('d/m/Y', strtotime(end($fechas_s))) : '';

                        // URL para ir a horarios: primera fecha pendiente de cubrir
                        $primera_fecha_pendiente = $fechas_s[0] ?? date('Y-m-d');
                        foreach ($fechas_s as $f) {
                            // Si hay puestos sin cubrir en esa fecha, usamos esa
                            $primera_fecha_pendiente = $f;
                            break;
                        }
                        $url_horarios = "horarios.php?fecha=" . urlencode($primera_fecha_pendiente)
                            . "&ctrl=" . urlencode($s['control_nombre'])
                            . "&solicitud_id=" . $s['id'];
                        ?>
                        <div class="pet-card <?= $s['estado'] ?>">
                            <div
                                style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px">
                                <div style="flex:1">
                                    <div style="font-size:14px;font-weight:600">
                                        <?= htmlspecialchars($s['programa_nombre']) ?>
                                        <span class="badge badge-neutro" style="margin-left:6px;font-size:10px">
                                            <?= htmlspecialchars($s['control_nombre']) ?> · <?= htmlspecialchars($s['plato']) ?>
                                        </span>
                                        <span class="badge <?= $clase_e ?>"
                                            style="margin-left:4px;font-size:10px"><?= $s['estado'] ?></span>
                                    </div>
                                    <div style="font-size:12px;color:var(--suave);margin-top:3px">
                                        <?= htmlspecialchars($s['director_nombre']) ?> ·
                                        <?= substr($s['hora_inicio'], 0, 5) ?>–<?= substr($s['hora_fin'], 0, 5) ?> ·
                                        <?= $fecha_ini_fmt . $fecha_fin_fmt ?>
                                        (<?= $s['num_dias'] ?> día<?= $s['num_dias'] > 1 ? 's' : '' ?>) ·
                                        Enviada el <?= date('d/m/Y', strtotime($s['fecha_peticion'])) ?>
                                    </div>
                                    <?php if (!empty($s['notas'])): ?>
                                        <div style="font-size:11px;color:var(--suave);margin-top:4px;font-style:italic">
                                            "<?= htmlspecialchars($s['notas']) ?>"</div>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;align-items:stretch">
                                    <?php if ($s['estado'] === 'pendiente'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="accion" value="en_proceso_solicitud">
                                            <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-outline"
                                                style="padding:6px 14px;font-size:12px;width:100%">⏳ En proceso</button>
                                        </form>
                                    <?php endif; ?>
                                    <!-- BOTÓN PRINCIPAL -->
                                    <a href="<?= htmlspecialchars($url_horarios) ?>" class="btn btn-ok"
                                        style="padding:6px 14px;font-size:12px;text-align:center;text-decoration:none">
                                        📅 Asignar equipo →
                                    </a>
                                    <button type="button" class="btn btn-outline" style="padding:6px 14px;font-size:12px"
                                        onclick="toggleSolRespuesta(<?= $s['id'] ?>)">✗ Rechazar</button>
                                </div>
                            </div>

                            <!-- Chips de puestos con asignación directa -->
                            <?php if (!empty($puestos_s)): ?>
                                <form method="POST">
                                    <input type="hidden" name="accion" value="asignar_puestos_solicitud">
                                    <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:10px">
                                        <?php foreach ($puestos_s as $sp):
                                            $cub = $sp['estado'] === 'cubierto';
                                            [$bg, $tc] = $cub ? ['#DBEAFE', '#1e40af'] : ['#FEF3C7', '#92400e'];

                                            $st_emp = $conn->prepare("
                                                SELECT e.id, u.nombre,
                                                    (SELECT COUNT(*) FROM turnos t
                                                     WHERE t.empleado_id = e.id
                                                       AND t.fecha IN (SELECT fecha FROM solicitud_fechas WHERE solicitud_id=?)
                                                       AND t.estado = 'cubierto') AS turnos_en_fechas
                                                FROM empleados e
                                                JOIN usuarios u ON u.id = e.usuario_id
                                                WHERE e.puesto = ? AND u.estado = 'activo'
                                                ORDER BY turnos_en_fechas ASC, u.nombre ASC
                                            ");
                                            $st_emp->bind_param("is", $s['id'], $sp['puesto_solicitado']);
                                            $st_emp->execute();
                                            $emps_puesto = $st_emp->get_result()->fetch_all(MYSQLI_ASSOC);
                                            $st_emp->close();
                                        ?><div style="display:flex;align-items:center;gap:8px;background:<?= $bg ?>;border-radius:6px;padding:6px 10px">
                                        <div style="flex:1;min-width:0">
                                            <span
                                                style="font-size:12px;font-weight:600;color:<?= $tc ?>"><?= htmlspecialchars($sp['puesto_solicitado']) ?></span>
                                            <?php if ($cub): ?>
                                                <span style="font-size:11px;color:<?= $tc ?>;opacity:.8;margin-left:6px">✓
                                                    <?= htmlspecialchars($sp['emp_nombre']) ?></span>
                                            <?php else: ?>
                                                <span style="font-size:11px;color:<?= $tc ?>;opacity:.8;margin-left:6px">Sin asignar</span>
                                            <?php endif; ?>
                                        </div>
                                                <select name="asignacion[<?= $sp['id'] ?>]" style="font-size:11px;padding:3px 6px;border:1px solid var(--borde);border-radius:5px;background:var(--blanco);max-width:200px">
                                                    <option value="0">— Sin asignar —</option>
                                                    <?php foreach ($emps_puesto as $ep):
                                                        $ocupado = $ep['turnos_en_fechas'] > 0;
                                                        $label = htmlspecialchars($ep['nombre']);
                                                        if ($ocupado) $label .= ' ⚠ ocupado';
                                                    ?>
                                                        <option value="<?= $ep['id'] ?>"
                                                            <?= ($cub && $sp['emp_id'] == $ep['id']) ? 'selected' : '' ?>
                                                            <?= $ocupado ? 'style="color:#b45309"' : '' ?>>
                                                            <?= $label ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php $pct = $s['num_puestos'] > 0 ? round($s['puestos_cubiertos'] / $s['num_puestos'] * 100) : 0; ?>
                                    <div style="margin-bottom:10px">
                                        <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--suave);margin-bottom:3px">
                                            <span>Puestos cubiertos</span>
                                            <span><?= $s['puestos_cubiertos'] ?>/<?= $s['num_puestos'] ?></span>
                                        </div>
                                        <div style="height:5px;background:var(--fondo);border-radius:3px;overflow:hidden">
                                            <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct === 100 ? '#16A34A' : '#1e40af' ?>;border-radius:3px;transition:width 0.3s"></div>
                                        </div>
                                    </div>

                                    <!-- Fechas con enlace directo a cada día -->
                                    <?php if (count($fechas_s) > 1): ?>
                                        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:10px">
                                            <span style="font-size:10px;color:var(--suave);align-self:center">Ir al día:</span>
                                            <?php foreach ($fechas_s as $f):
                                                $url_dia = "horarios.php?fecha=" . urlencode($f)
                                                    . "&ctrl=" . urlencode($s['control_nombre'])
                                                    . "&solicitud_id=" . $s['id'];
                                            ?>
                                                <a href="<?= htmlspecialchars($url_dia) ?>"
                                                    style="font-size:11px;padding:2px 8px;background:var(--fondo);border:1px solid var(--borde);border-radius:5px;text-decoration:none;color:var(--texto)">
                                                    <?= date('d/m', strtotime($f)) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <button type="submit" class="btn btn-ok" style="padding:5px 16px;font-size:12px">✓ Guardar asignaciones</button>
                                </form>
                            <?php endif; ?>

                            <!-- Form rechazo -->
                            <div class="respuesta-form" id="sol-form-<?= $s['id'] ?>">
                                <form method="POST">
                                    <input type="hidden" name="accion" value="rechazar_solicitud">
                                    <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                    <div class="form-field" style="margin-bottom:10px">
                                        <label>Motivo del rechazo (opcional)</label>
                                        <textarea name="respuesta_solicitud" rows="2"
                                            placeholder="Explica por qué se rechaza..." style="resize:none"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-peligro"
                                        style="padding:6px 16px;font-size:12px">Confirmar rechazo</button>
                                    <button type="button" class="btn-outline"
                                        style="padding:6px 16px;font-size:12px;margin-left:6px"
                                        onclick="toggleSolRespuesta(<?= $s['id'] ?>)">Cancelar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Finalizadas -->
            <div class="tab-panel <?= $tab_activa === 'finalizadas' ? 'visible' : '' ?>" id="tab-finalizadas">
                <div class="fin-buscador">
                    <input type="text" id="fin-buscar" placeholder="Buscar por nombre, tipo, programa..."
                        oninput="filtrarFinalizadas()">
                    <div class="filtros">
                        <button class="fin-filtro-btn activo" data-filtro="todos"
                            onclick="setFiltro(this,'todos')">Todos</button>
                        <button class="fin-filtro-btn" data-filtro="aprobada"
                            onclick="setFiltro(this,'aprobada')">Aprobadas</button>
                        <button class="fin-filtro-btn" data-filtro="rechazada"
                            onclick="setFiltro(this,'rechazada')">Rechazadas</button>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">

                    <!-- Vacaciones -->
                    <div>
                        <div class="fin-col-titulo">Vacaciones (<?= count($vac_finalizadas) ?>)</div>
                        <?php if (empty($vac_finalizadas)): ?>
                            <p class="fin-vacio">No hay vacaciones finalizadas.</p>
                        <?php else: ?>
                            <p class="fin-vacio" id="fin-vacio-vac" style="display:none">Sin resultados.</p>
                            <?php foreach ($vac_finalizadas as $v):
                                $fi = date('d/m/Y', strtotime($v['fecha_inicio']));
                                $ff = date('d/m/Y', strtotime($v['fecha_fin']));
                                $tipo_label = match (true) {
                                    str_starts_with($v['motivo'] ?? '', 'Asunto propio') => 'Asunto propio',
                                    str_starts_with($v['motivo'] ?? '', 'Médico') => 'Médico',
                                    str_starts_with($v['motivo'] ?? '', 'Otro') => 'Otro',
                                    default => 'Vacaciones',
                                };
                                $bc = $v['estado'] === 'aprobada' ? 'badge-ok' : 'badge-peligro';
                                $buscar = strtolower($v['empleado_nombre'] . ' ' . $tipo_label . ' ' . $v['puesto']);
                                ?>
                                <div class="fin-row <?= $v['estado'] ?>" data-estado="<?= $v['estado'] ?>" data-col="vac"
                                    data-buscar="<?= htmlspecialchars($buscar) ?>"
                                    onclick="toggleFinDetalle('vac-<?= $v['id'] ?>')">
                                    <div class="fin-row-header">
                                        <span class="fin-row-nombre"><?= htmlspecialchars($v['empleado_nombre']) ?></span>
                                        <span class="badge <?= $bc ?>" style="font-size:10px"><?= $v['estado'] ?></span>
                                    </div>
                                    <div class="fin-row-meta"><?= $tipo_label ?> · <?= $fi ?> – <?= $ff ?> · <?= $v['dias'] ?>
                                        día<?= $v['dias'] > 1 ? 's' : '' ?></div>
                                    <div class="fin-detalle" id="fin-detalle-vac-<?= $v['id'] ?>">
                                        <strong>Empleado:</strong> <?= htmlspecialchars($v['empleado_nombre']) ?><br>
                                        <strong>Puesto:</strong> <?= htmlspecialchars($v['puesto']) ?><br>
                                        <strong>Tipo:</strong> <?= $tipo_label ?><br>
                                        <strong>Fechas:</strong> <?= $fi ?> – <?= $ff ?> · <?= $v['dias'] ?>
                                        día<?= $v['dias'] > 1 ? 's' : '' ?><br>
                                        <strong>Resultado:</strong> <span class="badge <?= $bc ?>"
                                            style="font-size:11px"><?= $v['estado'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Peticiones directores -->
                    <div>
                        <div class="fin-col-titulo">Peticiones directores (<?= count($pet_finalizadas) ?>)</div>
                        <?php if (empty($pet_finalizadas)): ?>
                            <p class="fin-vacio">No hay peticiones finalizadas.</p>
                        <?php else: ?>
                            <p class="fin-vacio" id="fin-vacio-pet" style="display:none">Sin resultados.</p>
                            <?php foreach ($pet_finalizadas as $p):
                                $bc = $p['estado'] === 'aprobada' ? 'badge-ok' : 'badge-peligro';
                                $buscar = strtolower($p['director_nombre'] . ' ' . $p['tipo'] . ' ' . ($p['programa_nombre'] ?? ''));
                                ?>
                                <div class="fin-row <?= $p['estado'] ?>" data-estado="<?= $p['estado'] ?>" data-col="pet"
                                    data-buscar="<?= htmlspecialchars($buscar) ?>"
                                    onclick="toggleFinDetalle('pet-<?= $p['id'] ?>')">
                                    <div class="fin-row-header">
                                        <span class="fin-row-nombre"><?= htmlspecialchars($p['director_nombre']) ?></span>
                                        <span class="badge <?= $bc ?>" style="font-size:10px"><?= $p['estado'] ?></span>
                                    </div>
                                    <div class="fin-row-meta">
                                        <?= htmlspecialchars($p['tipo']) ?>        <?php if ($p['programa_nombre']): ?> ·
                                            <?= htmlspecialchars($p['programa_nombre']) ?>        <?php endif; ?></div>
                                    <div class="fin-detalle" id="fin-detalle-pet-<?= $p['id'] ?>">
                                        <strong>Director:</strong> <?= htmlspecialchars($p['director_nombre']) ?><br>
                                        <strong>Tipo:</strong> <?= htmlspecialchars($p['tipo']) ?><br>
                                        <?php if ($p['programa_nombre']): ?><strong>Programa:</strong>
                                            <?= htmlspecialchars($p['programa_nombre']) ?><br><?php endif; ?>
                                        <strong>Descripción:</strong> <?= htmlspecialchars($p['descripcion'] ?? '-') ?><br>
                                        <?php if (!empty($p['respuesta'])): ?><strong>Respuesta:</strong>
                                            <?= htmlspecialchars($p['respuesta']) ?><br><?php endif; ?>
                                        <strong>Resultado:</strong> <span class="badge <?= $bc ?>"
                                            style="font-size:11px"><?= $p['estado'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Solicitudes de equipo -->
                    <div>
                        <div class="fin-col-titulo">Solicitudes de equipo (<?= count($solicitudes_finalizadas) ?>)</div>
                        <?php if (empty($solicitudes_finalizadas)): ?>
                            <p class="fin-vacio">No hay solicitudes finalizadas.</p>
                        <?php else: ?>
                            <p class="fin-vacio" id="fin-vacio-sol" style="display:none">Sin resultados.</p>
                            <?php foreach ($solicitudes_finalizadas as $s):
                                $bc = $s['estado'] === 'aprobada' ? 'badge-ok' : 'badge-peligro';
                                $buscar = strtolower($s['director_nombre'] . ' ' . $s['programa_nombre'] . ' ' . $s['control_nombre']);
                                ?>
                                <div class="fin-row <?= $s['estado'] ?>" data-estado="<?= $s['estado'] ?>" data-col="sol"
                                    data-buscar="<?= htmlspecialchars($buscar) ?>"
                                    onclick="toggleFinDetalle('sol-<?= $s['id'] ?>')">
                                    <div class="fin-row-header">
                                        <span class="fin-row-nombre"><?= htmlspecialchars($s['programa_nombre']) ?></span>
                                        <span class="badge <?= $bc ?>" style="font-size:10px"><?= $s['estado'] ?></span>
                                    </div>
                                    <div class="fin-row-meta"><?= htmlspecialchars($s['control_nombre']) ?> ·
                                        <?= htmlspecialchars($s['plato']) ?> · <?= $s['num_dias'] ?>
                                        día<?= $s['num_dias'] > 1 ? 's' : '' ?></div>
                                    <div class="fin-detalle" id="fin-detalle-sol-<?= $s['id'] ?>">
                                        <strong>Programa:</strong> <?= htmlspecialchars($s['programa_nombre']) ?><br>
                                        <strong>Director:</strong> <?= htmlspecialchars($s['director_nombre']) ?><br>
                                        <strong>Control:</strong> <?= htmlspecialchars($s['control_nombre']) ?> ·
                                        <?= htmlspecialchars($s['plato']) ?><br>
                                        <strong>Puestos:</strong> <?= $s['puestos_cubiertos'] ?>/<?= $s['num_puestos'] ?>
                                        cubiertos<br>
                                        <?php if (!empty($s['notas'])): ?><strong>Notas:</strong>
                                            <?= htmlspecialchars($s['notas']) ?><br><?php endif; ?>
                                        <strong>Resultado:</strong> <span class="badge <?= $bc ?>"
                                            style="font-size:11px"><?= $s['estado'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <script src="js/peticiones.js"></script>
    <script>
        function toggleSolRespuesta(id) {
            const f = document.getElementById('sol-form-' + id);
            f.style.display = f.style.display === 'block' ? 'none' : 'block';
        }
        window.addEventListener('DOMContentLoaded', () => {
            const tab = new URLSearchParams(window.location.search).get('tab');
            if (tab) {
                const btn = document.querySelector(`.tab-btn[onclick*="'${tab}'"]`);
                if (btn) cambiarTab(tab, btn);
            }
        });
    </script>
</body>

</html>
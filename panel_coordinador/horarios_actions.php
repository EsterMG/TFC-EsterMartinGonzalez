<?php
/* horarios_actions.php, procesa todas las acciones POST del módulo de horarios, se incluye desde horarios.php solo cuando la petición es POST. */

// ID de solicitud activa (viene del panel lateral)
$solicitud_id_ctx = (int) ($_POST['solicitud_id_ctx'] ?? 0);

// Helper: vincular turno asignado con solicitud_puestos
// Busca el puesto de la solicitud que coincida en puesto y fecha, y lo marca cubierto
function vincularConSolicitud(mysqli $conn, int $solicitud_id, string $puesto, int $empleado_id, string $fecha): void
{
    if (!$solicitud_id)
        return;

    // Buscar puesto pendiente de la solicitud que coincida con el puesto y la fecha
    $st = $conn->prepare("
        SELECT sp.id
        FROM solicitud_puestos sp
        JOIN solicitud_fechas sf ON sf.solicitud_id = sp.solicitud_id
        WHERE sp.solicitud_id = ?
          AND sp.puesto_solicitado = ?
          AND sf.fecha = ?
          AND sp.estado != 'cubierto'
        LIMIT 1
    ");
    $st->bind_param("iss", $solicitud_id, $puesto, $fecha);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row)
        return;

    $puesto_sol_id = $row['id'];
    $st = $conn->prepare("UPDATE solicitud_puestos SET empleado_id = ?, estado = 'cubierto' WHERE id = ?");
    $st->bind_param("ii", $empleado_id, $puesto_sol_id);
    $st->execute();
    $st->close();

    // Comprobar si todos los puestos de la solicitud están cubiertos
    $st = $conn->prepare("
        SELECT COUNT(*) AS t, SUM(CASE WHEN estado='cubierto' THEN 1 ELSE 0 END) AS c
        FROM solicitud_puestos WHERE solicitud_id = ?
    ");
    $st->bind_param("i", $solicitud_id);
    $st->execute();
    $res = $st->get_result()->fetch_assoc();
    $st->close();

    if ($res['t'] > 0 && $res['t'] == $res['c']) {
        $st = $conn->prepare("UPDATE solicitudes SET estado = 'aprobada' WHERE id = ?");
        $st->bind_param("i", $solicitud_id);
        $st->execute();
        $st->close();
    } else {
        // Al menos uno cubierto: marcar en proceso si sigue pendiente
        $st = $conn->prepare("UPDATE solicitudes SET estado = 'en_proceso' WHERE id = ? AND estado = 'pendiente'");
        $st->bind_param("i", $solicitud_id);
        $st->execute();
        $st->close();
    }
}

// MARCAR / DESMARCAR DÍA ESPECIAL (festivo o fin de semana)
if ($accion === 'toggle_dia_especial') {
    $tipo_dia = $_POST['tipo_dia'] ?? 'festivo';

    $stmt = $conn->prepare("SELECT id FROM festivos WHERE fecha = ? AND tipo = ?");
    $stmt->bind_param("ss", $fecha_ctx, $tipo_dia);
    $stmt->execute();
    $stmt->store_result();
    $existe = $stmt->num_rows > 0;
    $stmt->close();

    if ($existe) {
        if ($tipo_dia === 'festivo') {
            $stmt = $conn->prepare("DELETE FROM dias_extra WHERE fecha_turno = ? AND tipo = 'festivo' AND origen = 'automatico'");
            $stmt->bind_param("s", $fecha_ctx);
            $stmt->execute();
            $stmt->close();
        }
        $stmt = $conn->prepare("DELETE FROM festivos WHERE fecha = ? AND tipo = ?");
        $stmt->bind_param("ss", $fecha_ctx, $tipo_dia);
        $stmt->execute();
        $stmt->close();
        $msg_ok = $tipo_dia === 'festivo' ? "Festivo eliminado." : "Fin de semana desmarcado.";
    } else {
        $stmt = $conn->prepare("INSERT INTO festivos (fecha, tipo, descripcion) VALUES (?, ?, '')");
        $stmt->bind_param("ss", $fecha_ctx, $tipo_dia);
        $stmt->execute();
        $stmt->close();

        if ($tipo_dia === 'festivo') {
            $desc_auto = "Festivo " . $fecha_ctx;
            $stmt = $conn->prepare("
                INSERT IGNORE INTO dias_extra (empleado_id, fecha, tipo, dias, horas, descripcion, origen, fecha_turno)
                SELECT e.id, ?, 'festivo', 1,
                       ROUND(MOD(TIME_TO_SEC(TIMEDIFF(t.hora_fin, t.hora_inicio)) / 3600 + 24, 24), 2),
                       ?, 'automatico', ?
                FROM turnos t
                JOIN empleados e ON e.id = t.empleado_id
                WHERE t.fecha = ? AND t.estado = 'cubierto' AND t.empleado_id IS NOT NULL
                GROUP BY e.id
            ");
            $stmt->bind_param("ssss", $fecha_ctx, $desc_auto, $fecha_ctx, $fecha_ctx);
            $stmt->execute();
            $stmt->close();
        }
        $msg_ok = $tipo_dia === 'festivo' ? "Día marcado como festivo." : "Fin de semana marcado.";
    }

    header("Location: horarios.php?fecha={$fecha_ctx}&mes={$mes_ctx}&ok=" . urlencode($msg_ok));
    exit;
}

// CREAR TURNO NORMAL (sin asignar, para todos los puestos)
if ($accion === 'crear_turno') {
    $prog_id = (int) ($_POST['programa_id'] ?? 0);
    $control = trim($_POST['control_nombre'] ?? '');
    $hora_ini = $_POST['hora_inicio'] ?? '';
    $hora_fin = $_POST['hora_fin'] ?? '';
    $fechas_arr = parsearFechas($_POST['fechas_seleccionadas'] ?? '');
    $plato = 'Plato ' . preg_replace('/\D/', '', $control);

    if (!$control) {
        $msg_err = "Selecciona un control.";
    } elseif (!$hora_ini || !$hora_fin) {
        $msg_err = "Indica el horario.";
    } elseif (empty($fechas_arr)) {
        $msg_err = "Selecciona al menos un día.";
    } else {
        $pid = $prog_id > 0 ? $prog_id : null;
        $todos_puestos = obtenerTodosPuestos($conn);

        $stmt_bloque = $conn->prepare("INSERT INTO turnos_bloque (control_nombre, fecha, programa_id, hora_inicio, hora_fin, coordinador_id) VALUES (?,?,?,?,?,?)");
        $stmt_turno = $conn->prepare("INSERT INTO turnos (bloque_id, programa_id, coordinador_id, puesto_solicitado, control_nombre, plato, fecha, hora_inicio, hora_fin, estado) VALUES (?,?,?,?,?,?,?,?,?,'sin_cubrir')");

        $ok = 0;
        foreach ($fechas_arr as $fecha) {
            $stmt_bloque->bind_param("ssissi", $control, $fecha, $pid, $hora_ini, $hora_fin, $coordinador_id);
            $stmt_bloque->execute();
            $bloque_id = $conn->insert_id;

            foreach ($todos_puestos as $puesto) {
                $stmt_turno->bind_param("iisssssss", $bloque_id, $pid, $coordinador_id, $puesto, $control, $plato, $fecha, $hora_ini, $hora_fin);
                if ($stmt_turno->execute())
                    $ok++;
            }
        }
        $stmt_bloque->close();
        $stmt_turno->close();

        $msg_ok = "{$ok} turno(s) creado(s) en " . count($fechas_arr) . " día(s).";
        if (!empty($fechas_arr))
            $fecha_ctx = $fechas_arr[0];
    }
}

// CREAR TURNO POR PROGRAMA (asigna empleados seleccionados)
if ($accion === 'crear_turno_programa') {
    $prog_id = (int) ($_POST['programa_id'] ?? 0);
    $control = trim($_POST['control_nombre'] ?? '');
    $hora_ini = $_POST['hora_inicio'] ?? '';
    $hora_fin = $_POST['hora_fin'] ?? '';
    $fechas_arr = parsearFechas($_POST['fechas_seleccionadas'] ?? '');
    $empleados_ids = array_values(array_filter(array_map('intval', explode(',', $_POST['empleados_seleccionados'] ?? ''))));

    if (!$prog_id || !$control || !$hora_ini || !$hora_fin || empty($fechas_arr)) {
        $msg_err = "Faltan datos para crear el turno de programa.";
    } else {
        $plato = 'Plato ' . preg_replace('/\D/', '', $control);
        $pid = $prog_id > 0 ? $prog_id : null;

        $emp_por_puesto = [];
        if (!empty($empleados_ids)) {
            $placeholders = implode(',', array_fill(0, count($empleados_ids), '?'));
            $stmt = $conn->prepare("
                SELECT e.id AS empleado_id, e.puesto
                FROM empleados e JOIN usuarios u ON u.id = e.usuario_id
                WHERE e.id IN ($placeholders) AND u.estado = 'activo'
            ");
            $stmt->bind_param(str_repeat('i', count($empleados_ids)), ...$empleados_ids);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $emp)
                $emp_por_puesto[$emp['puesto']] = $emp['empleado_id'];
            $stmt->close();
        }

        $todos_puestos = obtenerTodosPuestos($conn);

        $stmt_bloque = $conn->prepare("INSERT INTO turnos_bloque (control_nombre, fecha, programa_id, hora_inicio, hora_fin, coordinador_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_sin_cub = $conn->prepare("INSERT INTO turnos (bloque_id, programa_id, coordinador_id, puesto_solicitado, control_nombre, plato, fecha, hora_inicio, hora_fin, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'sin_cubrir')");
        $stmt_cubierto = $conn->prepare("INSERT INTO turnos (bloque_id, empleado_id, programa_id, coordinador_id, puesto_solicitado, control_nombre, plato, fecha, hora_inicio, hora_fin, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'cubierto')");

        $ok = 0;
        foreach ($fechas_arr as $fecha) {
            $stmt_bloque->bind_param("ssissi", $control, $fecha, $pid, $hora_ini, $hora_fin, $coordinador_id);
            $stmt_bloque->execute();
            $bloque_id = $conn->insert_id;

            foreach ($todos_puestos as $puesto) {
                if (isset($emp_por_puesto[$puesto])) {
                    $emp_id = $emp_por_puesto[$puesto];
                    $stmt_cubierto->bind_param("iiiissssss", $bloque_id, $emp_id, $pid, $coordinador_id, $puesto, $control, $plato, $fecha, $hora_ini, $hora_fin);
                    if ($stmt_cubierto->execute()) {
                        $ok++;
                        // Vincular con solicitud si hay contexto
                        vincularConSolicitud($conn, $solicitud_id_ctx, $puesto, $emp_id, $fecha);
                    }
                } else {
                    $stmt_sin_cub->bind_param("iisssssss", $bloque_id, $pid, $coordinador_id, $puesto, $control, $plato, $fecha, $hora_ini, $hora_fin);
                    $stmt_sin_cub->execute();
                }
            }
        }
        $stmt_bloque->close();
        $stmt_sin_cub->close();
        $stmt_cubierto->close();

        $msg_ok = "{$ok} turno(s) cubierto(s) y el resto sin asignar en " . count($fechas_arr) . " día(s).";
        if (!empty($fechas_arr))
            $fecha_ctx = $fechas_arr[0];
    }
}

// EDITAR BLOQUE (horario y programa)
if ($accion === 'editar_bloque') {
    $bloque_id = (int) ($_POST['bloque_id'] ?? 0);
    $prog_id = (int) ($_POST['programa_id'] ?? 0);
    $hora_ini = $_POST['hora_inicio'] ?? '';
    $hora_fin = $_POST['hora_fin'] ?? '';
    $pid = $prog_id > 0 ? $prog_id : null;

    if ($bloque_id) {
        $stmt = $conn->prepare("UPDATE turnos_bloque SET programa_id=?, hora_inicio=?, hora_fin=? WHERE id=? AND coordinador_id=?");
        $stmt->bind_param("issii", $pid, $hora_ini, $hora_fin, $bloque_id, $coordinador_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE turnos SET programa_id=?, hora_inicio=?, hora_fin=? WHERE bloque_id=? AND estado='sin_cubrir'");
        $stmt->bind_param("issi", $pid, $hora_ini, $hora_fin, $bloque_id);
        $stmt->execute() ? $msg_ok = "Bloque actualizado." : $msg_err = "Error al actualizar.";
        $stmt->close();
    }
}

// BORRAR BLOQUE (y todos sus turnos)
if ($accion === 'borrar_bloque') {
    $bloque_id = (int) ($_POST['bloque_id'] ?? 0);
    if ($bloque_id) {
        $stmt = $conn->prepare("DELETE FROM turnos WHERE bloque_id=?");
        $stmt->bind_param("i", $bloque_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM turnos_bloque WHERE id=? AND coordinador_id=?");
        $stmt->bind_param("ii", $bloque_id, $coordinador_id);
        $stmt->execute() ? $msg_ok = "Bloque eliminado." : $msg_err = "Error al eliminar.";
        $stmt->close();
    }
}

// ASIGNAR EMPLEADO A UN TURNO EXISTENTE
if ($accion === 'asignar_empleado') {
    $turno_id = (int) ($_POST['turno_id'] ?? 0);
    $empleado_id = (int) ($_POST['empleado_id'] ?? 0);

    if ($turno_id && $empleado_id) {
        $stmt = $conn->prepare("UPDATE turnos SET empleado_id=?, estado='cubierto' WHERE id=?");
        $stmt->bind_param("ii", $empleado_id, $turno_id);
        $stmt->execute();
        $stmt->close();

        // Obtener datos del turno para festivos y vinculación solicitud
        $stmt = $conn->prepare("
            SELECT t.fecha, t.puesto_solicitado, f.tipo AS tipo_festivo
            FROM turnos t
            LEFT JOIN festivos f ON f.fecha = t.fecha
            WHERE t.id = ?
        ");
        $stmt->bind_param("i", $turno_id);
        $stmt->execute();
        $turno_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($turno_info) {
            if ($turno_info['tipo_festivo'] === 'festivo')
                registrarDiaExtraFestivo($conn, $empleado_id, $turno_info['fecha']);

            // Vincular con solicitud si hay contexto
            vincularConSolicitud(
                $conn,
                $solicitud_id_ctx,
                $turno_info['puesto_solicitado'],
                $empleado_id,
                $turno_info['fecha']
            );
        }
        $msg_ok = "Empleado asignado.";
    }
}

// ASIGNAR EMPLEADO A UNA SOLICITUD (desde barra de solicitud en el Gantt)
if ($accion === 'asignar_sol') {
    $puesto_id = (int) ($_POST['puesto_id'] ?? 0);
    $sol_id = (int) ($_POST['solicitud_id'] ?? 0);
    $empleado_id = (int) ($_POST['empleado_id'] ?? 0);

    if ($empleado_id > 0) {
        $stmt = $conn->prepare("UPDATE solicitud_puestos SET empleado_id=?, estado='cubierto' WHERE id=? AND solicitud_id=?");
        $stmt->bind_param("iii", $empleado_id, $puesto_id, $sol_id);
    } else {
        $stmt = $conn->prepare("UPDATE solicitud_puestos SET empleado_id=NULL, estado='pendiente' WHERE id=? AND solicitud_id=?");
        $stmt->bind_param("ii", $puesto_id, $sol_id);
    }
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS t, SUM(CASE WHEN estado='cubierto' THEN 1 ELSE 0 END) AS c FROM solicitud_puestos WHERE solicitud_id=?");
    $stmt->bind_param("i", $sol_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res['t'] > 0 && $res['t'] == $res['c']) {
        $stmt = $conn->prepare("UPDATE solicitudes SET estado='aprobada', coordinador_id=?, fecha_respuesta=NOW() WHERE id=?");
        $stmt->bind_param("ii", $coordinador_id, $sol_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("SELECT * FROM solicitudes WHERE id=?");
        $stmt->bind_param("i", $sol_id);
        $stmt->execute();
        $sol = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("SELECT fecha FROM solicitud_fechas WHERE solicitud_id=?");
        $stmt->bind_param("i", $sol_id);
        $stmt->execute();
        $fechas_sol = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'fecha');
        $stmt->close();

        $stmt = $conn->prepare("SELECT * FROM solicitud_puestos WHERE solicitud_id=?");
        $stmt->bind_param("i", $sol_id);
        $stmt->execute();
        $puestos_sol = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $pid_sol = (int) ($sol['programa_id'] ?? 0);
        foreach ($fechas_sol as $f) {
            foreach ($puestos_sol as $p) {
                if ($pid_sol > 0) {
                    $ins = $conn->prepare("INSERT IGNORE INTO turnos (empleado_id, programa_id, coordinador_id, puesto_solicitado, control_nombre, plato, fecha, hora_inicio, hora_fin, estado) VALUES (?,?,?,?,?,?,?,?,?,'cubierto')");
                    $ins->bind_param("iiissssss", $p['empleado_id'], $pid_sol, $coordinador_id, $p['puesto_solicitado'], $sol['control_nombre'], $sol['plato'], $f, $p['hora_inicio'], $p['hora_fin']);
                } else {
                    $ins = $conn->prepare("INSERT IGNORE INTO turnos (empleado_id, coordinador_id, puesto_solicitado, control_nombre, plato, fecha, hora_inicio, hora_fin, estado) VALUES (?,?,?,?,?,?,?,?,'cubierto')");
                    $ins->bind_param("iissssss", $p['empleado_id'], $coordinador_id, $p['puesto_solicitado'], $sol['control_nombre'], $sol['plato'], $f, $p['hora_inicio'], $p['hora_fin']);
                }
                $ins->execute();
                $ins->close();
            }
        }
        $msg_ok = "Solicitud aprobada y turnos generados.";
    } else {
        $msg_ok = "Empleado asignado.";
    }
}

// EDITAR TURNO (horario y/o empleado)
if ($accion === 'editar_turno') {
    $turno_id = (int) ($_POST['turno_id'] ?? 0);
    $hora_ini = $_POST['hora_inicio'] ?? '';
    $hora_fin = $_POST['hora_fin'] ?? '';
    $emp_id = (int) ($_POST['empleado_id'] ?? 0);

    if ($turno_id) {
        if ($emp_id) {
            $stmt = $conn->prepare("UPDATE turnos SET hora_inicio=?, hora_fin=?, empleado_id=?, estado='cubierto' WHERE id=? AND coordinador_id=?");
            $stmt->bind_param("ssiii", $hora_ini, $hora_fin, $emp_id, $turno_id, $coordinador_id);
        } else {
            $stmt = $conn->prepare("UPDATE turnos SET hora_inicio=?, hora_fin=? WHERE id=? AND coordinador_id=?");
            $stmt->bind_param("ssii", $hora_ini, $hora_fin, $turno_id, $coordinador_id);
        }
        $stmt->execute() ? $msg_ok = "Turno actualizado." : $msg_err = "Error al actualizar.";
        $stmt->close();

        // Vincular con solicitud si hay empleado y contexto
        if ($emp_id && $solicitud_id_ctx) {
            $stmt = $conn->prepare("SELECT fecha, puesto_solicitado FROM turnos WHERE id=?");
            $stmt->bind_param("i", $turno_id);
            $stmt->execute();
            $ti = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($ti)
                vincularConSolicitud($conn, $solicitud_id_ctx, $ti['puesto_solicitado'], $emp_id, $ti['fecha']);
        }
    }
}

// CREAR Y ASIGNAR TURNO DESDE DRAG
if ($accion === 'crear_y_asignar_bloque') {
    $bloque_id = (int) ($_POST['bloque_id'] ?? 0);
    $emp_id = (int) ($_POST['empleado_id'] ?? 0);
    $puesto = trim($_POST['puesto_solicitado'] ?? '');
    $hora_ini = $_POST['hora_inicio'] ?? '';
    $hora_fin = $_POST['hora_fin'] ?? '';

    if ($bloque_id && $emp_id && $puesto && $hora_ini && $hora_fin) {
        $stmt = $conn->prepare("SELECT programa_id, control_nombre, fecha, coordinador_id FROM turnos_bloque WHERE id=?");
        $stmt->bind_param("i", $bloque_id);
        $stmt->execute();
        $bloque = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($bloque) {
            $plato = 'Plato ' . preg_replace('/\D/', '', $bloque['control_nombre']);
            $stmt = $conn->prepare("INSERT INTO turnos (bloque_id, empleado_id, programa_id, coordinador_id, puesto_solicitado, control_nombre, plato, fecha, hora_inicio, hora_fin, estado) VALUES (?,?,?,?,?,?,?,?,?,?,'cubierto')");
            $stmt->bind_param("iiiissssss", $bloque_id, $emp_id, $bloque['programa_id'], $bloque['coordinador_id'], $puesto, $bloque['control_nombre'], $plato, $bloque['fecha'], $hora_ini, $hora_fin);

            if ($stmt->execute()) {
                $msg_ok = "Turno asignado.";

                // Festivo
                $stmt2 = $conn->prepare("SELECT tipo FROM festivos WHERE fecha = ?");
                $stmt2->bind_param("s", $bloque['fecha']);
                $stmt2->execute();
                $festivo = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
                if ($festivo && $festivo['tipo'] === 'festivo')
                    registrarDiaExtraFestivo($conn, $emp_id, $bloque['fecha']);

                // Vincular con solicitud si hay contexto
                vincularConSolicitud($conn, $solicitud_id_ctx, $puesto, $emp_id, $bloque['fecha']);
            } else {
                $msg_err = "Error al crear el turno.";
            }
            $stmt->close();
        }
    } else {
        $msg_err = "Faltan datos para asignar el turno.";
    }
}

// LIMPIAR CONTROL
if ($accion === 'limpiar_control') {
    $ctrl = trim($_POST['ctrl_nombre'] ?? '');
    if ($ctrl) {
        $stmt = $conn->prepare("DELETE FROM turnos WHERE control_nombre=? AND fecha=? AND coordinador_id=?");
        $stmt->bind_param("ssi", $ctrl, $fecha_ctx, $coordinador_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM turnos_bloque WHERE control_nombre=? AND fecha=? AND coordinador_id=?");
        $stmt->bind_param("ssi", $ctrl, $fecha_ctx, $coordinador_id);
        $stmt->execute() ? $msg_ok = "Control limpiado." : $msg_err = "Error al limpiar.";
        $stmt->close();
    }
}

// BORRAR TURNO
if ($accion === 'borrar_turno') {
    $turno_id = (int) ($_POST['turno_id'] ?? 0);

    $stmt = $conn->prepare("SELECT empleado_id, fecha FROM turnos WHERE id=? AND coordinador_id=?");
    $stmt->bind_param("ii", $turno_id, $coordinador_id);
    $stmt->execute();
    $turno_borrar = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM turnos WHERE id=? AND coordinador_id=?");
    $stmt->bind_param("ii", $turno_id, $coordinador_id);
    $stmt->execute() ? $msg_ok = "Turno eliminado." : $msg_err = "Error al eliminar.";
    $stmt->close();

    if ($turno_borrar && $turno_borrar['empleado_id']) {
        $stmt = $conn->prepare("DELETE FROM dias_extra WHERE empleado_id=? AND fecha_turno=? AND tipo='festivo' AND origen='automatico'");
        $stmt->bind_param("is", $turno_borrar['empleado_id'], $turno_borrar['fecha']);
        $stmt->execute();
        $stmt->close();
    }
}
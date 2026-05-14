<?php
/**
 * horarios_data.php
 * Carga todos los datos necesarios para renderizar la vista de horarios.
 * Se incluye desde horarios.php después de procesar el POST.
 */

// Párametros de la url
$msg_ok = $_GET['ok'] ?? '';
$msg_err = $_GET['err'] ?? '';
$fecha_sel = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_sel))
    $fecha_sel = date('Y-m-d');

$fecha_obj = new DateTime($fecha_sel);
$hoy_str = date('Y-m-d');
$mes_offset = (int) ($_GET['mes'] ?? 0);
$fecha_ant = (clone $fecha_obj)->modify('-1 day')->format('Y-m-d');
$fecha_sig = (clone $fecha_obj)->modify('+1 day')->format('Y-m-d');

// Convierte "HH:MM:SS" o "HH:MM" a minutos desde medianoche
$hhmm = fn($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);

$meses_es = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$dias_es = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

// Festivo del día seleccionado
$stmt = $conn->prepare("SELECT id, tipo, descripcion FROM festivos WHERE fecha = ?");
$stmt->bind_param("s", $fecha_sel);
$stmt->execute();
$festivo_hoy = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Listas auxiliares
$stmt = $conn->prepare("SELECT id, nombre, hora_inicio, hora_fin FROM programas ORDER BY id ASC");
$stmt->execute();
$programas_lista = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT DISTINCT e.puesto FROM empleados e JOIN usuarios u ON u.id = e.usuario_id WHERE u.estado = 'activo' ORDER BY e.puesto ASC");
$stmt->execute();
$puestos_todos = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'puesto');
$stmt->close();
usort($puestos_todos, fn($a, $b) => ordenPuesto($a) <=> ordenPuesto($b));

// Datos del calendario (mes actual)
$primer_dia = new DateTime("first day of this month");
$primer_dia->modify("{$mes_offset} months");
$ultimo_dia = clone $primer_dia;
$ultimo_dia->modify("last day of this month");
$f_ini = $primer_dia->format('Y-m-d');
$f_fin = $ultimo_dia->format('Y-m-d');

// Turnos del mes (para el mini calendario)
$stmt = $conn->prepare("SELECT t.fecha, COUNT(*) AS total, SUM(CASE WHEN t.estado='cubierto' THEN 1 ELSE 0 END) AS cubiertos FROM turnos t WHERE t.fecha BETWEEN ? AND ? GROUP BY t.fecha");
$stmt->bind_param("ss", $f_ini, $f_fin);
$stmt->execute();
$cal_data = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r)
    $cal_data[$r['fecha']] = $r;
$stmt->close();

// Festivos del mes
$stmt = $conn->prepare("SELECT fecha, tipo FROM festivos WHERE fecha BETWEEN ? AND ?");
$stmt->bind_param("ss", $f_ini, $f_fin);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r)
    $cal_data[$r['fecha']]['tipo_especial'] = $r['tipo'];
$stmt->close();

// Programas por día del mes (mostrar en el calendario)
$stmt = $conn->prepare("
    SELECT b.fecha, p.nombre AS prog_nombre
    FROM turnos_bloque b
    LEFT JOIN programas p ON p.id = b.programa_id
    WHERE b.fecha BETWEEN ? AND ? AND b.coordinador_id = ?
    ORDER BY b.fecha, b.hora_inicio
");
$stmt->bind_param("ssi", $f_ini, $f_fin, $coordinador_id);
$stmt->execute();
$progs_por_dia = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r)
    $progs_por_dia[$r['fecha']][] = $r['prog_nombre'] ?? 'Sin programa';
$stmt->close();

// Días especiales para el JS
$stmt = $conn->prepare("SELECT fecha, tipo FROM festivos WHERE fecha BETWEEN ? AND ?");
$stmt->bind_param("ss", $f_ini, $f_fin);
$stmt->execute();
$dias_especiales_js = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// DATOS DEL DÍA SELECCIONADO
// Bloques del día
$stmt = $conn->prepare("
    SELECT b.id AS bloque_id, b.control_nombre, b.hora_inicio, b.hora_fin, b.programa_id,
           p.nombre AS prog_nombre
    FROM turnos_bloque b
    LEFT JOIN programas p ON p.id = b.programa_id
    WHERE b.fecha = ? AND b.coordinador_id = ?
    ORDER BY b.control_nombre, b.hora_inicio
");
$stmt->bind_param("si", $fecha_sel, $coordinador_id);
$stmt->execute();
$bloques_dia = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Turnos del día
$stmt = $conn->prepare("
    SELECT t.id, t.hora_inicio, t.hora_fin, t.puesto_solicitado, t.control_nombre,
           t.plato, t.programa_id, t.estado, t.bloque_id,
           p.nombre AS prog_nombre, u.nombre AS emp_nombre, e.id AS emp_id
    FROM turnos t
    LEFT JOIN programas p  ON p.id = t.programa_id
    LEFT JOIN empleados e  ON e.id = t.empleado_id
    LEFT JOIN usuarios u   ON u.id = e.usuario_id
    WHERE t.fecha = ? AND t.control_nombre IS NOT NULL
    ORDER BY t.control_nombre, t.hora_inicio
");
$stmt->bind_param("s", $fecha_sel);
$stmt->execute();
$turnos_dia = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Solicitudes aprobadas/ en proceso del día
$stmt = $conn->prepare("
    SELECT s.id AS sol_id, s.control_nombre, s.plato, s.hora_inicio, s.hora_fin, s.programa_id,
           p.nombre AS prog_nombre,
           sp.id AS puesto_sol_id, sp.puesto_solicitado,
           sp.hora_inicio AS p_ini, sp.hora_fin AS p_fin, sp.estado AS puesto_estado,
           e.id AS emp_id, u.nombre AS emp_nombre
    FROM solicitudes s
    JOIN solicitud_fechas sf  ON sf.solicitud_id = s.id
    JOIN solicitud_puestos sp ON sp.solicitud_id = s.id
    LEFT JOIN programas p     ON p.id = s.programa_id
    LEFT JOIN empleados e     ON e.id = sp.empleado_id
    LEFT JOIN usuarios u      ON u.id = e.usuario_id
    WHERE sf.fecha = ? AND s.estado IN ('en_proceso')
    ORDER BY s.control_nombre, s.hora_inicio
");
$stmt->bind_param("s", $fecha_sel);
$stmt->execute();
$solis_dia = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Empleados activos con indicador de si ya tienen turno hoy
$stmt = $conn->prepare("
    SELECT e.id, e.puesto, u.nombre,
           (SELECT COUNT(*) FROM turnos t2 WHERE t2.empleado_id = e.id AND t2.fecha = ? AND t2.estado = 'cubierto') AS ocupado
    FROM empleados e
    JOIN usuarios u ON u.id = e.usuario_id
    WHERE u.estado = 'activo'
    ORDER BY e.puesto, u.nombre
");
$stmt->bind_param("s", $fecha_sel);
$stmt->execute();
$empleados_dia = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ventana horaria del gantt
$all_ini = [];
$all_fin = [];
foreach ($turnos_dia as $t) {
    $ii = $hhmm($t['hora_inicio']);
    $ff = $hhmm($t['hora_fin']);
    if ($ff <= $ii)
        $ff += 1440;
    $all_ini[] = $ii;
    $all_fin[] = $ff;
}
foreach ($solis_dia as $s) {
    $ii = $hhmm($s['p_ini']);
    $ff = $hhmm($s['p_fin']);
    if ($ff <= $ii)
        $ff += 1440;
    $all_ini[] = $ii;
    $all_fin[] = $ff;
}

$slot_ini = 4 * 60 + 30; // Por defecto empieza 4:30
if (!empty($all_fin)) {
    $min_ini = min($all_ini);
    if ($min_ini < $slot_ini)
        $slot_ini = max(0, (int) floor(($min_ini - 15) / 15) * 15);
    $slot_fin = (int) ceil((max($all_fin) + 15) / 15) * 15;
} else {
    $slot_fin = 27 * 60;
}

$ventana = $slot_fin - $slot_ini;
$slots = [];
for ($s = $slot_ini; $s < $slot_fin; $s += 15)
    $slots[] = sprintf('%02d:%02d', intdiv($s, 60) % 24, $s % 60);
$ns = count($slots);

// Calcula la posición y anchura de una barra en el Gantt (en porcentaje)
$gpos = function (string $ini, string $fin) use ($slot_ini, $ventana, $hhmm): array {
    $i = $hhmm($ini) - $slot_ini;
    $f = $hhmm($fin) - $slot_ini;
    if ($f <= $i)
        $f += 1440;
    return [max(0, $i / $ventana * 100), max(0.5, ($f - $i) / $ventana * 100)];
};

// Agrupar datos por control
$por_ctrl = [];
for ($cn = 1; $cn <= 10; $cn++) {
    $por_ctrl["Control {$cn}"] = ['bloques' => [], 'turnos' => [], 'solis' => [], 'vacio' => true];
}

foreach ($bloques_dia as $b) {
    $ctrl = $b['control_nombre'];
    if (!isset($por_ctrl[$ctrl]))
        $por_ctrl[$ctrl] = ['bloques' => [], 'turnos' => [], 'solis' => [], 'vacio' => true];
    $por_ctrl[$ctrl]['bloques'][] = $b;
    $por_ctrl[$ctrl]['vacio'] = false;
}
foreach ($turnos_dia as $t) {
    $ctrl = $t['control_nombre'] ?? '—';
    if (!isset($por_ctrl[$ctrl]))
        $por_ctrl[$ctrl] = ['bloques' => [], 'turnos' => [], 'solis' => [], 'vacio' => true];
    $por_ctrl[$ctrl]['turnos'][] = $t;
    $por_ctrl[$ctrl]['vacio'] = false;
}
foreach ($solis_dia as $s) {
    $ctrl = $s['control_nombre'] ?? '—';
    if (!isset($por_ctrl[$ctrl]))
        $por_ctrl[$ctrl] = ['bloques' => [], 'turnos' => [], 'solis' => [], 'vacio' => true];
    $por_ctrl[$ctrl]['solis'][$s['sol_id']][] = $s;
    $por_ctrl[$ctrl]['vacio'] = false;
}
ksort($por_ctrl);
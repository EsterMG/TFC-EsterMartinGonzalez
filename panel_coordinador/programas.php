<?php
session_start();
if (empty($_SESSION['rol']) || $_SESSION['rol'] !== 'coordinador') {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "turnostv");
$conn->set_charset("utf8mb4");
if ($conn->connect_error)
    die("Error: " . $conn->connect_error);

$usuario_id = $_SESSION['usuario_id'];
$stmt = $conn->prepare("SELECT id FROM coordinadores WHERE usuario_id=?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$coord = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$coord)
    die("Coordinador no encontrado.");
$coordinador_id = $coord['id'];

$stmt = $conn->prepare("SELECT id, nombre, hora_inicio, hora_fin FROM programas ORDER BY id ASC");
$stmt->execute();
$programas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT
        b.programa_id,
        b.fecha,
        b.hora_inicio AS bloque_ini,
        b.hora_fin AS bloque_fin,
        b.control_nombre,
        t.puesto_solicitado,
        t.estado,
        u.nombre AS emp_nombre
    FROM turnos_bloque b
    JOIN turnos t ON t.bloque_id = b.id
    LEFT JOIN empleados e ON e.id = t.empleado_id
    LEFT JOIN usuarios u ON u.id = e.usuario_id
    WHERE b.coordinador_id = ?
    AND b.fecha >= CURDATE() - INTERVAL 7 DAY
    ORDER BY b.programa_id, b.fecha, b.control_nombre, t.puesto_solicitado
");
$stmt->bind_param("i", $coordinador_id);
$stmt->execute();
$todos_turnos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$por_programa = [];
foreach ($todos_turnos as $t) {
    $pid = $t['programa_id'];
    $f = $t['fecha'];
    $ctrl = $t['control_nombre'];
    if (!isset($por_programa[$pid]))
        $por_programa[$pid] = [];
    if (!isset($por_programa[$pid][$f]))
        $por_programa[$pid][$f] = [];
    if (!isset($por_programa[$pid][$f][$ctrl]))
        $por_programa[$pid][$f][$ctrl] = [];
    $por_programa[$pid][$f][$ctrl][] = $t;
}

$orden_claves = ['JEFE', 'MEZCLA', 'SONIDO', 'CCU', 'ILUMINA', 'EVS', 'MULTIPLAY', 'ROTULO', 'PROMPT', 'PRIMERA', 'CAMARA', 'AUXILIAR'];
$norm = fn($s) => strtoupper(strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'ñ' => 'n', 'Ñ' => 'N']));
$orden_fn = function ($p) use ($orden_claves, $norm) {
    $n = $norm($p);
    foreach ($orden_claves as $i => $c) {
        if (str_contains($n, $c))
            return $i;
    }
    return 99;
};

$dias_es = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
$meses_es = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$titulo_pagina = 'Programas';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TurnosTV — Programas</title>
    <link rel="stylesheet" href="../fragmentos/styles/base.css">
    <link rel="stylesheet" href="styles/programas.css">
</head>

<body>
    <?php include '../fragmentos/sidebar.php'; ?>
    <div class="content">
        <?php include '../fragmentos/header.php'; ?>
        <div class="body">

            <div class="page-header">
                <h2 class="page-titulo">Programas</h2>
                <span class="page-subtitulo">Mostrando desde hace 7 días</span>
            </div>

            <div class="prog-lista">
                <?php foreach ($programas as $prog):
                    $pid = $prog['id'];
                    $datos = $por_programa[$pid] ?? [];
                    $n_dias = count($datos);
                    $total_t = 0;
                    $cubiertos_t = 0;
                    foreach ($datos as $f => $ctrls)
                        foreach ($ctrls as $ctrl => $turnos)
                            foreach ($turnos as $t) {
                                $total_t++;
                                if ($t['estado'] === 'cubierto')
                                    $cubiertos_t++;
                            }
                    ?>
                    <div class="prog-item" id="prog-<?= $pid ?>">
                        <div class="prog-cabecera" onclick="toggleProg(<?= $pid ?>)">
                            <div class="prog-nombre"><?= htmlspecialchars($prog['nombre']) ?></div>
                            <div class="prog-stats">
                                <?php if ($prog['hora_inicio'] !== '00:00:00' || $prog['hora_fin'] !== '00:00:00'): ?>
                                    <span class="prog-horario"><?= substr($prog['hora_inicio'], 0, 5) ?> –
                                        <?= substr($prog['hora_fin'], 0, 5) ?></span>
                                <?php endif; ?>
                                <?php if ($n_dias > 0): ?>
                                    <span class="prog-badge prog-badge-dias"><?= $n_dias ?>
                                        día<?= $n_dias !== 1 ? 's' : '' ?></span>
                                    <?php if ($total_t > 0):
                                        $pct = $cubiertos_t / $total_t; ?>
                                        <span
                                            class="prog-badge <?= $pct >= 1 ? 'prog-badge-ok' : 'prog-badge-pend' ?>"><?= $cubiertos_t ?>/<?= $total_t ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="prog-badge prog-badge-vacio">Sin turnos</span>
                                <?php endif; ?>
                            </div>
                            <span class="prog-flecha">▼</span>
                        </div>

                        <div class="prog-cuerpo">
                            <?php if (empty($datos)): ?>
                                <div class="prog-sin-datos">No hay turnos asignados a este programa en los próximos días.</div>
                            <?php else: ?>
                                <div class="prog-dias-wrap">
                                    <table class="prog-dias-tabla">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Control</th>
                                                <th>Horario</th>
                                                <th>Puestos</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            ksort($datos);
                                            foreach ($datos as $fecha => $ctrls):
                                                ksort($ctrls);
                                                $fobj = new DateTime($fecha);
                                                $dow = (int) $fobj->format('w');
                                                $f_txt = $dias_es[$dow] . ' ' . $fobj->format('j') . ' ' . $meses_es[(int) $fobj->format('n') - 1] . ' ' . $fobj->format('Y');
                                                $first_ctrl = true;
                                                foreach ($ctrls as $ctrl => $turnos):
                                                    usort($turnos, fn($a, $b) => $orden_fn($a['puesto_solicitado']) <=> $orden_fn($b['puesto_solicitado']));
                                                    $cub_ctrl = count(array_filter($turnos, fn($t) => $t['estado'] === 'cubierto'));
                                                    $tot_ctrl = count($turnos);
                                                    $bloque_ini = $turnos[0]['bloque_ini'] ?? '';
                                                    $bloque_fin = $turnos[0]['bloque_fin'] ?? '';
                                                    ?>
                                                    <tr>
                                                        <td class="td-fecha">
                                                            <?php if ($first_ctrl): ?>
                                                                <a class="td-fecha-link"
                                                                    href="horarios.php?fecha=<?= $fecha ?>&ctrl=<?= urlencode($ctrl) ?>"><?= htmlspecialchars($f_txt) ?></a>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="td-ctrl"><?= htmlspecialchars($ctrl) ?></td>
                                                        <td class="td-horario"><?= substr($bloque_ini, 0, 5) ?> –
                                                            <?= substr($bloque_fin, 0, 5) ?>
                                                        </td>
                                                        <td>
                                                            <div class="puestos-grid">
                                                                <?php foreach ($turnos as $t):
                                                                    $es_cub = $t['estado'] === 'cubierto'; ?>
                                                                    <span class="puesto-chip <?= $es_cub ? 'cub' : 'sin' ?>">
                                                                        <?= htmlspecialchars($t['puesto_solicitado']) ?>
                                                                        <?php if ($es_cub && $t['emp_nombre']): ?>
                                                                            <span class="chip-emp">·
                                                                                <?= htmlspecialchars($t['emp_nombre']) ?></span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </td>
                                                        <td class="td-resumen">
                                                            <?php if ($cub_ctrl === $tot_ctrl): ?>
                                                                <span class="mini-ok">✓ Completo</span>
                                                            <?php elseif ($cub_ctrl === 0): ?>
                                                                <span class="mini-pend">Sin cubrir</span>
                                                            <?php else: ?>
                                                                <span class="mini-pend"><?= $cub_ctrl ?>/<?= $tot_ctrl ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php $first_ctrl = false; endforeach; endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
    <script src="js/programas.js"></script>
</body>

</html>
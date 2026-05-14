<?php
session_start();
if (empty($_SESSION['rol']) || $_SESSION['rol'] !== 'empleado') {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "turnostv");
$conn->set_charset("utf8mb4");
if ($conn->connect_error)
    die("Error: " . $conn->connect_error);

$usuario_id = $_SESSION['usuario_id'];
$stmt = $conn->prepare("SELECT id, puesto FROM empleados WHERE usuario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$empleado = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$empleado)
    die("Empleado no encontrado.");

$empleado_id = $empleado['id'];
$puesto = $empleado['puesto'];

// Mes a mostrar
$mes_offset = (int) ($_GET['mes'] ?? 0);
$primer_dia = new DateTime("first day of this month");
$primer_dia->modify("{$mes_offset} months");
$ultimo_dia = clone $primer_dia;
$ultimo_dia->modify("last day of this month");
$f_ini = $primer_dia->format('Y-m-d');
$f_fin = $ultimo_dia->format('Y-m-d');
$hoy = date('Y-m-d');

// Turnos del mes
$stmt = $conn->prepare("
    SELECT
        t.id, t.fecha, t.hora_inicio, t.hora_fin,
        t.puesto_solicitado, t.control_nombre, t.plato, t.estado,
        p.nombre AS programa,
        f.tipo AS tipo_festivo
    FROM turnos t
    LEFT JOIN programas p ON p.id = t.programa_id
    LEFT JOIN festivos f ON f.fecha = t.fecha
    WHERE t.empleado_id = ?
    AND t.fecha BETWEEN ? AND ?
    AND t.estado = 'cubierto'
    ORDER BY t.fecha, t.hora_inicio
");
$stmt->bind_param("iss", $empleado_id, $f_ini, $f_fin);
$stmt->execute();
$turnos_mes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Festivos del mes
$stmt = $conn->prepare("SELECT fecha, tipo, descripcion FROM festivos WHERE fecha BETWEEN ? AND ?");
$stmt->bind_param("ss", $f_ini, $f_fin);
$stmt->execute();
$festivos_mes = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $f)
    $festivos_mes[$f['fecha']] = $f;
$stmt->close();

// Vacaciones aprobadas que se solapan con el mes
$stmt = $conn->prepare("
    SELECT fecha_inicio, fecha_fin, motivo
    FROM vacaciones
    WHERE empleado_id = ?
    AND estado = 'aprobada'
    AND fecha_fin >= ? AND fecha_inicio <= ?
    ORDER BY fecha_inicio
");
$stmt->bind_param("iss", $empleado_id, $f_ini, $f_fin);
$stmt->execute();
$vacaciones_aprobadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// Expandir vacaciones a días individuales dentro del mes
$dias_vacaciones = [];
foreach ($vacaciones_aprobadas as $v) {
    $cur = new DateTime(max($v['fecha_inicio'], $f_ini));
    $fin = new DateTime(min($v['fecha_fin'], $f_fin));
    while ($cur <= $fin) {
        $dias_vacaciones[$cur->format('Y-m-d')] = $v['motivo'] ?? 'Vacaciones';
        $cur->modify('+1 day');
    }
}

// Agrupar turnos por fecha
$turnos_por_dia = [];
foreach ($turnos_mes as $t)
    $turnos_por_dia[$t['fecha']][] = $t;

$meses_es = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$dias_es = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$mes_ant = $mes_offset - 1;
$mes_sig = $mes_offset + 1;
$titulo_pagina = 'Mis turnos';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TurnosTV — Mis turnos</title>
    <link rel="stylesheet" href="../fragmentos/styles/base.css">
    <link rel="stylesheet" href="styles/mis_turnos.css">
</head>

<body>
    <?php include '../fragmentos/sidebar.php'; ?>
    <div class="content">
        <?php include '../fragmentos/header.php'; ?>
        <div class="body">

            <!-- Cabecera -->
            <div class="mt-header">
                <h2 class="mt-titulo">Mis turnos</h2>
                <div class="mt-nav">
                    <a href="mis_turnos.php?mes=<?= $mes_ant ?>" class="mt-arrow">←</a>
                    <span class="mt-mes"><?= $meses_es[(int) $primer_dia->format('n') - 1] ?>
                        <?= $primer_dia->format('Y') ?></span>
                    <a href="mis_turnos.php?mes=<?= $mes_sig ?>" class="mt-arrow">→</a>
                    <?php if ($mes_offset !== 0): ?>
                        <a href="mis_turnos.php" class="mt-hoy">Hoy</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Leyenda -->
            <div class="mt-leyenda">
                <span><i class="mt-i mt-i-turno"></i>Turno</span>
                <span><i class="mt-i mt-i-vac"></i>Vacaciones</span>
                <span><i class="mt-i mt-i-festivo"></i>Festivo</span>
                <span><i class="mt-i mt-i-finde"></i>Fin de semana</span>
            </div>

            <!-- Calendario -->
            <div class="mt-cal-card">
                <div class="mt-cal-grid">
                    <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $d): ?>
                        <div class="mt-dow"><?= $d ?></div>
                    <?php endforeach; ?>

                    <?php
                    $idow = (int) $primer_dia->format('N') - 1;
                    for ($v = 0; $v < $idow; $v++)
                        echo '<div class="mt-dia mt-vacio"></div>';

                    $dmes = (int) $ultimo_dia->format('j');
                    for ($nd = 1; $nd <= $dmes; $nd++):
                        $fnd = $primer_dia->format('Y-m-') . str_pad($nd, 2, '0', STR_PAD_LEFT);
                        $dow_nd = (int) (new DateTime($fnd))->format('N');
                        $festivo = $festivos_mes[$fnd] ?? null;
                        $turnos = $turnos_por_dia[$fnd] ?? [];
                        $es_vac = isset($dias_vacaciones[$fnd]);
                        $es_hoy = $fnd === $hoy;
                        $finde = $dow_nd >= 6;

                        $cls = 'mt-dia';
                        if ($es_hoy)
                            $cls .= ' mt-hoy';
                        if ($es_vac)
                            $cls .= ' mt-vacaciones';
                        elseif (!empty($turnos))
                            $cls .= ' mt-con-turno';
                        if ($festivo && $festivo['tipo'] === 'festivo')
                            $cls .= ' mt-festivo';
                        if ($festivo && $festivo['tipo'] === 'fin_de_semana')
                            $cls .= ' mt-finde';
                        if ($finde && !$festivo && !$es_vac)
                            $cls .= ' mt-finde-natural';

                        $clickable = !empty($turnos) || $es_vac;
                        ?>
                        <div class="<?= $cls ?>" <?= $clickable ? "onclick=\"abrirDetalle('" . $fnd . "')\" style=\"cursor:pointer\"" : '' ?>>
                            <div class="mt-num"><?= $nd ?></div>

                            <?php if ($festivo): ?>
                                <div class="mt-badge">
                                    <?= $festivo['tipo'] === 'festivo' ? '🎉' : '📅' ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($es_vac): ?>
                                <div class="mt-vac-chip">🏖 Vacaciones</div>
                            <?php endif; ?>

                            <?php foreach ($turnos as $t): ?>
                                <div class="mt-turno-chip">
                                    <span
                                        class="mt-chip-hora"><?= substr($t['hora_inicio'], 0, 5) ?>–<?= substr($t['hora_fin'], 0, 5) ?></span>
                                    <span
                                        class="mt-chip-prog"><?= htmlspecialchars($t['programa'] ?? $t['control_nombre'] ?? '—') ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- POPUP DETALLE DÍA -->
            <div class="ov" id="ovDetalle">
                <div class="mt-pop" onclick="event.stopPropagation()">
                    <div class="mt-pop-header">
                        <div>
                            <div class="mt-pop-fecha" id="det-fecha"></div>
                            <div class="mt-pop-subtitulo" id="det-festivo"></div>
                        </div>
                        <button onclick="cerrarDetalle()" class="mt-pop-cerrar">×</button>
                    </div>
                    <div id="det-turnos"></div>
                </div>
            </div>

        </div>
    </div>

    <script>
        const TURNOS = <?= json_encode($turnos_por_dia) ?>;
        const FESTIVOS = <?= json_encode($festivos_mes) ?>;
        const VACACIONES = <?= json_encode($dias_vacaciones) ?>;

        const DIAS_ES = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        const MESES_ES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        function abrirDetalle(fecha) {
            const turnos = TURNOS[fecha] || [];
            const festivo = FESTIVOS[fecha] || null;
            const esVac = VACACIONES[fecha] || null;

            const d = new Date(fecha + 'T00:00:00');
            const dow = d.getDay();
            const dia = d.getDate();
            const mes = d.getMonth();
            const anyo = d.getFullYear();

            document.getElementById('det-fecha').textContent =
                DIAS_ES[dow] + ', ' + dia + ' de ' + MESES_ES[mes] + ' de ' + anyo;

            const subEl = document.getElementById('det-festivo');
            if (festivo) {
                subEl.textContent = festivo.tipo === 'festivo' ? '🎉 Día festivo' : '📅 Fin de semana';
                subEl.style.display = 'block';
            } else {
                subEl.style.display = 'none';
            }

            const cont = document.getElementById('det-turnos');
            cont.innerHTML = '';

            // Mostrar vacaciones si aplica
            if (esVac) {
                const vcard = document.createElement('div');
                vcard.className = 'det-card det-card-vac';
                vcard.innerHTML = `
            <div class="det-prog" style="color:#0369a1">🏖 Vacaciones aprobadas</div>
            <div class="det-grid">
                <div class="det-item" style="grid-column:1/-1">
                    <div class="det-label">Motivo</div>
                    <div class="det-val">${esVac}</div>
                </div>
            </div>
        `;
                cont.appendChild(vcard);
            }

            // Mostrar turnos
            if (turnos.length > 0) {
                turnos.forEach(t => {
                    const card = document.createElement('div');
                    card.className = 'det-card';
                    card.innerHTML = `
                <div class="det-prog">${t.programa || '—'}</div>
                <div class="det-grid">
                    <div class="det-item">
                        <div class="det-label">Horario</div>
                        <div class="det-val">${t.hora_inicio.slice(0, 5)} – ${t.hora_fin.slice(0, 5)}</div>
                    </div>
                    <div class="det-item">
                        <div class="det-label">Control</div>
                        <div class="det-val">${t.control_nombre || '—'}</div>
                    </div>
                    <div class="det-item">
                        <div class="det-label">Plato</div>
                        <div class="det-val">${t.plato || '—'}</div>
                    </div>
                    <div class="det-item">
                        <div class="det-label">Puesto</div>
                        <div class="det-val">${t.puesto_solicitado || '—'}</div>
                    </div>
                </div>
                ${t.tipo_festivo === 'festivo' ? '<div class="det-festivo-badge">🎉 Día festivo — se computa como día extra</div>' : ''}
            `;
                    cont.appendChild(card);
                });
            }

            if (!esVac && turnos.length === 0) {
                cont.innerHTML = '<p style="color:var(--suave);font-size:13px;padding:16px 20px">Sin información para este día.</p>';
            }

            document.getElementById('ovDetalle').classList.add('open');
        }

        function cerrarDetalle() {
            document.getElementById('ovDetalle').classList.remove('open');
        }

        document.getElementById('ovDetalle').addEventListener('click', function (e) {
            if (e.target === this) cerrarDetalle();
        });
    </script>
</body>

</html>
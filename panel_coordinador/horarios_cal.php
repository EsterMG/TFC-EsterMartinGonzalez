<?php
session_start();
if (empty($_SESSION['rol']) || $_SESSION['rol'] !== 'coordinador') {
    http_response_code(403);
    exit;
}

$conn = new mysqli("localhost", "root", "", "turnostv");
$conn->set_charset("utf8mb4");

// Obtener coordinador_id
$stmt = $conn->prepare("SELECT id FROM coordinadores WHERE usuario_id=?");
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$coord = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$coord) {
    http_response_code(403);
    exit;
}
$coordinador_id = $coord['id'];

$fecha_sel = $_GET['fecha'] ?? date('Y-m-d');
$mes_offset = (int) ($_GET['mes'] ?? 0);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_sel))
    $fecha_sel = date('Y-m-d');

$hoy_str = date('Y-m-d');
$meses_es = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

$primer_dia = new DateTime("first day of this month");
$primer_dia->modify("{$mes_offset} months");
$ultimo_dia = clone $primer_dia;
$ultimo_dia->modify("last day of this month");
$f_ini = $primer_dia->format('Y-m-d');
$f_fin = $ultimo_dia->format('Y-m-d');

// Turnos del mes
$stmt = $conn->prepare("
    SELECT t.fecha, COUNT(*) AS total,
           SUM(CASE WHEN t.estado='cubierto' THEN 1 ELSE 0 END) AS cubiertos
    FROM turnos t
    WHERE t.fecha BETWEEN ? AND ?
    GROUP BY t.fecha
");
$stmt->bind_param("ss", $f_ini, $f_fin);
$stmt->execute();
$cal_data = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r)
    $cal_data[$r['fecha']] = $r;
$stmt->close();

// Festivos y fines de semana del mes
$stmt = $conn->prepare("SELECT fecha, tipo FROM festivos WHERE fecha BETWEEN ? AND ?");
$stmt->bind_param("ss", $f_ini, $f_fin);
$stmt->execute();
$especiales = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $cal_data[$r['fecha']]['tipo_especial'] = $r['tipo'];
    $especiales[$r['fecha']] = $r['tipo'];
}
$stmt->close();

// Programas por día del mes
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

$conn->close();

// Generar HTML del grid
ob_start();

foreach (['L', 'M', 'X', 'J', 'V', 'S', 'D'] as $d)
    echo "<div class='cal-dow'>{$d}</div>";

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

    // Nombres de programas del día
    $progs_dia = $progs_por_dia[$fnd] ?? [];
    $progs_html = '';
    foreach (array_unique($progs_dia) as $pn)
        $progs_html .= "<div class='cal-prog-nombre'>" . htmlspecialchars($pn) . "</div>";

    $tag = '';
    if ($tipo_e === 'festivo') {
        $tag = "<div class='cal-mini cal-fest'>🎉" . ($total > 0 ? " {$cubiertos}/{$total}" : "") . "</div>" . $progs_html;
    } elseif ($tipo_e === 'fin_de_semana') {
        $tag = "<div class='cal-mini cal-fd'>📅" . ($total > 0 ? " {$cubiertos}/{$total}" : "") . "</div>" . $progs_html;
    } elseif ($total > 0) {
        $pct = $cubiertos / $total;
        $tc = $pct >= 1 ? 'ok' : ($pct > 0 ? 'pend' : 'vacio-t');
        $tag = "<div class='cal-mini {$tc}'>{$cubiertos}/{$total}</div>" . $progs_html;
    } else {
        $tag = $progs_html;
    }

    $style = $dow_nd === 7 ? 'border-right:none' : '';
    echo "<div class='{$cls_dia}' data-fecha='{$fnd}' onclick=\"calDiaClick('{$fnd}')\" style='{$style}'>"
        . "<div class='cal-num'>{$nd}</div>{$tag}</div>";
}

$grid_html = ob_get_clean();

header('Content-Type: application/json');
echo json_encode([
    'titulo' => $meses_es[(int) $primer_dia->format('n') - 1] . ' ' . $primer_dia->format('Y'),
    'grid' => $grid_html,
    'especiales' => $especiales,
]);
<?php
/**
 * horarios_panel_solicitud.php
 * Devuelve el HTML actualizado del panel lateral de solicitud.
 * Se llama por AJAX cada 30s desde horarios.php para reflejar asignaciones.
 */
session_start();
if (empty($_SESSION['rol']) || $_SESSION['rol'] !== 'coordinador') {
    http_response_code(403);
    exit;
}

$solicitud_id = (int) ($_GET['solicitud_id'] ?? 0);
if (!$solicitud_id) {
    http_response_code(400);
    exit;
}

$conn = new mysqli("localhost", "root", "", "turnostv");
$conn->set_charset("utf8mb4");

$st = $conn->prepare("
    SELECT sp.id, sp.puesto_solicitado, sp.estado, u.nombre AS emp_nombre
    FROM solicitud_puestos sp
    LEFT JOIN empleados e ON e.id = sp.empleado_id
    LEFT JOIN usuarios  u ON u.id = e.usuario_id
    WHERE sp.solicitud_id = ?
    ORDER BY sp.id ASC
");
$st->bind_param("i", $solicitud_id);
$st->execute();
$puestos = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
$conn->close();

$total = count($puestos);
$cubiertos = count(array_filter($puestos, fn($p) => $p['estado'] === 'cubierto'));
$pct = $total > 0 ? round($cubiertos / $total * 100) : 0;
?>
<div class="sol-progreso">
    <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--suave);margin-bottom:3px">
        <span>Puestos cubiertos</span>
        <span>
            <?= $cubiertos ?>/
            <?= $total ?>
        </span>
    </div>
    <div class="sol-progreso-bar">
        <div class="sol-progreso-fill"
            style="width:<?= $pct ?>%;background:<?= $pct === 100 ? '#16A34A' : '#1e40af' ?>">
        </div>
    </div>
</div>
<div class="sol-panel-body">
    <?php foreach ($puestos as $pp):
        $cub_p = $pp['estado'] === 'cubierto';
        $tc = $cub_p ? '#1e40af' : '#92400e';
        ?>
        <div class="sol-puesto-row">
            <div>
                <div class="sol-puesto-nombre">
                    <?= htmlspecialchars($pp['puesto_solicitado']) ?>
                </div>
                <div class="sol-puesto-emp" style="color:<?= $tc ?>">
                    <?= $cub_p ? htmlspecialchars($pp['emp_nombre']) : 'Sin asignar' ?>
                </div>
            </div>
            <span
                style="width:8px;height:8px;border-radius:50%;background:<?= $cub_p ? '#16A34A' : '#D97706' ?>;flex-shrink:0;display:inline-block"></span>
        </div>
    <?php endforeach; ?>
</div>
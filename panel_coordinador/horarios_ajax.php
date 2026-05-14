<?php
session_start();
if (empty($_SESSION['rol']) || $_SESSION['rol'] !== 'coordinador') {
    http_response_code(403);
    exit;
}

$conn = new mysqli("localhost", "root", "", "turnostv");
$conn->set_charset("utf8mb4");

$accion = $_POST['accion'] ?? '';
$fecha_ctx = $_POST['fecha_ctx'] ?? '';
$tipo_dia = $_POST['tipo_dia'] ?? 'festivo';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_ctx)) {
    http_response_code(400);
    exit;
}

if ($accion === 'toggle_dia_especial') {

    $stmt = $conn->prepare("SELECT id FROM festivos WHERE fecha = ? AND tipo = ?");
    $stmt->bind_param("ss", $fecha_ctx, $tipo_dia);
    $stmt->execute();
    $stmt->store_result();
    $existe = $stmt->num_rows > 0;
    $stmt->close();

    if ($existe) {
        // Quitar
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
    } else {
        // Añadir
        $stmt = $conn->prepare("INSERT INTO festivos (fecha, tipo, descripcion) VALUES (?, ?, '')");
        $stmt->bind_param("ss", $fecha_ctx, $tipo_dia);
        $stmt->execute();
        $stmt->close();

        if ($tipo_dia === 'festivo') {
            $desc_auto = "Festivo " . $fecha_ctx;
            $stmt = $conn->prepare("
                INSERT IGNORE INTO dias_extra (empleado_id, fecha, tipo, dias, horas, descripcion, origen, fecha_turno)
                SELECT e.id, ?, 'festivo', 1,
                       0,
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
    }
}

if ($accion === 'info_programa') {
    $prog_id = (int) ($_POST['programa_id'] ?? 0);
    $empleados = [];
    if ($prog_id) {
        $stmt = $conn->prepare("
            SELECT u.nombre, e.puesto
            FROM programa_empleado pe
            JOIN empleados e ON e.id = pe.empleado_id
            JOIN usuarios u  ON u.id = e.usuario_id
            WHERE pe.programa_id = ? AND u.estado = 'activo'
            ORDER BY e.puesto, u.nombre
        ");
        $stmt->bind_param("i", $prog_id);
        $stmt->execute();
        $empleados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    echo json_encode(['empleados' => $empleados]);
    exit;
}

$conn->close();
echo json_encode(['ok' => true]);
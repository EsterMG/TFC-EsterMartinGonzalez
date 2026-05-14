<?php
/**
 * horarios_helpers.php
 * Funciones de ayuda usadas por horarios_actions.php y horarios_data.php 
 */

// Convierte un string "fecha1,fecha2,..." en array de fechas válidas
function parsearFechas(string $raw): array
{
    return array_values(array_filter(
        array_map('trim', explode(',', $raw)),
        fn($f) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)
    ));
}

// Devuelve todos los puestos activos de la base de datos
function obtenerTodosPuestos(mysqli $conn): array
{
    $stmt = $conn->prepare("
        SELECT DISTINCT e.puesto
        FROM empleados e
        JOIN usuarios u ON u.id = e.usuario_id
        WHERE u.estado = 'activo'
    ");
    $stmt->execute();
    $puestos = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'puesto');
    $stmt->close();
    return $puestos;
}

// Registra un día extra de festivo para un empleado (si no existe ya)
function registrarDiaExtraFestivo(mysqli $conn, int $empleado_id, string $fecha): void
{
    $desc = "Festivo " . $fecha;
    $stmt = $conn->prepare("
        INSERT IGNORE INTO dias_extra
            (empleado_id, fecha, tipo, dias, horas, descripcion, origen, fecha_turno)
        VALUES (?, ?, 'festivo', 1, 0, ?, 'automatico', ?)
    ");
    $stmt->bind_param("isss", $empleado_id, $fecha, $desc, $fecha);
    $stmt->execute();
    $stmt->close();
}

// Genera todas las opciones de hora para los selectores (de 00:00 a 29:45)
function opcionesHora(): string
{
    $out = '';
    for ($h = 0; $h < 30; $h++) {
        for ($m = 0; $m < 60; $m += 15) {
            $v = sprintf('%02d:%02d', $h % 24, $m);
            $lbl = ($h >= 24 ? '(+1) ' : '') . $v;
            $out .= "<option value=\"{$v}\">{$lbl}</option>";
        }
    }
    return $out;
}

function ordenPuesto(string $puesto): int
{
    static $claves = ['JEFE', 'MEZCLA', 'SONIDO', 'CCU', 'ILUMINA', 'EVS', 'MULTIPLAY', 'ROTULO', 'PROMPT', 'PRIMERA', 'CAMARA', 'AUXILIAR'];
    $n = strtoupper(strtr($puesto, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'Á' => 'A',
        'É' => 'E',
        'Í' => 'I',
        'Ó' => 'O',
        'Ú' => 'U',
        'ñ' => 'n',
        'Ñ' => 'N'
    ]));
    foreach ($claves as $i => $c) {
        if (str_contains($n, $c))
            return $i;
    }
    return 99;
}

function indicePuesto(string $puesto): string
{
    $i = ordenPuesto($puesto);
    return $i === 99 ? '99' : (string) $i;
}
<?php
session_start();

if ($_SESSION['rol'] !== 'coordinador') {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "turnostv");
$conn->set_charset("utf8mb4");

/* EDITAR */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar') {
    $uid = (int) $_POST['usuario_id'];
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $puesto = $_POST['puesto'];
    $num_empleado = trim($_POST['num_empleado']);
    $estado = $_POST['estado'];
    $vacaciones_total = (int) $_POST['vacaciones_total'];

    $check = $conn->prepare("SELECT id FROM empleados WHERE num_empleado = ? AND usuario_id != ? LIMIT 1");
    $check->bind_param("si", $num_empleado, $uid);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        $error_fila = $uid;
        $error_msg = "El número <strong>$num_empleado</strong> ya está en uso por otro empleado.";
    } else {
        $check->close();
        $stmt = $conn->prepare("
            UPDATE usuarios u JOIN empleados e ON e.usuario_id = u.id
            SET u.nombre=?, u.email=?, u.telefono=?, u.estado=?,
                e.puesto=?, e.num_empleado=?, e.vacaciones_total=?
            WHERE u.id=?
        ");
        $stmt->bind_param("ssssssii", $nombre, $email, $telefono, $estado, $puesto, $num_empleado, $vacaciones_total, $uid);
        $stmt->execute();
        $stmt->close();
        header("Location: empleados.php");
        exit;
    }
}

/* BORRAR */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'borrar') {
    $uid = (int) $_POST['usuario_id'];
    $stmt = $conn->prepare("DELETE FROM empleados WHERE usuario_id=?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id=?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->close();
    header("Location: empleados.php");
    exit;
}

/* INSERTAR */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'insertar') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $puesto = $_POST['puesto'];
    $num_empleado = trim($_POST['num_empleado']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $error_insertar = null;
    $modal_nuevo_abierto = false;

    $check = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $error_insertar = "El email <strong>$email</strong> ya está en uso por otro usuario.";
    }
    $check->close();

    if (!$error_insertar) {
        $check = $conn->prepare("SELECT id FROM empleados WHERE num_empleado = ? LIMIT 1");
        $check->bind_param("s", $num_empleado);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $error_insertar = "El número <strong>$num_empleado</strong> ya está en uso por otro empleado.";
        }
        $check->close();
    }

    if ($error_insertar) {
        $modal_nuevo_abierto = true;
    } else {
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre,email,telefono,password,rol,estado) VALUES (?,?,?,?,'empleado','activo')");
        $stmt->bind_param("ssss", $nombre, $email, $telefono, $password);
        $stmt->execute();
        $nuevo_id = $stmt->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO empleados (usuario_id,num_empleado,puesto) VALUES (?,?,?)");
        $stmt->bind_param("iss", $nuevo_id, $num_empleado, $puesto);
        $stmt->execute();
        $stmt->close();

        header("Location: empleados.php");
        exit;
    }
}

/* AÑADIR DÍA EXTRA */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'añadir_extra') {
    $empleado_id = (int) $_POST['empleado_id'];
    $fecha = $_POST['fecha'];
    $tipo = $_POST['tipo'];
    $horas = (float) str_replace(',', '.', $_POST['horas'] ?? 0);
    $dias = (float) str_replace(',', '.', $_POST['dias'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');

    $stmt = $conn->prepare("
        INSERT INTO dias_extra (empleado_id, fecha, tipo, horas, dias, descripcion, origen)
        VALUES (?, ?, ?, ?, ?, ?, 'manual')
    ");
    $stmt->bind_param("issdds", $empleado_id, $fecha, $tipo, $horas, $dias, $descripcion);
    $stmt->execute();
    $stmt->close();

    $uid_redir = $_POST['usuario_id_redir'] ?? '';
    header("Location: empleados.php?expandir=$uid_redir");
    exit;
}

/* BORRAR DÍA EXTRA */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'borrar_extra') {
    $extra_id = (int) $_POST['extra_id'];
    $stmt = $conn->prepare("DELETE FROM dias_extra WHERE id=?");
    $stmt->bind_param("i", $extra_id);
    $stmt->execute();
    $stmt->close();

    $uid_redir = $_POST['usuario_id_redir'] ?? '';
    header("Location: empleados.php?expandir=$uid_redir");
    exit;
}

/* FILTROS */
$q = $_GET['q'] ?? '';
$estado = $_GET['estado'] ?? '';
$puestos = $_GET['puesto'] ?? [];
$expandir = (int) ($_GET['expandir'] ?? 0);

/* PAGINACIÓN */
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

$sql = "
    SELECT u.id, u.nombre, u.email, u.telefono, u.foto_perfil, u.estado,
           e.id as emp_id, e.num_empleado, e.puesto,
           e.vacaciones_total, e.vacaciones_gastadas,
           (e.vacaciones_total - e.vacaciones_gastadas) as vacaciones_disponibles,
           COALESCE(SUM(d.dias), 0) as extra_dias,
           COALESCE(SUM(d.horas), 0) as extra_horas
    FROM usuarios u
    LEFT JOIN empleados e ON e.usuario_id = u.id
    LEFT JOIN dias_extra d ON d.empleado_id = e.id
    WHERE u.rol = 'empleado'
";

$params = [];
$types = "";

if (!empty($q)) {
    $sql .= " AND (u.nombre LIKE ? OR u.email LIKE ? OR u.telefono LIKE ? OR e.num_empleado LIKE ? OR e.puesto LIKE ?)";
    $s = "%$q%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
    $types .= "sssss";
}
if (!empty($estado)) {
    $sql .= " AND u.estado=?";
    $params[] = $estado;
    $types .= "s";
}

if (!empty($puestos)) {
    $in = implode(",", array_fill(0, count($puestos), "?"));
    $sql .= " AND e.puesto IN ($in)";
    foreach ($puestos as $p) {
        $params[] = $p;
        $types .= "s";
    }
}

$sql .= " GROUP BY u.id ORDER BY u.nombre ASC LIMIT $por_pagina OFFSET $offset";

$stmt = $conn->prepare($sql);
if (!empty($params))
    $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* TOTAL PAGINACIÓN */
$sql_total = "
    SELECT COUNT(DISTINCT u.id) as total
    FROM usuarios u
    LEFT JOIN empleados e ON e.usuario_id = u.id
    WHERE u.rol = 'empleado'
";

$params_total = [];
$types_total = "";

if (!empty($q)) {
    $sql_total .= " AND (u.nombre LIKE ? OR u.email LIKE ? OR u.telefono LIKE ? OR e.num_empleado LIKE ? OR e.puesto LIKE ?)";
    $s = "%$q%";
    $params_total = array_merge($params_total, [$s, $s, $s, $s, $s]);
    $types_total .= "sssss";
}
if (!empty($estado)) {
    $sql_total .= " AND u.estado=?";
    $params_total[] = $estado;
    $types_total .= "s";
}
if (!empty($puestos)) {
    $in = implode(",", array_fill(0, count($puestos), "?"));
    $sql_total .= " AND e.puesto IN ($in)";
    foreach ($puestos as $p) {
        $params_total[] = $p;
        $types_total .= "s";
    }
}

$stmt_total = $conn->prepare($sql_total);
if (!empty($params_total))
    $stmt_total->bind_param($types_total, ...$params_total);
$stmt_total->execute();
$total_empleados = $stmt_total->get_result()->fetch_assoc()['total'];

$total_paginas = ceil($total_empleados / $por_pagina);

$puestos_canonicos = ['JEFE', 'MEZCLA', 'SONIDO', 'CCU', 'ILUMINA', 'EVS', 'MULTIPLAY', 'ROTULO', 'PROMPT', 'PRIMERA', 'CAMARA', 'AUXILIAR'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados</title>
    <link rel="stylesheet" href="../fragmentos/styles/base.css">
    <link rel="stylesheet" href="styles/empleados.css">
</head>

<body>

    <?php include '../fragmentos/sidebar.php'; ?>
    <div class="content">
        <?php include '../fragmentos/header.php'; ?>
        <div class="body">

            <div class="emp-header">
                <h2 class="emp-titulo">Empleados</h2>
                <button class="btn" id="emp-btn-nuevo">+ Nuevo empleado</button>
            </div>

            <!-- FILTROS -->
            <form method="GET" class="card filters">

                <div class="filters-row">
                    <input class="input" type="text" name="q" placeholder="Buscar empleados..."
                        value="<?= htmlspecialchars($q) ?>">

                    <select name="estado" class="input">
                        <option value="">Estado</option>
                        <option value="activo" <?= $estado === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= $estado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>

                    <button type="submit" class="btn">Filtrar</button>
                    <a href="empleados.php" class="btn-outline">Limpiar</a>
                </div>

                <!-- segunda fila -->
                <div class="filters-puestos">
                    <label>
                        <input type="checkbox" name="puesto[]" value="JEFE" <?= in_array('JEFE', $puestos ?? []) ? 'checked' : '' ?>>
                        Jefe
                    </label>

                    <label>
                        <input type="checkbox" name="puesto[]" value="MEZCLA" <?= in_array('MEZCLA', $puestos ?? []) ? 'checked' : '' ?>>
                        Mezclador
                    </label>

                    <label>
                        <input type="checkbox" name="puesto[]" value="SONIDO" <?= in_array('SONIDO', $puestos ?? []) ? 'checked' : '' ?>>
                        Sonido
                    </label>

                    <label>
                        <input type="checkbox" name="puesto[]" value="CCU" <?= in_array('CCU', $puestos ?? []) ? 'checked' : '' ?>>
                        CCU
                    </label>

                    <label>
                        <input type="checkbox" name="puesto[]" value="ILUMINA" <?= in_array('ILUMINA', $puestos ?? []) ? 'checked' : '' ?>>
                        Iluminación
                    </label>

                    <label>
                        <input type="checkbox" name="puesto[]" value="EVS" <?= in_array('EVS', $puestos ?? []) ? 'checked' : '' ?>>
                        EVS
                    </label>

                    <label>
                        <input type="checkbox" name="puesto[]" value="MULTIPLAY" <?= in_array('MULTIPLAY', $puestos ?? []) ? 'checked' : '' ?>>
                        Multiplay
                    </label>

                    <label>
                        <input type="checkbox" name="puesto[]" value="ROTULO" <?= in_array('ROTULO', $puestos ?? []) ? 'checked' : '' ?>>
                        Rótulo
                    </label>

                    <label>
                        <input type="checkbox" name="puesto[]" value="PROMPT" <?= in_array('PROMPT', $puestos ?? []) ? 'checked' : '' ?>>
                        Prompt
                    </label>

                    <label>
                        <input type="checkbox" name="puesto[]" value="PRIMERA" <?= in_array('PRIMERA', $puestos ?? []) ? 'checked' : '' ?>>
                        Primera
                    </label>

                    <label>
                        <input type="checkbox" name="puesto[]" value="CAMARA" <?= in_array('CAMARA', $puestos ?? []) ? 'checked' : '' ?>>
                        Cámara
                    </label>

                    <label>
                        <input type="checkbox" name="puesto[]" value="AUXILIAR" <?= in_array('AUXILIAR', $puestos ?? []) ? 'checked' : '' ?>>
                        Auxiliar
                    </label>

                </div>

            </form>

            <!-- TABLA -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Nº Emp.</th>
                                <th>Puesto</th>
                                <th>Estado</th>
                                <th>Vacaciones</th>
                                <th>Extras</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody> <?php while ($row = $result->fetch_assoc()):
                            $bc = match ($row['estado'] ?? '') {
                                'activo' => 'badge-ok',
                                'inactivo' => 'badge-neutro',
                                default => 'badge-neutro'
                            };
                            $tiene_error = isset($error_fila) && $error_fila === $row['id'];
                            $esta_expandido = $expandir === $row['id'];

                            // Cargar días extra de este empleado
                            $extras = [];
                            if ($esta_expandido) {
                                $eq = $conn->prepare("SELECT * FROM dias_extra WHERE empleado_id = ? ORDER BY fecha DESC");
                                $eq->bind_param("i", $row['emp_id']);
                                $eq->execute();
                                $extras = $eq->get_result()->fetch_all(MYSQLI_ASSOC);
                                $eq->close();
                            }
                            ?>

                                <!-- FILA PRINCIPAL -->
                                <form method="POST">
                                    <tr
                                        class="<?= $tiene_error ? 'fila-con-error' : '' ?> <?= $esta_expandido ? 'fila-expandida' : '' ?>">
                                        <input type="hidden" name="accion" value="editar">
                                        <input type="hidden" name="usuario_id" value="<?= $row['id'] ?>">

                                        <td><img class="avatar"
                                                src="<?= !empty($row['foto_perfil']) ? htmlspecialchars($row['foto_perfil']) : '../img/default.png' ?>">
                                        </td>

                                        <td><input class="ev-input input <?= $tiene_error ? 'ev-activo' : '' ?>"
                                                name="nombre" type="text" <?= $tiene_error ? '' : 'disabled' ?>
                                                value="<?= htmlspecialchars($row['nombre'] ?? '') ?>"></td>

                                        <td><input class="ev-input input <?= $tiene_error ? 'ev-activo' : '' ?>"
                                                name="email" type="email" <?= $tiene_error ? '' : 'disabled' ?>
                                                value="<?= htmlspecialchars($row['email'] ?? '') ?>"></td>

                                        <td><input class="ev-input input <?= $tiene_error ? 'ev-activo' : '' ?>"
                                                name="telefono" type="tel" <?= $tiene_error ? '' : 'disabled' ?>
                                                value="<?= htmlspecialchars($row['telefono'] ?? '') ?>"></td>

                                        <td>
                                            <input class="ev-input input <?= $tiene_error ? 'ev-activo ev-error' : '' ?>"
                                                name="num_empleado" <?= $tiene_error ? '' : 'disabled' ?>
                                                value="<?= htmlspecialchars($row['num_empleado'] ?? '') ?>">
                                            <?php if ($tiene_error): ?>
                                                <span class="msg-error-campo"><?= $error_msg ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="ev-vista <?= $tiene_error ? 'ev-oculto' : '' ?>">
                                                <?= htmlspecialchars($row['puesto'] ?? '-') ?>
                                        </span>
                                            <select class="ev-edicion input" name="puesto" <?= $tiene_error ? 'style="display:inline-block"' : 'style="display:none"' ?>>
                                                <?php foreach ($puestos_canonicos as $pc): ?>
                                                    <option value="<?= $pc ?>" <?= ($row['puesto'] ?? '') === $pc ? 'selected' : '' ?>>
                                                        <?= $pc ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                
                                        <td>
                                            <span class="ev-vista badge <?= $bc ?> <?= $tiene_error ? 'ev-oculto' : '' ?>">
                                                <?= htmlspecialchars($row['estado'] ?? '-') ?>
                                            </span>
                                            <select class="ev-edicion input" name="estado" <?= $tiene_error ? 'style="display:inline-block"' : 'style="display:none"' ?>>
                                                <option value="activo" <?= ($row['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo
                                                </option>
                                                <option value="inactivo" <?= ($row['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>
                                                    Inactivo</option>
                                            </select>
                                        </td>
                                
                                        <!-- VACACIONES -->
                                        <td class="td-vacaciones">
                                            <span class="ev-vista">
                                                <span class="vac-disponibles"><?= $row['vacaciones_disponibles'] ?></span>
                                                <span class="vac-label"> días disp.</span>
                                            </span>
                                            <div class="ev-edicion ev-oculto vac-edicion">
                                                <div class="vac-fila">
                                                    <span class="mini-label">Total</span>
                                                    <input class="input" type="number" name="vacaciones_total" min="0" max="365"
                                                        value="<?= $row['vacaciones_total'] ?>" style="width:70px;">
                                                </div>
                                                <div class="vac-fila">
                                                    <span class="mini-label">Gastados</span>
                                                    <span class="vac-valor"><?= $row['vacaciones_gastadas'] ?> días</span>
                                                </div>
                                                <div class="vac-fila">
                                                    <span class="mini-label">Disponibles</span>
                                                    <span class="vac-valor vac-disponibles"><?= $row['vacaciones_disponibles'] ?>
                                                        días</span>
                                                </div>
                                            </div>
                                        </td>
                                
                                        <!-- EXTRAS -->
                                        <td class="td-extras">
                                            <span class="ev-vista">

                                                <?php if ($row['extra_dias'] > 0 || $row['extra_horas'] > 0): ?>
                                        
                                                    <?php if ($row['extra_dias'] > 0): ?>
                                                        <span class="extra-chip"><?= number_format($row['extra_dias'], 1) ?>d</span>
                                                    <?php endif; ?>
                                        
                                                    <?php if ($row['extra_horas'] > 0): ?>
                                                        <span class="extra-chip"><?= number_format($row['extra_horas'], 1) ?>h</span>
                                                    <?php endif; ?>
                                        
                                                <?php else: ?>
                                                    <span class="text-suave">—</span>
                                                <?php endif; ?>                                        
                                            </span>
                                        </td>
                                
                                        <!-- ACCIONES -->
                                        <td class="td-acciones">
                                            <button type="button" class="btn ev-btn-editar" <?= $tiene_error ? 'style="display:none"' : '' ?>>Editar</button>
                                            <button type="submit" class="btn btn-ok ev-btn-guardar" <?= $tiene_error ? '' : 'style="display:none"' ?>>Guardar</button>
                                            <button type="button" class="btn btn-outline ev-btn-cancelar" <?= $tiene_error ? '' : 'style="display:none"' ?>>Cancelar</button>
                                            <button type="button" class="btn btn-peligro ev-btn-borrar" <?= $tiene_error ? '' : 'style="display:none"' ?>
                                                data-id="<?= $row['id'] ?>" data-nombre="<?= htmlspecialchars($row['nombre']) ?>">
                                                Borrar
                                            </button>
                                            <a href="empleados.php?expandir=<?= $esta_expandido ? 0 : $row['id'] ?>"
                                                class="btn btn-outline btn-extras-toggle">
                                                <?= $esta_expandido ? '▲ Cerrar' : '+ Extras' ?>
                                            </a>
                                        </td>
                                    </tr>                
                                    </form>
                                

                <?php if ($esta_expandido): ?>
                <!-- FILA EXPANDIDA: historial + formulario días extra -->
                <tr class="fila-detalle">
                        <td colspan="10">
                            <div class="detalle-box">
                
                                <div class="detalle-header">
                                    <strong>Días extra — <?= htmlspecialchars($row['nombre']) ?></strong>
                                </div>
                
                                <!-- Tabla historial -->
                                <?php if (!empty($extras)): ?>
                                    <table class="table table-extras">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Tipo</th>
                                                <th>Días</th>
                                                <th>Horas</th>
                                                <th>Descripción</th>
                                                <th>Origen</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($extras as $ex): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($ex['fecha']) ?></td>
                                                    <td>
                                                        <span class="badge <?= $ex['tipo'] === 'festivo' ? 'badge-aviso' : 'badge-neutro' ?>">
                                                            <?= htmlspecialchars($ex['tipo']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= $ex['dias'] > 0 ? number_format($ex['dias'], 1) : '—' ?></td>
                                                    <td><?= $ex['horas'] > 0 ? number_format($ex['horas'], 1) : '—' ?></td>
                                                    <td><?= htmlspecialchars($ex['descripcion']) ?></td>
                                                    <td><span class="badge badge-neutro"><?= $ex['origen'] ?></span></td>
                                                    <td>
                                                        <?php if ($ex['origen'] === 'manual'): ?>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="accion" value="borrar_extra">
                                                                <input type="hidden" name="extra_id" value="<?= $ex['id'] ?>">
                                                                <input type="hidden" name="usuario_id_redir" value="<?= $row['id'] ?>">
                                                                <button type="submit" class="btn btn-peligro btn-sm">✕</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="text-suave" style="margin:8px 0 16px;">No hay días extra registrados.</p>
                                <?php endif; ?>
                                
                                                <!-- Formulario añadir extra -->
                                                <div class="detalle-form-titulo">Añadir día extra</div>
                                                <form method="POST" class="form-extra">
                                                    <input type="hidden" name="accion" value="añadir_extra">
                                                    <input type="hidden" name="empleado_id" value="<?= $row['emp_id'] ?>">
                                    <input type="hidden" name="usuario_id_redir" value="<?= $row['id'] ?>">
                                
                                    <div class="form-extra-grid">
                                        <div>
                                            <label class="mini-label">Fecha *</label>
                                            <input class="input" type="date" name="fecha" required value="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div>
                                            <label class="mini-label">Tipo *</label>
                                            <select class="input" name="tipo" required>
                                                <option value="dia_extra">Día extra</option>
                                                <option value="hora_extra">Hora extra</option>
                                                <option value="festivo">Festivo trabajado</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mini-label">Días</label>
                                            <input class="input" type="number" name="dias" min="0" step="0.5" value="0" style="width:80px;">
                                        </div>
                                        <div>
                                            <label class="mini-label">Horas</label>
                                            <input class="input" type="number" name="horas" min="0" step="0.5" value="0" style="width:80px;">
                                        </div>
                                        <div style="flex:2">
                                            <label class="mini-label">Descripción</label>
                                            <input class="input" type="text" name="descripcion" placeholder="Ej: Festivo 1 de mayo" style="width:100%;">
                                        </div>
                                        <div style="align-self:flex-end;">
                                            <button type="submit" class="btn">Añadir</button>
                                        </div>
                                    </div>
                                </form>
                                
                                </div>
                                </td>
                                /tr>
                                <?php endif; ?>
                                <?php endwhile; ?>
                                </tbody>
                                </table>
                                <!-- Paginación -->
                                <?php if ($total_paginas > 1): ?>
                                <div class="paginacion">
                                
                                    <?php if ($pagina > 1): ?>
                                    <a class="pagina" href="?pagina=<?= $pagina - 1 ?>&q=<?= urlencode($q) ?>&estado=<?= urlencode($estado) ?>">‹</a>
                                    <?php endif; ?>
                                
                                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                    <a class="pagina <?= $i == $pagina ? 'activa' : '' ?>"
                                        href="?pagina=<?= $i ?>&q=<?= urlencode($q) ?>&estado=<?= urlencode($estado) ?>">
                                        <?= $i ?>
                                    </a>
                                    <?php endfor; ?>
                                
                                    <?php if ($pagina < $total_paginas): ?>
                                    <a class="pagina" href="?pagina=<?= $pagina + 1 ?>&q=<?= urlencode($q) ?>&estado=<?= urlencode($estado) ?>">›</a>
                                    <?php endif; ?>
                                
                                </div>
                                <?php endif; ?>
                                </div>
                                </div>
                                </div>
                
                <!-- MODAL NUEVO EMPLEADO -->
                <div id="emp-modal-nuevo" class="emp-modal-overlay" style="display:none;">
                    <div class="emp-modal-box">
                        <div class="emp-modal-header">
                            <h3>Nuevo empleado</h3>
                            <button type="button" class="emp-modal-close" id="emp-modal-close">&times;</button>
                        </div>
                        <form method="POST" id="form-nuevo-empleado" novalidate>
                            <input type="hidden" name="accion" value="insertar">
                
                            <?php if (!empty($error_insertar)): ?>
                                <div class="aviso-error" style="margin-bottom:1rem;">⚠️ <?= $error_insertar ?></div>
                            <?php endif; ?>
                
                            <div class="emp-form-grid">
                                <div class="emp-form-group">
                                    <label>Nombre completo *</label>
                                    <input class="input" type="text" name="nombre" required placeholder="Ej: Ana García"
                                        value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                                </div>
                                <div class="emp-form-group">
                                    <label>Email *</label>
                                    <input
                                        class="input <?= !empty($error_insertar) && str_contains($error_insertar, 'email') ? 'ev-error' : '' ?>"
                                        type="email" name="email" required placeholder="ana@turnostv.es"
                                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                                <div class="emp-form-group">
                                    <label>Teléfono</label>
                                    <input class="input" type="tel" name="telefono" placeholder="600 000 000"
                                        value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                                </div>
                                <div class="emp-form-group">
                                    <label>Nº Empleado *</label>
                                    <input
                                        class="input <?= !empty($error_insertar) && str_contains($error_insertar, 'número') ? 'ev-error' : '' ?>"
                                        type="text" name="num_empleado" required placeholder="EMP-001"
                                        value="<?= htmlspecialchars($_POST['num_empleado'] ?? '') ?>">
                                </div>
                                <div class="emp-form-group">
                                    <label>Puesto *</label>
                                    <select class="input" name="puesto" required>
                                        <option value="">— Seleccionar —</option>
                                        <?php foreach ($puestos_canonicos as $pc): ?>
                                            <option value="<?= $pc ?>" <?= ($_POST['puesto'] ?? '') === $pc ? 'selected' : '' ?>><?= $pc ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="emp-form-group">
                                    <label>Contraseña inicial *</label>
                                    <input class="input" type="password" name="password" required autocomplete="new-password"
                                        placeholder="Mínimo 6 caracteres" minlength="6">
                                </div>
                            </div>
                            <div class="emp-modal-footer">
                                <button type="button" class="btn-outline" id="emp-modal-cancel">Cancelar</button>
                                <button type="submit" class="btn">Crear empleado</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- MODAL BORRAR -->
                <div id="emp-modal-borrar" class="emp-modal-overlay" style="display:none;">
                    <div class="emp-modal-box emp-modal-box--sm">
                        <div class="emp-modal-header">
                            <h3>¿Borrar empleado?</h3>
                            <button type="button" class="emp-modal-close" id="emp-borrar-close">&times;</button>
                        </div>
                        <p class="emp-modal-desc" id="emp-borrar-texto"></p>
                        <form method="POST">
                            <input type="hidden" name="accion" value="borrar">
                            <input type="hidden" name="usuario_id" id="emp-borrar-id">
                            <div class="emp-modal-footer">
                                <button type="button" class="btn-outline" id="emp-borrar-cancel">Cancelar</button>
                                <button type="submit" class="btn btn-peligro">Sí, borrar</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <script src="js/empleados.js"></script>
                </body>
                
                </html>
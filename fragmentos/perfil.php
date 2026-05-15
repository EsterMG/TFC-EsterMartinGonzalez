<?php
session_start();

if (empty($_SESSION['rol']) || empty($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

$conn = new mysqli("localhost", "root", "", "turnostv");
$conn->set_charset("utf8mb4");

$ok = isset($_GET['ok']) ? "Perfil actualizado correctamente" : "";
$error = "";

/* GUARDAR PERFIL */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $actual = $_POST['actual'] ?? '';
    $nueva = $_POST['nueva'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';
    $foto_sql = null;
    $password_sql = null;

    /* FOTO */
    if (!empty($_FILES['foto']['name'])) {

        $nombreFoto = time() . "_" . basename($_FILES['foto']['name']);
        $ruta_fisica = "../uploads/" . $nombreFoto; // donde se guarda el archivo
        $foto_sql = "uploads/" . $nombreFoto;    // lo que se guarda en BD

        if (!is_dir("../uploads")) {
            mkdir("../uploads", 0777, true);
        }

        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $ruta_fisica)) {
            $foto_sql = null; // si falla la subida, no guardar nada
        }
    }

    /* PASSWORD */
    if (!empty($actual) || !empty($nueva) || !empty($confirmar)) {

        $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $hash = $stmt->get_result()->fetch_assoc()['password'];
        $stmt->close();

        if (!password_verify($actual, $hash)) {
            $error = "La contraseña actual es incorrecta.";
        } elseif ($nueva !== $confirmar) {
            $error = "Las contraseñas no coinciden.";
        } else {
            $password_sql = password_hash($nueva, PASSWORD_DEFAULT);
        }
    }

    /* UPDATE */
    if (empty($error)) {
        if ($foto_sql && $password_sql) {
            $stmt = $conn->prepare("
                UPDATE usuarios 
                SET nombre=?, telefono=?, foto_perfil=?, password=?
                WHERE id=?
            ");
            $stmt->bind_param("ssssi", $nombre, $telefono, $foto_sql, $password_sql, $usuario_id);

        } elseif ($foto_sql) {
            $stmt = $conn->prepare("
                UPDATE usuarios 
                SET nombre=?, telefono=?, foto_perfil=?
                WHERE id=?
            ");
            $stmt->bind_param("sssi", $nombre, $telefono, $foto_sql, $usuario_id);

        } elseif ($password_sql) {
            $stmt = $conn->prepare("
                UPDATE usuarios 
                SET nombre=?, telefono=?, password=?
                WHERE id=?
            ");
            $stmt->bind_param("sssi", $nombre, $telefono, $password_sql, $usuario_id);

        } else {
            $stmt = $conn->prepare("
                UPDATE usuarios 
                SET nombre=?, telefono=?
                WHERE id=?
            ");
            $stmt->bind_param("ssi", $nombre, $telefono, $usuario_id);
        }

        $stmt->execute();
        $stmt->close();

        // Refrescar sesión con los nuevos datos
        $_SESSION['nombre'] = $nombre;

        // Si se subió foto nueva, actualizarla también en sesión
        if ($foto_sql) {
            $_SESSION['foto_perfil'] = $foto_sql;
        }
        header("Location: perfil.php?ok=1");
        exit;
    }
}

/* DATOS PERFIL */
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.nombre,
        u.email,
        u.telefono,
        u.foto_perfil,
        u.rol,
        e.num_empleado,
        e.puesto,
        e.vacaciones_total AS dias_vacaciones
    FROM usuarios u
    LEFT JOIN empleados e ON e.usuario_id = u.id
    WHERE u.id = ?
");

$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$perfil = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();

$modo_editar = isset($_GET['edit']) && $_GET['edit'] == 1;
$es_empleado = in_array($rol, ['empleado', 'coordinador']);
$puesto = $perfil['puesto'] ?? ucfirst($rol);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TurnosTV — Mi perfil</title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="styles/base.css">
    <link rel="stylesheet" href="styles/perfil.css">
</head>

<body>

    <?php include 'sidebar.php'; ?>
    <div class="content">
        <?php
        $titulo_pagina = "Mi perfil";
        include 'header.php';
        ?>

        <div class="body">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Mi perfil</span>

                    <?php if (!$modo_editar): ?>
                        <a href="perfil.php?edit=1" class="btn">Editar perfil</a>
                    <?php else: ?>
                        <a href="perfil.php" class="btn-outline">Cancelar</a>
                    <?php endif; ?>
                </div>

                <?php if ($error): ?>
                    <p class="mensaje-error"><?= $error ?></p>
                <?php endif; ?>
                <?php if ($ok): ?>
                    <p class="mensaje-ok"><?= $ok ?></p>
                <?php endif; ?>

                <!-- VER -->
                <?php if (!$modo_editar): ?>
                    <div class="perfil-card">
                        <div class="perfil-top">
                            <img src="<?= !empty($perfil['foto_perfil']) ? '../' . $perfil['foto_perfil'] : '../img/default.png' ?>"
                                class="perfil-avatar-lg" onerror="this.src='../img/default.png'">

                            <h2><?= htmlspecialchars($perfil['nombre']) ?></h2>
                            <p><?= htmlspecialchars($perfil['email']) ?></p>
                        </div>

                        <div class="perfil-grid">
                            <?php if (!empty($perfil['num_empleado'])): ?>
                                <div class="perfil-item">
                                    <span>Nº empleado</span>
                                    <strong><?= htmlspecialchars($perfil['num_empleado']) ?></strong>
                                </div>
                            <?php endif; ?>

                            <div class="perfil-item">
                                <span>Puesto</span>
                                <strong><?= htmlspecialchars($puesto) ?></strong>
                            </div>

                            <div class="perfil-item">
                                <span>Teléfono</span>
                                <strong><?= htmlspecialchars($perfil['telefono'] ?? '-') ?></strong>
                            </div>

                            <?php if ($es_empleado): ?>
                                <div class="perfil-item">
                                    <span>Vacaciones</span>
                                    <strong><?= htmlspecialchars($perfil['dias_vacaciones'] ?? 0) ?> días</strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- EDITAR -->
                <?php else: ?>
                    <form method="POST" enctype="multipart/form-data" class="perfil-form">

                        <div class="form-field">
                            <label>Foto</label>
                            <input type="file" name="foto">
                        </div>

                        <div class="form-field">
                            <label>Nombre</label>
                            <input type="text" name="nombre" value="<?= htmlspecialchars($perfil['nombre']) ?>">
                        </div>

                        <div class="form-field">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" value="<?= htmlspecialchars($perfil['telefono'] ?? '') ?>">
                        </div>

                        <div class="form-field">
                            <label>Contraseña actual</label>
                            <input type="password" name="actual">
                        </div>

                        <div class="form-field">
                            <label>Nueva contraseña</label>
                            <input type="password" name="nueva">
                        </div>

                        <div class="form-field">
                            <label>Confirmar contraseña</label>
                            <input type="password" name="confirmar">
                        </div>

                        <button class="btn-send">Guardar cambios</button>

                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="js/perfil.js"></script>

</body>

</html>
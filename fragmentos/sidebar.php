<?php
/* fragmentos/sidebar.php */

/* SESIÓN  */
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* Seguridad: si no hay sesión, fuera */
if (empty($_SESSION['rol']) || empty($_SESSION['usuario_id'])) {
  header("Location: ../login.php");
  exit;
}

/* DATOS USUARIO (BD)  */
$conn = new mysqli("localhost", "root", "", "turnostv");
$conn->set_charset("utf8mb4");

$usuario_id = $_SESSION['usuario_id'];

$stmt = $conn->prepare("SELECT email, foto_perfil FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();


/* DATOS SESIÓN  */
$rol = $_SESSION['rol'];
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$puesto = $_SESSION['puesto'] ?? ucfirst($rol);

/* Nombre corto */
$partes = explode(' ', $nombre);
$nombre_corto = $partes[0]; // primer nombre
$apellido = $partes[1] ?? ''; // primer apellido completo

// Para el logout: nombre + primer apellido
$nombre_logout = trim($partes[0] . ' ' . ($partes[1] ?? ''));

/* UI SEGÚN ROL  */
$clase_badge = match ($rol) {
  'administrador' => 'admin',
  'coordinador' => 'coord',
  'director' => 'director',
  default => 'empleado',
};

$etiqueta_rol = match ($rol) {
  'administrador' => 'Administrador',
  'coordinador' => 'Coordinador',
  'director' => 'Director',
  default => 'Empleado',
};

/* Página activa */
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
  <div class="logo">TurnosTV</div>

  <div class="rol-badge <?= $clase_badge ?>">
    <?= $etiqueta_rol ?>
  </div>

  <!-- MINI PERFIL USUARIO  -->
  <div class="sidebar-profile">
    <img src="<?= !empty($usuario['foto_perfil']) ? '../' . $usuario['foto_perfil'] : '../img/default.png' ?>"
      class="sidebar-avatar" onerror="this.src='../img/default.png'">

    <div class="sidebar-user-info">
      <div class="sidebar-user-name"><?= htmlspecialchars($partes[0]) ?></div>
      <div class="sidebar-user-name" style="font-weight:400;opacity:0.75;"><?= htmlspecialchars($apellido) ?></div>
    </div>
  </div>

  <nav>
    <?php if ($rol === 'administrador'): ?>

      <div class="nav-section">Sistema</div>
      <a href="panel_admin.php" class="nav-link <?= $pagina_actual === 'panel_admin.php' ? 'active' : '' ?>">Inicio</a>
      <a href="../usuarios.php" class="nav-link <?= $pagina_actual === 'usuarios.php' ? 'active' : '' ?>">Usuarios</a>
      <a href="../programas.php"
        class="nav-link <?= $pagina_actual === '../programas.php' ? 'active' : '' ?>">Programas</a>
      <a href="../turnos.php" class="nav-link <?= $pagina_actual === 'turnos.php' ? 'active' : '' ?>">Turnos</a>
      <a href="../empleados.php"
        class="nav-link <?= $pagina_actual === '../empleados.php' ? 'active' : '' ?>">Empleados</a>
      <a href="../configuracion.php"
        class="nav-link <?= $pagina_actual === 'configuracion.php' ? 'active' : '' ?>">Configuración</a>

    <?php elseif ($rol === 'coordinador'): ?>

      <div class="nav-section">Gestión</div>
      <a href="../panel_coordinador/panel_coordinador.php"
        class="nav-link <?= $pagina_actual === 'panel_coordinador.php' ? 'active' : '' ?>">Inicio</a>
      <a href="../panel_coordinador/programas.php"
        class="nav-link <?= $pagina_actual === '../panel_coordinador/programas.php' ? 'active' : '' ?>">Programas</a>
      <a href="../panel_coordinador/horarios.php"
        class="nav-link <?= $pagina_actual === '../panel_coordinador/horarios.php' ? 'active' : '' ?>">Horarios</a>
      <a href="../panel_coordinador/empleados.php"
        class="nav-link <?= $pagina_actual === '../panel_coordinador/empleados.php' ? 'active' : '' ?>">Empleados</a>
      <a href="../panel_coordinador/peticiones.php"
        class="nav-link <?= $pagina_actual === '../panel_coordinador/peticiones.php' ? 'active' : '' ?>">Peticiones</a>

    <?php elseif ($rol === 'director'): ?>

      <div class="nav-section">Mi programa</div>
      <a href="../panel_director/panel_director.php"
        class="nav-link <?= $pagina_actual === '../panel_director/panel_director.php' ? 'active' : '' ?>">Inicio</a>
      <a href="../panel_director/turnos_programa.php"
        class="nav-link <?= $pagina_actual === '../panel_director/turnos_programa.php' ? 'active' : '' ?>">Turnos del
        programa</a>
      <a href="../panel_director/mis_peticiones.php"
        class="nav-link <?= $pagina_actual === '../panel_director/mis_peticiones.php' ? 'active' : '' ?>">Mis
        peticiones</a>

    <?php else: ?>

      <div class="nav-section">Mi espacio</div>
      <a href="../panel_empleado/panel_empleado.php"
        class="nav-link <?= $pagina_actual === '../panel_empleado/panel_empleado.php' ? 'active' : '' ?>">Inicio</a>
      <a href="../panel_empleado/mis_turnos.php"
        class="nav-link <?= $pagina_actual === '../panel_empleado/mis_turnos.php' ? 'active' : '' ?>">Mis turnos</a>
      <a href="../panel_empleado/mis_solicitudes.php"
        class="nav-link <?= $pagina_actual === '../panel_empleado/mis_solicitudes.php' ? 'active' : '' ?>">Mis
        solicitudes</a>

    <?php endif; ?>

    <div class="nav-section">Cuenta</div>
    <a href="../fragmentos/perfil.php"
      class="nav-link <?= $pagina_actual === '../fragmentos/perfil.php' ? 'active' : '' ?>">Mi perfil</a>

  </nav>

  <!-- USUARIO + LOGOUT  -->
  <div class="user-area">
    <div>
      <div class="user-name"><?= htmlspecialchars($nombre_logout) ?></div>
      <div class="user-role"><?= htmlspecialchars($puesto) ?></div>
    </div>

    <button id="btn-logout" class="logout" title="Cerrar sesión"
      data-nombre="<?= htmlspecialchars($partes[0]) ?>">⏻</button>
  </div>
</aside>

<!-- MODAL LOGOUT -->
<div id="modal-logout" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-icono">⏻</div>
    <h3 class="modal-titulo">Cerrar sesión</h3>
    <p class="modal-desc">
      ¿<strong><?= htmlspecialchars($partes[0]) ?> seguro que quieres salir?</strong>.
    </p>
    <div class="modal-acciones">
      <button class="btn-modal-cancelar">Cancelar</button>
      <a href="../logout.php" class="btn-modal-confirmar">Cerrar sesión</a>
    </div>
  </div>
</div>

<script src="/mi-proyectoTFC-Ester/fragmentos/js/sidebar.js"></script>
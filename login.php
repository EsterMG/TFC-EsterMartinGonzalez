<?php
session_start();

// Si ya hay sesión activa, redirigir directamente al panel correspondiente
if (!empty($_SESSION['rol'])) {
  switch ($_SESSION['rol']) {
    case 'administrador':
      header("Location: panel_admin.php");
      exit;
    case 'coordinador':
      header("Location: panel_coordinador\panel_coordinador.php");
      exit;
    case 'director':
      header("Location: panel_director\panel_director.php");
      exit;
    default:
      header("Location: panel_empleado\panel_empleado.php");
      exit;
  }
}

$mensaje = "";

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "turnostv";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
  die("Conexión fallida: " . $conn->connect_error);
}

// Solo ejecutar si se ha enviado el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $email = trim($_POST["email"] ?? "");
  $pass = trim($_POST["password"] ?? "");

  if (empty($email) || empty($pass)) {
    $mensaje = "<p class='mensaje-error'>Todos los campos son obligatorios.</p>";

  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $mensaje = "<p class='mensaje-error'>El correo electrónico no es válido.</p>";

  } else {

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? AND estado = 'activo'");

    if (!$stmt) {
      die("<p>Error en la consulta: " . $conn->error . "</p>");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $user = $resultado->fetch_assoc();

    // Comprobar si el usuario existe
    if ($user && password_verify($pass, $user["password"])) {

      // Guardar datos en sesión 
      $_SESSION['usuario_id'] = $user['id'];
      $_SESSION['nombre'] = $user['nombre'];
      $_SESSION['email'] = $user['email'];
      $_SESSION['rol'] = $user['rol'];

      // Si el rol es empleado, buscar también su puesto
      if ($user['rol'] === 'empleado') {
        $stmt2 = $conn->prepare("SELECT puesto FROM empleados WHERE usuario_id = ?");
        $stmt2->bind_param("i", $user['id']);
        $stmt2->execute();
        $res2 = $stmt2->get_result()->fetch_assoc();
        $_SESSION['puesto'] = $res2['puesto'] ?? 'Empleado';
        $stmt2->close();
      }

      // Redirigir según el rol
      switch ($user['rol']) {
        case 'administrador':
          $mensaje = "<p class='mensaje-error'>El panel de administrador está en construcción.</p>";
          // header("Location: panel_admin.php"); exit;
          break;
        case 'coordinador':
          header("Location: panel_coordinador/panel_coordinador.php");
          exit;
        case 'director':
          header("Location: panel_director/panel_director.php");
          exit;
        default:
          header("Location: panel_empleado/panel_empleado.php");
          exit;
      }

    } else {
      $mensaje = "<p class='mensaje-error'>Email o contraseña incorrectos, o usuario bloqueado.</p>";
    }

    $stmt->close();
  }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TurnosTV — Inicia sesión</title>
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="fragmentos/styles/base.css">
  <link rel="stylesheet" href="login.css">
</head>

<body>

  <header class="login-header">
    <div class="login-brand">
      <div class="login-logo">TurnosTV</div>
      <div class="login-divider"></div>
      <div>
        <h1 class="login-title">Gestión de turnos para televisión</h1>
        <p class="login-desc">Asigna técnicos, gestiona turnos por programa y consulta tu horario semanal de un vistazo.
        </p>
      </div>
    </div>

  </header>

  <div class="login-body">
    <div class="login-box">
      <img src="img/favicon.png" alt="Logo TurnosTV" class="login-logo-img">
      <h2 class="login-box-title">Bienvenido</h2>
      <p class="login-box-sub">Introduce tus datos de usuario para acceder</p>

      <?php if (!empty($mensaje))
        echo $mensaje; ?>

      <form action="login.php" method="POST">
        <div class="form-field">
          <label for="email">EMAIL</label>
          <input type="email" name="email" id="email" placeholder="tu@email.com" autofocus>
        </div>
        <div class="form-field">
          <label for="password">CONTRASEÑA</label>
          <input type="password" name="password" id="password" placeholder="**********">
        </div>
        <button type="submit" class="btn-send">Entrar →</button>
      </form>
      <div class="login-footer-info">
        Si no dispones de usuario para acceder, habla con tu responsable.
      </div>
      <div class="login-credit">
        Trabajo Final de Grado DAW de Ester Martín González
      </div>
    </div>

  </div>
  <footer class="site-footer">
    <div>TurnosTV © 2026</div>
    <div>Trabajo Final de Curso DAW</div>
    <div>Ester Martín González</div>
  </footer>
</body>

</html>
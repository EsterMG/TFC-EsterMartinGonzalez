<?php
if (session_status() === PHP_SESSION_NONE)
  session_start();

$nombre_completo = $_SESSION['nombre'] ?? 'Usuario';
$nombre_corto = explode(' ', $nombre_completo)[0];

$dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
$meses = [
  'enero',
  'febrero',
  'marzo',
  'abril',
  'mayo',
  'junio',
  'julio',
  'agosto',
  'septiembre',
  'octubre',
  'noviembre',
  'diciembre'
];

$subtitulo = $subtitulo ?? "Hola, {$nombre_corto} — " .
  $dias[(int) date('w')] . ', ' . date('j') . ' de ' .
  $meses[(int) date('n') - 1];
?>

<header class="header">

  <button class="btn-menu" id="btn-menu" title="Menú">☰</button>

  <div>
    <div class="page-title"><?= $titulo_pagina ?? 'Panel' ?></div>
    <div class="page-subtitle"><?= htmlspecialchars($subtitulo) ?></div>
  </div>

  <?php if (!empty($boton_cabecera)): ?>
    <div class="header-actions">
      <?php if (!empty($boton_cabecera['href'])): ?>
        <a href="<?= htmlspecialchars($boton_cabecera['href']) ?>" class="btn">
          <?= htmlspecialchars($boton_cabecera['texto']) ?>
        </a>
      <?php else: ?>
        <button class="btn" <?= !empty($boton_cabecera['onclick']) ? 'onclick="' . $boton_cabecera['onclick'] . '"' : '' ?>>
          <?= htmlspecialchars($boton_cabecera['texto']) ?>
        </button>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</header>

<!-- Overlay para cerrar sidebar en móvil -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>
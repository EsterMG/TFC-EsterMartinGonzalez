/* programas.js */

function toggleProg(pid) {
  const el = document.getElementById("prog-" + pid);
  el.classList.toggle("abierto");
}
function irAFecha(fecha) {
  window.location.href = "horarios.php?fecha=" + fecha;
}

document.addEventListener("DOMContentLoaded", () => {
  const input = document.getElementById("fotoInput");
  const preview = document.getElementById("previewFoto");

  if (!input || !preview) return;

  input.addEventListener("change", function () {
    const file = this.files[0];

    if (!file) return;

    // validar tipo
    if (!file.type.startsWith("image/")) {
      alert("Solo se permiten imágenes");
      input.value = "";
      return;
    }

    // validar tamaño
    const maxSize = 2 * 1024 * 1024;

    if (file.size > maxSize) {
      alert("La imagen supera 2MB");
      input.value = "";
      return;
    }

    const reader = new FileReader();

    reader.onload = function (e) {
      preview.src = e.target.result;
    };

    reader.onerror = function () {
      alert("Error al cargar la imagen");
    };

    reader.readAsDataURL(file);
  });
});

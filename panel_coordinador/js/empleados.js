document.addEventListener("DOMContentLoaded", function () {
  /* Edición inline */
  document.querySelectorAll(".ev-btn-editar").forEach((btn) => {
    btn.addEventListener("click", () => {
      const row = btn.closest("tr");

      row.querySelectorAll(".ev-input").forEach((el) => {
        el.disabled = false;
        el.classList.add("ev-activo");
      });

      row
        .querySelectorAll(".ev-vista")
        .forEach((el) => (el.style.display = "none"));
      row.querySelectorAll(".ev-edicion").forEach((el) => {
        el.style.display = "block";
        el.classList.remove("ev-oculto");
      });

      btn.style.display = "none";
      row.querySelector(".ev-btn-guardar").style.display = "inline-block";
      row.querySelector(".ev-btn-cancelar").style.display = "inline-block";
      row.querySelector(".ev-btn-borrar").style.display = "inline-block";
    });
  });

  document.querySelectorAll(".ev-btn-cancelar").forEach((btn) => {
    btn.addEventListener("click", () => (location.href = "empleados.php"));
  });

  /* MODAL NUEVO*/
  const modalNuevo = document.getElementById("emp-modal-nuevo");
  const btnNuevo = document.getElementById("emp-btn-nuevo");
  const btnCerrarNuevo = document.getElementById("emp-modal-close");
  const btnCancelarNuevo = document.getElementById("emp-modal-cancel");

  btnNuevo.addEventListener("click", () => (modalNuevo.style.display = "flex"));
  btnCerrarNuevo.addEventListener(
    "click",
    () => (modalNuevo.style.display = "none"),
  );
  btnCancelarNuevo.addEventListener(
    "click",
    () => (modalNuevo.style.display = "none"),
  );
  modalNuevo.addEventListener("click", (e) => {
    if (e.target === modalNuevo) modalNuevo.style.display = "none";
  });

  // Reabrir modal si hubo error al insertar
  if (document.querySelector(".emp-modal-box .aviso-error")) {
    modalNuevo.style.display = "flex";
  }

  /* MODAL BORRAR */
  const modalBorrar = document.getElementById("emp-modal-borrar");
  const inputBorrarId = document.getElementById("emp-borrar-id");
  const textoBorrar = document.getElementById("emp-borrar-texto");

  document.querySelectorAll(".ev-btn-borrar").forEach((btn) => {
    btn.addEventListener("click", () => {
      inputBorrarId.value = btn.dataset.id;
      textoBorrar.textContent = `¿Seguro que quieres borrar a ${btn.dataset.nombre}? Esta acción no se puede deshacer.`;
      modalBorrar.style.display = "flex";
    });
  });

  document
    .getElementById("emp-borrar-close")
    .addEventListener("click", () => (modalBorrar.style.display = "none"));
  document
    .getElementById("emp-borrar-cancel")
    .addEventListener("click", () => (modalBorrar.style.display = "none"));
  modalBorrar.addEventListener("click", (e) => {
    if (e.target === modalBorrar) modalBorrar.style.display = "none";
  });

  /* VALIDACIÓN MODAL NUEVO */
  const formNuevo = document.getElementById("form-nuevo-empleado");
  const camposRequeridos = [
    { name: "nombre", msg: "El nombre no puede estar vacío." },
    { name: "email", msg: "El email no puede estar vacío." },
    {
      name: "num_empleado",
      msg: "El número de empleado no puede estar vacío.",
    },
    { name: "puesto", msg: "Selecciona un puesto." },
    { name: "password", msg: "La contraseña no puede estar vacía." },
  ];

  function mostrarError(campo, mensaje) {
    campo.classList.add("ev-error");
    let span = campo.parentElement.querySelector(".error-inline");
    if (!span) {
      span = document.createElement("span");
      span.className = "error-inline";
      campo.parentElement.appendChild(span);
    }
    span.textContent = mensaje;
  }

  function quitarError(campo) {
    campo.classList.remove("ev-error");
    const span = campo.parentElement.querySelector(".error-inline");
    if (span) span.remove();
  }

  camposRequeridos.forEach(({ name }) => {
    const campo = formNuevo
      ? formNuevo.querySelector(`[name="${name}"]`)
      : null;
    if (!campo) return;
    campo.addEventListener("input", () => quitarError(campo));
    campo.addEventListener("change", () => quitarError(campo));
  });

  if (formNuevo) {
    formNuevo.addEventListener("submit", (e) => {
      let valido = true;

      camposRequeridos.forEach(({ name, msg }) => {
        const campo = formNuevo.querySelector(`[name="${name}"]`);
        if (!campo) return;
        const vacio =
          campo.value.trim() === "" ||
          (campo.tagName === "SELECT" && campo.value === "");
        if (vacio) {
          e.preventDefault();
          mostrarError(campo, msg);
          valido = false;
        } else {
          quitarError(campo);
        }
      });

      const emailCampo = formNuevo.querySelector('[name="email"]');
      if (
        emailCampo &&
        emailCampo.value.trim() &&
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailCampo.value.trim())
      ) {
        e.preventDefault();
        mostrarError(emailCampo, "Introduce un email válido.");
      }

      const pwdCampo = formNuevo.querySelector('[name="password"]');
      if (pwdCampo && pwdCampo.value.length > 0 && pwdCampo.value.length < 6) {
        e.preventDefault();
        mostrarError(
          pwdCampo,
          "La contraseña debe tener al menos 6 caracteres.",
        );
      }
    });
  }

  /* Escape */
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      modalNuevo.style.display = "none";
      modalBorrar.style.display = "none";
    }
  });
});

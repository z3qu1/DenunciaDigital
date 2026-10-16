(() => {
  let modal = null;
  let destino = '';
  let anterior = null;

  function crear() {
    modal = document.createElement('div');
    modal.className = 'logout-fondo';
    modal.hidden = true;
    modal.innerHTML =
      '<div class="logout-caja" role="dialog" aria-modal="true" aria-labelledby="logoutTitulo">' +
        '<h2 id="logoutTitulo">¿Cerrar sesión?</h2>' +
        '<p>Tendrás que iniciar sesión de nuevo para volver a entrar.</p>' +
        '<div class="logout-botones">' +
          '<button type="button" class="logout-cancelar">Cancelar</button>' +
          '<button type="button" class="logout-confirmar">Sí, cerrar sesión</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modal);

    modal.querySelector('.logout-cancelar').addEventListener('click', cerrar);
    modal.querySelector('.logout-confirmar').addEventListener('click', () => {
      window.location.href = destino;
    });
    modal.addEventListener('click', (e) => { if (e.target === modal) cerrar(); });
  }

  function abrir(url) {
    if (!modal) crear();
    destino = url;
    anterior = document.activeElement;
    modal.hidden = false;
    modal.querySelector('.logout-cancelar').focus();
  }

  function cerrar() {
    if (!modal) return;
    modal.hidden = true;
    if (anterior) anterior.focus();
  }

  document.addEventListener('click', (e) => {
    const enlace = e.target.closest('a[href$="logout.php"]');
    if (!enlace) return;
    e.preventDefault();
    abrir(enlace.href);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal && !modal.hidden) cerrar();
  });
})();
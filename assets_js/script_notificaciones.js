(() => {
  const URL = '/PROYECTO_PAGE/reportes/notificaciones.php';
  const URL_MIS = '/PROYECTO_PAGE/reportes/mis_reporte.php';
  const INTERVALO = 15000;

  const cont = document.getElementById('campana');
  if (!cont) return;

  let csrf = '';
  let ultimaFirma = '';

  cont.innerHTML =
    '<button type="button" class="camp-btn" aria-label="Notificaciones" aria-expanded="false">' +
      '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
        '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 21a2 2 0 0 0 4 0"/></svg>' +
      '<span class="camp-num" hidden>0</span>' +
    '</button>' +
    '<div class="camp-panel" hidden>' +
      '<div class="camp-cab"><strong>Notificaciones</strong>' +
        '<button type="button" class="camp-todas">Marcar todas como leídas</button></div>' +
      '<ul class="camp-lista"></ul>' +
    '</div>';

  const btn = cont.querySelector('.camp-btn');
  const num = cont.querySelector('.camp-num');
  const panel = cont.querySelector('.camp-panel');
  const lista = cont.querySelector('.camp-lista');
  const todas = cont.querySelector('.camp-todas');

  const esc = (t) => String(t).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function pintar(d) {
    csrf = d.csrf;
    num.hidden = d.no_leidas === 0;
    num.textContent = d.no_leidas > 9 ? '9+' : d.no_leidas;
    todas.hidden = d.no_leidas === 0;

    lista.innerHTML = d.items.length
      ? d.items.map((n) =>
          '<li><a href="' + URL_MIS + '#r' + n.reporte + '" class="camp-item' + (n.leida ? '' : ' nueva') +
          '" data-id="' + n.id + '"><span>' + esc(n.mensaje) + '</span><small>' + esc(n.cuando) + '</small></a></li>'
        ).join('')
      : '<li class="camp-vacio">No tienes notificaciones.</li>';
  }

  async function cargar() {
    try {
      const r = await fetch(URL, { cache: 'no-store', credentials: 'same-origin' });
      if (!r.ok) return;
      const d = await r.json();
      if (!d.ok) return;
      const firma = JSON.stringify(d);
      if (firma === ultimaFirma) return;
      ultimaFirma = firma;
      pintar(d);
    } catch (_) { /* sin conexión: se reintenta */ }
  }

  async function marcar(id) {
    const datos = new URLSearchParams({ csrf: csrf, id: id || 0 });
    await fetch(URL, { method: 'POST', body: datos, credentials: 'same-origin' });
    ultimaFirma = '';
    await cargar();
  }

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const abierto = panel.hidden;
    panel.hidden = !abierto;
    btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
  });

  document.addEventListener('click', (e) => {
    if (!cont.contains(e.target)) { panel.hidden = true; btn.setAttribute('aria-expanded', 'false'); }
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') panel.hidden = true; });

  todas.addEventListener('click', () => marcar(0));

  lista.addEventListener('click', (e) => {
    const a = e.target.closest('.camp-item');
    if (!a) return;
    // Marca como leída y sigue al enlace
    e.preventDefault();
    const destino = a.href;
    marcar(a.dataset.id).finally(() => { window.location.href = destino; });
  });

  cargar();
  setInterval(() => { if (!document.hidden) cargar(); }, INTERVALO);
})();
(() => {
  const URL_DATOS = '/PROYECTO_PAGE/administrador/dashboard/dashboard.php?datos=1';
  const INTERVALO = 5000;

  let DATA = null;
  let ultimo = '';
  let temporizador = null;

  const COLORES_TIPO = {
    'Seguridad': '#f87171', 'Basura': '#34d399', 'Alumbrado': '#fbbf24',
    'Ruidos': '#a78bfa', 'Servicios básicos': '#38bdf8', 'Vías y transporte': '#fb923c'
  };
  const ETIQUETA_ESTADO = { pendiente: 'Pendiente', proceso: 'En proceso', resuelto: 'Resuelto' };

  const $  = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];
  const esc = (t) => String(t).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* ===================== MENÚ Y ENCABEZADO ===================== */
  function iniciarMenu() {
    const app = $('#app'), btn = $('#menuBtn'), overlay = $('#overlay');
    if (!btn) return;
    const abrir = (v) => {
      app.classList.toggle('menu-open', v);
      btn.setAttribute('aria-expanded', v ? 'true' : 'false');
    };
    btn.addEventListener('click', () => abrir(!app.classList.contains('menu-open')));
    overlay.addEventListener('click', () => abrir(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') abrir(false); });
    $$('.menu-link').forEach((a) => a.addEventListener('click', () => abrir(false)));
    $$('.menu-link').forEach((a) => {
      if (!a.getAttribute('href').startsWith('#')) return;
      a.addEventListener('click', () => {
        $$('.menu-link').forEach((l) => l.classList.remove('active'));
        a.classList.add('active');
      });
    });
  }

  function iniciarEncabezado() {
    const fecha = $('#fecha');
    if (fecha) fecha.textContent = new Date().toLocaleDateString('es-BO',
      { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    const saludo = $('#saludo');
    if (saludo) {
      const h = new Date().getHours();
      saludo.textContent = (h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches') + ', Administrador 👋';
    }
    const anio = $('#anio');
    if (anio) anio.textContent = new Date().getFullYear();

    const notif = $('#notifBtn');
    if (notif) notif.addEventListener('click', () => $('#notifDot')?.classList.add('off'));

    const titulo = $('.page-title');
    if (titulo && !$('#vivo')) {
      const v = document.createElement('small');
      v.id = 'vivo';
      v.className = 'vivo';
      v.textContent = '● Conectando...';
      titulo.appendChild(v);
    }
  }

  function marcarVivo(ok) {
    const v = $('#vivo');
    if (!v) return;
    v.classList.toggle('off', !ok);
    v.textContent = ok
      ? '● En vivo · actualizado ' + new Date().toLocaleTimeString('es-BO')
      : '● Sin conexión · reintentando...';
  }

  /* ===================== CONTADORES ===================== */
  function contar(el, meta) {
    if (!el) return;
    const desde = parseInt(el.dataset.valor || '0', 10);
    el.dataset.valor = meta;
    if (desde === meta) { el.textContent = meta.toLocaleString('es-BO'); return; }
    const dur = 800, ini = performance.now();
    (function paso(ahora) {
      const p = Math.min((ahora - ini) / dur, 1);
      el.textContent = Math.round(desde + (meta - desde) * (1 - Math.pow(1 - p, 3))).toLocaleString('es-BO');
      if (p < 1) requestAnimationFrame(paso);
    })(ini);
  }

  function pintarKpis(k) {
    contar($('#kTotal'), k.total);
    contar($('#kPend'),  k.pendientes);
    contar($('#kRes'),   k.resueltas);
    contar($('#kUsu'),   k.usuarios);
    const hoy = $('#kHoy');
    if (hoy) hoy.textContent = 'Hoy: ' + k.hoy + (k.hoy === 1 ? ' nueva' : ' nuevas');
    const pct = $('#kPct');
    if (pct) pct.textContent = (k.total ? Math.round((k.resueltas / k.total) * 100) : 0) + '% del total';
    const pill = $('#pillPend');
    if (pill) pill.textContent = k.pendientes;
  }

  /* ===================== GRÁFICO DE LÍNEA ===================== */
  function dibujarLinea() {
    const svg = $('#lineChart'), etiquetas = $('#lineLabels');
    if (!svg) return;

    const { valores, etiquetas: dias } = DATA.semana;
    const W = 600, H = 220, PAD = 20;
    const max = Math.max(...valores, 1) * 1.15;
    const paso = (W - PAD * 2) / (valores.length - 1);

    const pts = valores.map((v, i) => [PAD + i * paso, H - PAD - (v / max) * (H - PAD * 2)]);
    const linea = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
    const area = linea + ' L' + pts[pts.length - 1][0] + ' ' + (H - PAD) + ' L' + pts[0][0] + ' ' + (H - PAD) + ' Z';

    let html = '<defs><linearGradient id="gArea" x1="0" y1="0" x2="0" y2="1">' +
      '<stop offset="0%" stop-color="#38bdf8" stop-opacity=".35"/>' +
      '<stop offset="100%" stop-color="#38bdf8" stop-opacity="0"/></linearGradient></defs>';
    for (let i = 0; i < 4; i++) {
      const y = PAD + i * ((H - PAD * 2) / 3);
      html += '<line class="grid-line" x1="' + PAD + '" x2="' + (W - PAD) + '" y1="' + y + '" y2="' + y + '"/>';
    }
    html += '<path id="areaPath" d="' + area + '" fill="url(#gArea)" stroke="none" opacity="0"/>';
    html += '<path id="linePath" class="line-path" d="' + linea + '" fill="none"/>';
    pts.forEach((p, i) => {
      html += '<circle class="line-dot" cx="' + p[0] + '" cy="' + p[1] + '" r="5" opacity="0" ' +
        'style="transition: opacity .4s ' + (0.5 + i * 0.07) + 's, r .2s">' +
        '<title>' + dias[i] + ': ' + valores[i] + ' denuncias</title></circle>';
    });
    svg.innerHTML = html;

    const path = $('#linePath');
    const largo = path.getTotalLength();
    path.style.strokeDasharray = largo;
    path.style.strokeDashoffset = largo;
    path.getBoundingClientRect();
    path.style.transition = 'stroke-dashoffset 1s cubic-bezier(.2,.8,.2,1)';
    path.style.strokeDashoffset = 0;

    const areaEl = $('#areaPath');
    areaEl.style.transition = 'opacity .8s .4s';
    requestAnimationFrame(() => { areaEl.style.opacity = 1; });
    $$('.line-dot', svg).forEach((d) => requestAnimationFrame(() => { d.style.opacity = 1; }));

    if (etiquetas) etiquetas.innerHTML = dias.map((d) => '<span>' + d + '</span>').join('');
  }

  /* ===================== DONA ===================== */
  function dibujarDona() {
    const svg = $('#donut'), leyenda = $('#donutLegend'), totalEl = $('#donutTotal');
    if (!svg) return;

    const r = 70, C = 2 * Math.PI * r;
    const total = DATA.estados.reduce((s, e) => s + e.valor, 0);
    const base = total || 1;
    let acumulado = 0;

    let html = '<circle cx="90" cy="90" r="' + r + '" stroke="#0f1b26"/>';
    DATA.estados.forEach((e, i) => {
      const largo = (e.valor / base) * C;
      html += '<circle class="seg" cx="90" cy="90" r="' + r + '" stroke="' + e.color + '" ' +
        'stroke-dasharray="0 ' + C + '" stroke-dashoffset="' + (-acumulado) + '" ' +
        'data-len="' + largo + '" data-c="' + C + '" style="transition-delay:' + (i * 0.12) + 's"/>';
      acumulado += largo;
    });
    svg.innerHTML = html;

    requestAnimationFrame(() => {
      $$('.seg', svg).forEach((s) => {
        const len = parseFloat(s.dataset.len);
        s.style.strokeDasharray = (len > 0 ? Math.max(len - 3, 0.1) : 0) + ' ' + s.dataset.c;
      });
    });

    contar(totalEl, total);
    if (leyenda) leyenda.innerHTML = DATA.estados.map((e) =>
      '<li><i style="background:' + e.color + '"></i>' + esc(e.nombre) + '<b>' + e.valor + '</b></li>').join('');
  }

  /* ===================== BARRAS ===================== */
  function dibujarBarras() {
    const cont = $('#barChart');
    if (!cont) return;
    if (!DATA.tipos.length) { cont.innerHTML = '<p class="empty">Aún no hay denuncias.</p>'; return; }
    const max = Math.max(...DATA.tipos.map((t) => t.valor), 1);

    cont.innerHTML = DATA.tipos.map((t) =>
      '<div class="bar-row"><span>' + esc(t.nombre) + '</span>' +
      '<div class="bar-track"><div class="bar-fill" data-w="' + ((t.valor / max) * 100).toFixed(1) + '"></div></div>' +
      '<b>' + t.valor + '</b></div>').join('');

    requestAnimationFrame(() => {
      $$('.bar-fill', cont).forEach((b, i) => {
        b.style.transitionDelay = (i * 0.07) + 's';
        b.style.width = b.dataset.w + '%';
      });
    });
  }

  /* ===================== ACTIVIDAD ===================== */
  function dibujarActividad() {
    const ul = $('#activity');
    if (!ul) return;
    ul.innerHTML = DATA.actividad.length
      ? DATA.actividad.map((a) => '<li><strong>' + esc(a.texto) + '</strong><small>' + esc(a.cuando) + '</small></li>').join('')
      : '<li><strong>Sin actividad todavía</strong></li>';
  }

  /* ===================== TABLA + BUSCADOR ===================== */
  function dibujarTabla() {
    const tbody = $('#tablaBody'), vacio = $('#vacio'), input = $('#buscador');
    if (!tbody || !DATA) return;

    const q = (input ? input.value : '').trim().toLowerCase();
    const filas = DATA.recientes.filter((r) =>
      !q || (r.tipo + ' ' + r.lugar + ' ' + r.estado + ' ' + r.id).toLowerCase().includes(q));

    tbody.innerHTML = filas.map((r) => {
      const color = COLORES_TIPO[r.tipo] || '#38bdf8';
      return '<tr>' +
        '<td>#' + r.id + '</td>' +
        '<td><span class="tipo-cell"><i style="color:' + color + ';background:' + color + '"></i>' + esc(r.tipo) + '</span></td>' +
        '<td>' + esc(r.lugar) + '</td>' +
        '<td>' + esc(r.fecha) + '</td>' +
        '<td><span class="badge ' + esc(r.estado) + '">' + (ETIQUETA_ESTADO[r.estado] || esc(r.estado)) + '</span></td>' +
      '</tr>';
    }).join('');

    if (vacio) {
      vacio.textContent = DATA.recientes.length
        ? 'No se encontraron denuncias con esa búsqueda.'
        : 'Aún no hay denuncias registradas.';
      vacio.hidden = filas.length > 0;
    }
  }

  function iniciarBuscador() {
    const input = $('#buscador');
    if (input) input.addEventListener('input', dibujarTabla);
  }

  /* ===================== SINCRONIZACIÓN CON LA BASE ===================== */
  function mostrarAviso(texto) {
    let aviso = $('#avisoSync');
    if (!texto) { if (aviso) aviso.remove(); return; }
    if (!aviso) {
      aviso = document.createElement('p');
      aviso.id = 'avisoSync';
      aviso.className = 'error-msg';
      $('#content').prepend(aviso);
    }
    aviso.textContent = texto;
  }

  async function sincronizar() {
    try {
      const res = await fetch(URL_DATOS, { cache: 'no-store', credentials: 'same-origin' });
      if (res.status === 401) {
        mostrarAviso('Tu sesión de administrador expiró. Inicia sesión de nuevo.');
        marcarVivo(false);
        return;
      }
      const texto = await res.text();
      let datos;
      try { datos = JSON.parse(texto); }
      catch (_) {
        console.error('Respuesta no válida:', texto);
        mostrarAviso('El servidor devolvió una respuesta inesperada (mira F12 → Consola).');
        marcarVivo(false);
        return;
      }
      if (!datos.ok) { mostrarAviso(datos.mensaje || 'No se pudieron cargar los datos.'); marcarVivo(false); return; }

      mostrarAviso('');
      marcarVivo(true);

      const firma = JSON.stringify(datos);
      if (firma === ultimo) return;
      ultimo = firma;
      DATA = datos;

      pintarKpis(datos.kpis);
      dibujarLinea();
      dibujarDona();
      dibujarBarras();
      dibujarActividad();
      dibujarTabla();
    } catch (err) {
      console.error(err);
      marcarVivo(false);
    }
  }

  function programar() {
    clearInterval(temporizador);
    temporizador = setInterval(() => { if (!document.hidden) sincronizar(); }, INTERVALO);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) sincronizar(); });
  }

  /* ===================== ARRANQUE ===================== */
  async function iniciar() {
    iniciarMenu();
    iniciarEncabezado();
    iniciarBuscador();
    await sincronizar();
    programar();
  }

  document.addEventListener('DOMContentLoaded', iniciar);
})();
(() => {
  /* ===================== DATOS DE EJEMPLO =====================
     Más adelante se pueden reemplazar con datos reales de la base
     (por ejemplo, un archivo PHP que devuelva JSON). */
  const DATA = {
    semana: {
      etiquetas: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
      valores:   [14, 22, 17, 31, 26, 38, 29]
    },
    estados: [
      { nombre: 'Resueltas',  valor: 176, color: '#34d399' },
      { nombre: 'En proceso', valor: 30,  color: '#fbbf24' },
      { nombre: 'Pendientes', valor: 42,  color: '#f87171' }
    ],
    tipos: [
      { nombre: 'Seguridad',         valor: 68 },
      { nombre: 'Basura',            valor: 54 },
      { nombre: 'Alumbrado',         valor: 41 },
      { nombre: 'Ruidos',            valor: 33 },
      { nombre: 'Servicios básicos', valor: 29 },
      { nombre: 'Vías y transporte', valor: 23 }
    ],
    actividad: [
      { texto: 'Nueva denuncia de Seguridad',       cuando: 'Hace 4 minutos' },
      { texto: 'Denuncia #243 marcada como resuelta', cuando: 'Hace 32 minutos' },
      { texto: 'Se registró un nuevo usuario',      cuando: 'Hace 1 hora' },
      { texto: 'Respuesta enviada en denuncia #240', cuando: 'Hace 3 horas' },
      { texto: 'Nueva denuncia de Alumbrado',       cuando: 'Hace 5 horas' }
    ],
    recientes: [
      { id: 248, tipo: 'Seguridad',         lugar: 'Av. 6 de Marzo, plaza',   fecha: '15/10/2026', estado: 'pendiente' },
      { id: 247, tipo: 'Basura',            lugar: 'Calle 3, zona norte',     fecha: '15/10/2026', estado: 'proceso'   },
      { id: 246, tipo: 'Alumbrado',         lugar: 'Pasaje Los Pinos',        fecha: '14/10/2026', estado: 'resuelto'  },
      { id: 245, tipo: 'Ruidos',            lugar: 'Av. Juan Pablo II',       fecha: '14/10/2026', estado: 'pendiente' },
      { id: 244, tipo: 'Servicios básicos', lugar: 'Calle 5, manzana 12',     fecha: '13/10/2026', estado: 'proceso'   },
      { id: 243, tipo: 'Vías y transporte', lugar: 'Esquina Av. Cívica',      fecha: '13/10/2026', estado: 'resuelto'  }
    ]
  };

  const COLORES_TIPO = {
    'Seguridad': '#f87171', 'Basura': '#34d399', 'Alumbrado': '#fbbf24',
    'Ruidos': '#a78bfa', 'Servicios básicos': '#38bdf8', 'Vías y transporte': '#fb923c'
  };
  const ETIQUETA_ESTADO = { pendiente: 'Pendiente', proceso: 'En proceso', resuelto: 'Resuelto' };

  const $  = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

  /* ===================== CARGA DE COMPONENTES ===================== */
  async function cargarComponentes() {
    const slots = $$('[data-component]');
    slots.forEach((s) => { s.innerHTML = '<p class="loading-msg">Cargando...</p>'; });

    await Promise.all(slots.map(async (slot) => {
      const archivo = slot.dataset.component;
      try {
        const res = await fetch(archivo, { cache: 'no-cache' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        slot.innerHTML = await res.text();
      } catch (err) {
        slot.innerHTML =
          '<p class="error-msg">No se pudo cargar <strong>' + archivo + '</strong>. ' +
          'Abre esta página desde un servidor (por ejemplo http://localhost/...) y no con doble clic.</p>';
        console.error('Error al cargar el componente', archivo, err);
      }
    }));
  }

  /* ===================== MENÚ LATERAL (móvil) ===================== */
  function iniciarMenu() {
    const app = $('#app');
    const btn = $('#menuBtn');
    const overlay = $('#overlay');
    if (!btn) return;

    const abrir = (v) => {
      app.classList.toggle('menu-open', v);
      btn.setAttribute('aria-expanded', v ? 'true' : 'false');
    };

    btn.addEventListener('click', () => abrir(!app.classList.contains('menu-open')));
    overlay.addEventListener('click', () => abrir(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') abrir(false); });
    $$('.menu-link').forEach((a) => a.addEventListener('click', () => abrir(false)));

    // Resalta el enlace del menú que apunta a una sección de esta página
    $$('.menu-link').forEach((a) => {
      if (!a.getAttribute('href').startsWith('#')) return;
      a.addEventListener('click', () => {
        $$('.menu-link').forEach((l) => l.classList.remove('active'));
        a.classList.add('active');
      });
    });
  }

  /* ===================== ENCABEZADO ===================== */
  function iniciarEncabezado() {
    const fecha = $('#fecha');
    if (fecha) {
      fecha.textContent = new Date().toLocaleDateString('es-BO', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
      });
    }

    const saludo = $('#saludo');
    if (saludo) {
      const h = new Date().getHours();
      const t = h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
      saludo.textContent = t + ', Administrador 👋';
    }

    const anio = $('#anio');
    if (anio) anio.textContent = new Date().getFullYear();

    const notif = $('#notifBtn');
    if (notif) {
      notif.addEventListener('click', () => {
        const punto = $('#notifDot');
        if (punto) punto.classList.add('off');
      });
    }
  }

  /* ===================== CONTADORES ANIMADOS ===================== */
  function animarContadores() {
    $$('[data-count]').forEach((el) => {
      const meta = parseInt(el.dataset.count, 10) || 0;
      const dur = 1400;
      const ini = performance.now();

      function paso(ahora) {
        const p = Math.min((ahora - ini) / dur, 1);
        const suave = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(meta * suave).toLocaleString('es-BO');
        if (p < 1) requestAnimationFrame(paso);
      }
      requestAnimationFrame(paso);
    });
  }

  /* ===================== GRÁFICO DE LÍNEA ===================== */
  function dibujarLinea() {
    const svg = $('#lineChart');
    const etiquetas = $('#lineLabels');
    if (!svg) return;

    const { valores, etiquetas: dias } = DATA.semana;
    const W = 600, H = 220, PAD = 20;
    const max = Math.max(...valores) * 1.15;
    const paso = (W - PAD * 2) / (valores.length - 1);

    const pts = valores.map((v, i) => [PAD + i * paso, H - PAD - (v / max) * (H - PAD * 2)]);
    const linea = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
    const area = linea + ' L' + pts[pts.length - 1][0] + ' ' + (H - PAD) + ' L' + pts[0][0] + ' ' + (H - PAD) + ' Z';

    let html =
      '<defs><linearGradient id="gArea" x1="0" y1="0" x2="0" y2="1">' +
      '<stop offset="0%" stop-color="#38bdf8" stop-opacity=".35"/>' +
      '<stop offset="100%" stop-color="#38bdf8" stop-opacity="0"/></linearGradient></defs>';

    for (let i = 0; i < 4; i++) {
      const y = PAD + i * ((H - PAD * 2) / 3);
      html += '<line class="grid-line" x1="' + PAD + '" x2="' + (W - PAD) + '" y1="' + y + '" y2="' + y + '"/>';
    }

    html += '<path id="areaPath" d="' + area + '" fill="url(#gArea)" opacity="0"/>';
    html += '<path id="linePath" class="line-path" d="' + linea + '" fill="none"/>';
    pts.forEach((p, i) => {
      html += '<circle class="line-dot" cx="' + p[0] + '" cy="' + p[1] + '" r="5" opacity="0" ' +
              'style="transition: opacity .4s ' + (0.9 + i * 0.1) + 's, r .2s">' +
              '<title>' + dias[i] + ': ' + valores[i] + ' denuncias</title></circle>';
    });
    svg.innerHTML = html;

    // Animación de trazo
    const path = $('#linePath');
    const largo = path.getTotalLength();
    path.style.strokeDasharray = largo;
    path.style.strokeDashoffset = largo;
    path.getBoundingClientRect();
    path.style.transition = 'stroke-dashoffset 1.4s cubic-bezier(.2,.8,.2,1)';
    path.style.strokeDashoffset = 0;

    const areaEl = $('#areaPath');
    areaEl.style.transition = 'opacity 1s .6s';
    requestAnimationFrame(() => { areaEl.style.opacity = 1; });
    $$('.line-dot', svg).forEach((d) => requestAnimationFrame(() => { d.style.opacity = 1; }));

    if (etiquetas) etiquetas.innerHTML = dias.map((d) => '<span>' + d + '</span>').join('');
  }

  /* ===================== GRÁFICO DE DONA ===================== */
  function dibujarDona() {
    const svg = $('#donut');
    const leyenda = $('#donutLegend');
    const totalEl = $('#donutTotal');
    if (!svg) return;

    const r = 70, C = 2 * Math.PI * r;
    const total = DATA.estados.reduce((s, e) => s + e.valor, 0);
    let acumulado = 0;

    let html = '<circle cx="90" cy="90" r="' + r + '" stroke="#0f1b26"/>';
    DATA.estados.forEach((e, i) => {
      const largo = (e.valor / total) * C;
      html += '<circle class="seg" cx="90" cy="90" r="' + r + '" stroke="' + e.color + '" ' +
              'stroke-dasharray="0 ' + C + '" stroke-dashoffset="' + (-acumulado) + '" ' +
              'data-len="' + largo + '" data-c="' + C + '" stroke-linecap="butt" ' +
              'style="transition-delay:' + (i * 0.15) + 's"/>';
      acumulado += largo;
    });
    svg.innerHTML = html;

    requestAnimationFrame(() => {
      $$('.seg', svg).forEach((s) => {
        const gap = 3;
        s.style.strokeDasharray = Math.max(s.dataset.len - gap, 0) + ' ' + s.dataset.c;
      });
    });

    if (totalEl) {
      const dur = 1200, ini = performance.now();
      (function paso(ahora) {
        const p = Math.min((ahora - ini) / dur, 1);
        totalEl.textContent = Math.round(total * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(paso);
      })(ini);
    }

    if (leyenda) {
      leyenda.innerHTML = DATA.estados.map((e) =>
        '<li><i style="background:' + e.color + '"></i>' + e.nombre + '<b>' + e.valor + '</b></li>'
      ).join('');
    }
  }

  /* ===================== BARRAS POR TIPO ===================== */
  function dibujarBarras() {
    const cont = $('#barChart');
    if (!cont) return;
    const max = Math.max(...DATA.tipos.map((t) => t.valor));

    cont.innerHTML = DATA.tipos.map((t) =>
      '<div class="bar-row"><span>' + t.nombre + '</span>' +
      '<div class="bar-track"><div class="bar-fill" data-w="' + ((t.valor / max) * 100).toFixed(1) + '"></div></div>' +
      '<b>' + t.valor + '</b></div>'
    ).join('');

    requestAnimationFrame(() => {
      $$('.bar-fill', cont).forEach((b, i) => {
        b.style.transitionDelay = (i * 0.09) + 's';
        b.style.width = b.dataset.w + '%';
      });
    });
  }

  /* ===================== ACTIVIDAD ===================== */
  function dibujarActividad() {
    const ul = $('#activity');
    if (!ul) return;
    ul.innerHTML = DATA.actividad.map((a) =>
      '<li><strong>' + a.texto + '</strong><small>' + a.cuando + '</small></li>'
    ).join('');
  }

  /* ===================== TABLA + BUSCADOR ===================== */
  function dibujarTabla(filtro = '') {
    const tbody = $('#tablaBody');
    const vacio = $('#vacio');
    if (!tbody) return;

    const q = filtro.trim().toLowerCase();
    const filas = DATA.recientes.filter((r) =>
      !q || (r.tipo + ' ' + r.lugar + ' ' + r.estado + ' ' + r.id).toLowerCase().includes(q)
    );

    tbody.innerHTML = filas.map((r, i) =>
      '<tr class="row-in" style="animation-delay:' + (i * 60) + 'ms">' +
        '<td>#' + r.id + '</td>' +
        '<td><span class="tipo-cell"><i style="color:' + COLORES_TIPO[r.tipo] + ';background:' + COLORES_TIPO[r.tipo] + '"></i>' + r.tipo + '</span></td>' +
        '<td>' + r.lugar + '</td>' +
        '<td>' + r.fecha + '</td>' +
        '<td><span class="badge ' + r.estado + '">' + ETIQUETA_ESTADO[r.estado] + '</span></td>' +
      '</tr>'
    ).join('');

    if (vacio) vacio.hidden = filas.length > 0;
  }

  function iniciarBuscador() {
    const input = $('#buscador');
    if (!input) return;
    input.addEventListener('input', () => dibujarTabla(input.value));
  }

  /* ===================== ARRANQUE ===================== */
  async function iniciar() {
    await cargarComponentes();

    iniciarMenu();
    iniciarEncabezado();
    animarContadores();
    dibujarLinea();
    dibujarDona();
    dibujarBarras();
    dibujarActividad();
    dibujarTabla();
    iniciarBuscador();
  }

  document.addEventListener('DOMContentLoaded', iniciar);
})();
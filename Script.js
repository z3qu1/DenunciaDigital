document.addEventListener('DOMContentLoaded', () => {
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];

  /* Menú móvil */
  const burger = $('#burger'), nav = $('#nav');
  const closeMenu = () => { nav.classList.remove('open'); burger.classList.remove('open'); burger.setAttribute('aria-expanded', 'false'); };
  burger.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    burger.classList.toggle('open', open);
    burger.setAttribute('aria-expanded', open);
  });
  $$('a', nav).forEach(a => a.addEventListener('click', closeMenu));

  /* Header y botón subir */
  const header = $('#header'), top = $('#top');
  const onScroll = () => {
    header.classList.toggle('scrolled', scrollY > 60);
    top.classList.toggle('show', scrollY > 500);
  };
  addEventListener('scroll', onScroll, { passive: true }); onScroll();
  top.addEventListener('click', () => scrollTo({ top: 0, behavior: 'smooth' }));

  /* Carrusel */
  const slides = $$('.slide'), dotsBox = $('#dots'), bar = $('#bar');
  const DURATION = 6000;
  let current = 0, timer, start;

  slides.forEach((_, i) => {
    const d = document.createElement('button');
    d.className = 'dot'; d.setAttribute('aria-label', 'Ir a la diapositiva ' + (i + 1));
    d.addEventListener('click', () => { go(i); });
    dotsBox.appendChild(d);
  });
  const dots = $$('.dot');

  function go(n) {
    current = (n + slides.length) % slides.length;
    slides.forEach((s, i) => s.classList.toggle('active', i === current));
    dots.forEach((d, i) => d.classList.toggle('active', i === current));
    restart();
  }
  function tick(t) {
    if (!start) start = t;
    const p = Math.min((t - start) / DURATION, 1);
    bar.style.width = p * 100 + '%';
    if (p >= 1) go(current + 1); else timer = requestAnimationFrame(tick);
  }
  function restart() { cancelAnimationFrame(timer); start = null; bar.style.width = '0'; timer = requestAnimationFrame(tick); }

  $('#next').addEventListener('click', () => go(current + 1));
  $('#prev').addEventListener('click', () => go(current - 1));
  document.addEventListener('keydown', e => { if (e.key === 'ArrowRight') go(current + 1); if (e.key === 'ArrowLeft') go(current - 1); });

  // Deslizar con el dedo
  const hero = $('#inicio'); let x0 = null;
  hero.addEventListener('touchstart', e => x0 = e.touches[0].clientX, { passive: true });
  hero.addEventListener('touchend', e => {
    if (x0 === null) return;
    const dx = e.changedTouches[0].clientX - x0;
    if (Math.abs(dx) > 50) go(current + (dx < 0 ? 1 : -1));
    x0 = null;
  });
  // Pausa cuando la pestaña no está visible
  document.addEventListener('visibilitychange', () => document.hidden ? cancelAnimationFrame(timer) : restart());
  go(0);

  /* Pestañas de comunidad */
  $$('.tab').forEach(tab => tab.addEventListener('click', () => {
    $$('.tab').forEach(t => t.classList.remove('active'));
    $$('.panel').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    $('#' + tab.dataset.tab).classList.add('active');
  }));

  /* Animación al hacer scroll */
  const io = new IntersectionObserver((entries) => {
    entries.forEach((en, i) => {
      if (en.isIntersecting) {
        setTimeout(() => en.target.classList.add('in'), (i % 3) * 120);
        io.unobserve(en.target);
      }
    });
  }, { threshold: .15 });
  $$('.reveal').forEach(el => io.observe(el));

  /* Formulario (demostración: sin servidor) */
  const form = $('#form'), msg = $('#msg');
  form.addEventListener('submit', e => {
    e.preventDefault();
    const tipo = form.tipo.value, detalle = form.detalle.value.trim();
    msg.classList.remove('err');
    if (!tipo || detalle.length < 10) {
      msg.classList.add('err');
      msg.textContent = 'Elige un tipo y describe el hecho con al menos 10 caracteres.';
      return;
    }
    const code = 'DD-' + Math.random().toString(36).slice(2, 8).toUpperCase();
    msg.textContent = 'Denuncia enviada. Tu código de seguimiento es ' + code + '.';
    form.reset();
  });

  $('#year').textContent = new Date().getFullYear();
});

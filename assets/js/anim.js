// Animations GSAP de la page : intro du haut de page, apparitions au défilement, chiffres animés,
// captures inclinées en 3D, boutons « magnétiques ». Sans GSAP (ou mouvements réduits), tout reste visible.
(() => {
  const html = document.documentElement;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!window.gsap || !window.ScrollTrigger || reduced) { html.classList.add('no-gsap'); return; }
  gsap.registerPlugin(ScrollTrigger);
  // Les animations suivent le temps réel : sur un appareil lent (scène 3D lourde), l'intro ne traîne pas.
  gsap.ticker.lagSmoothing(0);
  // Tout est créé dans le même passage que l'ajout de la classe : aucun clignotement.
  html.classList.add('gsap');
  const $$ = s => gsap.utils.toArray(s);

  // --- Intro
  gsap.timeline({ defaults: { ease: 'power3.out' } })
    .from('.hero .badge, .hero .countdown', { y: 16, opacity: 0, duration: 0.5, stagger: 0.08 })
    .from('.hero h1 .ln > span', { yPercent: 110, rotate: 3, duration: 0.75, stagger: 0.1 }, '-=0.3')
    .from('.hero .lead', { y: 24, opacity: 0, duration: 0.6 }, '-=0.45')
    .from('.hero .cta > *', { y: 20, opacity: 0, scale: 0.96, duration: 0.5, stagger: 0.08 }, '-=0.4')
    .from('.hero .meta > *', { opacity: 0, y: 8, duration: 0.4, stagger: 0.06 }, '-=0.3')
    // Valeur d'arrivée explicite : la scène 3D se charge après ce script (son opacité CSS vaut encore 0 ici).
    .fromTo('[data-scene]', { opacity: 0, scale: 0.9 }, { opacity: 1, scale: 1, duration: 1.4, ease: 'power2.out' }, 0.1);

  // --- Capture principale : se redresse en 3D pendant le défilement
  gsap.fromTo('.hero .shot', { rotateX: 24, y: 60, scale: 0.92, opacity: 0.6 }, {
    rotateX: 0, y: 0, scale: 1, opacity: 1, ease: 'none',
    scrollTrigger: { trigger: '.hero .shot', start: 'top 98%', end: 'top 35%', scrub: 0.6 }
  });

  // --- Apparitions génériques
  const reveal = (targets, vars, trigger) => $$(targets).forEach(el => gsap.from(el, {
    opacity: 0, y: 40, duration: 0.9, ease: 'power3.out', ...vars,
    scrollTrigger: { trigger: trigger || el, start: 'top 88%', once: true }
  }));
  gsap.from('.fw-chip', { opacity: 0, y: 24, scale: 0.8, duration: 0.6, ease: 'back.out(1.8)', stagger: 0.05, scrollTrigger: { trigger: '.fw-row', start: 'top 90%', once: true } });
  reveal('.center.reveal, .faq details, .contact-item, form.avis');
  gsap.from('.stat', { opacity: 0, y: 30, duration: 0.7, stagger: 0.1, ease: 'power3.out', scrollTrigger: { trigger: '.stats', start: 'top 88%', once: true } });
  $$('.grid3').forEach(g => gsap.from(g.children, { opacity: 0, y: 46, rotateX: -12, duration: 0.8, stagger: 0.12, ease: 'power3.out', scrollTrigger: { trigger: g, start: 'top 85%', once: true } }));

  // --- Fonctionnalités : texte et capture arrivent de côtés opposés, la capture pivote en 3D
  $$('.feature').forEach((f, i) => {
    const left = i % 2 === 0;
    const tl = gsap.timeline({ scrollTrigger: { trigger: f, start: 'top 80%', once: true } });
    tl.from(f.querySelector('.f-text'), { x: left ? -60 : 60, opacity: 0, duration: 0.9, ease: 'power3.out' })
      .from(f.querySelector('.f-text li') ? f.querySelectorAll('.f-text li') : [], { x: -16, opacity: 0, stagger: 0.08, duration: 0.5 }, '-=0.5')
      .from(f.querySelector('.f-shot'), { x: left ? 80 : -80, rotateY: left ? -22 : 22, opacity: 0, duration: 1.1, ease: 'power3.out' }, 0);
    // Légère inclinaison continue pendant le défilement
    gsap.to(f.querySelector('.f-shot'), { rotateY: left ? 4 : -4, rotateX: 2, ease: 'none', scrollTrigger: { trigger: f, start: 'top bottom', end: 'bottom top', scrub: true } });
  });
  reveal('.alt > .wrap > .f-shot', { y: 70, rotateX: 14, scale: 0.96, duration: 1.1 });

  // --- Chiffres : défilent jusqu'à leur valeur
  $$('.stat b:not([data-static])').forEach(b => {
    const target = Number(String(b.textContent).replace(/\D/g, '')) || 0;
    const o = { v: 0 };
    gsap.to(o, { v: target, duration: 1.6, ease: 'power2.out', scrollTrigger: { trigger: b, start: 'top 92%', once: true },
      onUpdate: () => { b.textContent = Math.round(o.v).toLocaleString('fr-FR').replace(/ /g, ' '); } });
  });

  // --- Communauté : cartes et icône flottante
  gsap.from('.community-card', { opacity: 0, y: 60, scale: 0.97, duration: 1, ease: 'power3.out', scrollTrigger: { trigger: '.community-card', start: 'top 85%', once: true } });
  gsap.from('.community-perks li', { opacity: 0, x: -20, stagger: 0.1, duration: 0.6, scrollTrigger: { trigger: '.community-perks', start: 'top 90%', once: true } });
  gsap.to('.community-orb', { y: -14, rotate: 6, duration: 2.6, ease: 'sine.inOut', yoyo: true, repeat: -1 });
  gsap.to('.community-orb .ring', { scale: 1.5, opacity: 0, duration: 2.2, ease: 'power1.out', repeat: -1, stagger: 0.7 });

  // --- Appel final
  gsap.from('.final .wrap > *', { opacity: 0, y: 30, stagger: 0.12, duration: 0.8, ease: 'power3.out', scrollTrigger: { trigger: '.final', start: 'top 80%', once: true } });

  // --- Boutons principaux « magnétiques » (suivent légèrement le pointeur)
  if (matchMedia('(pointer: fine)').matches) {
    $$('.magnet').forEach(btn => {
      const x = gsap.quickTo(btn, 'x', { duration: 0.4, ease: 'power3' }), y = gsap.quickTo(btn, 'y', { duration: 0.4, ease: 'power3' });
      btn.addEventListener('pointermove', e => { const r = btn.getBoundingClientRect(); x((e.clientX - r.left - r.width / 2) * 0.25); y((e.clientY - r.top - r.height / 2) * 0.35); });
      btn.addEventListener('pointerleave', () => { x(0); y(0); });
    });
  }

  // Les images chargées plus tard changent la hauteur de la page : recalcul des déclencheurs.
  addEventListener('load', () => ScrollTrigger.refresh());
})();

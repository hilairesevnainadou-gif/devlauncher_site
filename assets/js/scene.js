// Scène 3D du haut de page : un noyau lumineux (DevLauncher) autour duquel gravitent les logos des frameworks,
// dans un champ de particules. Suit la souris, s'efface au défilement, se met en pause hors de l'écran.
import * as THREE from '../vendor/three.module.min.js';

const host = document.querySelector('[data-scene]');
const data = JSON.parse(document.getElementById('fw-data').textContent);
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const dark = () => matchMedia('(prefers-color-scheme: dark)').matches;
let started = false; // boucle de rendu prête (utilisé au redimensionnement)

const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
renderer.setPixelRatio(Math.min(devicePixelRatio, 1.75));
renderer.outputColorSpace = THREE.SRGBColorSpace;
host.appendChild(renderer.domElement);

const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
camera.position.set(0, 0, 15);
const world = new THREE.Group();
scene.add(world);

// --- Lumières : bleu Novatech + cyan, pour des reflets sur le noyau
scene.add(new THREE.AmbientLight(0xffffff, 0.55));
const key = new THREE.PointLight(0x2f9bff, 60, 40); key.position.set(6, 5, 8); scene.add(key);
const rim = new THREE.PointLight(0x00d4c8, 40, 40); rim.position.set(-7, -4, 4); scene.add(rim);

// --- Noyau : icosaèdre facetté + coque filaire qui tourne en sens inverse + halo
const core = new THREE.Group();
const solid = new THREE.Mesh(new THREE.IcosahedronGeometry(1.35, 0), new THREE.MeshStandardMaterial({ color: 0x0373bb, metalness: 0.35, roughness: 0.3, flatShading: true }));
const shell = new THREE.Mesh(new THREE.IcosahedronGeometry(2.05, 1), new THREE.MeshBasicMaterial({ color: 0x4aa3e0, wireframe: true, transparent: true, opacity: 0.32 }));
core.add(solid, shell);

function radialTexture(inner, outer) {
  const c = document.createElement('canvas'); c.width = c.height = 256;
  const g = c.getContext('2d');
  const grd = g.createRadialGradient(128, 128, 0, 128, 128, 128);
  grd.addColorStop(0, inner); grd.addColorStop(1, outer);
  g.fillStyle = grd; g.fillRect(0, 0, 256, 256);
  const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; return t;
}
const halo = new THREE.Sprite(new THREE.SpriteMaterial({ map: radialTexture('rgba(56,160,255,0.55)', 'rgba(56,160,255,0)'), blending: THREE.AdditiveBlending, depthWrite: false }));
halo.scale.set(9, 9, 1);
core.add(halo);
world.add(core);

// --- Logos : pastilles blanches (texture dessinée à partir des tracés SVG) sur trois anneaux inclinés
function logoTexture(d, color) {
  const s = 256, c = document.createElement('canvas'); c.width = c.height = s;
  const g = c.getContext('2d');
  g.shadowColor = 'rgba(16,24,40,0.25)'; g.shadowBlur = 22; g.shadowOffsetY = 8;
  g.fillStyle = '#ffffff';
  g.beginPath(); g.arc(s / 2, s / 2, s / 2 - 26, 0, Math.PI * 2); g.fill();
  g.shadowColor = 'transparent';
  g.translate(s / 2 - 66, s / 2 - 66); g.scale(132 / 24, 132 / 24);
  g.fillStyle = color === 'currentColor' ? '#15191d' : color;
  g.fill(new Path2D(d));
  const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = 4; return t;
}
const rings = [
  { radius: 5.2, tilt: [1.15, 0.0, 0.35], speed: 0.16 },
  { radius: 6.9, tilt: [1.35, 0.0, -0.5], speed: -0.11 },
  { radius: 8.4, tilt: [1.05, 0.0, 0.1], speed: 0.075 }
].map(r => {
  const g = new THREE.Group();
  g.rotation.set(...r.tilt);
  const line = new THREE.Mesh(new THREE.TorusGeometry(r.radius, 0.012, 6, 160), new THREE.MeshBasicMaterial({ color: 0x4aa3e0, transparent: true, opacity: 0.22 }));
  g.add(line);
  world.add(g);
  return { ...r, group: g, items: [] };
});
data.forEach((l, i) => {
  const ring = rings[i % rings.length];
  const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: logoTexture(l.d, l.color), transparent: true, depthWrite: false }));
  sprite.scale.set(1.6, 1.6, 1);
  ring.group.add(sprite);
  ring.items.push({ sprite, phase: 0 });
});
rings.forEach(r => r.items.forEach((it, k) => { it.phase = (k / r.items.length) * Math.PI * 2 + r.radius; }));

// --- Particules
const N = 900, pos = new Float32Array(N * 3);
for (let i = 0; i < N; i++) {
  const r = 9 + Math.random() * 14, th = Math.random() * Math.PI * 2, ph = Math.acos(2 * Math.random() - 1);
  pos[i * 3] = r * Math.sin(ph) * Math.cos(th); pos[i * 3 + 1] = r * Math.sin(ph) * Math.sin(th) * 0.6; pos[i * 3 + 2] = r * Math.cos(ph) - 6;
}
const pgeo = new THREE.BufferGeometry(); pgeo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
const pmat = new THREE.PointsMaterial({ size: 0.06, color: 0x4aa3e0, transparent: true, opacity: 0.7, depthWrite: false });
const stars = new THREE.Points(pgeo, pmat);
scene.add(stars);

// --- Taille : les anneaux se resserrent sur les écrans étroits
function resize() {
  const w = host.clientWidth, h = host.clientHeight;
  renderer.setSize(w, h, false);
  camera.aspect = w / h; camera.updateProjectionMatrix();
  // Les anneaux doivent tenir dans la largeur visible (colonne de droite, ou plein écran sur mobile)
  const fit = Math.min(1, Math.max(0.4, camera.aspect * 0.56));
  world.scale.setScalar(fit);
  // Animations désactivées : une seule image est dessinée, elle doit l'être à la bonne taille.
  if (reduced && started) frame();
}
new ResizeObserver(resize).observe(host);
resize();

// --- Interaction : parallaxe à la souris, effacement au défilement
const pointer = { x: 0, y: 0, tx: 0, ty: 0 };
addEventListener('pointermove', e => { pointer.tx = (e.clientX / innerWidth - 0.5) * 2; pointer.ty = (e.clientY / innerHeight - 0.5) * 2; }, { passive: true });
let visible = true;
new IntersectionObserver(([e]) => { visible = e.isIntersecting; if (visible) loop(); }).observe(host);

const clock = new THREE.Clock();
let raf = 0;
function frame() {
  const t = clock.getElapsedTime();
  pointer.x += (pointer.tx - pointer.x) * 0.05; pointer.y += (pointer.ty - pointer.y) * 0.05;
  world.rotation.y = pointer.x * 0.25; world.rotation.x = pointer.y * 0.15;
  solid.rotation.set(t * 0.25, t * 0.35, 0);
  shell.rotation.set(-t * 0.12, -t * 0.18, 0);
  const pulse = 1 + Math.sin(t * 1.6) * 0.04; solid.scale.setScalar(pulse);
  halo.material.opacity = 0.75 + Math.sin(t * 1.6) * 0.2;
  for (const r of rings) for (const it of r.items) {
    const a = it.phase + t * r.speed;
    it.sprite.position.set(Math.cos(a) * r.radius, Math.sin(a) * r.radius, 0);
  }
  stars.rotation.y = t * 0.015;
  // Défilement : la scène recule et s'estompe quand on quitte le haut de page
  const p = Math.min(1, scrollY / (host.clientHeight * 0.9));
  camera.position.z = 15 + p * 6; camera.position.y = -p * 2;
  renderer.domElement.style.opacity = String(1 - p * 0.85);
  pmat.color.set(dark() ? 0x6cb5e8 : 0x4aa3e0);
  renderer.render(scene, camera);
}
function loop() {
  cancelAnimationFrame(raf);
  if (!visible || document.hidden) return;
  frame();
  if (!reduced) raf = requestAnimationFrame(loop);
}
document.addEventListener('visibilitychange', loop);
started = true;
loop();
host.classList.add('ready');

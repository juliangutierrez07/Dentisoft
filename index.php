<?php
/**
 * Home publico - DentiSoft 1.0
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$loginUrl = BASE_URL . '/login.php';
$loginPaciente = BASE_URL . '/portal-login.php';
$selectorUrl = BASE_URL . '/seleccionar-acceso.php';
$contactEmail = defined('CLINICA_EMAIL') ? CLINICA_EMAIL : 'contacto@dentisoft.com';
$siteUrl = BASE_URL . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DentiSoft - La nueva generacion de gestion odontologica</title>
    <meta name="description" content="DentiSoft es una plataforma inteligente para consultorios odontologicos: pacientes, citas, historias clinicas, facturacion y reportes en una experiencia premium.">
    <meta name="keywords" content="software odontologico, gestion dental, agenda odontologica, historias clinicas digitales, facturacion dental">
    <meta name="author" content="DentiSoft">
    <meta name="theme-color" content="#050712">
    <meta property="og:title" content="DentiSoft - Gestion odontologica inteligente">
    <meta property="og:description" content="Pacientes, citas, historias clinicas y facturacion en una sola plataforma inteligente.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?= BASE_URL ?>/assets/img/logo.png">
    <link rel="canonical" href="<?= $siteUrl ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/home.css" rel="stylesheet">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "DentiSoft",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "description": "Plataforma inteligente para gestion odontologica, pacientes, citas, historias clinicas, facturacion y reportes."
    }
    </script>
</head>
<body>
    <canvas class="ambient-canvas" id="ambientCanvas" aria-hidden="true"></canvas>
    <div class="page-glow" aria-hidden="true"></div>

    <header class="site-header">
        <nav class="nav-shell" aria-label="Navegacion principal">
            <a class="brand magnetic" href="<?= $siteUrl ?>" aria-label="Inicio DentiSoft">
                <span class="brand-mark">
                    <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="" width="36" height="36">
                </span>
                <span>DentiSoft</span>
            </a>
            <div class="nav-links" aria-label="Secciones">
                <a href="#futuro">Futuro</a>
                <a href="#mockups">Mockups</a>
                <a href="#explorar">Explorar</a>
                <a href="#testimonios">Clientes</a>
            </div>
            <div class="nav-access-buttons">
                <a class="nav-access-btn nav-access-paciente magnetic" href="<?= $loginPaciente ?>">
                    <i class="bi bi-person" aria-hidden="true"></i>
                    <span>Portal Paciente</span>
                </a>
                <a class="nav-access-btn nav-access-clinico magnetic" href="<?= $loginUrl ?>">
                    <i class="bi bi-hospital" aria-hidden="true"></i>
                    <span>Acceso Clínico</span>
                </a>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero-cinematic" aria-labelledby="hero-title">
            <div class="hero-video" aria-label="Fondo animado sutil de DentiSoft">
                <div class="orbital-ring ring-a"></div>
                <div class="orbital-ring ring-b"></div>
                <div class="orbital-ring ring-c"></div>
            </div>

            <div class="hero-content hero-layout reveal">
                <div class="hero-copy">
                    <p class="eyebrow">DentiSoft Platform</p>
                    <h1 id="hero-title">Gestiona tu consultorio desde una sola plataforma.</h1>
                    <p class="hero-subtitle">Pacientes, citas, historias clinicas, tratamientos, facturacion y portal para pacientes en una experiencia moderna y segura.</p>
                    <div class="hero-actions">
                        <a class="btn-primary magnetic" href="<?= $loginUrl ?>">
                            <i class="bi bi-hospital" aria-hidden="true"></i>
                            <span>Acceso Clínico</span>
                        </a>
                        <a class="btn-secondary magnetic" href="<?= $loginPaciente ?>">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <span>Portal del Paciente</span>
                        </a>
                        <a class="btn-ghost magnetic" href="#futuro">
                            <i class="bi bi-arrow-down-circle" aria-hidden="true"></i>
                            <span>Conocer mas</span>
                        </a>
                    </div>
                </div>

                <div class="hero-product-stage" aria-label="Vista previa de modulos de DentiSoft">
                    <div class="product-window main-window">
                        <div class="window-bar">
                            <span></span><span></span><span></span>
                            <strong>Agenda clinica</strong>
                        </div>
                        <div class="product-grid">
                            <aside class="product-sidebar">
                                <i class="bi bi-grid-1x2-fill"></i>
                                <i class="bi bi-calendar2-week"></i>
                                <i class="bi bi-file-medical"></i>
                                <i class="bi bi-receipt"></i>
                            </aside>
                            <div class="calendar-preview">
                                <div class="calendar-header">
                                    <span>Hoy</span>
                                    <strong>Junio 2026</strong>
                                </div>
                                <div class="appointment-row active">
                                    <span>09:00</span>
                                    <div><strong>Maria Torres</strong><small>Limpieza dental</small></div>
                                </div>
                                <div class="appointment-row">
                                    <span>10:30</span>
                                    <div><strong>Juan Perez</strong><small>Ortodoncia</small></div>
                                </div>
                                <div class="appointment-row">
                                    <span>12:00</span>
                                    <div><strong>Ana Gomez</strong><small>Valoracion</small></div>
                                </div>
                            </div>
                            <div class="clinical-preview">
                                <span>Historia clinica</span>
                                <strong>Odontograma y evolucion</strong>
                                <div class="clinical-lines"><i></i><i></i><i></i></div>
                            </div>
                        </div>
                    </div>

                    <article class="floating-module module-billing">
                        <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                        <span>Facturacion</span>
                        <strong>$1.280.000</strong>
                    </article>
                    <article class="floating-module module-portal">
                        <i class="bi bi-person-check" aria-hidden="true"></i>
                        <span>Portal del paciente</span>
                        <strong>Cita confirmada</strong>
                    </article>
                    <article class="floating-module module-treatment">
                        <i class="bi bi-clipboard2-pulse" aria-hidden="true"></i>
                        <span>Tratamiento</span>
                        <strong>Plan activo</strong>
                    </article>

                    <div class="dental-core hero-dental-model" aria-hidden="true">
                        <div class="tooth-3d">
                            <div class="tooth-crown"></div>
                            <div class="tooth-root root-left"></div>
                            <div class="tooth-root root-right"></div>
                        </div>
                    </div>
                </div>
            </div>
            <a class="scroll-cue" href="#futuro" aria-label="Ir a la siguiente seccion">
                <span></span>
            </a>
        </section>

        <section class="future-section section" id="futuro">
            <div class="section-heading reveal">
                <p class="eyebrow">El futuro del consultorio</p>
                <h2>Una suite clinica que se siente tan avanzada como tu practica.</h2>
            </div>
            <div class="floating-card-grid">
                <?php
                $features = [
                    ['bi-person-vcard', 'Gestion de pacientes', 'Perfiles completos, busqueda inteligente y datos clinicos listos para actuar.'],
                    ['bi-calendar2-week', 'Agenda inteligente', 'Estados, horarios, profesionales y confirmaciones en un flujo visual.'],
                    ['bi-clipboard2-pulse', 'Historias clinicas digitales', 'Documentacion estructurada para decisiones clinicas con trazabilidad.'],
                    ['bi-credit-card-2-front', 'Facturacion avanzada', 'Pagos, saldos y facturas integrados con la operacion diaria.'],
                    ['bi-graph-up-arrow', 'Reportes estrategicos', 'Indicadores ejecutivos para entender productividad, cartera e ingresos.'],
                    ['bi-shield-lock', 'Seguridad empresarial', 'Roles, permisos y control de acceso para proteger la informacion.'],
                ];
                foreach ($features as $feature): ?>
                    <article class="future-card tilt-card reveal">
                        <div class="card-shine"></div>
                        <i class="bi <?= $feature[0] ?>" aria-hidden="true"></i>
                        <h3><?= $feature[1] ?></h3>
                        <p><?= $feature[2] ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mockup-section section" id="mockups">
            <div class="section-heading centered reveal">
                <p class="eyebrow">Mockups premium</p>
                <h2>Tu consultorio sincronizado en cada pantalla.</h2>
            </div>
            <div class="device-stage reveal">
                <div class="device laptop">
                    <div class="screen dashboard-screen">
                        <div class="screen-nav"></div>
                        <div class="screen-main">
                            <div class="metric-card big"><span>Agenda hoy</span><strong>28</strong></div>
                            <div class="metric-card"><span>Ingresos</span><strong>$18.4M</strong></div>
                            <div class="metric-card"><span>Pacientes</span><strong>10.2K</strong></div>
                            <div class="screen-chart"><span></span><span></span><span></span><span></span><span></span></div>
                        </div>
                    </div>
                    <div class="laptop-base"></div>
                </div>
                <div class="device tablet">
                    <div class="screen">
                        <div class="patient-card-mini">
                            <i class="bi bi-person-heart"></i>
                            <strong>Laura Medina</strong>
                            <span>Control de ortodoncia</span>
                        </div>
                        <div class="timeline-mini"><span></span><span></span><span></span></div>
                    </div>
                </div>
                <div class="device phone">
                    <div class="screen">
                        <div class="phone-notch"></div>
                        <div class="phone-check"><i class="bi bi-check2"></i></div>
                        <strong>Cita confirmada</strong>
                        <span>9:30 AM</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="interactive-section section" id="explorar">
            <div class="section-heading reveal">
                <p class="eyebrow">Experiencia interactiva</p>
                <h2>Explora los flujos que mantienen viva la operacion.</h2>
            </div>
            <div class="explorer reveal" data-active="pacientes">
                <div class="explorer-tabs" role="tablist" aria-label="Modulos principales">
                    <button class="explorer-tab active" type="button" data-panel="pacientes" role="tab" aria-selected="true">
                        <i class="bi bi-people"></i> Pacientes
                    </button>
                    <button class="explorer-tab" type="button" data-panel="citas" role="tab" aria-selected="false">
                        <i class="bi bi-calendar-event"></i> Citas
                    </button>
                    <button class="explorer-tab" type="button" data-panel="historias" role="tab" aria-selected="false">
                        <i class="bi bi-file-medical"></i> Historias clinicas
                    </button>
                    <button class="explorer-tab" type="button" data-panel="facturacion" role="tab" aria-selected="false">
                        <i class="bi bi-receipt"></i> Facturacion
                    </button>
                </div>
                <div class="explorer-panels">
                    <article class="explorer-panel active" id="panel-pacientes">
                        <span>Paciente 360</span>
                        <h3>Historial, datos personales y evolucion clinica en un solo perfil.</h3>
                        <p>Consulta informacion relevante, documentos y proximas citas sin saltar entre pantallas.</p>
                    </article>
                    <article class="explorer-panel" id="panel-citas">
                        <span>Agenda inteligente</span>
                        <h3>Coordina el dia completo del consultorio con estados claros.</h3>
                        <p>Visualiza disponibilidad, confirma atenciones y reduce friccion operativa.</p>
                    </article>
                    <article class="explorer-panel" id="panel-historias">
                        <span>Registro clinico</span>
                        <h3>Historias digitales con trazabilidad y acceso seguro.</h3>
                        <p>Documenta diagnosticos, tratamientos y avances desde una experiencia ordenada.</p>
                    </article>
                    <article class="explorer-panel" id="panel-facturacion">
                        <span>Finanzas conectadas</span>
                        <h3>Facturas, pagos y cartera integrados con cada paciente.</h3>
                        <p>Entiende ingresos y saldos pendientes con menos trabajo manual.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="stats-section" aria-label="Estadisticas de DentiSoft">
            <div class="stats-grid reveal">
                <div><strong data-count="100000" data-prefix="+">0</strong><span>Citas registradas</span></div>
                <div><strong data-count="50" data-prefix="+">0</strong><span>Clinicas activas</span></div>
                <div><strong data-count="10000" data-prefix="+">0</strong><span>Pacientes gestionados</span></div>
                <div><strong data-count="99.9" data-suffix="%">0</strong><span>Disponibilidad</span></div>
            </div>
        </section>

        <section class="testimonials-section section" id="testimonios">
            <div class="section-heading centered reveal">
                <p class="eyebrow">Confianza clinica</p>
                <h2>Equipos que quieren operar como empresas tecnologicas.</h2>
            </div>
            <div class="testimonial-carousel reveal" aria-label="Testimonios de clientes">
                <article class="testimonial-slide active">
                    <div class="portrait portrait-a" role="img" aria-label="Foto de Laura Mendez"></div>
                    <p>"DentiSoft hizo que nuestro consultorio se sintiera moderno desde el primer dia. La agenda, las historias y los pagos finalmente hablan entre si."</p>
                    <h3>Dra. Laura Mendez</h3>
                    <span>Directora clinica</span>
                </article>
                <article class="testimonial-slide">
                    <div class="portrait portrait-b" role="img" aria-label="Foto de Carlos Rivas"></div>
                    <p>"El equipo administrativo trabaja con mas velocidad y menos errores. La plataforma transmite orden, control y confianza."</p>
                    <h3>Carlos Rivas</h3>
                    <span>Administrador odontologico</span>
                </article>
                <article class="testimonial-slide">
                    <div class="portrait portrait-c" role="img" aria-label="Foto de Andrea Torres"></div>
                    <p>"Encontrar la informacion clinica en segundos mejora la atencion. Se siente como pasar de archivos a un centro de comando."</p>
                    <h3>Andrea Torres</h3>
                    <span>Coordinadora asistencial</span>
                </article>
                <div class="carousel-dots" aria-label="Controles del carrusel">
                    <button class="active" type="button" aria-label="Ver testimonio 1"></button>
                    <button type="button" aria-label="Ver testimonio 2"></button>
                    <button type="button" aria-label="Ver testimonio 3"></button>
                </div>
            </div>
        </section>

        <section class="final-cta reveal" id="contacto">
            <div class="cta-aurora" aria-hidden="true"></div>
            <div class="cta-content">
                <p class="eyebrow">DentiSoft</p>
                <h2>Transforma tu consultorio con DentiSoft</h2>
                <p>Una plataforma inteligente para gestionar pacientes, citas, historias clinicas, facturacion y reportes con una experiencia memorable.</p>
                <div class="hero-actions">
                    <a class="btn-primary magnetic" href="<?= $loginUrl ?>">
                        <i class="bi bi-hospital" aria-hidden="true"></i>
                        <span>Acceso Clínico</span>
                    </a>
                    <a class="btn-secondary magnetic" href="<?= $loginPaciente ?>">
                        <i class="bi bi-person" aria-hidden="true"></i>
                        <span>Portal del Paciente</span>
                    </a>
                    <a class="btn-ghost magnetic" href="mailto:<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="bi bi-send" aria-hidden="true"></i>
                        <span>Solicitar Informacion</span>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="brand footer-logo" href="<?= $siteUrl ?>">
                    <span class="brand-mark">
                        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="" width="34" height="34">
                    </span>
                    <span>DentiSoft</span>
                </a>
                <p>Gestion odontologica premium para consultorios que quieren crecer con tecnologia, seguridad y claridad operativa.</p>
            </div>
            <div>
                <h3>Contacto</h3>
                <a href="mailto:<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?></a>
                <a href="#contacto">Solicitar informacion</a>
            </div>
            <div>
                <h3>Soporte</h3>
                <a href="<?= $loginUrl ?>">Acceso al sistema</a>
                <a href="#explorar">Modulos</a>
                <a href="#mockups">Demostracion</a>
            </div>
            <div>
                <h3>Redes y legal</h3>
                <a href="#" aria-label="LinkedIn de DentiSoft"><i class="bi bi-linkedin"></i> LinkedIn</a>
                <a href="#">Terminos</a>
                <a href="#">Privacidad</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> DentiSoft. Todos los derechos reservados.</span>
            <span><?= defined('CLINICA_CIUDAD') ? CLINICA_CIUDAD : 'Colombia' ?></span>
        </div>
    </footer>

    <script>
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const canvas = document.getElementById('ambientCanvas');
        const ctx = canvas.getContext('2d');
        let particles = [];

        function resizeCanvas() {
            canvas.width = window.innerWidth * window.devicePixelRatio;
            canvas.height = window.innerHeight * window.devicePixelRatio;
            ctx.setTransform(window.devicePixelRatio, 0, 0, window.devicePixelRatio, 0, 0);
        }

        function createParticles() {
            const count = Math.min(80, Math.floor(window.innerWidth / 18));
            particles = Array.from({ length: count }, () => ({
                x: Math.random() * window.innerWidth,
                y: Math.random() * window.innerHeight,
                r: Math.random() * 1.8 + 0.4,
                vx: (Math.random() - 0.5) * 0.22,
                vy: (Math.random() - 0.5) * 0.22,
                a: Math.random() * 0.5 + 0.2
            }));
        }

        function drawParticles() {
            ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);
            particles.forEach((p, index) => {
                p.x += p.vx;
                p.y += p.vy;
                if (p.x < 0 || p.x > window.innerWidth) p.vx *= -1;
                if (p.y < 0 || p.y > window.innerHeight) p.vy *= -1;

                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(125, 249, 255, ${p.a})`;
                ctx.fill();

                for (let j = index + 1; j < particles.length; j++) {
                    const q = particles[j];
                    const distance = Math.hypot(p.x - q.x, p.y - q.y);
                    if (distance < 120) {
                        ctx.beginPath();
                        ctx.moveTo(p.x, p.y);
                        ctx.lineTo(q.x, q.y);
                        ctx.strokeStyle = `rgba(99, 102, 241, ${0.12 * (1 - distance / 120)})`;
                        ctx.stroke();
                    }
                }
            });
            if (!reduceMotion) requestAnimationFrame(drawParticles);
        }

        resizeCanvas();
        createParticles();
        drawParticles();
        window.addEventListener('resize', () => {
            resizeCanvas();
            createParticles();
        });

        const revealItems = document.querySelectorAll('.reveal');
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        revealItems.forEach((item) => revealObserver.observe(item));

        document.querySelectorAll('.tilt-card').forEach((card) => {
            card.addEventListener('pointermove', (event) => {
                if (reduceMotion) return;
                const rect = card.getBoundingClientRect();
                const x = event.clientX - rect.left;
                const y = event.clientY - rect.top;
                const rotateY = ((x / rect.width) - 0.5) * 10;
                const rotateX = ((0.5 - y / rect.height)) * 10;
                card.style.setProperty('--rx', `${rotateX}deg`);
                card.style.setProperty('--ry', `${rotateY}deg`);
                card.style.setProperty('--mx', `${x}px`);
                card.style.setProperty('--my', `${y}px`);
            });
            card.addEventListener('pointerleave', () => {
                card.style.setProperty('--rx', '0deg');
                card.style.setProperty('--ry', '0deg');
            });
        });

        const tabs = document.querySelectorAll('.explorer-tab');
        const panels = document.querySelectorAll('.explorer-panel');
        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.panel;
                tabs.forEach((item) => {
                    item.classList.toggle('active', item === tab);
                    item.setAttribute('aria-selected', item === tab ? 'true' : 'false');
                });
                panels.forEach((panel) => {
                    panel.classList.toggle('active', panel.id === `panel-${target}`);
                });
            });
        });

        const counters = document.querySelectorAll('[data-count]');
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const element = entry.target;
                const target = Number(element.dataset.count);
                const prefix = element.dataset.prefix || '';
                const suffix = element.dataset.suffix || '';
                const decimals = target % 1 === 0 ? 0 : 1;
                const duration = 1500;
                const start = performance.now();

                const tick = (now) => {
                    const progress = Math.min((now - start) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    const value = target * eased;
                    element.textContent = prefix + value.toLocaleString('es-CO', {
                        maximumFractionDigits: decimals,
                        minimumFractionDigits: decimals
                    }) + suffix;
                    if (progress < 1) requestAnimationFrame(tick);
                };
                requestAnimationFrame(tick);
                counterObserver.unobserve(element);
            });
        }, { threshold: 0.55 });
        counters.forEach((counter) => counterObserver.observe(counter));

        const slides = document.querySelectorAll('.testimonial-slide');
        const dots = document.querySelectorAll('.carousel-dots button');
        let activeSlide = 0;

        function showSlide(index) {
            activeSlide = index;
            slides.forEach((slide, i) => slide.classList.toggle('active', i === index));
            dots.forEach((dot, i) => dot.classList.toggle('active', i === index));
        }

        dots.forEach((dot, index) => dot.addEventListener('click', () => showSlide(index)));
        if (!reduceMotion) {
            setInterval(() => showSlide((activeSlide + 1) % slides.length), 5200);
        }
    </script>
</body>
</html>

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
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400;1,9..144,500&family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/home.css?v=<?= filemtime(__DIR__ . '/assets/css/home.css') ?>" rel="stylesheet">
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
                <a href="#modulos">Módulos</a>
                <a href="#precios">Precios</a>
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
            <div class="hero-content hero-layout reveal">
                <div class="hero-copy">
                    <p class="eyebrow">DentiSoft Platform</p>
                    <h1 id="hero-title">Gestiona tu consultorio <em>desde un solo lugar.</em></h1>
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
                        <a class="btn-ghost magnetic" href="#modulos">
                            <i class="bi bi-arrow-down-circle" aria-hidden="true"></i>
                            <span>Conocer mas</span>
                        </a>
                    </div>
                    <div class="trust-row">
                        <div class="trust-item">
                            <span class="trust-num">50+</span>
                            <span class="trust-label">Clinicas activas</span>
                        </div>
                        <div class="trust-divider"></div>
                        <div class="trust-item">
                            <span class="trust-num">10.000+</span>
                            <span class="trust-label">Pacientes gestionados</span>
                        </div>
                        <div class="trust-divider"></div>
                        <div class="trust-item">
                            <span class="trust-num">100.000+</span>
                            <span class="trust-label">Citas registradas</span>
                        </div>
                        <div class="trust-divider"></div>
                        <div class="trust-item">
                            <span class="trust-num">99.9%</span>
                            <span class="trust-label">Disponibilidad</span>
                        </div>
                    </div>
                </div>

                <div class="hero-product-stage" aria-label="Vista previa de modulos de DentiSoft">
                    <svg class="odontogram-bg" viewBox="0 0 400 400" fill="none" aria-hidden="true">
                        <g stroke="#2FE0B0" stroke-width="1.2">
                            <path d="M60 120 Q200 40 340 120" fill="none" opacity="0.5"/>
                            <path d="M60 260 Q200 340 340 260" fill="none" opacity="0.5"/>
                            <rect x="62" y="105" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="87" y="95" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="112" y="87" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="137" y="80" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="162" y="75" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="187" y="72" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="212" y="72" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="237" y="75" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="262" y="80" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="287" y="87" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="312" y="95" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="337" y="105" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="62" y="255" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="87" y="265" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="112" y="273" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="137" y="280" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="162" y="285" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="187" y="288" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="212" y="288" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="237" y="285" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="262" y="280" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="287" y="273" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="312" y="265" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                            <rect x="337" y="255" width="16" height="20" rx="5" stroke="#2FE0B0" stroke-width="1" opacity="0.55"/>
                        </g>
                    </svg>

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
                                    <span class="appointment-tag">En curso</span>
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
                        </div>
                    </div>

                    <article class="floating-module module-billing">
                        <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                        <span>Facturacion del mes</span>
                        <strong>$1.280.000</strong>
                    </article>
                    <article class="floating-module module-clinical">
                        <i class="bi bi-clipboard2-pulse" aria-hidden="true"></i>
                        <span>Historia clinica</span>
                        <strong>Odontograma actualizado</strong>
                    </article>
                </div>
            </div>
            <a class="scroll-cue" href="#modulos" aria-label="Ir a la siguiente seccion">
                <span></span>
            </a>
        </section>

        <section class="future-section section" id="modulos">
            <div class="section-heading reveal">
                <p class="section-eyebrow"><span class="num">6 modulos</span> · uno por funcion clinica</p>
                <h2>Cada parte del consultorio, en su lugar.</h2>
                <p class="section-sub">Sin integraciones raras ni pestañas duplicadas: agenda, historia clinica y cobros comparten la misma ficha de paciente.</p>
            </div>
            <div class="modules-grid">
                <?php
                $features = [
                    ['01 / AGENDA', 'Citas sin choques', 'Estados, horarios, profesionales y confirmaciones en un flujo visual.'],
                    ['02 / HISTORIA CLINICA', 'Odontograma digital', 'Registro por pieza dental, evolucion de tratamientos y trazabilidad clinica.'],
                    ['03 / FACTURACION', 'Cobros claros', 'Pagos, saldos y facturas integrados con la operacion diaria.'],
                    ['04 / PORTAL PACIENTE', 'Autonomia del paciente', 'Tus pacientes agendan, confirman y consultan su historial sin llamar al consultorio.'],
                    ['05 / EQUIPO', 'Roles por profesional', 'Cada odontologo ve su agenda y sus pacientes; el administrador ve todo el consultorio.'],
                    ['06 / REPORTES', 'Numeros al dia', 'Indicadores ejecutivos para entender productividad, cartera e ingresos.'],
                ];
                foreach ($features as $feature): ?>
                    <article class="module reveal">
                        <div class="module-fdi"><?= $feature[0] ?></div>
                        <h3><?= $feature[1] ?></h3>
                        <p><?= $feature[2] ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section" id="precios">
            <div class="section-heading reveal" style="max-width:640px;">
                <p class="section-eyebrow"><span class="num">3 planes</span> · uno por tamano de consultorio</p>
                <h2>Precio claro, sin cotizacion a ciegas.</h2>
                <p class="section-sub">Sin permanencia minima. Cambia de plan o cancela cuando quieras.</p>
            </div>

            <div class="billing-toggle" role="group" aria-label="Ciclo de facturacion">
                <button class="toggle-btn active" id="btn-monthly" type="button" onclick="setBilling('monthly')">Mensual</button>
                <button class="toggle-btn" id="btn-annual" type="button" onclick="setBilling('annual')">
                    Anual <span class="toggle-save">Ahorra 15%</span>
                </button>
            </div>

            <div class="pricing-grid reveal">
                <div class="price-card">
                    <div class="price-plan-fdi">01 / ESENCIAL</div>
                    <div class="price-plan-name">Esencial</div>
                    <div class="price-plan-for">Para el odontologo que atiende solo, en un unico consultorio.</div>
                    <div class="price-amount">
                        <span class="price-currency">COP</span>
                        <span class="price-number" data-monthly="74.000" data-annual="63.000">$74.000</span>
                    </div>
                    <div class="price-period">por mes · 1 profesional</div>
                    <div class="price-annual-note" id="note-1"></div>
                    <ul class="price-features">
                        <li>Agenda con recordatorios por WhatsApp</li>
                        <li>Historia clinica y <b>odontograma digital</b></li>
                        <li>Portal del paciente incluido</li>
                        <li>Facturacion DIAN integrada</li>
                    </ul>
                    <a href="mailto:<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>?subject=Quiero%20contratar%20el%20plan%20Esencial" class="price-cta">Contratar Esencial</a>
                </div>

                <div class="price-card featured">
                    <div class="price-badge">Mas elegido</div>
                    <div class="price-plan-fdi">02 / PROFESIONAL</div>
                    <div class="price-plan-name">Profesional</div>
                    <div class="price-plan-for">Para consultorios de varios odontologos bajo un mismo techo.</div>
                    <div class="price-amount">
                        <span class="price-currency">COP</span>
                        <span class="price-number" data-monthly="149.000" data-annual="127.000">$149.000</span>
                    </div>
                    <div class="price-period">por mes · hasta 5 profesionales</div>
                    <div class="price-annual-note" id="note-2"></div>
                    <ul class="price-features">
                        <li>Todo lo de <b>Esencial</b></li>
                        <li>Reportes de ocupacion e ingresos</li>
                        <li>Roles por profesional</li>
                        <li>Recordatorios automaticos + confirmacion por portal</li>
                    </ul>
                    <a href="mailto:<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>?subject=Quiero%20contratar%20el%20plan%20Profesional" class="price-cta primary">Contratar Profesional</a>
                </div>

                <div class="price-card">
                    <div class="price-plan-fdi">03 / CLINICA</div>
                    <div class="price-plan-name">Clinica</div>
                    <div class="price-plan-for">Para clinicas con varias sedes o mas de 6 profesionales.</div>
                    <div class="price-amount">
                        <span class="price-currency">Desde</span>
                        <span class="price-number" style="font-size:1.4rem;">Cotizacion</span>
                    </div>
                    <div class="price-period">precio segun sedes y profesionales</div>
                    <div class="price-annual-note"></div>
                    <ul class="price-features">
                        <li>Todo lo de <b>Profesional</b></li>
                        <li>Multi-sede con reportes consolidados</li>
                        <li>Migracion de datos asistida</li>
                        <li>Soporte prioritario</li>
                    </ul>
                    <a href="mailto:<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>?subject=Quiero%20hablar%20sobre%20el%20plan%20Clinica" class="price-cta">Hablar con un asesor</a>
                </div>
            </div>

            <div class="price-footnote">
                <span>Cumple Ley 1581 de proteccion de datos</span>
                <span class="dot"></span>
                <span>Facturacion electronica DIAN</span>
                <span class="dot"></span>
                <span>Soporte en espanol, horario Colombia</span>
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
                <a href="#modulos">Modulos</a>
                <a href="#precios">Precios</a>
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
                ctx.fillStyle = `rgba(47, 224, 176, ${p.a})`;
                ctx.fill();

                for (let j = index + 1; j < particles.length; j++) {
                    const q = particles[j];
                    const distance = Math.hypot(p.x - q.x, p.y - q.y);
                    if (distance < 120) {
                        ctx.beginPath();
                        ctx.moveTo(p.x, p.y);
                        ctx.lineTo(q.x, q.y);
                        ctx.strokeStyle = `rgba(139, 126, 255, ${0.12 * (1 - distance / 120)})`;
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

        function setBilling(mode) {
            const btnMonthly = document.getElementById('btn-monthly');
            const btnAnnual = document.getElementById('btn-annual');
            btnMonthly.classList.toggle('active', mode === 'monthly');
            btnAnnual.classList.toggle('active', mode === 'annual');

            document.querySelectorAll('.price-number[data-monthly]').forEach((el) => {
                const val = mode === 'annual' ? el.getAttribute('data-annual') : el.getAttribute('data-monthly');
                el.textContent = '$' + val;
            });

            const note1 = document.getElementById('note-1');
            const note2 = document.getElementById('note-2');
            if (mode === 'annual') {
                note1.textContent = 'Facturado anual · equivale a $63.000/mes';
                note2.textContent = 'Facturado anual · equivale a $127.000/mes';
            } else {
                note1.textContent = '';
                note2.textContent = '';
            }
        }
    </script>
</body>
</html>

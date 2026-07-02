<?php
/**
 * Login — DentiSoft 1.0
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

// Si ya está logueado, ir al dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

// ─── Procesar POST del login ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $password = $_POST['password'] ?? '';
    $role     = trim($_POST['role'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Por favor completa todos los campos.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare("
                SELECT u.id, u.nombre, u.apellido, u.email, u.password, u.estado,
                       r.id AS rol_id, r.nombre AS rol
                FROM usuarios u
                INNER JOIN roles r ON u.rol_id = r.id
                WHERE u.email = :email
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($password, $usuario['password'])) {
                // Validar que el rol seleccionado coincida con el rol del usuario
                $rolSeleccionado = strtolower($role);
                $rolUsuario = strtolower($usuario['rol']);
                
                // Mapeo de valores seleccionados a nombres de rol
                $roleMap = [
                    'odontologo' => 'odontologo',
                    'asistente' => 'asistente',
                    'admin' => 'administrador'
                ];
                
                $rolNormalizado = $roleMap[$rolSeleccionado] ?? $rolSeleccionado;
                
                if (empty($role) || $rolUsuario !== $rolNormalizado) {
                    $error = 'El rol seleccionado es incorrecto.';
                } elseif ($usuario['estado'] === 'inactivo') {
                    $error = 'Tu cuenta está desactivada. Contacta al administrador.';
                } else {
                    // Crear sesión
                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['nombre']     = $usuario['nombre'];
                    $_SESSION['apellido']   = $usuario['apellido'];
                    $_SESSION['email']      = $usuario['email'];
                    $_SESSION['rol']        = $usuario['rol'];
                    $_SESSION['rol_id']     = $usuario['rol_id'];

                    // Actualizar último acceso CON manejo de errores
                    try {
                        $updateStmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id");
                        $updateResult = $updateStmt->execute([':id' => $usuario['id']]);
                        
                        if (!$updateResult) {
                            error_log('Login: Error al actualizar ultimo_acceso para usuario_id=' . $usuario['id']);
                        } else {
                            error_log('Login exitoso: usuario_id=' . $usuario['id'] . ', email=' . $usuario['email']);
                        }
                    } catch (Exception $e) {
                        error_log('Login: Excepción al actualizar ultimo_acceso para usuario_id=' . $usuario['id'] . ': ' . $e->getMessage());
                    }

                    // Registrar en auditoría (pasar usuario_id explícitamente)
                    registrarAuditoria('login', 'usuarios', $usuario['id'], null, ['ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

                    // Redirigir
                    $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . '/dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirect);
                    exit;
                }
            } else {
                $error = 'Correo o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            $error = 'Error del servidor. Intenta más tarde.';
            error_log('Login error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — DentiSoft</title>
    <meta name="description" content="Accede al sistema de gestión odontológica DentiSoft.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        :root {
            --primary: #1a56db;
            --primary-dark: #1240a0;
            --primary-light: #3b82f6;
            --accent: #0891b2;
            --accent-light: #22d3ee;
            --success: #10b981;
            --danger: #ef4444;
            --bg-panel: #0f172a;
            --bg-panel-2: #0e2a50;
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-400: #94a3b8;
            --gray-600: #475569;
            --gray-800: #1e293b;
            --text: #0f172a;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,.10), 0 2px 6px rgba(0,0,0,.06);
            --shadow-lg: 0 20px 40px rgba(0,0,0,.15);
            --radius: 14px;
            --transition: all .28s cubic-bezier(.4,0,.2,1);
        }

        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            font-family: 'Inter', sans-serif;
            background: var(--bg-panel);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }

        .login-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        .login-panel {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Panel izquierdo */
        .login-panel--left {
            flex: 1.1;
            background: linear-gradient(155deg, #0e2a50 0%, #0f172a 45%, #0c1a38 100%);
            overflow: hidden;
            padding: 3rem 2rem;
            flex-direction: column;
        }

        .login-panel--left::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.025'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
        }

        .panel-overlay {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 60% at 50% 0%, rgba(26,86,219,.35) 0%, transparent 65%),
                radial-gradient(ellipse 50% 40% at 90% 100%, rgba(8,145,178,.3) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .float-card {
            position: absolute;
            display: flex;
            align-items: center;
            gap: .55rem;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.12);
            backdrop-filter: blur(12px);
            color: #fff;
            font-size: .8rem;
            font-weight: 600;
            padding: .6rem 1rem;
            border-radius: 40px;
            z-index: 2;
            animation: floatCard 4s ease-in-out infinite;
            white-space: nowrap;
        }

        .float-card svg { color: var(--accent-light); flex-shrink: 0; }

        .float-card--1 { top: 6%; left: 8%; animation-delay: 0s; }
        .float-card--2 { top: 14%; right: 6%; animation-delay: .8s; }
        .float-card--3 { bottom: 18%; left: 6%; animation-delay: 1.4s; }

        @keyframes floatCard {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .illustration-wrap {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.6rem;
            text-align: center;
        }

        .nurse-svg {
            width: min(320px, 80%);
            height: auto;
            filter: drop-shadow(0 20px 40px rgba(26,86,219,.5));
            animation: nurseFloat 5s ease-in-out infinite;
        }

        @keyframes nurseFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .brand-tagline {
            text-align: center;
        }

        .brand-name {
            font-size: 2.2rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 4px;
            text-transform: uppercase;
            background: linear-gradient(90deg, #fff 0%, var(--accent-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-sub {
            color: rgba(255,255,255,.55);
            font-size: .95rem;
            font-weight: 400;
            margin-top: .3rem;
        }

        .stats-row {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 50px;
            padding: .9rem 2rem;
            backdrop-filter: blur(10px);
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .15rem;
        }

        .stat-num {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--accent-light);
        }

        .stat-label {
            font-size: .72rem;
            color: rgba(255,255,255,.45);
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .stat-divider {
            width: 1px;
            height: 32px;
            background: rgba(255,255,255,.15);
        }

        /* Panel derecho */
        .login-panel--right {
            flex: .9;
            background: var(--white);
            min-height: 100vh;
            padding: 2rem 1.5rem;
            overflow-y: auto;
        }

        .form-container {
            width: 100%;
            max-width: 420px;
            margin: auto;
            padding: 2rem 0;
        }

        .mobile-logo {
            display: none;
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: 3px;
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            margin-bottom: 1.2rem;
            box-shadow: 0 8px 20px rgba(26,86,219,.35);
        }

        .form-header {
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: .35rem;
        }

        .form-subtitle {
            color: var(--gray-400);
            font-size: .95rem;
        }

        /* Selector de rol */
        .role-selector {
            display: flex;
            background: var(--gray-100);
            border-radius: 12px;
            padding: 5px;
            margin-bottom: 1.8rem;
            gap: 4px;
            border: 1px solid var(--gray-200);
        }

        .role-option {
            flex: 1;
            position: relative;
            cursor: pointer;
        }

        .role-option input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .role-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            padding: .55rem .3rem;
            border-radius: 8px;
            font-size: .82rem;
            font-weight: 600;
            color: var(--gray-400);
            transition: var(--transition);
            white-space: nowrap;
            user-select: none;
        }

        .role-btn svg {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
        }

        .role-option input:checked + .role-btn {
            background: var(--white);
            color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        .role-option:hover .role-btn {
            color: var(--primary-light);
        }

        .form-group {
            margin-bottom: 1.3rem;
        }

        .form-label {
            display: block;
            font-size: .875rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: .5rem;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: var(--gray-400);
            display: flex;
            align-items: center;
            pointer-events: none;
            transition: var(--transition);
        }

        .form-input {
            width: 100%;
            padding: .9rem 3rem .9rem 2.8rem;
            background: var(--gray-50);
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: .95rem;
            transition: var(--transition);
            outline: none;
        }

        .form-input::placeholder {
            color: var(--gray-400);
        }

        .form-input:focus {
            border-color: var(--primary);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(26,86,219,.12);
        }

        .input-wrap:has(.form-input:focus) .input-icon {
            color: var(--primary);
        }

        .form-input.is-invalid {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(239,68,68,.1);
        }

        .error-message {
            color: var(--danger);
            font-size: .8rem;
            margin-top: .45rem;
            display: none;
            padding-left: .25rem;
        }

        .form-group.has-error .error-message {
            display: block;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray-400);
            display: flex;
            align-items: center;
            padding: 0;
            transition: var(--transition);
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        .toggle-password.active {
            color: var(--primary);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.8rem;
            font-size: .875rem;
        }

        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .forgot-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: .5rem;
            cursor: pointer;
            color: var(--gray-600);
            user-select: none;
        }

        .checkbox-container input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .checkmark {
            width: 18px;
            height: 18px;
            background: var(--gray-100);
            border: 1.5px solid var(--gray-200);
            border-radius: 5px;
            flex-shrink: 0;
            position: relative;
            transition: var(--transition);
        }

        .checkbox-container:hover .checkmark {
            border-color: var(--primary-light);
        }

        .checkbox-container input:checked ~ .checkmark {
            background: var(--primary);
            border-color: var(--primary);
        }

        .checkmark::after {
            content: '';
            position: absolute;
            display: none;
            left: 5px;
            top: 1px;
            width: 5px;
            height: 10px;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .checkbox-container input:checked ~ .checkmark::after {
            display: block;
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            border-radius: var(--radius);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: .03em;
            color: #fff;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            border: none;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: .5rem;
            box-shadow: 0 6px 20px rgba(26,86,219,.35);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            opacity: 0;
            transition: var(--transition);
        }

        .btn-submit:hover::before { opacity: 1; }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(26,86,219,.45);
        }

        .btn-submit:active { transform: translateY(0); }

        .btn-text, .loader { position: relative; z-index: 1; }

        .loader {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .9s linear infinite;
            display: none;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .is-loading .loader { display: inline-block; }
        .is-loading .btn-text { display: none; }

        .form-footer {
            text-align: center;
            margin-top: 1.6rem;
            font-size: .875rem;
            color: var(--gray-400);
        }

        .form-footer a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }

        .form-footer a:hover { text-decoration: underline; }

        /* Responsive */
        @media (max-width: 900px) {
            .login-panel--left {
                display: none;
            }

            .login-panel--right {
                flex: 1;
                background: linear-gradient(155deg, #0e2a50 0%, #0f172a 100%);
                padding: 2rem 1.5rem;
            }

            .form-container {
                background: var(--white);
                border-radius: 24px;
                padding: 2.5rem 2rem;
                box-shadow: var(--shadow-lg);
                max-width: 460px;
                margin: auto;
            }

            .mobile-logo {
                display: block;
            }
        }

        @media (max-width: 480px) {
            .login-panel--right {
                padding: 1rem;
                align-items: flex-start;
                padding-top: 2rem;
            }

            .form-container {
                padding: 2rem 1.4rem;
                border-radius: 20px;
            }

            .form-title {
                font-size: 1.4rem;
            }

            .role-btn {
                font-size: .75rem;
                gap: .25rem;
                padding: .5rem .2rem;
            }

            .role-btn svg { display: none; }

            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: .75rem;
            }

            .float-card--1,
            .float-card--2,
            .float-card--3 {
                display: none;
            }
        }

        :focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">

        <!-- Panel Izquierdo: Imagen / Branding -->
        <div class="login-panel login-panel--left">
            <div class="panel-overlay"></div>

            <!-- Floating cards decorativas -->
            <div class="float-card float-card--1">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                <span>Monitoreo activo</span>
            </div>
            <div class="float-card float-card--2">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>1,240 Pacientes</span>
            </div>
            <div class="float-card float-card--3">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span>Progreso +18%</span>
            </div>

            <!-- Ilustración SVG Enfermería -->
            <div class="illustration-wrap">
                <svg class="nurse-svg" viewBox="0 0 400 480" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Cruz médica fondo -->
                    <rect x="172" y="30" width="56" height="160" rx="16" fill="white" fill-opacity="0.08"/>
                    <rect x="120" y="82" width="160" height="56" rx="16" fill="white" fill-opacity="0.08"/>

                    <!-- Cuerpo / bata -->
                    <ellipse cx="200" cy="370" rx="72" ry="85" fill="#1e4d8c" fill-opacity="0.9"/>
                    <ellipse cx="200" cy="370" rx="72" ry="85" fill="url(#bodyGrad)"/>

                    <!-- Brazos -->
                    <path d="M128 300 Q95 340 105 390" stroke="#f5d0b0" stroke-width="28" stroke-linecap="round"/>
                    <path d="M272 300 Q305 340 295 390" stroke="#f5d0b0" stroke-width="28" stroke-linecap="round"/>

                    <!-- Guantes -->
                    <circle cx="104" cy="395" r="20" fill="#60b8e0"/>
                    <circle cx="296" cy="395" r="20" fill="#60b8e0"/>

                    <!-- Estetoscopio -->
                    <path d="M165 295 Q155 320 148 345 Q142 365 155 375 Q168 385 178 370" stroke="#60b8e0" stroke-width="5" stroke-linecap="round" fill="none"/>
                    <circle cx="178" cy="370" r="10" fill="#60b8e0" stroke="white" stroke-width="2"/>

                    <!-- Cuello -->
                    <rect x="183" y="245" width="34" height="55" rx="17" fill="#f5d0b0"/>

                    <!-- Cabeza -->
                    <ellipse cx="200" cy="210" rx="52" ry="56" fill="#f5d0b0"/>

                    <!-- Gorro de enfermería -->
                    <path d="M148 185 L200 145 L252 185 Z" fill="white" fill-opacity="0.95"/>
                    <rect x="148" y="183" width="104" height="14" rx="7" fill="white"/>
                    <rect x="170" y="160" width="60" height="8" rx="4" fill="#e63946"/>

                    <!-- Cruz en gorro -->
                    <rect x="195" y="154" width="10" height="26" rx="3" fill="#e63946"/>
                    <rect x="186" y="163" width="28" height="10" rx="3" fill="#e63946"/>

                    <!-- Ojos -->
                    <ellipse cx="185" cy="208" rx="6" ry="7" fill="#2d2d2d"/>
                    <ellipse cx="215" cy="208" rx="6" ry="7" fill="#2d2d2d"/>
                    <circle cx="187" cy="206" r="2" fill="white"/>
                    <circle cx="217" cy="206" r="2" fill="white"/>

                    <!-- Sonrisa -->
                    <path d="M187 228 Q200 240 213 228" stroke="#c4795a" stroke-width="2.5" stroke-linecap="round" fill="none"/>

                    <!-- Bata: línea central y bolsillo -->
                    <line x1="200" y1="300" x2="200" y2="450" stroke="white" stroke-width="2" stroke-opacity="0.25"/>
                    <rect x="215" y="340" width="36" height="30" rx="6" fill="white" fill-opacity="0.15" stroke="white" stroke-width="1" stroke-opacity="0.3"/>
                    <line x1="233" y1="347" x2="233" y2="363" stroke="#60b8e0" stroke-width="2.5" stroke-linecap="round"/>
                    <line x1="226" y1="355" x2="240" y2="355" stroke="#60b8e0" stroke-width="2.5" stroke-linecap="round"/>

                    <!-- Portapapeles en mano izquierda -->
                    <rect x="75" y="350" width="44" height="58" rx="5" fill="white" fill-opacity="0.9"/>
                    <rect x="91" y="344" width="12" height="10" rx="3" fill="#aaa"/>
                    <line x1="82" y1="368" x2="112" y2="368" stroke="#ccc" stroke-width="2"/>
                    <line x1="82" y1="378" x2="105" y2="378" stroke="#ccc" stroke-width="2"/>
                    <line x1="82" y1="388" x2="112" y2="388" stroke="#ccc" stroke-width="2"/>

                    <!-- Pulso latido fondo -->
                    <polyline points="30,430 60,430 75,400 90,460 110,420 130,430 160,430" stroke="white" stroke-width="2.5" fill="none" stroke-opacity="0.2" stroke-linecap="round" stroke-linejoin="round"/>
                    <polyline points="240,430 270,430 285,400 300,460 320,420 340,430 370,430" stroke="white" stroke-width="2.5" fill="none" stroke-opacity="0.2" stroke-linecap="round" stroke-linejoin="round"/>

                    <defs>
                        <linearGradient id="bodyGrad" x1="128" y1="285" x2="272" y2="455" gradientUnits="userSpaceOnUse">
                            <stop offset="0%" stop-color="#2d6dbf" stop-opacity="0.6"/>
                            <stop offset="100%" stop-color="#1a3a5c" stop-opacity="0.4"/>
                        </linearGradient>
                    </defs>
                </svg>

                <div class="brand-tagline">
                    <h1 class="brand-name">DENTISOFT</h1>
                    <p class="brand-sub">Sistema De Gestión De Consultorios Odontológicos</p>
                </div>

                <!-- Stats row -->
                <div class="stats-row">
                    <div class="stat-item">
                        <span class="stat-num">98%</span>
                        <span class="stat-label">Satisfacción</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <span class="stat-num">3</span>
                        <span class="stat-label">Módulos</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <span class="stat-num">18</span>
                        <span class="stat-label">Consultorios</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Derecho: Formulario -->
        <div class="login-panel login-panel--right">
            <div class="form-container">

                <!-- Logo móvil -->
                <div class="mobile-logo">DENTISOFT</div>

                <div class="form-header">
                    <div class="form-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <h2 class="form-title">Inicio De Sesión</h2>
                    <p class="form-subtitle">Ingresa tus credenciales asignadas para continuar</p>
                </div>

                <form id="loginForm" method="POST" action="login.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <!-- Selector de Rol -->
                    <div class="role-selector" role="radiogroup" aria-label="Selecciona tu rol">
                        <label class="role-option">
                            <input type="radio" name="role" value="odontologo" checked>
                            <span class="role-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                Odontólogo
                            </span>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="asistente">
                            <span class="role-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                Asistente
                            </span>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="admin">
                            <span class="role-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                                Admin
                            </span>
                        </label>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; color: #fca5a5; padding: 12px 16px; font-size: 0.9rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-group" id="emailGroup">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            </span>
                            <input type="email" id="email" class="form-input" name="email" placeholder="correo@odontologia.com" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="error-message">Por favor ingresa un correo válido.</div>
                    </div>

                    <div class="form-group" id="passwordGroup">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            <input type="password" id="password" class="form-input" name="password" placeholder="••••••••" autocomplete="current-password" minlength="6" required>
                            <button type="button" class="toggle-password" id="togglePassword" aria-label="Mostrar contraseña">
                                <svg class="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <div class="error-message">La contraseña es obligatoria (mín. 6 caracteres).</div>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-container">
                            <input type="checkbox" id="rememberMe" name="remember">
                            <span class="checkmark"></span>
                            Recordarme
                        </label>
                        <a href="#" class="forgot-link">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="submit" id="submitBtn" class="btn-submit">
                        <span class="btn-text">Iniciar Sesión</span>
                        <span class="loader"></span>
                    </button>
                </form>

                <p class="form-footer">
                    ¿Necesitas acceso? <a href="#">Contacta a tu administrador</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Toggle mostrar/ocultar contraseña
        document.getElementById('togglePassword').addEventListener('click', function() {
            const input = document.getElementById('password');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            this.classList.toggle('active');
        });

        // Submit con spinner
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.classList.add('is-loading');
            btn.disabled = true;
        });
    </script>
</body>
</html>

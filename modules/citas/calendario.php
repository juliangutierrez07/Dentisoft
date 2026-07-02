<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('citas.ver');

$paginaTitulo = 'Calendario de Citas';
$cssAdicional = 'citas-premium.css';
$jsAdicional = 'calendario-citas.js';

$fechaInicial = trim((string) ($_GET['desde'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicial)) {
    $fechaInicial = date('Y-m-d');
}

$today = date('Y-m-d');

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="container-fluid py-4 citas-page calendar-page">
    <section class="citas-hero calendar-hero mb-4">
        <div class="citas-hero-main">
            <span class="citas-eyebrow"><i class="bi bi-calendar3-week"></i> Agenda premium</span>
            <h1>Calendario de Citas</h1>
            <p>Gestiona la agenda clinica con vistas mensual, semanal y diaria, estados visuales y sincronizacion automatica.</p>
        </div>
        <div class="citas-hero-actions">
            <a href="index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Volver</a>
            <a href="crear.php?fecha=<?= h($today) ?>&volver=calendario" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nueva cita</a>
        </div>
    </section>

    <section class="stats-grid calendar-stats mb-4" aria-label="Resumen calendario">
        <article class="stat-card stat-today">
            <span class="stat-icon"><i class="bi bi-calendar-day"></i></span>
            <div><span class="stat-label">Citas hoy</span><strong data-calendar-stat="hoy">0</strong></div>
        </article>
        <article class="stat-card stat-pending">
            <span class="stat-icon"><i class="bi bi-hourglass-split"></i></span>
            <div><span class="stat-label">Pendientes</span><strong data-calendar-stat="pendiente">0</strong></div>
        </article>
        <article class="stat-card stat-confirmed">
            <span class="stat-icon"><i class="bi bi-check2-circle"></i></span>
            <div><span class="stat-label">Confirmadas</span><strong data-calendar-stat="confirmada">0</strong></div>
        </article>
        <article class="stat-card stat-canceled">
            <span class="stat-icon"><i class="bi bi-x-circle"></i></span>
            <div><span class="stat-label">Canceladas</span><strong data-calendar-stat="cancelada">0</strong></div>
        </article>
    </section>

    <section class="calendar-toolbar card bg-dark border-secondary shadow-sm mb-4">
        <div class="card-body">
            <div class="calendar-toolbar-grid fullcalendar-toolbar-grid">
                <div class="calendar-search">
                    <label for="calendarSearch">Buscar cita</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input id="calendarSearch" type="search" class="form-control" placeholder="Paciente, odontologo o motivo">
                    </div>
                </div>
                <div class="calendar-date-field">
                    <label for="calendarGoToDate">Ir a fecha</label>
                    <input id="calendarGoToDate" type="date" class="form-control" value="<?= h($fechaInicial) ?>">
                </div>
                <button type="button" class="btn btn-primary calendar-update" id="calendarRefresh">
                    <i class="bi bi-arrow-repeat"></i> Actualizar
                </button>
                <a href="crear.php?fecha=<?= h($today) ?>&volver=calendario" class="btn btn-outline-light calendar-update">
                    <i class="bi bi-plus-circle"></i> Crear cita
                </a>
            </div>
            <div class="calendar-quickbar" aria-label="Filtros de estado">
                <button type="button" class="filter-chip active" data-calendar-filter="all">Todas</button>
                <button type="button" class="filter-chip" data-calendar-filter="pendiente">Pendientes</button>
                <button type="button" class="filter-chip" data-calendar-filter="confirmada">Confirmadas</button>
                <button type="button" class="filter-chip" data-calendar-filter="cancelada">Canceladas</button>
                <button type="button" class="filter-chip" data-calendar-filter="atendida">Finalizadas</button>
            </div>
        </div>
    </section>

    <section class="calendar-shell fullcalendar-shell is-loading" id="calendarShell" aria-label="Calendario de citas" data-initial-date="<?= h($fechaInicial) ?>" data-today="<?= h($today) ?>">
        <div class="calendar-skeleton fullcalendar-skeleton" aria-hidden="true">
            <span></span><span></span><span></span>
        </div>
        <div id="dentisoftFullCalendar"></div>
    </section>

    <a href="crear.php?fecha=<?= h($today) ?>&volver=calendario" class="calendar-fab" aria-label="Nueva cita"><i class="bi bi-plus-lg"></i><span>Nueva cita</span></a>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.20/locales/es.global.min.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

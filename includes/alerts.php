<?php
/**
 * Componente de Alertas — DentiSoft 1.0
 * Muestra mensajes flash almacenados en sesión
 */
$alerta = getAlerta();
if ($alerta):
?>
<div class="alert alert-<?= htmlspecialchars($alerta['tipo']) ?> alert-dismissible fade show shadow-sm animate-fadeIn" role="alert">
    <?php
    $iconos = [
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-circle-fill',
        'info'    => 'bi-info-circle-fill',
    ];
    $icono = $iconos[$alerta['tipo']] ?? 'bi-info-circle-fill';
    ?>
    <i class="bi <?= $icono ?> me-2"></i>
    <?= htmlspecialchars($alerta['mensaje']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
</div>
<?php endif; ?>

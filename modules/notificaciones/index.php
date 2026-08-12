<?php
/**
 * Módulo Notificaciones — listado completo.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requireLogin();

$paginaTitulo = 'Notificaciones';

$usuario = currentUser();
$userId = $_SESSION['usuario_id'];

// Normaliza el url_accion para el entorno actual (quita prefijo /DentiSoft1.0 embebido).
function urlAccionNotif(?string $u): string {
    $u = trim((string) $u);
    if ($u === '') return '';
    if (preg_match('#^https?://#i', $u)) return $u;
    $u = preg_replace('#^/DentiSoft1\.0(?=/)#', '', $u);
    if ($u === '' || $u[0] !== '/') $u = '/' . $u;
    if (BASE_URL !== '' && strpos($u, BASE_URL . '/') !== 0) $u = BASE_URL . $u;
    return $u;
}

function tiempoRelNotif(?string $fecha): string {
    $ts = $fecha ? strtotime($fecha) : false;
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60)      return 'Ahora mismo';
    if ($diff < 3600)    return floor($diff / 60) . ' min';
    if ($diff < 86400)   return floor($diff / 3600) . 'h';
    if ($diff < 604800)  return floor($diff / 86400) . 'd';
    return date('d/m/Y', $ts);
}

$notificaciones = [];
$noLeidas = 0;
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, tipo, titulo, mensaje, leida, url_accion, created_at
        FROM notificaciones
        WHERE usuario_id = :uid OR usuario_id IS NULL
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->execute([':uid' => $userId]);
    $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($notificaciones as $n) {
        if ((int) $n['leida'] === 0) $noLeidas++;
    }
} catch (PDOException $e) {
    error_log('Notificaciones módulo error: ' . $e->getMessage());
}

$iconos = [
    'cita'    => ['bi-calendar-check', 'info'],
    'pago'    => ['bi-cash-coin', 'success'],
    'sistema' => ['bi-gear', 'primary'],
    'alerta'  => ['bi-exclamation-triangle', 'warning'],
];

require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.notif-page{max-width:860px;margin:0 auto}
.notif-page .np-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:22px}
.notif-page .np-eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:.78rem;font-weight:600;color:#2FE0B0;text-transform:uppercase;letter-spacing:.05em}
.notif-page h1{font-family:'Fraunces',serif;font-weight:500;font-size:1.9rem;color:#EEF2F4;margin:6px 0 2px}
.notif-page .np-sub{color:rgba(255,255,255,.55);font-size:.92rem}
.np-btn{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(139,126,255,.4);background:transparent;color:#B9B2FF;font-weight:600;font-size:.85rem;padding:9px 14px;border-radius:10px;cursor:pointer;transition:background .18s ease,color .18s ease}
.np-btn:hover{background:rgba(139,126,255,.16);color:#fff}
.np-btn[disabled]{opacity:.4;cursor:not-allowed}
.np-card{background:#0F141C;border:1px solid rgba(255,255,255,.10);border-radius:16px;overflow:hidden;box-shadow:0 18px 50px rgba(0,0,0,.4)}
.notif-row{display:flex;gap:14px;align-items:flex-start;padding:16px 18px;border-bottom:1px solid rgba(255,255,255,.06);text-decoration:none;transition:background .18s ease}
.notif-row:last-child{border-bottom:none}
.notif-row:hover{background:rgba(255,255,255,.04)}
.notif-row.unread{background:rgba(139,126,255,.06)}
.nr-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;font-size:1.05rem;box-shadow:0 6px 16px rgba(0,0,0,.3)}
.nr-icon.info{background:linear-gradient(135deg,#3B82F6,#2563EB)}
.nr-icon.success{background:linear-gradient(135deg,#2FE0B0,#1F9E7C)}
.nr-icon.primary{background:linear-gradient(135deg,#8B7EFF,#6D5EF5)}
.nr-icon.warning{background:linear-gradient(135deg,#F5B94F,#D9932F)}
.nr-body{flex:1;min-width:0}
.nr-title{font-weight:700;color:#EEF2F4;font-size:.95rem;display:flex;align-items:center;gap:8px}
.nr-dot{width:8px;height:8px;border-radius:50%;background:#2FE0B0;flex-shrink:0}
.nr-msg{color:rgba(255,255,255,.62);font-size:.86rem;margin-top:4px;line-height:1.5}
.nr-time{color:rgba(255,255,255,.4);font-size:.76rem;margin-top:6px;display:inline-flex;align-items:center;gap:5px}
.nr-go{color:rgba(255,255,255,.28);font-size:1rem;align-self:center;transition:color .18s ease,transform .18s ease}
.notif-row:hover .nr-go{color:#2FE0B0;transform:translateX(2px)}
.np-empty{text-align:center;padding:60px 20px;color:rgba(255,255,255,.5)}
.np-empty i{font-size:2.6rem;opacity:.35;display:block;margin-bottom:12px}
</style>

<div class="container-fluid py-4">
    <div class="notif-page">
        <div class="np-head">
            <div>
                <span class="np-eyebrow"><i class="bi bi-bell"></i> Centro de notificaciones</span>
                <h1>Notificaciones</h1>
                <div class="np-sub">
                    <?php if ($noLeidas > 0): ?>
                        Tienes <strong style="color:#2FE0B0"><?= $noLeidas ?></strong> sin leer.
                    <?php else: ?>
                        Estás al día. No tienes notificaciones sin leer.
                    <?php endif; ?>
                </div>
            </div>
            <button type="button" class="np-btn" id="btnMarcarTodasPage" <?= $noLeidas === 0 ? 'disabled' : '' ?>>
                <i class="bi bi-check2-all"></i> Marcar todas como leídas
            </button>
        </div>

        <div class="np-card">
            <?php if (empty($notificaciones)): ?>
                <div class="np-empty">
                    <i class="bi bi-bell-slash"></i>
                    No tienes notificaciones.
                </div>
            <?php else: ?>
                <?php foreach ($notificaciones as $n):
                    [$icono, $color] = $iconos[$n['tipo']] ?? $iconos['sistema'];
                    $destino = urlAccionNotif($n['url_accion']);
                    $leida = (int) $n['leida'] === 1;
                    $attrs = 'class="notif-row' . ($leida ? '' : ' unread') . '" data-id="' . (int) $n['id'] . '"';
                    if ($destino !== '') {
                        $attrs .= ' href="' . htmlspecialchars($destino, ENT_QUOTES) . '"';
                    } else {
                        $attrs .= ' href="#" data-nourl="1"';
                    }
                ?>
                <a <?= $attrs ?>>
                    <div class="nr-icon <?= $color ?>"><i class="bi <?= $icono ?>"></i></div>
                    <div class="nr-body">
                        <div class="nr-title">
                            <?= htmlspecialchars($n['titulo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            <?php if (!$leida): ?><span class="nr-dot" title="Sin leer"></span><?php endif; ?>
                        </div>
                        <div class="nr-msg"><?= htmlspecialchars($n['mensaje'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                        <div class="nr-time"><i class="bi bi-clock-history"></i><?= htmlspecialchars(tiempoRelNotif($n['created_at'])) ?></div>
                    </div>
                    <?php if ($destino !== ''): ?><i class="bi bi-chevron-right nr-go"></i><?php endif; ?>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var apiBase = '<?= BASE_URL ?>/api/notificaciones_api.php';

    var btnTodas = document.getElementById('btnMarcarTodasPage');
    if (btnTodas) {
        btnTodas.addEventListener('click', function () {
            btnTodas.disabled = true;
            fetch(apiBase + '?action=marcar_todas', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function () { location.reload(); })
                .catch(function () { location.reload(); });
        });
    }

    document.querySelectorAll('.notif-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            var id = parseInt(row.getAttribute('data-id') || '0', 10);
            var noUrl = row.getAttribute('data-nourl') === '1';
            if (id > 0) {
                try {
                    fetch(apiBase + '?action=marcar_leida', {
                        method: 'POST', keepalive: true,
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    });
                } catch (err) { /* no-op */ }
            }
            if (noUrl) { e.preventDefault(); location.reload(); }
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

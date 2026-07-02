    </main><!-- fin main-content -->

    <!-- ═══ SCRIPTS ═══ -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        // Variable global con el BASE_URL y CSRF token para usar en JS
        const BASE_URL = '<?= BASE_URL ?>';
        const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    </script>
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
    <?php if (isset($jsAdicional)): ?>
        <script src="<?= BASE_URL ?>/assets/js/<?= $jsAdicional ?>"></script>
    <?php endif; ?>
</body>
</html>

</main>

<footer class="site-footer mt-auto">
    <div class="container py-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="footer-logo-wrap">
                    <img src="<?= e(url('assets/img/logo.png')) ?>" alt="Logo <?= e(APP_NAME) ?>" class="footer-logo">
                </span>
                <div>
                    <div class="fw-bold text-white"><?= e(APP_NAME) ?></div>
                    <div class="small footer-tag"><?= e(APP_TAGLINE) ?></div>
                </div>
            </div>
            <div class="small footer-muted text-md-end">
                Hệ thống quản lý thành viên, sự kiện, đăng ký và điểm danh<br>
                &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> – Bài tập lớn Lập trình PHP cơ bản
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

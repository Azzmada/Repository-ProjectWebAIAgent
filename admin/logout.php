<?php
/**
 * Admin logout (POST + CSRF).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (!is_post()) {
    redirect(is_admin_logged_in() ? base_url('admin/dashboard.php') : base_url('admin/index.php'));
}
verify_csrf();

logout_admin();
flash('info', 'Anda telah keluar dari panel admin.');
redirect(base_url('admin/index.php'));

<?php
/**
 * User logout.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

logout_user();
flash('info', 'Anda telah keluar dari akun.');
redirect(page_url('home'));

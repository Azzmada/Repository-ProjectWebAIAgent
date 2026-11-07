<?php
/**
 * Cancel an unpaid booking.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';

require_login();

$user = auth_user();
if (!$user) {
    redirect(base_url('auth/login.php'));
}
if (!is_post()) {
    redirect(base_url('user/my-bookings.php'));
}
verify_csrf();

$code = input('code');
$booking = $code !== '' ? get_booking_for_user($code, (int) $user['id']) : false;
if (!$booking) {
    flash('error', 'Pesanan tidak ditemukan atau bukan milik akun Anda.');
    redirect(base_url('user/my-bookings.php'));
}

if ($booking['status'] !== 'pending') {
    flash('error', 'Hanya pesanan yang belum dibayar yang dapat dibatalkan.');
    redirect(base_url('user/booking-detail.php?code=' . urlencode($code)));
}

try {
    Database::begin();
    Database::execute('UPDATE bookings SET status = "cancelled" WHERE id = ? AND status = "pending"', [(int) $booking['id']]);
    Database::execute('UPDATE payments SET status = "failed" WHERE booking_id = ? AND status = "pending"', [(int) $booking['id']]);

    // Give the promo quota back if one was used.
    if ((int) ($booking['promo_id'] ?? 0) > 0) {
        $usage = Database::fetch('SELECT id FROM promo_usage WHERE booking_id = ? LIMIT 1', [(int) $booking['id']]);
        if ($usage) {
            Database::execute('DELETE FROM promo_usage WHERE booking_id = ?', [(int) $booking['id']]);
            Database::execute('UPDATE promos SET used_count = GREATEST(used_count - 1, 0) WHERE id = ?', [(int) $booking['promo_id']]);
        }
    }

    Database::commit();
} catch (Throwable $e) {
    Database::rollback();
    error_log('[booking] cancel failed: ' . $e->getMessage());
    flash('error', 'Pesanan gagal dibatalkan. Silakan coba lagi.');
    redirect(base_url('user/booking-detail.php?code=' . urlencode($code)));
}

try {
    send_booking_status_update((int) $booking['id'], 'pending');
} catch (Throwable $e) {
    error_log('[booking] cancel mail failed: ' . $e->getMessage());
}

flash('success', 'Pesanan ' . $booking['booking_code'] . ' berhasil dibatalkan.');
redirect(base_url('user/booking-detail.php?code=' . urlencode($code)));

<?php
/**
 * Create the booking atomically.
 *
 * Runs as a POST-only endpoint from the review page. Everything is
 * re-validated server-side inside a database transaction: flights, prices,
 * passengers and promo quota. Only then is the booking + legs + passengers
 * + promo usage written and the customer redirected to payment.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';

require_login();
expire_stale_bookings();

$user = auth_user();
if (!$user) {
    redirect(base_url('auth/login.php'));
}

if (!is_post()) {
    redirect(base_url('booking/review.php'));
}
verify_csrf();

$draft = booking_active_draft();
if (!$draft) {
    flash('info', 'Sesi pemesanan Anda sudah berakhir. Silakan pilih penerbangan kembali.');
    redirect(page_url('pesawat'));
}

$legsError = null;
$legs = booking_draft_legs($draft, $legsError);
if (!$legs || $legsError !== null || count($legs) !== count((array) ($draft['legs'] ?? []))) {
    clear_booking_draft();
    flash('error', $legsError ?: 'Penerbangan pada sesi Anda sudah tidak tersedia.');
    redirect(page_url('pesawat'));
}

$counts = booking_normalize_counts(
    (int) ($draft['counts']['adult'] ?? 1),
    (int) ($draft['counts']['child'] ?? 0),
    (int) ($draft['counts']['infant'] ?? 0)
);
$cabin = (string) ($draft['cabin'] ?? 'Ekonomi');
if (!in_array($cabin, ['Ekonomi', 'Bisnis', 'First Class'], true)) {
    $cabin = 'Ekonomi';
}

$passengers = [];
foreach ((array) ($draft['passengers'] ?? []) as $row) {
    if (!is_array($row)) {
        continue;
    }
    $name = clean((string) ($row['name'] ?? ''));
    $type = (string) ($row['type'] ?? 'adult');
    if (!in_array($type, ['adult', 'child', 'infant'], true) || mb_strlen($name) < 2) {
        continue;
    }
    $passengers[] = ['name' => $name, 'type' => $type];
}

if (count($passengers) !== total_pax($counts)) {
    flash('error', 'Data penumpang tidak lengkap. Silakan isi kembali.');
    redirect(base_url('booking/passengers.php'));
}

$contact = [
    'name'  => clean((string) ($draft['contact']['name'] ?? $user['name'])),
    'email' => strtolower(clean((string) ($draft['contact']['email'] ?? $user['email']))),
    'phone' => clean((string) ($draft['contact']['phone'] ?? $user['phone'])),
];
if (!valid_email($contact['email']) || !valid_phone($contact['phone'])) {
    flash('error', 'Kontak pemesanan tidak valid. Silakan periksa kembali.');
    redirect(base_url('booking/passengers.php'));
}

$basePricing = booking_pricing($legs, $counts);
$promoState = booking_draft_promo($draft, $basePricing['subtotal'], (int) $user['id']);

if ($promoState['code'] !== '' && !$promoState['valid']) {
    $draft['promo_code'] = '';
    booking_store_draft($draft);
    flash('error', $promoState['message'] ?: 'Kode promo tidak dapat digunakan lagi.');
    redirect(base_url('booking/review.php'));
}

$promo = $promoState['valid'] ? $promoState['promo'] : null;
$pricing = booking_pricing($legs, $counts, $promo);

$expiryMinutes = setting_int('booking_expiry_minutes', BOOKING_EXPIRE_MINUTES);
if ($expiryMinutes < 15) {
    $expiryMinutes = BOOKING_EXPIRE_MINUTES;
}
$expiresAt = date('Y-m-d H:i:s', strtotime('+' . $expiryMinutes . ' minutes'));

$bookingId = 0;
$bookingCode = '';

try {
    Database::begin();

    // Lock the promo row so two checkouts cannot oversell the last quota.
    if ($promo !== null) {
        $lockedPromo = Database::fetch('SELECT * FROM promos WHERE id = ? FOR UPDATE', [(int) $promo['id']]);
        if (!$lockedPromo) {
            throw new RuntimeException('Kode promo tidak ditemukan.');
        }
        $recheck = validate_promo((string) $lockedPromo['code'], $basePricing['subtotal'], (int) $user['id']);
        if (!$recheck['ok']) {
            throw new RuntimeException($recheck['message']);
        }
        if ((int) $lockedPromo['max_usage'] > 0 && (int) $lockedPromo['used_count'] >= (int) $lockedPromo['max_usage']) {
            throw new RuntimeException('Kuota kode promo ini sudah habis.');
        }
        $promo = $recheck['promo'] ?? $lockedPromo;
        $pricing = booking_pricing($legs, $counts, $promo);
    }

    $bookingCode = generate_booking_code();
    $bookingId = Database::insert(
        'INSERT INTO bookings
            (user_id, booking_code, flight_id, subtotal, discount, total_price, status, travel_date, cabin_class, pax, promo_id, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, "pending", ?, ?, ?, ?, ?)',
        [
            (int) $user['id'],
            $bookingCode,
            (int) $legs[0]['flight_id'],
            $pricing['subtotal'],
            $pricing['discount'],
            $pricing['total'],
            $legs[0]['date'],
            $cabin,
            total_pax($counts),
            $promo !== null ? (int) $promo['id'] : null,
            $expiresAt,
        ]
    );

    foreach ($legs as $index => $leg) {
        Database::insert(
            'INSERT INTO booking_flights (booking_id, flight_id, travel_date, leg_order, price) VALUES (?, ?, ?, ?, ?)',
            [$bookingId, (int) $leg['flight_id'], $leg['date'], $index + 1, (float) $leg['price']]
        );
    }

    foreach ($passengers as $index => $passenger) {
        $isPrimary = $index === 0;
        Database::insert(
            'INSERT INTO booking_passengers (booking_id, name, type, email, phone) VALUES (?, ?, ?, ?, ?)',
            [
                $bookingId,
                $passenger['name'],
                $passenger['type'],
                $isPrimary ? $contact['email'] : null,
                $isPrimary ? $contact['phone'] : null,
            ]
        );
    }

    if ($promo !== null) {
        Database::insert(
            'INSERT INTO promo_usage (promo_id, booking_id) VALUES (?, ?)',
            [(int) $promo['id'], $bookingId]
        );
        Database::execute('UPDATE promos SET used_count = used_count + 1 WHERE id = ?', [(int) $promo['id']]);
    }

    Database::commit();
} catch (Throwable $e) {
    Database::rollback();
    error_log('[booking] create failed: ' . $e->getMessage());
    flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Pesanan gagal dibuat. Silakan coba lagi.');
    redirect(base_url('booking/review.php'));
}

clear_booking_draft();

// E-mail failures must never break the booking flow.
try {
    send_booking_pending($bookingId);
} catch (Throwable $e) {
    error_log('[booking] pending mail failed: ' . $e->getMessage());
}

flash('success', 'Pesanan ' . $bookingCode . ' berhasil dibuat. Selesaikan pembayaran sebelum batas waktu.');
redirect(base_url('booking/payment.php?code=' . urlencode($bookingCode)));

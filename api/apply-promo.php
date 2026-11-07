<?php
/**
 * AJAX promo-code endpoint.
 *
 * POST (JSON or form): code + CSRF token.
 * The subtotal is taken from the session booking draft — never from the
 * browser — so the API cannot be used to fake a discount.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Gunakan metode POST.'], 405);
}
if (!csrf_valid()) {
    json_response(['ok' => false, 'message' => 'Sesi kedaluwarsa. Muat ulang halaman.'], 419);
}
if (!is_logged_in()) {
    json_response(['ok' => false, 'message' => 'Silakan masuk untuk memakai promo.'], 401);
}

$user = auth_user();
$draft = booking_active_draft();
if (!$draft) {
    json_response(['ok' => false, 'message' => 'Sesi pemesanan tidak ditemukan.'], 404);
}

$legsError = null;
$legs = booking_draft_legs($draft, $legsError);
if (!$legs || $legsError !== null) {
    json_response(['ok' => false, 'message' => $legsError ?: 'Penerbangan tidak valid.'], 422);
}

$counts = booking_normalize_counts(
    (int) ($draft['counts']['adult'] ?? 1),
    (int) ($draft['counts']['child'] ?? 0),
    (int) ($draft['counts']['infant'] ?? 0)
);
$base = booking_pricing($legs, $counts);

$code = strtoupper(clean((string) ($_POST['code'] ?? '')));

if ($code === '') {
    $draft['promo_code'] = '';
    booking_store_draft($draft);
    json_response([
        'ok'       => true,
        'message'  => 'Kode promo dihapus.',
        'subtotal' => $base['subtotal'],
        'discount' => 0,
        'total'    => $base['total'],
    ]);
}

$result = validate_promo($code, $base['subtotal'], (int) $user['id']);
if (!$result['ok']) {
    json_response(['ok' => false, 'message' => $result['message']], 422);
}

$draft['promo_code'] = (string) $result['promo']['code'];
booking_store_draft($draft);

$discount = (float) $result['discount'];
json_response([
    'ok'                  => true,
    'message'             => $result['message'],
    'code'                => (string) $result['promo']['code'],
    'subtotal'            => $base['subtotal'],
    'discount'            => $discount,
    'total'               => max(0, $base['subtotal'] - $discount),
    'subtotal_formatted'  => rupiah($base['subtotal']),
    'discount_formatted'  => rupiah($discount),
    'total_formatted'     => rupiah(max(0, $base['subtotal'] - $discount)),
]);

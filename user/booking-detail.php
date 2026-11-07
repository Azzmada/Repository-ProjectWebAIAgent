<?php
/**
 * Booking detail for the signed-in customer.
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

$code = trim((string) ($_GET['code'] ?? ''));
$booking = $code !== '' ? get_booking_for_user($code, (int) $user['id']) : false;
if (!$booking) {
    flash('error', 'Pesanan tidak ditemukan atau bukan milik akun Anda.');
    redirect(base_url('user/my-bookings.php'));
}

$bookingId = (int) $booking['id'];
$legs = get_booking_legs($bookingId);
$passengers = get_booking_passengers($bookingId);
$payments = get_booking_payments($bookingId);
$counts = booking_counts_from_passengers($passengers, (int) $booking['pax']);
$pricing = booking_pricing($legs, $counts);
$pricing['discount'] = (float) $booking['discount'];
$pricing['total'] = (float) $booking['total_price'];
$promo = $booking['promo_id']
    ? Database::fetch('SELECT code, title FROM promos WHERE id = ?', [(int) $booking['promo_id']])
    : false;

$isPending = $booking['status'] === 'pending';
$isPaid = $booking['status'] === 'paid';
$typeLabels = ['adult' => 'Dewasa', 'child' => 'Anak', 'infant' => 'Bayi'];
$paymentBadges = [
    'pending'  => 'bg-yellow-100 text-yellow-700 border-yellow-200',
    'paid'     => 'bg-green-100 text-green-700 border-green-200',
    'failed'   => 'bg-red-100 text-red-700 border-red-200',
    'refunded' => 'bg-gray-100 text-gray-600 border-gray-200',
];

$pageTitle = 'Detail Pesanan ' . $booking['booking_code'] . ' - ' . APP_NAME;
$metaDescription = 'Detail penerbangan, penumpang dan status pembayaran pesanan Anda.';
$activeNav = 'bookings';

require __DIR__ . '/../includes/header.php';
?>
<div class="bg-gray-50 py-8 sm:py-10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <a href="<?= e(base_url('user/my-bookings.php')) ?>" class="text-sm font-semibold text-primary hover:text-primaryDark"><i class="fa-solid fa-arrow-left mr-1"></i>Pesanan Saya</a>
                <h1 class="text-3xl font-bold text-gray-900 mt-1"><?= e($booking['booking_code']) ?></h1>
                <p class="text-gray-500 text-sm">Dipesan <?= e(format_date_id($booking['created_at'], true)) ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($isPending): ?>
                    <a href="<?= e(base_url('booking/payment.php?code=' . urlencode($code))) ?>" class="bg-accent hover:bg-accentDark text-white font-bold px-5 py-2.5 rounded-xl transition"><i class="fa-solid fa-credit-card mr-1"></i>Bayar Sekarang</a>
                <?php endif; ?>
                <?php if ($isPaid): ?>
                    <a href="<?= e(base_url('booking/e-ticket.php?code=' . urlencode($code))) ?>" class="bg-primary hover:bg-primaryDark text-white font-bold px-5 py-2.5 rounded-xl transition"><i class="fa-solid fa-download mr-1"></i>E-Tiket PDF</a>
                <?php endif; ?>
                <a href="<?= e(base_url('user/invoice.php?code=' . urlencode($code))) ?>" target="_blank" rel="noopener" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-2.5 rounded-xl transition"><i class="fa-solid fa-file-invoice mr-1"></i>Invoice</a>
            </div>
        </div>

        <?php if ($isPending): ?>
            <div class="bg-gradient-to-r from-orange-500 to-accent rounded-2xl p-5 text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-lg mb-6">
                <div>
                    <p class="text-orange-100 text-sm">Selesaikan pembayaran dalam</p>
                    <p class="text-2xl font-extrabold font-mono payment-countdown" data-until="<?= e(countdown_value($booking['expires_at'])) ?>">--:--:--</p>
                </div>
                <div class="text-sm text-orange-50 sm:text-right">
                    <p>Batas: <?= e(format_date_id($booking['expires_at']) . ' ' . ($booking['expires_at'] ? date('H:i', strtotime($booking['expires_at'])) : '')) ?></p>
                    <p class="text-orange-100">Status berubah otomatis menjadi kedaluwarsa setelah batas waktu.</p>
                </div>
            </div>
        <?php elseif ($isPaid): ?>
            <div class="bg-green-50 border border-green-200 rounded-2xl p-5 flex items-center gap-4 mb-6">
                <span class="w-12 h-12 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xl"><i class="fa-solid fa-check"></i></span>
                <div>
                    <p class="font-bold text-green-900">Pembayaran diterima — selamat terbang!</p>
                    <p class="text-sm text-green-800">E-tiket Anda sudah aktif dan dikirim ke <?= e($booking['user_email'] ?? $user['email']) ?>.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-red-50 border border-red-200 rounded-2xl p-5 flex items-center gap-4 mb-6">
                <span class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-xl"><i class="fa-solid fa-circle-xmark"></i></span>
                <div>
                    <p class="font-bold text-red-900">Pesanan <?= e(status_label((string) $booking['status'])) ?></p>
                    <p class="text-sm text-red-800">Pesanan ini tidak aktif lagi. Silakan buat pesanan baru jika masih ingin terbang.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Itinerary -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="font-bold text-gray-900"><i class="fa-solid fa-route text-primary mr-2"></i>Rute Penerbangan</h2>
                        <span class="text-xs font-semibold bg-blue-50 text-primary px-3 py-1 rounded-full"><?= e($booking['cabin_class']) ?></span>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($legs as $index => $leg):
                            $nextDay = (string) $leg['arr_time'] < (string) $leg['dep_time'];
                        ?>
                            <div class="p-5">
                                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                                    <div class="flex items-center gap-3 sm:w-1/4">
                                        <span class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center"><i class="fa-solid fa-plane text-primary"></i></span>
                                        <div class="min-w-0">
                                            <p class="font-bold text-gray-900 text-sm truncate"><?= e($leg['airline_name']) ?></p>
                                            <p class="text-xs text-gray-500"><?= e($leg['flight_num']) ?> &middot; <?= e($leg['aircraft']) ?></p>
                                            <p class="text-[11px] text-primary font-semibold">Rute <?= $index + 1 ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 flex-1">
                                        <div>
                                            <p class="text-xl font-extrabold text-gray-900 leading-none"><?= e(substr((string) $leg['dep_time'], 0, 5)) ?></p>
                                            <p class="text-xs text-gray-500"><?= e($leg['from_code']) ?> &middot; <?= e($leg['from_name']) ?></p>
                                        </div>
                                        <div class="flex-1 flex items-center px-1">
                                            <span class="h-px bg-gray-300 flex-1"></span>
                                            <i class="fa-solid fa-plane text-gray-400 text-[10px] mx-1"></i>
                                            <span class="h-px bg-gray-300 flex-1"></span>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xl font-extrabold text-gray-900 leading-none"><?= e(substr((string) $leg['arr_time'], 0, 5)) ?><?= $nextDay ? '<sup class="text-[10px] text-orange-500">+1</sup>' : '' ?></p>
                                            <p class="text-xs text-gray-500"><?= e($leg['to_code']) ?> &middot; <?= e($leg['to_name']) ?></p>
                                        </div>
                                    </div>
                                    <div class="sm:w-1/5 sm:text-right">
                                        <p class="text-sm font-semibold text-gray-800"><?= e(format_date_id($leg['travel_date'], true)) ?></p>
                                        <p class="text-xs text-gray-500"><?= e(format_duration((int) $leg['duration'])) ?> &middot; <?= (int) $leg['stops'] === 0 ? 'Langsung' : (int) $leg['stops'] . ' Transit' ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Passengers -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="font-bold text-gray-900"><i class="fa-solid fa-users text-primary mr-2"></i>Penumpang</h2>
                    </div>
                    <div class="divide-y divide-gray-50">
                        <?php foreach ($passengers as $index => $passenger): ?>
                            <div class="px-5 py-3 flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-sm"><i class="fa-solid fa-user"></i></span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 text-sm truncate"><?= e($passenger['name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= e($typeLabels[$passenger['type']] ?? 'Dewasa') ?><?= $index === 0 && $passenger['email'] ? ' · ' . e($passenger['email']) : '' ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Payments -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="font-bold text-gray-900"><i class="fa-solid fa-receipt text-primary mr-2"></i>Riwayat Pembayaran</h2>
                    </div>
                    <?php if (!$payments): ?>
                        <p class="px-5 py-6 text-sm text-gray-500">Belum ada percobaan pembayaran.</p>
                    <?php else: ?>
                        <div class="divide-y divide-gray-50">
                            <?php foreach ($payments as $payment): ?>
                                <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                                    <span class="w-10 h-10 rounded-xl bg-gray-50 text-gray-600 flex items-center justify-center"><i class="fa-solid fa-credit-card"></i></span>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-900 text-sm"><?= e(payment_method_label((string) $payment['method'])) ?></p>
                                        <p class="text-xs text-gray-500 font-mono"><?= e($payment['reference'] ?: '-') ?></p>
                                        <p class="text-xs text-gray-400"><?= e(format_date_id($payment['created_at'])) ?><?= $payment['paid_at'] ? ' · dibayar ' . e(date('H:i', strtotime($payment['paid_at']))) : '' ?></p>
                                    </div>
                                    <div class="text-left sm:text-right">
                                        <p class="text-sm font-bold text-gray-900"><?= e(rupiah($payment['amount'])) ?></p>
                                        <span class="inline-block text-[11px] font-semibold px-2 py-0.5 rounded-full border <?= e($paymentBadges[$payment['status']] ?? 'bg-gray-100 text-gray-600 border-gray-200') ?>"><?= e(ucfirst((string) $payment['status'])) ?></span>
                                    </div>
                                    <?php if ($payment['proof_path']): ?>
                                        <a href="<?= e(upload_url((string) $payment['proof_path'])) ?>" target="_blank" rel="noopener" class="text-xs font-semibold text-primary hover:text-primaryDark whitespace-nowrap"><i class="fa-solid fa-paperclip mr-1"></i>Bukti</a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h2 class="font-bold text-gray-900 mb-4">Rincian Biaya</h2>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600"><span>Subtotal</span><span><?= e(rupiah($booking['subtotal'])) ?></span></div>
                        <?php if ((float) $booking['discount'] > 0): ?>
                            <div class="flex justify-between text-green-600"><span>Diskon<?= $promo ? ' (' . e($promo['code']) . ')' : '' ?></span><span>-<?= e(rupiah($booking['discount'])) ?></span></div>
                        <?php endif; ?>
                        <div class="flex justify-between items-center border-t border-gray-100 pt-3 mt-1">
                            <span class="font-bold text-gray-900">Total</span>
                            <span class="text-xl font-extrabold text-accent"><?= e(rupiah($booking['total_price'])) ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h2 class="font-bold text-gray-900 mb-3">Status Pesanan</h2>
                    <span class="inline-block text-sm font-semibold px-3 py-1.5 rounded-full border <?= e(status_badge((string) $booking['status'])) ?>"><?= e(status_label((string) $booking['status'])) ?></span>
                    <div class="mt-4 space-y-2 text-sm text-gray-600">
                        <p><i class="fa-solid fa-user text-primary w-4 mr-1"></i><?= e($booking['user_name']) ?></p>
                        <p class="break-all"><i class="fa-solid fa-envelope text-primary w-4 mr-1"></i><?= e($booking['user_email']) ?></p>
                        <p><i class="fa-solid fa-phone text-primary w-4 mr-1"></i><?= e($booking['user_phone']) ?></p>
                    </div>
                </div>

                <?php if ($isPending): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-5">
                        <h2 class="font-bold text-gray-900 mb-2">Batalkan Pesanan</h2>
                        <p class="text-sm text-gray-500 mb-3">Pesanan yang belum dibayar dapat dibatalkan kapan saja.</p>
                        <form method="post" action="<?= e(base_url('user/cancel-booking.php')) ?>" onsubmit="return confirm('Batalkan pesanan ini? Tindakan ini tidak dapat dibatalkan.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="code" value="<?= e($code) ?>">
                            <button type="submit" class="w-full bg-red-50 hover:bg-red-100 text-red-600 font-semibold py-2.5 rounded-xl transition text-sm"><i class="fa-solid fa-ban mr-1"></i>Batalkan Pesanan</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4">
                    <p class="text-sm text-blue-900"><i class="fa-solid fa-headset text-primary mr-1"></i>Butuh bantuan? Hubungi <a href="mailto:<?= e(setting('contact_email', 'support@terbanglebih.com')) ?>" class="font-semibold underline"><?= e(setting('contact_email', 'support@terbanglebih.com')) ?></a></p>
                </div>
            </aside>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

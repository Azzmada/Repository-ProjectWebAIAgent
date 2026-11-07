<?php
/**
 * Step 4 — choose a payment method and create the payment record.
 *
 * The booking itself was already created with status "pending"; this page
 * turns it into a concrete payment (transfer / VA / e-wallet / card) and
 * redirects to the instruction page.
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

$code = trim((string) ($_GET['code'] ?? $_POST['code'] ?? ''));
$booking = $code !== '' ? get_booking_for_user($code, (int) $user['id']) : false;
if (!$booking) {
    flash('error', 'Pesanan tidak ditemukan atau bukan milik akun Anda.');
    redirect(base_url('user/my-bookings.php'));
}

$bookingId = (int) $booking['id'];
$legs = get_booking_legs($bookingId);
$passengers = get_booking_passengers($bookingId);
$counts = booking_counts_from_passengers($passengers, (int) $booking['pax']);
$pricing = booking_pricing($legs, $counts);
$pricing['discount'] = (float) $booking['discount'];
$pricing['total'] = (float) $booking['total_price'];

$methods = [
    'transfer'   => ['label' => 'Transfer Bank', 'desc' => 'BCA, Mandiri, BNI, BRI', 'icon' => 'fa-building-columns', 'color' => 'text-blue-600 bg-blue-50'],
    'va'         => ['label' => 'Virtual Account', 'desc' => 'Verifikasi otomatis 24/7', 'icon' => 'fa-hashtag', 'color' => 'text-purple-600 bg-purple-50'],
    'ewallet'    => ['label' => 'E-Wallet', 'desc' => 'OVO, GoPay, DANA', 'icon' => 'fa-wallet', 'color' => 'text-green-600 bg-green-50'],
    'creditcard' => ['label' => 'Kartu Kredit', 'desc' => 'Visa & Mastercard (simulasi)', 'icon' => 'fa-credit-card', 'color' => 'text-orange-600 bg-orange-50'],
];

$latestPending = Database::fetch(
    'SELECT * FROM payments WHERE booking_id = ? AND status = "pending" ORDER BY id DESC LIMIT 1',
    [$bookingId]
);
$paidPayment = Database::fetch(
    'SELECT * FROM payments WHERE booking_id = ? AND status = "paid" ORDER BY id DESC LIMIT 1',
    [$bookingId]
);

if (is_post()) {
    verify_csrf();

    if ($booking['status'] !== 'pending') {
        flash('info', 'Pesanan ini tidak lagi menunggu pembayaran.');
        redirect(base_url('booking/payment.php?code=' . urlencode($code)));
    }

    $method = input('method');
    if (!isset($methods[$method])) {
        flash('error', 'Pilih metode pembayaran yang tersedia.');
        redirect(base_url('booking/payment.php?code=' . urlencode($code)));
    }

    // Resume an existing payment instead of duplicating it.
    if ($latestPending && $latestPending['method'] === $method) {
        redirect(base_url('booking/instruction.php?code=' . urlencode($code) . '&payment=' . (int) $latestPending['id']));
    }

    Database::execute(
        'UPDATE payments SET status = "failed" WHERE booking_id = ? AND status = "pending"',
        [$bookingId]
    );

    $paymentId = Database::insert(
        'INSERT INTO payments (booking_id, method, amount, status, reference) VALUES (?, ?, ?, "pending", ?)',
        [
            $bookingId,
            $method,
            (float) $booking['total_price'],
            payment_reference($method, (string) $booking['booking_code']),
        ]
    );

    flash('info', 'Pilih metode pembayaran selesai. Ikuti instruksi untuk menyelesaikan transaksi.');
    redirect(base_url('booking/instruction.php?code=' . urlencode($code) . '&payment=' . $paymentId));
}

$pageTitle = 'Pembayaran ' . $booking['booking_code'] . ' - ' . APP_NAME;
$metaDescription = 'Pilih metode pembayaran untuk pesanan tiket pesawat Anda.';
$activeNav = 'pesawat';
$currentStep = 3;

require __DIR__ . '/../includes/header.php';
?>
<div class="bg-gray-50 py-8 sm:py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <?php require __DIR__ . '/../includes/booking-steps.php'; ?>

        <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Pembayaran</h1>
                <p class="text-gray-500 mt-1">Kode booking <span class="font-mono font-bold text-gray-800"><?= e($booking['booking_code']) ?></span></p>
            </div>
            <a href="<?= e(base_url('user/booking-detail.php?code=' . urlencode($code))) ?>" class="text-sm font-semibold text-primary hover:text-primaryDark"><i class="fa-solid fa-file-lines mr-1"></i>Detail Pesanan</a>
        </div>

        <?php if ($booking['status'] === 'paid' || $paidPayment): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-green-100 p-8 text-center max-w-2xl mx-auto">
                <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fa-solid fa-check text-4xl"></i></div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Pembayaran Berhasil</h2>
                <p class="text-gray-500 mb-6">E-tiket Anda sudah aktif dan dikirim ke <?= e($booking['user_email'] ?? $user['email']) ?>.</p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="<?= e(base_url('booking/e-ticket.php?code=' . urlencode($code))) ?>" class="bg-primary hover:bg-primaryDark text-white font-bold px-6 py-3 rounded-xl transition"><i class="fa-solid fa-download mr-2"></i>Unduh E-Tiket</a>
                    <a href="<?= e(base_url('user/booking-detail.php?code=' . urlencode($code))) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold px-6 py-3 rounded-xl transition">Lihat Pesanan</a>
                </div>
            </div>
        <?php elseif (in_array($booking['status'], ['cancelled', 'expired'], true)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-8 text-center max-w-2xl mx-auto">
                <div class="w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fa-solid fa-circle-xmark text-4xl"></i></div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Pesanan <?= e(status_label((string) $booking['status'])) ?></h2>
                <p class="text-gray-500 mb-6">Pesanan ini tidak dapat dibayar lagi. Silakan buat pesanan baru jika masih ingin terbang.</p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="<?= e(page_url('pesawat')) ?>" class="bg-primary hover:bg-primaryDark text-white font-bold px-6 py-3 rounded-xl transition">Cari Tiket Lagi</a>
                    <a href="<?= e(base_url('user/my-bookings.php')) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold px-6 py-3 rounded-xl transition">Pesanan Saya</a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <!-- Countdown -->
                    <div class="bg-gradient-to-r from-orange-500 to-accent rounded-2xl p-5 text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-lg">
                        <div>
                            <p class="text-orange-100 text-sm">Selesaikan pembayaran sebelum</p>
                            <p class="text-2xl font-extrabold font-mono payment-countdown" data-until="<?= e(countdown_value($booking['expires_at'])) ?>">--:--:--</p>
                        </div>
                        <div class="text-sm text-orange-50 sm:text-right">
                            <p>Batas waktu: <?= e(format_date_id($booking['expires_at']) . ' ' . ($booking['expires_at'] ? date('H:i', strtotime($booking['expires_at'])) : '')) ?></p>
                            <p class="text-orange-100">Kursi dapat dilepas otomatis setelah waktu habis.</p>
                        </div>
                    </div>

                    <?php if ($latestPending): ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                            <span class="w-12 h-12 rounded-xl bg-white text-primary flex items-center justify-center text-xl"><i class="fa-solid <?= e($methods[$latestPending['method']]['icon'] ?? 'fa-credit-card') ?>"></i></span>
                            <div class="flex-1">
                                <p class="font-bold text-blue-900">Pembayaran belum selesai</p>
                                <p class="text-sm text-blue-800"><?= e(payment_method_label((string) $latestPending['method'])) ?> &middot; <span class="font-mono"><?= e($latestPending['reference']) ?></span></p>
                            </div>
                            <a href="<?= e(base_url('booking/instruction.php?code=' . urlencode($code) . '&payment=' . (int) $latestPending['id'])) ?>" class="bg-primary hover:bg-primaryDark text-white font-semibold px-5 py-2.5 rounded-xl transition text-center whitespace-nowrap">Lanjutkan</a>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(base_url('booking/payment.php')) ?>" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
                        <?= csrf_field() ?>
                        <input type="hidden" name="code" value="<?= e($code) ?>">
                        <h2 class="text-xl font-bold text-gray-900 mb-1">Pilih Metode Pembayaran</h2>
                        <p class="text-sm text-gray-500 mb-5">Semua metode terhubung ke sistem verifikasi otomatis.</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach ($methods as $key => $method): ?>
                                <label class="payment-option relative border-2 border-gray-200 hover:border-primary rounded-2xl p-4 cursor-pointer transition flex items-center gap-3">
                                    <input type="radio" name="method" value="<?= e($key) ?>" required class="peer sr-only" <?= $latestPending && $latestPending['method'] === $key ? 'checked' : '' ?>>
                                    <span class="w-11 h-11 rounded-xl <?= e($method['color']) ?> flex items-center justify-center text-lg"><i class="fa-solid <?= e($method['icon']) ?>"></i></span>
                                    <span class="min-w-0">
                                        <span class="block font-bold text-gray-900 text-sm"><?= e($method['label']) ?></span>
                                        <span class="block text-xs text-gray-500"><?= e($method['desc']) ?></span>
                                    </span>
                                    <span class="ml-auto w-5 h-5 rounded-full border-2 border-gray-300 peer-checked:border-primary peer-checked:bg-primary flex items-center justify-center text-white text-[10px]"><i class="fa-solid fa-check opacity-0 peer-checked:opacity-100"></i></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gray-50 rounded-xl p-4">
                            <div>
                                <p class="text-sm text-gray-500">Total pembayaran</p>
                                <p class="text-2xl font-extrabold text-accent"><?= e(rupiah($booking['total_price'])) ?></p>
                            </div>
                            <button type="submit" class="bg-accent hover:bg-accentDark text-white font-bold px-8 py-3.5 rounded-xl shadow-lg transition text-lg">Bayar Sekarang <i class="fa-solid fa-arrow-right ml-1"></i></button>
                        </div>
                        <p class="text-xs text-gray-500 mt-3"><i class="fa-solid fa-shield-halved text-green-600 mr-1"></i>Pembayaran diverifikasi otomatis setelah bukti transfer diunggah. Kartu kredit menggunakan mode simulasi.</p>
                    </form>
                </div>

                <aside class="lg:col-span-1">
                    <div class="lg:sticky lg:top-24 space-y-6">
                        <?php require __DIR__ . '/../includes/booking-itinerary.php'; ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                            <h3 class="font-bold text-gray-900 mb-3 text-sm"><i class="fa-solid fa-circle-question text-primary mr-1"></i>Butuh bantuan?</h3>
                            <p class="text-sm text-gray-500 mb-3">Hubungi layanan pelanggan 24/7 jika pembayaran Anda belum terverifikasi.</p>
                            <p class="text-sm font-semibold text-gray-800"><i class="fa-solid fa-phone text-primary mr-1"></i><?= e(setting('contact_phone', '0800-1-888-999')) ?></p>
                            <p class="text-sm font-semibold text-gray-800 break-all"><i class="fa-solid fa-envelope text-primary mr-1"></i><?= e(setting('contact_email', 'support@terbanglebih.com')) ?></p>
                        </div>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

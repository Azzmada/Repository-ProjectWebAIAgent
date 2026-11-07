<?php
/**
 * Payment instructions + proof upload.
 *
 * Shows the method-specific instructions (bank transfer, virtual account,
 * e-wallet or simulated card), the countdown, and the proof upload form.
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

$paymentId = input_int('payment');
$payment = false;
if ($paymentId > 0) {
    $payment = Database::fetch('SELECT * FROM payments WHERE id = ? AND booking_id = ? LIMIT 1', [$paymentId, $bookingId]);
}
if (!$payment) {
    $payment = Database::fetch('SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1', [$bookingId]);
}
if (!$payment) {
    flash('info', 'Pilih metode pembayaran terlebih dahulu.');
    redirect(base_url('booking/payment.php?code=' . urlencode($code)));
}

if ($booking['status'] === 'paid' || $payment['status'] === 'paid') {
    flash('info', 'Pembayaran pesanan ini sudah diterima.');
    redirect(base_url('booking/payment.php?code=' . urlencode($code)));
}
if (in_array($booking['status'], ['cancelled', 'expired'], true)) {
    flash('error', 'Pesanan ini sudah ' . strtolower(status_label((string) $booking['status'])) . ' dan tidak dapat dibayar.');
    redirect(base_url('booking/payment.php?code=' . urlencode($code)));
}

$legs = get_booking_legs($bookingId);
$passengers = get_booking_passengers($bookingId);
$counts = booking_counts_from_passengers($passengers, (int) $booking['pax']);
$pricing = booking_pricing($legs, $counts);
$pricing['discount'] = (float) $booking['discount'];
$pricing['total'] = (float) $booking['total_price'];

$method = (string) $payment['method'];
$banks = [
    'BCA'     => ['number' => setting('bank_bca_number', '1234567890'), 'name' => setting('bank_bca_name', 'PT TerbangLebih Nusantara')],
    'Mandiri' => ['number' => setting('bank_mandiri_number', '1370009876543'), 'name' => setting('bank_mandiri_name', 'PT TerbangLebih Nusantara')],
    'BNI'     => ['number' => setting('bank_bni_number', '0123456789'), 'name' => setting('bank_bni_name', 'PT TerbangLebih Nusantara')],
    'BRI'     => ['number' => setting('bank_bri_number', '001201000123456'), 'name' => setting('bank_bri_name', 'PT TerbangLebih Nusantara')],
];
$wallets = [
    'OVO'   => setting('ewallet_ovo_number', '081234567890'),
    'GoPay' => setting('ewallet_gopay_number', '081234567890'),
    'DANA'  => setting('ewallet_dana_number', '081234567890'),
];

$pageTitle = 'Instruksi Pembayaran ' . $booking['booking_code'] . ' - ' . APP_NAME;
$metaDescription = 'Instruksi pembayaran dan unggah bukti transfer.';
$activeNav = 'pesawat';
$currentStep = 3;

$pageScripts = <<<'HTML'
<script>
function copyPaymentText(text) {
    const done = () => window.showToast('Nomor berhasil disalin.', 'success');
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, done);
    } else {
        done();
    }
}
</script>
HTML;

require __DIR__ . '/../includes/header.php';
?>
<div class="bg-gray-50 py-8 sm:py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <?php require __DIR__ . '/../includes/booking-steps.php'; ?>

        <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Instruksi Pembayaran</h1>
                <p class="text-gray-500 mt-1">Kode booking <span class="font-mono font-bold text-gray-800"><?= e($booking['booking_code']) ?></span> &middot; <?= e(payment_method_label($method)) ?></p>
            </div>
            <a href="<?= e(base_url('user/booking-detail.php?code=' . urlencode($code))) ?>" class="text-sm font-semibold text-primary hover:text-primaryDark"><i class="fa-solid fa-file-lines mr-1"></i>Detail Pesanan</a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Countdown -->
                <div class="bg-gradient-to-r from-orange-500 to-accent rounded-2xl p-5 text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-lg">
                    <div>
                        <p class="text-orange-100 text-sm">Sisa waktu pembayaran</p>
                        <p class="text-3xl font-extrabold font-mono payment-countdown" data-until="<?= e(countdown_value($booking['expires_at'])) ?>">--:--:--</p>
                    </div>
                    <div class="text-sm text-orange-50 sm:text-right">
                        <p>Batas waktu: <?= e(format_date_id($booking['expires_at']) . ' ' . ($booking['expires_at'] ? date('H:i', strtotime($booking['expires_at'])) : '')) ?></p>
                        <p class="text-orange-100">Pesanan otomatis kedaluwarsa jika melewati batas.</p>
                    </div>
                </div>

                <!-- Amount -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
                    <p class="text-sm text-gray-500">Total yang harus dibayar</p>
                    <div class="flex items-center justify-between gap-4 mt-1">
                        <p class="text-3xl sm:text-4xl font-extrabold text-accent"><?= e(rupiah($booking['total_price'])) ?></p>
                        <span class="text-xs font-semibold bg-yellow-50 text-yellow-700 border border-yellow-200 px-3 py-1.5 rounded-full whitespace-nowrap"><i class="fa-solid fa-clock mr-1"></i>Menunggu Pembayaran</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Bayar dengan jumlah tepat. Nomor referensi: <span class="font-mono font-bold text-gray-800"><?= e($payment['reference']) ?></span></p>
                </div>

                <!-- Method specific -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4"><i class="fa-solid fa-list-check text-primary mr-2"></i>Cara Pembayaran</h2>

                    <?php if ($method === 'transfer'): ?>
                        <p class="text-sm text-gray-600 mb-4">Transfer ke salah satu rekening resmi berikut, lalu unggah bukti transfer di bawah.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach ($banks as $bank => $account): ?>
                                <div class="border border-gray-200 rounded-xl p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-extrabold text-gray-900"><?= e($bank) ?></span>
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Bank Transfer</span>
                                    </div>
                                    <p class="font-mono font-bold text-lg text-gray-900"><?= e($account['number']) ?></p>
                                    <p class="text-xs text-gray-500 mb-2">a.n. <?= e($account['name']) ?></p>
                                    <button type="button" onclick="copyPaymentText('<?= e($account['number']) ?>')" class="text-xs font-semibold text-primary hover:text-primaryDark"><i class="fa-regular fa-copy mr-1"></i>Salin</button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ($method === 'va'): ?>
                        <p class="text-sm text-gray-600 mb-4">Buka mobile banking / ATM, pilih menu <strong>Virtual Account</strong>, lalu masukkan nomor VA di bawah ini.</p>
                        <div class="bg-purple-50 border border-purple-200 rounded-xl p-5 text-center">
                            <p class="text-sm text-purple-700 mb-1">Nomor Virtual Account</p>
                            <p class="font-mono font-extrabold text-2xl sm:text-3xl text-purple-800 tracking-wider"><?= e($payment['reference']) ?></p>
                            <button type="button" onclick="copyPaymentText('<?= e($payment['reference']) ?>')" class="mt-3 text-sm font-semibold text-purple-700 hover:text-purple-900"><i class="fa-regular fa-copy mr-1"></i>Salin nomor VA</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-3">Pembayaran VA umumnya terverifikasi otomatis dalam 1-5 menit setelah transaksi.</p>

                    <?php elseif ($method === 'ewallet'): ?>
                        <p class="text-sm text-gray-600 mb-4">Kirim pembayaran ke salah satu dompet digital berikut, lalu unggah bukti transaksi.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <?php foreach ($wallets as $wallet => $number): ?>
                                <div class="border border-gray-200 rounded-xl p-4 text-center">
                                    <p class="font-bold text-gray-900 mb-1"><?= e($wallet) ?></p>
                                    <p class="font-mono font-semibold text-sm text-gray-800"><?= e($number) ?></p>
                                    <p class="text-[11px] text-gray-500 mt-1">a.n. TerbangLebih</p>
                                    <button type="button" onclick="copyPaymentText('<?= e($number) ?>')" class="mt-2 text-xs font-semibold text-primary hover:text-primaryDark"><i class="fa-regular fa-copy mr-1"></i>Salin</button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php else: ?>
                        <p class="text-sm text-gray-600 mb-4">Masukkan data kartu Anda. Mode ini merupakan <strong>simulasi</strong> — tidak ada dana yang benar-benar ditagih.</p>
                        <form method="post" action="<?= e(base_url('booking/upload-proof.php')) ?>" class="space-y-4" onsubmit="return true;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="pay">
                            <input type="hidden" name="code" value="<?= e($code) ?>">
                            <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama pada Kartu</label>
                                <input type="text" required minlength="2" placeholder="NAMA PEMEGANG KARTU" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none uppercase">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Kartu</label>
                                <input type="text" required inputmode="numeric" maxlength="19" placeholder="4111 1111 1111 1111" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none font-mono">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Masa Berlaku</label>
                                    <input type="text" required maxlength="5" placeholder="MM/YY" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none font-mono">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">CVV</label>
                                    <input type="password" required maxlength="4" placeholder="123" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none font-mono">
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-accent hover:bg-accentDark text-white font-bold py-3.5 rounded-xl shadow-lg transition text-lg"><i class="fa-solid fa-lock mr-2"></i>Bayar <?= e(rupiah($booking['total_price'])) ?></button>
                            <p class="text-xs text-gray-500 text-center"><i class="fa-solid fa-circle-info mr-1"></i>Kartu kredit diproses dalam mode simulasi untuk keperluan demo.</p>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($method !== 'creditcard'): ?>
                    <!-- Proof upload -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-1">Upload Bukti Pembayaran</h2>
                        <p class="text-sm text-gray-500 mb-4">Format JPG, PNG atau PDF. Maksimal 2 MB. Status pesanan menjadi <strong>Lunas</strong> otomatis setelah bukti diunggah.</p>
                        <form method="post" action="<?= e(base_url('booking/upload-proof.php')) ?>" enctype="multipart/form-data" class="space-y-4">
                            <?= csrf_field() ?>
                            <input type="hidden" name="code" value="<?= e($code) ?>">
                            <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                            <label for="proof-input" class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-300 hover:border-primary rounded-2xl bg-gray-50 px-4 py-8 cursor-pointer transition">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-primary"></i>
                                <span class="text-sm font-semibold text-gray-700">Pilih file bukti pembayaran</span>
                                <span class="text-xs text-gray-500" id="proof-filename">Belum ada file dipilih</span>
                                <input type="file" id="proof-input" name="proof" accept="image/jpeg,image/png,application/pdf" required class="sr-only">
                            </label>
                            <button type="submit" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-3.5 rounded-xl shadow-lg transition text-lg"><i class="fa-solid fa-upload mr-2"></i>Kirim Bukti &amp; Verifikasi</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4 flex items-start gap-3">
                    <i class="fa-solid fa-shield-halved text-primary mt-0.5"></i>
                    <p class="text-sm text-blue-900">Jangan membagikan bukti pembayaran ke pihak lain selain kanal resmi TerbangLebih. Nomor rekening resmi hanya yang tertera di halaman ini.</p>
                </div>
            </div>

            <aside class="lg:col-span-1">
                <div class="lg:sticky lg:top-24 space-y-6">
                    <?php require __DIR__ . '/../includes/booking-itinerary.php'; ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <h3 class="font-bold text-gray-900 mb-3 text-sm"><i class="fa-solid fa-headset text-primary mr-1"></i>Layanan Pelanggan</h3>
                        <p class="text-sm text-gray-500 mb-3">Pembayaran belum terverifikasi lebih dari 10 menit? Hubungi kami.</p>
                        <p class="text-sm font-semibold text-gray-800"><i class="fa-solid fa-phone text-primary mr-1"></i><?= e(setting('contact_phone', '0800-1-888-999')) ?></p>
                        <p class="text-sm font-semibold text-gray-800 break-all"><i class="fa-solid fa-envelope text-primary mr-1"></i><?= e(setting('contact_email', 'support@terbanglebih.com')) ?></p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

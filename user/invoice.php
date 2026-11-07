<?php
/**
 * Printable invoice for a booking.
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
    flash('error', 'Invoice tidak ditemukan atau bukan milik akun Anda.');
    redirect(base_url('user/my-bookings.php'));
}

$bookingId = (int) $booking['id'];
$legs = get_booking_legs($bookingId);
$passengers = get_booking_passengers($bookingId);
$counts = booking_counts_from_passengers($passengers, (int) $booking['pax']);
$pricing = booking_pricing($legs, $counts);
$pricing['discount'] = (float) $booking['discount'];
$pricing['total'] = (float) $booking['total_price'];

$lastPayment = Database::fetch(
    'SELECT * FROM payments WHERE booking_id = ? ORDER BY (status = "paid") DESC, id DESC LIMIT 1',
    [$bookingId]
);
$invoiceNo = 'INV-' . preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $booking['booking_code']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($invoiceNo) ?> - <?= e(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { primary: '#0ea5e9', primaryDark: '#0284c7', accent: '#f97316', accentDark: '#ea580c' } } }
        };
    </script>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="bg-gray-100 font-sans text-gray-800 antialiased">
    <div class="max-w-3xl mx-auto px-4 py-8">
        <div class="no-print flex items-center justify-between gap-3 mb-4">
            <a href="<?= e(base_url('user/booking-detail.php?code=' . urlencode($code))) ?>" class="text-sm font-semibold text-primary hover:text-primaryDark"><i class="fa-solid fa-arrow-left mr-1"></i>Kembali ke detail</a>
            <button type="button" onclick="window.print()" class="bg-primary hover:bg-primaryDark text-white text-sm font-bold px-5 py-2.5 rounded-xl transition"><i class="fa-solid fa-print mr-2"></i>Cetak / Simpan PDF</button>
        </div>

        <div class="invoice-sheet bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden print-card">
            <!-- Header -->
            <div class="bg-gradient-to-r from-primary to-cyan-500 px-6 sm:px-8 py-7 text-white">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="bg-white/20 rounded-lg p-2"><i class="fa-solid fa-plane-departure text-xl"></i></span>
                            <span class="text-2xl font-extrabold tracking-tight">TerbangLebih</span>
                        </div>
                        <p class="text-blue-50 text-sm mt-2"><?= e(setting('site_tagline', 'Terbang lebih mudah, jelajahi dunia tanpa ribet.')) ?></p>
                        <p class="text-blue-100 text-xs mt-2"><?= e(setting('contact_email', 'support@terbanglebih.com')) ?> · <?= e(setting('contact_phone', '0800-1-888-999')) ?></p>
                    </div>
                    <div class="sm:text-right">
                        <p class="text-blue-100 text-xs uppercase tracking-widest">Invoice</p>
                        <p class="text-xl font-extrabold font-mono"><?= e($invoiceNo) ?></p>
                        <p class="text-blue-100 text-xs mt-1">Tanggal: <?= e(format_date_id($booking['created_at'])) ?></p>
                        <span class="inline-block mt-2 text-[11px] font-bold px-3 py-1 rounded-full bg-white/20"><?= e(strtoupper(status_label((string) $booking['status']))) ?></span>
                    </div>
                </div>
            </div>

            <div class="p-6 sm:p-8">
                <!-- Billing info -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold mb-1">Ditagihkan kepada</p>
                        <p class="font-bold text-gray-900"><?= e($booking['user_name']) ?></p>
                        <p class="text-sm text-gray-600 break-all"><?= e($booking['user_email']) ?></p>
                        <p class="text-sm text-gray-600"><?= e($booking['user_phone']) ?></p>
                    </div>
                    <div class="sm:text-right">
                        <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold mb-1">Detail Pesanan</p>
                        <p class="text-sm text-gray-600">Kode Booking: <span class="font-mono font-bold text-gray-900"><?= e($booking['booking_code']) ?></span></p>
                        <p class="text-sm text-gray-600">Kelas: <?= e($booking['cabin_class']) ?> · <?= (int) $booking['pax'] ?> penumpang</p>
                        <p class="text-sm text-gray-600">Metode: <?= e($lastPayment ? payment_method_label((string) $lastPayment['method']) : '-') ?></p>
                    </div>
                </div>

                <!-- Items -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-3 font-semibold rounded-l-lg">Deskripsi</th>
                                <th class="px-4 py-3 font-semibold">Tanggal</th>
                                <th class="px-4 py-3 font-semibold text-right rounded-r-lg">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($legs as $index => $leg):
                                $legTotal = $pricing['leg_totals'][$index] ?? 0;
                            ?>
                                <tr>
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-gray-900">Tiket <?= e($leg['from_name']) ?> → <?= e($leg['to_name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= e($leg['airline_name']) ?> <?= e($leg['flight_num']) ?> · <?= e(pax_label($counts, (string) $booking['cabin_class'])) ?></p>
                                    </td>
                                    <td class="px-4 py-4 text-gray-600"><?= e(format_date_id($leg['travel_date'])) ?><br><span class="text-xs text-gray-400"><?= e(substr((string) $leg['dep_time'], 0, 5)) ?> - <?= e(substr((string) $leg['arr_time'], 0, 5)) ?></span></td>
                                    <td class="px-4 py-4 text-right font-semibold text-gray-900"><?= e(rupiah($legTotal)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="flex justify-end mt-6">
                    <div class="w-full sm:w-72 space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600"><span>Subtotal</span><span><?= e(rupiah($booking['subtotal'])) ?></span></div>
                        <?php if ((float) $booking['discount'] > 0): ?>
                            <div class="flex justify-between text-green-600"><span>Diskon promo</span><span>-<?= e(rupiah($booking['discount'])) ?></span></div>
                        <?php endif; ?>
                        <div class="flex justify-between items-center border-t border-gray-200 pt-3">
                            <span class="font-bold text-gray-900">Total</span>
                            <span class="text-xl font-extrabold text-accent"><?= e(rupiah($booking['total_price'])) ?></span>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500"><span>Status pembayaran</span><span class="font-semibold <?= $booking['status'] === 'paid' ? 'text-green-600' : 'text-yellow-600' ?>"><?= e(status_label((string) $booking['status'])) ?></span></div>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-dashed border-gray-200">
                    <p class="text-xs text-gray-500 leading-relaxed">Invoice ini diterbitkan secara elektronik dan sah tanpa tanda tangan. Pembayaran dianggap lunas setelah sistem menerima dan memverifikasi bukti pembayaran. Untuk pertanyaan, hubungi <?= e(setting('contact_email', 'support@terbanglebih.com')) ?>.</p>
                    <p class="text-center text-xs text-gray-400 mt-6">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Terima kasih telah terbang bersama kami.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

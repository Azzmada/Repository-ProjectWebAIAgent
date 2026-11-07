<?php
/**
 * "Pesanan Saya" — list of the signed-in user's bookings.
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
$userId = (int) $user['id'];

$allowedStatuses = ['all', 'pending', 'paid', 'cancelled', 'expired'];
$status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}
$search = clean((string) ($_GET['q'] ?? ''));

$sql = 'SELECT b.*, f.flight_num, f.dep_time, f.arr_time,
               a.name AS airline_name, a.code AS airline_code,
               fc.name AS from_name, fc.iata_code AS from_code,
               tc.name AS to_name, tc.iata_code AS to_code,
               (SELECT COUNT(*) FROM booking_flights bf WHERE bf.booking_id = b.id) AS leg_count
        FROM bookings b
        JOIN flights f ON f.id = b.flight_id
        JOIN airlines a ON a.id = f.airline_id
        JOIN cities fc ON fc.id = f.from_city_id
        JOIN cities tc ON tc.id = f.to_city_id
        WHERE b.user_id = ?';
$params = [$userId];

if ($status !== 'all') {
    $sql .= ' AND b.status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $sql .= ' AND b.booking_code LIKE ?';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY b.created_at DESC LIMIT 100';

$bookings = Database::fetchAll($sql, $params);

$stats = Database::fetch(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'pending'), 0) AS pending,
            COALESCE(SUM(status = 'paid'), 0) AS paid,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN total_price ELSE 0 END), 0) AS spent
     FROM bookings WHERE user_id = ?",
    [$userId]
);

$tabs = [
    'all'       => 'Semua',
    'pending'   => 'Menunggu Pembayaran',
    'paid'      => 'Lunas',
    'cancelled' => 'Dibatalkan',
    'expired'   => 'Kedaluwarsa',
];

$pageTitle = 'Pesanan Saya - ' . APP_NAME;
$metaDescription = 'Lihat riwayat pemesanan, unduh e-tiket, dan kelola pembayaran Anda.';
$activeNav = 'bookings';

require __DIR__ . '/../includes/header.php';
?>
<div class="bg-gray-50 py-8 sm:py-10 min-h-[70vh]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Pesanan Saya</h1>
                <p class="text-gray-500 mt-1">Semua perjalanan dan status pembayaran Anda ada di sini.</p>
            </div>
            <a href="<?= e(page_url('pesawat')) ?>" class="bg-primary hover:bg-primaryDark text-white font-semibold px-5 py-2.5 rounded-xl transition text-center"><i class="fa-solid fa-plus mr-1"></i>Pesan Tiket Baru</a>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <p class="text-xs text-gray-500">Total Pesanan</p>
                <p class="text-2xl font-extrabold text-gray-900 mt-1"><?= (int) $stats['total'] ?></p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-yellow-100 p-4">
                <p class="text-xs text-gray-500">Menunggu Bayar</p>
                <p class="text-2xl font-extrabold text-yellow-600 mt-1"><?= (int) $stats['pending'] ?></p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-green-100 p-4">
                <p class="text-xs text-gray-500">Lunas</p>
                <p class="text-2xl font-extrabold text-green-600 mt-1"><?= (int) $stats['paid'] ?></p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-orange-100 p-4">
                <p class="text-xs text-gray-500">Total Belanja</p>
                <p class="text-xl font-extrabold text-accent mt-1"><?= e(rupiah($stats['spent'])) ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3 justify-between">
                <div class="flex gap-1.5 overflow-x-auto hide-scrollbar pb-1">
                    <?php foreach ($tabs as $key => $label):
                        $url = base_url('user/my-bookings.php?' . http_build_query(['status' => $key, 'q' => $search]));
                    ?>
                        <a href="<?= e($url) ?>" class="whitespace-nowrap text-sm font-semibold px-4 py-2 rounded-full transition <?= $status === $key ? 'bg-primary text-white shadow' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
                <form method="get" action="<?= e(base_url('user/my-bookings.php')) ?>" class="flex gap-2">
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari kode booking" class="pl-9 pr-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-primary outline-none w-full sm:w-56">
                    </div>
                    <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition">Cari</button>
                </form>
            </div>
        </div>

        <?php if (!$bookings): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                <i class="fa-solid fa-ticket-simple text-6xl text-gray-300 mb-4"></i>
                <h2 class="text-xl font-bold text-gray-800 mb-2">Belum ada pesanan</h2>
                <p class="text-gray-500 mb-5"><?= $search !== '' ? 'Tidak ada pesanan dengan kode tersebut.' : 'Mulai petualangan Anda dengan memesan tiket pesawat pertama.' ?></p>
                <a href="<?= e(page_url('pesawat')) ?>" class="inline-flex items-center gap-2 bg-accent hover:bg-accentDark text-white font-bold px-6 py-3 rounded-xl transition"><i class="fa-solid fa-plane-departure"></i> Cari Tiket</a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($bookings as $booking):
                    $isPending = $booking['status'] === 'pending';
                    $isPaid = $booking['status'] === 'paid';
                ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
                        <div class="p-5 flex flex-col lg:flex-row lg:items-center gap-5">
                            <div class="flex items-center gap-3 lg:w-1/4">
                                <span class="w-12 h-12 rounded-xl bg-blue-50 text-primary flex items-center justify-center text-xl flex-shrink-0"><i class="fa-solid fa-plane"></i></span>
                                <div class="min-w-0">
                                    <p class="font-bold text-gray-900 truncate"><?= e($booking['airline_name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= e($booking['flight_num']) ?> &middot; <?= (int) $booking['pax'] ?> pax</p>
                                    <p class="text-[11px] text-gray-400 font-mono mt-0.5"><?= e($booking['booking_code']) ?></p>
                                </div>
                            </div>

                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <div>
                                        <p class="text-lg font-extrabold text-gray-900 leading-none"><?= e(substr((string) $booking['dep_time'], 0, 5)) ?></p>
                                        <p class="text-xs font-semibold text-gray-500"><?= e($booking['from_code']) ?></p>
                                    </div>
                                    <div class="flex-1 flex items-center px-2">
                                        <span class="h-px bg-gray-300 flex-1"></span>
                                        <i class="fa-solid fa-plane text-gray-400 text-[10px] mx-1"></i>
                                        <span class="h-px bg-gray-300 flex-1"></span>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-extrabold text-gray-900 leading-none"><?= e(substr((string) $booking['arr_time'], 0, 5)) ?></p>
                                        <p class="text-xs font-semibold text-gray-500"><?= e($booking['to_code']) ?></p>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1.5"><i class="fa-regular fa-calendar mr-1"></i><?= e(format_date_id($booking['travel_date'], true)) ?><?= (int) $booking['leg_count'] > 1 ? ' · ' . (int) $booking['leg_count'] . ' rute' : '' ?></p>
                            </div>

                            <div class="lg:w-1/5 lg:text-right">
                                <p class="text-xs text-gray-500">Total</p>
                                <p class="text-lg font-extrabold text-accent"><?= e(rupiah($booking['total_price'])) ?></p>
                                <span class="inline-block text-[11px] font-semibold px-2.5 py-1 rounded-full border mt-1 <?= e(status_badge((string) $booking['status'])) ?>"><?= e(status_label((string) $booking['status'])) ?></span>
                            </div>

                            <div class="flex flex-wrap lg:flex-col gap-2 lg:w-auto lg:items-stretch">
                                <?php if ($isPending): ?>
                                    <a href="<?= e(base_url('booking/payment.php?code=' . urlencode($booking['booking_code']))) ?>" class="bg-accent hover:bg-accentDark text-white text-sm font-bold px-4 py-2 rounded-lg text-center transition">Bayar</a>
                                <?php endif; ?>
                                <?php if ($isPaid): ?>
                                    <a href="<?= e(base_url('booking/e-ticket.php?code=' . urlencode($booking['booking_code']))) ?>" class="bg-primary hover:bg-primaryDark text-white text-sm font-bold px-4 py-2 rounded-lg text-center transition"><i class="fa-solid fa-download mr-1"></i>E-Tiket</a>
                                <?php endif; ?>
                                <a href="<?= e(base_url('user/booking-detail.php?code=' . urlencode($booking['booking_code']))) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold px-4 py-2 rounded-lg text-center transition">Detail</a>
                            </div>
                        </div>
                        <?php if ($isPending && $booking['expires_at']): ?>
                            <div class="px-5 py-2.5 bg-yellow-50 border-t border-yellow-100 text-xs text-yellow-800 flex items-center gap-2">
                                <i class="fa-solid fa-clock"></i>
                                Selesaikan pembayaran sebelum <?= e(format_date_id($booking['expires_at']) . ' ' . date('H:i', strtotime($booking['expires_at']))) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

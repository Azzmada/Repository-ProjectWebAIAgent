<?php
/**
 * Admin dashboard — totals, 7-day revenue chart, recent bookings.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';

require_admin();
expire_stale_bookings();

$totals = Database::fetch(
    "SELECT COUNT(*) AS total_bookings,
            COALESCE(SUM(status = 'pending'), 0) AS pending_bookings,
            COALESCE(SUM(status = 'paid'), 0) AS paid_bookings,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN total_price ELSE 0 END), 0) AS revenue
     FROM bookings"
);
$userCount = (int) Database::fetch('SELECT COUNT(*) AS c FROM users')['c'];
$flightCount = (int) Database::fetch('SELECT COUNT(*) AS c FROM flights WHERE is_active = 1')['c'];
$airlineCount = (int) Database::fetch('SELECT COUNT(*) AS c FROM airlines')['c'];
$promoCount = (int) Database::fetch('SELECT COUNT(*) AS c FROM promos WHERE is_active = 1 AND valid_until >= CURDATE()')['c'];

// Revenue for the last 7 days (paid bookings).
$revenueRows = Database::fetchAll(
    "SELECT DATE(created_at) AS d, SUM(total_price) AS total
     FROM bookings
     WHERE status = 'paid' AND created_at >= (CURDATE() - INTERVAL 6 DAY)
     GROUP BY DATE(created_at)"
);
$revenueMap = [];
foreach ($revenueRows as $row) {
    $revenueMap[(string) $row['d']] = (float) $row['total'];
}
$chart = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime('-' . $i . ' days'));
    $chart[] = [
        'date'   => $date,
        'label'  => date('d/m', strtotime($date)),
        'amount' => $revenueMap[$date] ?? 0.0,
    ];
}
$chartMax = max(1.0, ...array_column($chart, 'amount'));

$recentBookings = Database::fetchAll(
    'SELECT b.*, u.name AS user_name, u.email AS user_email,
            fc.name AS from_name, fc.iata_code AS from_code,
            tc.name AS to_name, tc.iata_code AS to_code
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     JOIN flights f ON f.id = b.flight_id
     JOIN cities fc ON fc.id = f.from_city_id
     JOIN cities tc ON tc.id = f.to_city_id
     ORDER BY b.created_at DESC
     LIMIT 8'
);

$topRoutes = Database::fetchAll(
    'SELECT fc.name AS from_name, fc.iata_code AS from_code,
            tc.name AS to_name, tc.iata_code AS to_code,
            COUNT(*) AS total
     FROM bookings b
     JOIN flights f ON f.id = b.flight_id
     JOIN cities fc ON fc.id = f.from_city_id
     JOIN cities tc ON tc.id = f.to_city_id
     GROUP BY f.from_city_id, f.to_city_id
     ORDER BY total DESC
     LIMIT 5'
);

$adminTitle = 'Dashboard';
$adminActive = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<!-- Stat cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Pendapatan</p>
                <p class="text-2xl font-extrabold text-gray-900 mt-1"><?= e(rupiah($totals['revenue'])) ?></p>
                <p class="text-xs text-green-600 mt-1"><i class="fa-solid fa-check mr-1"></i><?= (int) $totals['paid_bookings'] ?> booking lunas</p>
            </div>
            <span class="w-11 h-11 rounded-xl bg-green-50 text-green-600 flex items-center justify-center"><i class="fa-solid fa-money-bill-trend-up"></i></span>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Booking</p>
                <p class="text-2xl font-extrabold text-gray-900 mt-1"><?= (int) $totals['total_bookings'] ?></p>
                <p class="text-xs text-yellow-600 mt-1"><i class="fa-solid fa-clock mr-1"></i><?= (int) $totals['pending_bookings'] ?> menunggu bayar</p>
            </div>
            <span class="w-11 h-11 rounded-xl bg-blue-50 text-primary flex items-center justify-center"><i class="fa-solid fa-ticket"></i></span>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pelanggan</p>
                <p class="text-2xl font-extrabold text-gray-900 mt-1"><?= $userCount ?></p>
                <p class="text-xs text-gray-500 mt-1">Akun terdaftar</p>
            </div>
            <span class="w-11 h-11 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center"><i class="fa-solid fa-users"></i></span>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Penerbangan Aktif</p>
                <p class="text-2xl font-extrabold text-gray-900 mt-1"><?= $flightCount ?></p>
                <p class="text-xs text-gray-500 mt-1"><?= $airlineCount ?> maskapai · <?= $promoCount ?> promo aktif</p>
            </div>
            <span class="w-11 h-11 rounded-xl bg-orange-50 text-accent flex items-center justify-center"><i class="fa-solid fa-plane"></i></span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <!-- Revenue chart -->
    <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="font-bold text-gray-900"><i class="fa-solid fa-chart-column text-primary mr-2"></i>Pendapatan 7 Hari Terakhir</h2>
                <p class="text-xs text-gray-500 mt-0.5">Berdasarkan booking berstatus lunas.</p>
            </div>
            <span class="text-xs font-semibold bg-green-50 text-green-700 border border-green-200 px-3 py-1.5 rounded-full"><?= e(rupiah(array_sum(array_column($chart, 'amount')))) ?></span>
        </div>
        <div class="bar-chart">
            <?php foreach ($chart as $day):
                $height = $day['amount'] > 0 ? max(5, round(($day['amount'] / $chartMax) * 100)) : 2;
            ?>
                <div class="bar-col">
                    <div class="bar" style="height:<?= $height ?>%" title="<?= e(format_date_id($day['date']) . ': ' . rupiah($day['amount'])) ?>"></div>
                    <span class="text-[10px] text-gray-500 whitespace-nowrap"><?= e($day['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Top routes -->
    <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <h2 class="font-bold text-gray-900 mb-4"><i class="fa-solid fa-fire text-accent mr-2"></i>Rute Terpopuler</h2>
        <?php if (!$topRoutes): ?>
            <p class="text-sm text-gray-500">Belum ada data booking.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($topRoutes as $index => $route): ?>
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center text-xs font-bold"><?= $index + 1 ?></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate"><?= e($route['from_code']) ?> → <?= e($route['to_code']) ?></p>
                            <p class="text-[11px] text-gray-500 truncate"><?= e($route['from_name']) ?> - <?= e($route['to_name']) ?></p>
                        </div>
                        <span class="text-xs font-bold text-primary"><?= (int) $route['total'] ?>x</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mt-5 pt-4 border-t border-gray-100 grid grid-cols-2 gap-2">
            <a href="<?= e(base_url('admin/flights.php')) ?>" class="text-center text-xs font-semibold bg-gray-50 hover:bg-gray-100 text-gray-700 py-2.5 rounded-lg transition"><i class="fa-solid fa-plus mr-1"></i>Penerbangan</a>
            <a href="<?= e(base_url('admin/promos.php')) ?>" class="text-center text-xs font-semibold bg-gray-50 hover:bg-gray-100 text-gray-700 py-2.5 rounded-lg transition"><i class="fa-solid fa-plus mr-1"></i>Promo</a>
        </div>
    </div>
</div>

<!-- Recent bookings -->
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-bold text-gray-900"><i class="fa-solid fa-clock-rotate-left text-primary mr-2"></i>Booking Terbaru</h2>
        <a href="<?= e(base_url('admin/bookings.php')) ?>" class="text-xs font-semibold text-primary hover:text-primaryDark">Lihat semua <i class="fa-solid fa-arrow-right text-[10px]"></i></a>
    </div>
    <div class="overflow-x-auto table-scroll">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="text-left px-5 py-3 font-semibold">Kode</th>
                    <th class="text-left px-5 py-3 font-semibold">Pelanggan</th>
                    <th class="text-left px-5 py-3 font-semibold">Rute</th>
                    <th class="text-left px-5 py-3 font-semibold">Tanggal</th>
                    <th class="text-right px-5 py-3 font-semibold">Total</th>
                    <th class="text-left px-5 py-3 font-semibold">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$recentBookings): ?>
                    <tr><td colspan="6" class="px-5 py-8 text-center text-gray-500">Belum ada booking.</td></tr>
                <?php endif; ?>
                <?php foreach ($recentBookings as $booking): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3"><a href="<?= e(base_url('admin/bookings.php?code=' . urlencode($booking['booking_code']))) ?>" class="font-mono font-bold text-primary hover:underline"><?= e($booking['booking_code']) ?></a></td>
                        <td class="px-5 py-3"><p class="font-semibold text-gray-800"><?= e($booking['user_name']) ?></p><p class="text-xs text-gray-500"><?= e($booking['user_email']) ?></p></td>
                        <td class="px-5 py-3 text-gray-600"><?= e($booking['from_code']) ?> → <?= e($booking['to_code']) ?></td>
                        <td class="px-5 py-3 text-gray-600"><?= e(format_date_id($booking['travel_date'])) ?></td>
                        <td class="px-5 py-3 text-right font-semibold text-gray-900"><?= e(rupiah($booking['total_price'])) ?></td>
                        <td class="px-5 py-3"><span class="text-[11px] font-semibold px-2.5 py-1 rounded-full border <?= e(status_badge((string) $booking['status'])) ?>"><?= e(status_label((string) $booking['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

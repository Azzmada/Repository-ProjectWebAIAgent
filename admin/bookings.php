<?php
/**
 * Admin — booking list + detail + status management.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';

require_admin();
expire_stale_bookings();

$allowedStatuses = ['pending', 'paid', 'cancelled', 'expired'];

if (is_post()) {
    verify_csrf();
    $action = input('action');

    if ($action === 'status') {
        $code = input('code');
        $newStatus = input('status');
        $booking = $code !== '' ? get_booking_by_code($code) : false;

        if (!$booking) {
            flash('error', 'Booking tidak ditemukan.');
            redirect(base_url('admin/bookings.php'));
        }
        if (!in_array($newStatus, $allowedStatuses, true)) {
            flash('error', 'Status tidak valid.');
            redirect(base_url('admin/bookings.php?code=' . urlencode($code)));
        }

        $oldStatus = (string) $booking['status'];
        $bookingId = (int) $booking['id'];

        try {
            Database::begin();

            Database::execute('UPDATE bookings SET status = ? WHERE id = ?', [$newStatus, $bookingId]);

            if ($newStatus === 'paid') {
                $pending = Database::fetch(
                    'SELECT * FROM payments WHERE booking_id = ? AND status = "pending" ORDER BY id DESC LIMIT 1',
                    [$bookingId]
                );
                if ($pending) {
                    Database::execute('UPDATE payments SET status = "paid", paid_at = NOW() WHERE id = ?', [(int) $pending['id']]);
                } else {
                    Database::insert(
                        'INSERT INTO payments (booking_id, method, amount, status, reference, paid_at)
                         VALUES (?, "transfer", ?, "paid", ?, NOW())',
                        [$bookingId, (float) $booking['total_price'], 'MANUAL-' . $booking['booking_code']]
                    );
                }
                Database::execute(
                    'UPDATE payments SET status = "failed" WHERE booking_id = ? AND status = "pending"',
                    [$bookingId]
                );
            }

            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                Database::execute(
                    'UPDATE payments SET status = "failed" WHERE booking_id = ? AND status = "pending"',
                    [$bookingId]
                );
                if ((int) ($booking['promo_id'] ?? 0) > 0) {
                    $usage = Database::fetch('SELECT id FROM promo_usage WHERE booking_id = ? LIMIT 1', [$bookingId]);
                    if ($usage) {
                        Database::execute('DELETE FROM promo_usage WHERE booking_id = ?', [$bookingId]);
                        Database::execute('UPDATE promos SET used_count = GREATEST(used_count - 1, 0) WHERE id = ?', [(int) $booking['promo_id']]);
                    }
                }
            }

            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('[admin booking] status update failed: ' . $e->getMessage());
            flash('error', 'Status booking gagal diperbarui.');
            redirect(base_url('admin/bookings.php?code=' . urlencode($code)));
        }

        if ($newStatus !== $oldStatus) {
            try {
                send_booking_status_update($bookingId, $oldStatus);
            } catch (Throwable $e) {
                error_log('[admin booking] status mail failed: ' . $e->getMessage());
            }
        }

        flash('success', 'Status ' . $booking['booking_code'] . ' diubah menjadi ' . status_label($newStatus) . '.');
        redirect(base_url('admin/bookings.php?code=' . urlencode($code)));
    }
}

$detailCode = trim((string) ($_GET['code'] ?? ''));
$detail = $detailCode !== '' ? get_booking_by_code($detailCode) : false;
if ($detailCode !== '' && !$detail) {
    flash('error', 'Booking tidak ditemukan.');
    redirect(base_url('admin/bookings.php'));
}

$adminTitle = $detail ? 'Detail Booking' : 'Booking';
$adminActive = 'bookings';

if ($detail):
    $bookingId = (int) $detail['id'];
    $legs = get_booking_legs($bookingId);
    $passengers = get_booking_passengers($bookingId);
    $payments = get_booking_payments($bookingId);
    $promoRow = $detail['promo_id']
        ? Database::fetch('SELECT * FROM promos WHERE id = ?', [(int) $detail['promo_id']])
        : false;

    require __DIR__ . '/includes/header.php';
?>
    <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <a href="<?= e(base_url('admin/bookings.php')) ?>" class="text-sm font-semibold text-primary hover:text-primaryDark"><i class="fa-solid fa-arrow-left mr-1"></i>Semua Booking</a>
            <h2 class="text-2xl font-extrabold text-gray-900 mt-1 font-mono"><?= e($detail['booking_code']) ?></h2>
            <p class="text-sm text-gray-500">Dibuat <?= e(format_date_id($detail['created_at'], true)) ?> · <?= e(date('H:i', strtotime($detail['created_at']))) ?></p>
        </div>
        <div class="flex flex-wrap gap-2 items-center">
            <span class="text-xs font-semibold px-3 py-1.5 rounded-full border <?= e(status_badge((string) $detail['status'])) ?>"><?= e(status_label((string) $detail['status'])) ?></span>
            <?php if ((int) $detail['leg_count'] ?? 0): ?><?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <!-- Itinerary -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900"><i class="fa-solid fa-route text-primary mr-2"></i>Rute Penerbangan</h3>
                    <span class="text-xs font-semibold bg-blue-50 text-primary px-3 py-1 rounded-full"><?= e($detail['cabin_class']) ?> · <?= (int) $detail['pax'] ?> pax</span>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($legs as $index => $leg): ?>
                        <div class="p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                            <div class="sm:w-1/4">
                                <p class="font-bold text-gray-900 text-sm"><?= e($leg['airline_name']) ?></p>
                                <p class="text-xs text-gray-500"><?= e($leg['flight_num']) ?> · <?= e($leg['aircraft']) ?></p>
                                <p class="text-[11px] text-primary font-semibold">Rute <?= $index + 1 ?></p>
                            </div>
                            <div class="flex-1 flex items-center gap-3">
                                <div>
                                    <p class="text-lg font-extrabold text-gray-900 leading-none"><?= e(substr((string) $leg['dep_time'], 0, 5)) ?></p>
                                    <p class="text-xs text-gray-500"><?= e($leg['from_code']) ?> · <?= e($leg['from_name']) ?></p>
                                </div>
                                <div class="flex-1 flex items-center px-1"><span class="h-px bg-gray-300 flex-1"></span><i class="fa-solid fa-plane text-gray-400 text-[10px] mx-1"></i><span class="h-px bg-gray-300 flex-1"></span></div>
                                <div class="text-right">
                                    <p class="text-lg font-extrabold text-gray-900 leading-none"><?= e(substr((string) $leg['arr_time'], 0, 5)) ?></p>
                                    <p class="text-xs text-gray-500"><?= e($leg['to_code']) ?> · <?= e($leg['to_name']) ?></p>
                                </div>
                            </div>
                            <div class="sm:w-1/5 sm:text-right">
                                <p class="text-sm font-semibold text-gray-800"><?= e(format_date_id($leg['travel_date'], true)) ?></p>
                                <p class="text-xs text-gray-500"><?= e(format_duration((int) $leg['duration'])) ?> · <?= (int) $leg['stops'] === 0 ? 'Langsung' : (int) $leg['stops'] . ' transit' ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Passengers -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100"><h3 class="font-bold text-gray-900"><i class="fa-solid fa-users text-primary mr-2"></i>Penumpang</h3></div>
                <div class="divide-y divide-gray-50">
                    <?php foreach ($passengers as $passenger): ?>
                        <div class="px-5 py-3 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-xs"><i class="fa-solid fa-user"></i></span>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 text-sm"><?= e($passenger['name']) ?></p>
                                <p class="text-xs text-gray-500"><?= e(ucfirst((string) $passenger['type'])) ?><?= $passenger['email'] ? ' · ' . e($passenger['email']) : '' ?><?= $passenger['phone'] ? ' · ' . e($passenger['phone']) : '' ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Payments -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100"><h3 class="font-bold text-gray-900"><i class="fa-solid fa-receipt text-primary mr-2"></i>Pembayaran</h3></div>
                <?php if (!$payments): ?>
                    <p class="px-5 py-6 text-sm text-gray-500">Belum ada pembayaran.</p>
                <?php else: ?>
                    <div class="divide-y divide-gray-50">
                        <?php foreach ($payments as $payment): ?>
                            <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-900 text-sm"><?= e(payment_method_label((string) $payment['method'])) ?></p>
                                    <p class="text-xs text-gray-500 font-mono"><?= e($payment['reference'] ?: '-') ?></p>
                                </div>
                                <p class="text-sm font-bold text-gray-900"><?= e(rupiah($payment['amount'])) ?></p>
                                <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full border <?= $payment['status'] === 'paid' ? 'bg-green-50 text-green-700 border-green-200' : ($payment['status'] === 'pending' ? 'bg-yellow-50 text-yellow-700 border-yellow-200' : 'bg-gray-100 text-gray-600 border-gray-200') ?>"><?= e(ucfirst((string) $payment['status'])) ?></span>
                                <?php if ($payment['proof_path']): ?>
                                    <a href="<?= e(upload_url((string) $payment['proof_path'])) ?>" target="_blank" rel="noopener" class="text-xs font-semibold text-primary hover:text-primaryDark"><i class="fa-solid fa-paperclip mr-1"></i>Lihat bukti</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <aside class="xl:col-span-1 space-y-6">
            <!-- Status form -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-bold text-gray-900 mb-3"><i class="fa-solid fa-pen-to-square text-primary mr-2"></i>Ubah Status</h3>
                <form method="post" class="space-y-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="code" value="<?= e($detail['booking_code']) ?>">
                    <select name="status" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-primary outline-none">
                        <?php foreach ($allowedStatuses as $status): ?>
                            <option value="<?= e($status) ?>" <?= $detail['status'] === $status ? 'selected' : '' ?>><?= e(status_label($status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-2.5 rounded-xl transition text-sm" data-confirm="Ubah status booking ini?">Simpan Status</button>
                </form>
                <?php if ($detail['status'] === 'pending' && $detail['expires_at']): ?>
                    <p class="text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg p-2.5 mt-3"><i class="fa-solid fa-clock mr-1"></i>Batas bayar <?= e(format_date_id($detail['expires_at']) . ' ' . date('H:i', strtotime($detail['expires_at']))) ?></p>
                <?php endif; ?>
            </div>

            <!-- Customer -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-bold text-gray-900 mb-3"><i class="fa-solid fa-user text-primary mr-2"></i>Pelanggan</h3>
                <p class="font-semibold text-gray-900"><?= e($detail['user_name']) ?></p>
                <p class="text-sm text-gray-600 break-all"><?= e($detail['user_email']) ?></p>
                <p class="text-sm text-gray-600"><?= e($detail['user_phone']) ?></p>
            </div>

            <!-- Price -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-bold text-gray-900 mb-3"><i class="fa-solid fa-calculator text-primary mr-2"></i>Rincian Biaya</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-gray-600"><span>Subtotal</span><span><?= e(rupiah($detail['subtotal'])) ?></span></div>
                    <?php if ((float) $detail['discount'] > 0): ?>
                        <div class="flex justify-between text-green-600"><span>Diskon<?= $promoRow ? ' (' . e($promoRow['code']) . ')' : '' ?></span><span>-<?= e(rupiah($detail['discount'])) ?></span></div>
                    <?php endif; ?>
                    <div class="flex justify-between items-center border-t border-gray-100 pt-2">
                        <span class="font-bold text-gray-900">Total</span>
                        <span class="text-xl font-extrabold text-accent"><?= e(rupiah($detail['total_price'])) ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 text-xs text-gray-500">
                <p><i class="fa-solid fa-fingerprint text-primary mr-1"></i>ID Booking: <span class="font-mono">#<?= (int) $detail['id'] ?></span></p>
                <p class="mt-1"><i class="fa-regular fa-clock text-primary mr-1"></i>Terakhir diubah: <?= e($detail['updated_at'] ? date('d/m/Y H:i', strtotime($detail['updated_at'])) : '-') ?></p>
            </div>
        </aside>
    </div>
<?php
    require __DIR__ . '/includes/footer.php';
    exit;
endif;

// -----------------------------------------------------------------
// Listing
// -----------------------------------------------------------------
$filterStatus = (string) ($_GET['status'] ?? 'all');
if (!in_array($filterStatus, array_merge(['all'], $allowedStatuses), true)) {
    $filterStatus = 'all';
}
$search = clean((string) ($_GET['q'] ?? ''));

$sql = 'SELECT b.*, u.name AS user_name, u.email AS user_email,
               fc.name AS from_name, fc.iata_code AS from_code,
               tc.name AS to_name, tc.iata_code AS to_code
        FROM bookings b
        JOIN users u ON u.id = b.user_id
        JOIN flights f ON f.id = b.flight_id
        JOIN cities fc ON fc.id = f.from_city_id
        JOIN cities tc ON tc.id = f.to_city_id
        WHERE 1=1';
$params = [];
if ($filterStatus !== 'all') {
    $sql .= ' AND b.status = ?';
    $params[] = $filterStatus;
}
if ($search !== '') {
    $sql .= ' AND (b.booking_code LIKE ? OR u.email LIKE ? OR u.name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY b.created_at DESC LIMIT 100';
$bookings = Database::fetchAll($sql, $params);

$statusCounts = [];
foreach (Database::fetchAll('SELECT status, COUNT(*) AS c FROM bookings GROUP BY status') as $row) {
    $statusCounts[$row['status']] = (int) $row['c'];
}

require __DIR__ . '/includes/header.php';
?>
<div class="mb-4 bg-white rounded-2xl border border-gray-200 p-4">
    <form method="get" class="flex flex-col lg:flex-row lg:items-center gap-3 justify-between">
        <div class="flex gap-1.5 overflow-x-auto hide-scrollbar pb-1">
            <?php
            $tabs = ['all' => 'Semua'] + array_combine($allowedStatuses, array_map('status_label', $allowedStatuses));
            foreach ($tabs as $key => $label):
                $count = $key === 'all' ? array_sum($statusCounts) : ($statusCounts[$key] ?? 0);
            ?>
                <a href="<?= e(base_url('admin/bookings.php?' . http_build_query(['status' => $key, 'q' => $search]))) ?>" class="whitespace-nowrap text-xs font-semibold px-3.5 py-2 rounded-full transition <?= $filterStatus === $key ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    <?= e($label) ?> <span class="opacity-70">(<?= $count ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="flex gap-2">
            <input type="hidden" name="status" value="<?= e($filterStatus) ?>">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari kode / nama / email" class="px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none w-full sm:w-64">
            <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold px-4 py-2.5 rounded-lg">Cari</button>
        </div>
    </form>
</div>

<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-bold text-gray-900"><i class="fa-solid fa-ticket text-primary mr-2"></i>Daftar Booking</h2>
        <span class="text-xs font-semibold bg-gray-100 text-gray-600 px-3 py-1 rounded-full"><?= count($bookings) ?> ditampilkan</span>
    </div>
    <div class="overflow-x-auto table-scroll">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold">Kode</th>
                    <th class="text-left px-4 py-3 font-semibold">Pelanggan</th>
                    <th class="text-left px-4 py-3 font-semibold">Rute</th>
                    <th class="text-left px-4 py-3 font-semibold">Travel</th>
                    <th class="text-right px-4 py-3 font-semibold">Total</th>
                    <th class="text-left px-4 py-3 font-semibold">Status</th>
                    <th class="text-right px-4 py-3 font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$bookings): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Tidak ada booking sesuai filter.</td></tr>
                <?php endif; ?>
                <?php foreach ($bookings as $booking): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="<?= e(base_url('admin/bookings.php?code=' . urlencode($booking['booking_code']))) ?>" class="font-mono font-bold text-primary hover:underline"><?= e($booking['booking_code']) ?></a>
                            <p class="text-[11px] text-gray-400"><?= e(format_date_id($booking['created_at'])) ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-800"><?= e($booking['user_name']) ?></p>
                            <p class="text-[11px] text-gray-500"><?= e($booking['user_email']) ?></p>
                        </td>
                        <td class="px-4 py-3 text-gray-700"><?= e($booking['from_code']) ?> → <?= e($booking['to_code']) ?><p class="text-[11px] text-gray-400"><?= (int) $booking['pax'] ?> pax · <?= e($booking['cabin_class']) ?></p></td>
                        <td class="px-4 py-3 text-gray-600 text-xs"><?= e(format_date_id($booking['travel_date'])) ?></td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-900"><?= e(rupiah($booking['total_price'])) ?></td>
                        <td class="px-4 py-3"><span class="text-[11px] font-semibold px-2.5 py-1 rounded-full border <?= e(status_badge((string) $booking['status'])) ?>"><?= e(status_label((string) $booking['status'])) ?></span></td>
                        <td class="px-4 py-3 text-right">
                            <a href="<?= e(base_url('admin/bookings.php?code=' . urlencode($booking['booking_code']))) ?>" class="text-xs font-semibold text-primary hover:text-primaryDark bg-blue-50 px-3 py-1.5 rounded-lg"><i class="fa-solid fa-eye mr-1"></i>Detail</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

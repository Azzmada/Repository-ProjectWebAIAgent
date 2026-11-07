<?php
/**
 * Step 3 — review the itinerary, apply a promo and start the order.
 *
 * All values are recalculated from MySQL; the browser never submits prices.
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

$draft = booking_active_draft();
if (!$draft) {
    flash('info', 'Sesi pemesanan Anda sudah berakhir. Silakan pilih penerbangan kembali.');
    redirect(page_url('pesawat'));
}

$legsError = null;
$legs = booking_draft_legs($draft, $legsError);
if (!$legs || $legsError !== null || count($legs) !== count((array) ($draft['legs'] ?? []))) {
    clear_booking_draft();
    flash('error', $legsError ?: 'Penerbangan pada sesi Anda sudah tidak tersedia. Silakan cari ulang.');
    redirect(page_url('pesawat'));
}

$counts = booking_normalize_counts(
    (int) ($draft['counts']['adult'] ?? 1),
    (int) ($draft['counts']['child'] ?? 0),
    (int) ($draft['counts']['infant'] ?? 0)
);
$cabin = (string) ($draft['cabin'] ?? 'Ekonomi');

$storedPassengers = [];
foreach ((array) ($draft['passengers'] ?? []) as $row) {
    if (!is_array($row)) {
        continue;
    }
    $storedPassengers[] = [
        'name' => (string) ($row['name'] ?? ''),
        'type' => (string) ($row['type'] ?? 'adult'),
    ];
}
if (count($storedPassengers) !== total_pax($counts) || in_array('', array_column($storedPassengers, 'name'), true)) {
    flash('info', 'Lengkapi data penumpang terlebih dahulu.');
    redirect(base_url('booking/passengers.php'));
}

$contact = [
    'name'  => (string) ($draft['contact']['name'] ?? $user['name']),
    'email' => (string) ($draft['contact']['email'] ?? $user['email']),
    'phone' => (string) ($draft['contact']['phone'] ?? $user['phone']),
];

$basePricing = booking_pricing($legs, $counts);

// -----------------------------------------------------------------
// Promo actions (normal POST + redirect so refresh cannot re-post)
// -----------------------------------------------------------------
if (is_post()) {
    verify_csrf();
    $action = input('action');

    if ($action === 'apply-promo') {
        $code = strtoupper(clean(input('promo_code')));
        if ($code === '') {
            flash('error', 'Masukkan kode promo terlebih dahulu.');
            redirect(base_url('booking/review.php'));
        }

        $result = validate_promo($code, $basePricing['subtotal'], (int) $user['id']);
        if (!$result['ok']) {
            $draft['promo_code'] = '';
            booking_store_draft($draft);
            flash('error', $result['message']);
            redirect(base_url('booking/review.php'));
        }

        $draft['promo_code'] = (string) $result['promo']['code'];
        booking_store_draft($draft);
        flash('success', $result['message']);
        redirect(base_url('booking/review.php'));
    }

    if ($action === 'remove-promo') {
        $draft['promo_code'] = '';
        booking_store_draft($draft);
        flash('success', 'Kode promo dihapus.');
        redirect(base_url('booking/review.php'));
    }

    redirect(base_url('booking/review.php'));
}

$promoState = booking_draft_promo($draft, $basePricing['subtotal'], (int) $user['id']);
$promo = $promoState['valid'] ? $promoState['promo'] : null;
$pricing = booking_pricing($legs, $counts, $promo);

$typeLabels = ['adult' => 'Dewasa', 'child' => 'Anak', 'infant' => 'Bayi'];
$typeIcons = ['adult' => 'fa-user', 'child' => 'fa-child', 'infant' => 'fa-baby'];

$pageTitle = 'Ringkasan Pemesanan - ' . APP_NAME;
$metaDescription = 'Periksa rincian penerbangan, data penumpang, dan total pembayaran sebelum melanjutkan.';
$activeNav = 'pesawat';
$currentStep = 2; // still the passenger/order details phase visually

require __DIR__ . '/../includes/header.php';
?>
<div class="bg-gray-50 py-8 sm:py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <?php require __DIR__ . '/../includes/booking-steps.php'; ?>

        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Ringkasan Pemesanan</h1>
            <p class="text-gray-500 mt-1">Periksa kembali detail perjalanan dan penumpang sebelum membuat pesanan.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Itinerary detail -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="font-bold text-gray-900"><i class="fa-solid fa-plane-departure text-primary mr-2"></i>Detail Penerbangan</h2>
                        <span class="text-xs font-semibold bg-blue-50 text-primary px-3 py-1 rounded-full"><?= e($cabin) ?></span>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($legs as $index => $leg):
                            $f = $leg['flight'];
                            $nextDay = (string) $f['arr_time'] < (string) $f['dep_time'];
                        ?>
                            <div class="p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                                <div class="flex items-center gap-3 sm:w-1/3">
                                    <span class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center"><i class="fa-solid fa-plane text-primary"></i></span>
                                    <div class="min-w-0">
                                        <p class="font-bold text-gray-900 text-sm truncate"><?= e($f['airline_name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= e($f['flight_num']) ?> &middot; <?= e($f['aircraft']) ?></p>
                                        <p class="text-[11px] text-primary font-semibold mt-0.5">Rute <?= $index + 1 ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 flex-1">
                                    <div>
                                        <p class="text-lg font-extrabold text-gray-900 leading-none"><?= e(substr((string) $f['dep_time'], 0, 5)) ?></p>
                                        <p class="text-xs text-gray-500"><?= e($f['from_code']) ?> &middot; <?= e($f['from_name']) ?></p>
                                    </div>
                                    <div class="flex-1 flex flex-col items-center px-1">
                                        <p class="text-[11px] text-gray-400"><?= e(format_duration((int) $f['duration'])) ?></p>
                                        <div class="w-full flex items-center">
                                            <span class="h-px bg-gray-300 flex-1"></span>
                                            <i class="fa-solid fa-plane text-gray-400 text-[10px] mx-1"></i>
                                            <span class="h-px bg-gray-300 flex-1"></span>
                                        </div>
                                        <p class="text-[11px] mt-0.5 <?= (int) $f['stops'] === 0 ? 'text-green-600' : 'text-orange-500' ?>"><?= (int) $f['stops'] === 0 ? 'Langsung' : (int) $f['stops'] . ' Transit' ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-extrabold text-gray-900 leading-none"><?= e(substr((string) $f['arr_time'], 0, 5)) ?><?= $nextDay ? '<sup class="text-[10px] text-orange-500">+1</sup>' : '' ?></p>
                                        <p class="text-xs text-gray-500"><?= e($f['to_code']) ?> &middot; <?= e($f['to_name']) ?></p>
                                    </div>
                                </div>
                                <div class="sm:w-1/6 sm:text-right">
                                    <p class="text-sm font-semibold text-gray-800"><?= e(format_date_id($leg['date'], true)) ?></p>
                                    <p class="text-xs text-gray-500"><?= (int) $f['baggage_kg'] ?> kg bagasi</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Passengers -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="font-bold text-gray-900"><i class="fa-solid fa-users text-primary mr-2"></i>Data Penumpang</h2>
                        <a href="<?= e(base_url('booking/passengers.php?edit=1')) ?>" class="text-sm font-semibold text-primary hover:text-primaryDark"><i class="fa-solid fa-pen-to-square mr-1"></i>Ubah</a>
                    </div>
                    <div class="p-5 space-y-3">
                        <?php foreach ($storedPassengers as $index => $passenger):
                            $type = in_array($passenger['type'], ['adult', 'child', 'infant'], true) ? $passenger['type'] : 'adult';
                        ?>
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-sm"><i class="fa-solid <?= e($typeIcons[$type]) ?>"></i></span>
                                <div>
                                    <p class="font-semibold text-gray-900 text-sm"><?= e($passenger['name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= e($typeLabels[$type]) ?></p>
                                </div>
                                <?php if ($index === 0): ?><span class="ml-auto text-[11px] font-semibold text-primary bg-blue-50 px-2 py-0.5 rounded-full">Pemesan</span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 text-sm">
                        <p class="text-gray-500 mb-1">Kontak pemesanan</p>
                        <p class="font-semibold text-gray-800"><?= e($contact['name']) ?> &middot; <?= e($contact['email']) ?> &middot; <?= e($contact['phone']) ?></p>
                    </div>
                </div>

                <!-- Promo -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
                    <h2 class="font-bold text-gray-900 mb-3"><i class="fa-solid fa-tags text-primary mr-2"></i>Kode Promo</h2>

                    <?php if ($promoState['valid'] && $promo): ?>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-green-50 border border-green-200 rounded-xl p-4">
                            <div>
                                <p class="font-bold text-green-800 font-mono tracking-wide"><?= e($promoState['code']) ?></p>
                                <p class="text-sm text-green-700"><?= e($promoState['message']) ?></p>
                            </div>
                            <form method="post" action="<?= e(base_url('booking/review.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove-promo">
                                <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-700 bg-white border border-red-200 px-4 py-2 rounded-lg transition"><i class="fa-solid fa-xmark mr-1"></i>Hapus</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <?php if ($promoState['code'] !== '' && $promoState['message'] !== ''): ?>
                            <div class="mb-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-3 text-sm">
                                <i class="fa-solid fa-triangle-exclamation mr-1"></i><?= e($promoState['message']) ?>
                            </div>
                        <?php endif; ?>
                        <form method="post" action="<?= e(base_url('booking/review.php')) ?>" class="flex flex-col sm:flex-row gap-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="apply-promo">
                            <input type="text" name="promo_code" placeholder="Masukkan kode promo" maxlength="40" class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none font-mono uppercase tracking-wide">
                            <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-semibold px-6 py-3 rounded-xl transition">Terapkan</button>
                        </form>
                        <p class="text-xs text-gray-500 mt-2">Kode promo dicek otomatis untuk masa berlaku, minimum transaksi dan kuota.</p>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="lg:col-span-1">
                <div class="lg:sticky lg:top-24 space-y-6">
                    <?php require __DIR__ . '/../includes/booking-itinerary.php'; ?>

                    <form method="post" action="<?= e(base_url('booking/create.php')) ?>" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <?= csrf_field() ?>
                        <p class="text-sm text-gray-500 mb-3">Dengan menekan tombol di bawah, pesanan akan dibuat dengan status <span class="font-semibold text-gray-700">menunggu pembayaran</span>.</p>
                        <button type="submit" class="w-full bg-accent hover:bg-accentDark text-white font-bold py-3.5 rounded-xl shadow-lg transition text-lg flex items-center justify-center gap-2">
                            <i class="fa-solid fa-lock"></i> Buat Pesanan &amp; Bayar
                        </button>
                        <a href="<?= e(base_url('booking/passengers.php?edit=1')) ?>" class="mt-3 w-full inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-xl transition text-sm">
                            <i class="fa-solid fa-arrow-left"></i> Kembali ke Data Penumpang
                        </a>
                    </form>

                    <div class="bg-green-50 border border-green-100 rounded-2xl p-4 flex items-start gap-3">
                        <i class="fa-solid fa-shield-halved text-green-600 mt-0.5"></i>
                        <p class="text-sm text-green-800">Transaksi dilindungi. Harga dan ketersediaan penerbangan diverifikasi ulang saat pesanan dibuat.</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Step 2 — passenger details.
 *
 * The page accepts one flight ID + travel date per leg from the search
 * results, hydrates them from MySQL and keeps the (validated) selection in
 * the session draft. Passenger names and the contact person are collected
 * here, then re-validated again on the review step.
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

$allowedCabins = ['Ekonomi', 'Bisnis', 'First Class'];
$draft = booking_active_draft();

// -----------------------------------------------------------------
// Build / restore the draft
// -----------------------------------------------------------------
$flightInput = input_array('flights');
$dateInput = input_array('dates');

if ($flightInput) {
    $legsError = null;
    $legs = booking_legs_from_ids($flightInput, $dateInput, $legsError);
    if (!$legs || $legsError !== null) {
        flash('error', $legsError ?: 'Penerbangan tidak valid. Silakan pilih ulang.');
        redirect(page_url('pesawat'));
    }

    $counts = booking_normalize_counts(
        input_int('adults', 1),
        input_int('children', 0),
        input_int('infants', 0)
    );
    $cabin = (string) (input('class') ?: 'Ekonomi');
    if (!in_array($cabin, $allowedCabins, true)) {
        $cabin = 'Ekonomi';
    }

    $draft = [
        'legs'       => array_map(
            static fn (array $leg): array => ['flight_id' => $leg['flight_id'], 'date' => $leg['date']],
            $legs
        ),
        'counts'     => $counts,
        'cabin'      => $cabin,
        'passengers' => booking_default_passengers($counts, $user),
        'contact'    => [
            'name'  => (string) $user['name'],
            'email' => (string) $user['email'],
            'phone' => (string) $user['phone'],
        ],
        'promo_code' => '',
    ];
    booking_store_draft($draft);
} elseif ($draft !== null) {
    $legsError = null;
    $legs = booking_draft_legs($draft, $legsError);
    if (!$legs || $legsError !== null || count($legs) !== count((array) ($draft['legs'] ?? []))) {
        clear_booking_draft();
        flash('error', $legsError ?: 'Sesi pemesanan Anda sudah berakhir. Silakan pilih penerbangan kembali.');
        redirect(page_url('pesawat'));
    }
    $counts = booking_normalize_counts(
        (int) ($draft['counts']['adult'] ?? 1),
        (int) ($draft['counts']['child'] ?? 0),
        (int) ($draft['counts']['infant'] ?? 0)
    );
    $cabin = (string) ($draft['cabin'] ?? 'Ekonomi');
    if (!in_array($cabin, $allowedCabins, true)) {
        $cabin = 'Ekonomi';
    }
} else {
    flash('info', 'Silakan pilih penerbangan terlebih dahulu.');
    redirect(page_url('pesawat'));
}

// Keep the draft counts/cabin in sync (they may have been normalised).
$draft['counts'] = $counts;
$draft['cabin'] = $cabin;

// -----------------------------------------------------------------
// Values shown in the form
// -----------------------------------------------------------------
$expectedTypes = [];
foreach (['adult', 'child', 'infant'] as $type) {
    for ($i = 0; $i < (int) $counts[$type]; $i++) {
        $expectedTypes[] = $type;
    }
}

$defaultPassengers = [];
foreach ($expectedTypes as $index => $type) {
    $existing = $draft['passengers'][$index] ?? [];
    $defaultPassengers[] = [
        'name' => (string) ($existing['name'] ?? ''),
        'type' => $type,
    ];
}

$contact = [
    'name'  => (string) ($draft['contact']['name'] ?? $user['name']),
    'email' => (string) ($draft['contact']['email'] ?? $user['email']),
    'phone' => (string) ($draft['contact']['phone'] ?? $user['phone']),
];

$passengerRows = $defaultPassengers;
$errors = [];

// -----------------------------------------------------------------
// Submit
// -----------------------------------------------------------------
if (is_post()) {
    verify_csrf();

    $passengerInput = input_array('passengers');
    $passengerRows = [];
    foreach ($expectedTypes as $index => $type) {
        $row = is_array($passengerInput[$index] ?? null) ? $passengerInput[$index] : [];
        $name = clean((string) ($row['name'] ?? ''));
        $passengerRows[] = ['name' => $name, 'type' => $type];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors[] = 'Nama penumpang ke-' . ($index + 1) . ' harus 2-120 karakter.';
        }
    }

    $contact = [
        'name'  => clean(input('contact_name')),
        'email' => strtolower(clean(input('contact_email'))),
        'phone' => clean(input('contact_phone')),
    ];
    if (mb_strlen($contact['name']) < 2 || mb_strlen($contact['name']) > 100) {
        $errors[] = 'Nama kontak harus 2-100 karakter.';
    }
    if (!valid_email($contact['email'])) {
        $errors[] = 'Email kontak tidak valid.';
    }
    if (!valid_phone($contact['phone'])) {
        $errors[] = 'Nomor telepon kontak tidak valid.';
    }
    if (!isset($_POST['agree'])) {
        $errors[] = 'Anda harus menyetujui syarat & ketentuan pemesanan.';
    }

    if (!$errors) {
        $draft['passengers'] = $passengerRows;
        $draft['contact'] = $contact;
        booking_store_draft($draft);
        redirect(base_url('booking/review.php'));
    }
}

// -----------------------------------------------------------------
// Rendering data
// -----------------------------------------------------------------
$promoState = booking_draft_promo($draft, booking_pricing($legs, $counts)['subtotal'], (int) $user['id']);
$pricing = booking_pricing($legs, $counts, $promoState['valid'] ? $promoState['promo'] : null);

$pageTitle = 'Data Penumpang - ' . APP_NAME;
$metaDescription = 'Lengkapi data penumpang untuk menyelesaikan pemesanan tiket pesawat Anda.';
$activeNav = 'pesawat';
$currentStep = 2;

require __DIR__ . '/../includes/header.php';
?>
<div class="bg-gray-50 py-8 sm:py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <?php require __DIR__ . '/../includes/booking-steps.php'; ?>

        <div class="mb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Data Penumpang</h1>
                <p class="text-gray-500 mt-1">Isi nama lengkap sesuai identitas (KTP / paspor / akta) agar e-tiket valid.</p>
            </div>
            <a href="<?= e(page_url('pesawat')) ?>" class="text-sm font-semibold text-primary hover:text-primaryDark"><i class="fa-solid fa-arrow-left mr-1"></i>Ubah penerbangan</a>
        </div>

        <?php if ($errors): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-4 mb-6">
                <p class="font-bold mb-1"><i class="fa-solid fa-circle-exclamation mr-1"></i>Periksa kembali data Anda</p>
                <ul class="list-disc list-inside text-sm space-y-0.5">
                    <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(base_url('booking/passengers.php')) ?>" class="grid grid-cols-1 lg:grid-cols-3 gap-6" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="edit" value="1">

            <div class="lg:col-span-2 space-y-6">
                <?php
                $typeMeta = [
                    'adult'  => ['label' => 'Dewasa', 'desc' => '12 tahun ke atas', 'icon' => 'fa-user', 'color' => 'bg-blue-50 text-primary'],
                    'child'  => ['label' => 'Anak', 'desc' => '2 - 11 tahun', 'icon' => 'fa-child', 'color' => 'bg-green-50 text-green-600'],
                    'infant' => ['label' => 'Bayi', 'desc' => 'Di bawah 2 tahun (tanpa kursi)', 'icon' => 'fa-baby', 'color' => 'bg-orange-50 text-accent'],
                ];
                $rowIndex = 0;
                foreach (['adult', 'child', 'infant'] as $type):
                    if ((int) $counts[$type] <= 0) {
                        continue;
                    }
                    $meta = $typeMeta[$type];
                ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
                        <div class="flex items-center gap-3 mb-5 pb-4 border-b border-gray-100">
                            <span class="w-10 h-10 rounded-xl <?= e($meta['color']) ?> flex items-center justify-center"><i class="fa-solid <?= e($meta['icon']) ?>"></i></span>
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Penumpang <?= e($meta['label']) ?></h2>
                                <p class="text-xs text-gray-500"><?= e($meta['desc']) ?></p>
                            </div>
                            <span class="ml-auto bg-gray-100 text-gray-600 text-xs font-bold px-2.5 py-1 rounded-full"><?= (int) $counts[$type] ?> orang</span>
                        </div>

                        <div class="space-y-4">
                            <?php for ($i = 0; $i < (int) $counts[$type]; $i++):
                                $index = $rowIndex;
                                $rowIndex++;
                                $value = $passengerRows[$index]['name'] ?? '';
                            ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1" for="pax-name-<?= $index ?>">
                                        <?= e($meta['label']) ?> <?= $i + 1 ?>
                                        <?php if ($type === 'adult' && $i === 0): ?>
                                            <span class="ml-1 text-[11px] font-semibold text-primary bg-blue-50 px-2 py-0.5 rounded-full">Pemesan</span>
                                        <?php endif; ?>
                                    </label>
                                    <input type="text"
                                           id="pax-name-<?= $index ?>"
                                           name="passengers[<?= $index ?>][name]"
                                           value="<?= e($value) ?>"
                                           required minlength="2" maxlength="120"
                                           placeholder="Nama lengkap sesuai identitas"
                                           autocomplete="<?= $type === 'adult' && $i === 0 ? 'name' : 'off' ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Contact -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6">
                    <div class="flex items-center gap-3 mb-5 pb-4 border-b border-gray-100">
                        <span class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center"><i class="fa-solid fa-address-book"></i></span>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Detail Kontak</h2>
                            <p class="text-xs text-gray-500">E-tiket, invoice dan info perubahan jadwal dikirim ke kontak ini.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="contact-name">Nama Kontak</label>
                            <input type="text" id="contact-name" name="contact_name" value="<?= e($contact['name']) ?>" required minlength="2" maxlength="100" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="contact-email">Email</label>
                            <input type="email" id="contact-email" name="contact_email" value="<?= e($contact['email']) ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="contact-phone">Nomor Telepon</label>
                            <input type="tel" id="contact-phone" name="contact_phone" value="<?= e($contact['phone']) ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                        </div>
                    </div>
                </div>

                <label class="flex items-start gap-3 bg-white rounded-2xl border border-gray-100 shadow-sm p-4 cursor-pointer">
                    <input type="checkbox" name="agree" value="1" required class="mt-1 w-4 h-4 text-primary rounded" <?= isset($_POST['agree']) ? 'checked' : '' ?>>
                    <span class="text-sm text-gray-600 leading-relaxed">Saya memastikan seluruh data penumpang benar dan menyetujui <a href="<?= e(page_url('bantuan')) ?>" class="text-primary font-semibold hover:underline">syarat &amp; ketentuan</a> pemesanan. Perubahan nama setelah tiket terbit dapat dikenakan biaya.</span>
                </label>

                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit" class="bg-accent hover:bg-accentDark text-white font-bold px-8 py-3.5 rounded-xl shadow-lg transition text-lg flex items-center justify-center gap-2">
                        Lanjut ke Ringkasan <i class="fa-solid fa-arrow-right"></i>
                    </button>
                    <a href="<?= e(page_url('pesawat')) ?>" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold px-6 py-3.5 rounded-xl transition text-center">Batal</a>
                </div>
            </div>

            <aside class="lg:col-span-1 space-y-6">
                <?php require __DIR__ . '/../includes/booking-itinerary.php'; ?>

                <div class="bg-blue-50 border border-blue-100 rounded-2xl p-5">
                    <h3 class="font-bold text-blue-900 mb-2"><i class="fa-solid fa-circle-info text-primary mr-1"></i>Tips pengisian</h3>
                    <ul class="text-sm text-blue-900/80 space-y-2 list-disc list-inside">
                        <li>Gunakan nama lengkap tanpa gelar, sesuai KTP atau paspor.</li>
                        <li>Bayi di bawah 2 tahun duduk di pangkuan penumpang dewasa.</li>
                        <li>Pastikan email aktif — e-tiket dikirim otomatis setelah pembayaran.</li>
                    </ul>
                </div>
            </aside>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

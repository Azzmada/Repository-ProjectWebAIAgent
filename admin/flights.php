<?php
/**
 * Admin — flight CRUD with filters.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

require_admin();

$airlines = Database::fetchAll('SELECT id, name, code FROM airlines ORDER BY name ASC');
$cities = Database::fetchAll('SELECT id, name, iata_code FROM cities ORDER BY is_popular DESC, name ASC');

if (is_post()) {
    verify_csrf();
    $action = input('action');

    if ($action === 'save') {
        $id = input_int('id');
        $airlineId = input_int('airline_id');
        $fromCityId = input_int('from_city_id');
        $toCityId = input_int('to_city_id');
        $flightNum = strtoupper(clean(input('flight_num')));
        $aircraft = clean(input('aircraft'));
        $depTime = clean(input('dep_time'));
        $arrTime = clean(input('arr_time'));
        $duration = input_int('duration');
        $price = (float) str_replace(',', '.', input('price'));
        $stops = input_int('stops');
        $baggage = input_int('baggage_kg');
        $refund = clean(input('refund_policy'));
        $reschedule = clean(input('reschedule_policy'));

        $errors = [];
        if (!Database::fetch('SELECT id FROM airlines WHERE id = ?', [$airlineId])) {
            $errors[] = 'Pilih maskapai yang valid.';
        }
        if (!Database::fetch('SELECT id FROM cities WHERE id = ?', [$fromCityId]) || !Database::fetch('SELECT id FROM cities WHERE id = ?', [$toCityId])) {
            $errors[] = 'Kota asal dan tujuan harus valid.';
        }
        if ($fromCityId === $toCityId) {
            $errors[] = 'Kota asal dan tujuan tidak boleh sama.';
        }
        if (!preg_match('/^[A-Z0-9]{2,12}$/', $flightNum)) {
            $errors[] = 'Nomor penerbangan harus 2-12 huruf/angka.';
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $depTime) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $arrTime)) {
            $errors[] = 'Jam berangkat dan tiba tidak valid.';
        }
        if ($duration < 15 || $duration > 1440) {
            $errors[] = 'Durasi harus antara 15 dan 1440 menit.';
        }
        if ($price <= 0 || $price > 999999999) {
            $errors[] = 'Harga harus lebih dari 0.';
        }
        if ($stops < 0 || $stops > 3) {
            $errors[] = 'Jumlah transit maksimal 3.';
        }
        if ($baggage < 0 || $baggage > 100) {
            $errors[] = 'Bagasi harus antara 0 dan 100 kg.';
        }
        if ($refund === '') {
            $refund = 'Tidak bisa refund';
        }
        if ($reschedule === '') {
            $reschedule = 'Bisa dengan biaya';
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash('error', $error);
            }
            redirect(base_url('admin/flights.php' . ($id > 0 ? '?edit=' . $id : '')));
        }

        $params = [
            $airlineId,
            $fromCityId,
            $toCityId,
            $flightNum,
            $aircraft,
            $depTime,
            $arrTime,
            $duration,
            $price,
            $stops,
            $baggage,
            post_bool('meal') ? 1 : 0,
            post_bool('wifi') ? 1 : 0,
            post_bool('entertainment') ? 1 : 0,
            $refund,
            $reschedule,
            isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($id > 0) {
            $params[] = $id;
            Database::execute(
                'UPDATE flights SET airline_id = ?, from_city_id = ?, to_city_id = ?, flight_num = ?, aircraft = ?,
                    dep_time = ?, arr_time = ?, duration = ?, price = ?, stops = ?, baggage_kg = ?,
                    meal = ?, wifi = ?, entertainment = ?, refund_policy = ?, reschedule_policy = ?, is_active = ?
                 WHERE id = ?',
                $params
            );
            flash('success', 'Penerbangan ' . $flightNum . ' berhasil diperbarui.');
        } else {
            Database::insert(
                'INSERT INTO flights
                    (airline_id, from_city_id, to_city_id, flight_num, aircraft, dep_time, arr_time, duration, price,
                     stops, baggage_kg, meal, wifi, entertainment, refund_policy, reschedule_policy, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $params
            );
            flash('success', 'Penerbangan ' . $flightNum . ' berhasil ditambahkan.');
        }
        redirect(base_url('admin/flights.php'));
    }

    if ($action === 'toggle') {
        $id = input_int('id');
        Database::execute('UPDATE flights SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?', [$id]);
        flash('success', 'Status penerbangan berhasil diubah.');
        redirect(base_url('admin/flights.php'));
    }

    if ($action === 'delete') {
        $id = input_int('id');
        $usedFlights = (int) Database::fetch('SELECT COUNT(*) AS c FROM booking_flights WHERE flight_id = ?', [$id])['c'];
        $usedBookings = (int) Database::fetch('SELECT COUNT(*) AS c FROM bookings WHERE flight_id = ?', [$id])['c'];
        if ($usedFlights > 0 || $usedBookings > 0) {
            flash('error', 'Penerbangan tidak dapat dihapus karena sudah dipakai pada booking. Nonaktifkan saja.');
        } else {
            Database::execute('DELETE FROM flights WHERE id = ?', [$id]);
            flash('success', 'Penerbangan berhasil dihapus.');
        }
        redirect(base_url('admin/flights.php'));
    }
}

$editId = input_int('edit');
$editing = $editId > 0 ? Database::fetch('SELECT * FROM flights WHERE id = ?', [$editId]) : false;

// Filters
$filters = [
    'q'          => clean((string) ($_GET['q'] ?? '')),
    'airline_id' => input_int('airline_id'),
    'from'       => input_int('from'),
    'to'         => input_int('to'),
    'active'     => (string) ($_GET['active'] ?? ''),
];
$where = [];
$params = [];
if ($filters['q'] !== '') {
    $where[] = 'f.flight_num LIKE ?';
    $params[] = '%' . $filters['q'] . '%';
}
if ($filters['airline_id'] > 0) {
    $where[] = 'f.airline_id = ?';
    $params[] = $filters['airline_id'];
}
if ($filters['from'] > 0) {
    $where[] = 'f.from_city_id = ?';
    $params[] = $filters['from'];
}
if ($filters['to'] > 0) {
    $where[] = 'f.to_city_id = ?';
    $params[] = $filters['to'];
}
if ($filters['active'] === '1' || $filters['active'] === '0') {
    $where[] = 'f.is_active = ?';
    $params[] = (int) $filters['active'];
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$flights = Database::fetchAll(
    'SELECT f.*, a.name AS airline_name, a.code AS airline_code,
            fc.name AS from_name, fc.iata_code AS from_code,
            tc.name AS to_name, tc.iata_code AS to_code
     FROM flights f
     JOIN airlines a ON a.id = f.airline_id
     JOIN cities fc ON fc.id = f.from_city_id
     JOIN cities tc ON tc.id = f.to_city_id
     ' . $whereSql . '
     ORDER BY f.is_active DESC, f.id DESC
     LIMIT 200',
    $params
);

$adminTitle = 'Penerbangan';
$adminActive = 'flights';
require __DIR__ . '/includes/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-4">
        <!-- Filter -->
        <form method="get" class="bg-white rounded-2xl border border-gray-200 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                <div class="lg:col-span-1">
                    <label class="block text-xs font-semibold text-gray-500 mb-1">No. Penerbangan</label>
                    <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="GA-402" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Maskapai</label>
                    <select name="airline_id" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                        <option value="0">Semua</option>
                        <?php foreach ($airlines as $airline): ?>
                            <option value="<?= (int) $airline['id'] ?>" <?= $filters['airline_id'] === (int) $airline['id'] ? 'selected' : '' ?>><?= e($airline['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Dari</label>
                    <select name="from" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                        <option value="0">Semua</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= (int) $city['id'] ?>" <?= $filters['from'] === (int) $city['id'] ? 'selected' : '' ?>><?= e($city['name']) ?> (<?= e($city['iata_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Ke</label>
                    <select name="to" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                        <option value="0">Semua</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= (int) $city['id'] ?>" <?= $filters['to'] === (int) $city['id'] ? 'selected' : '' ?>><?= e($city['name']) ?> (<?= e($city['iata_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-2">
                    <select name="active" class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                        <option value="">Semua Status</option>
                        <option value="1" <?= $filters['active'] === '1' ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= $filters['active'] === '0' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                    <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold px-4 py-2.5 rounded-lg">Filter</button>
                </div>
            </div>
            <div class="mt-2 flex justify-end"><a href="<?= e(base_url('admin/flights.php')) ?>" class="text-xs text-gray-500 hover:text-primary"><i class="fa-solid fa-rotate-left mr-1"></i>Reset filter</a></div>
        </form>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-bold text-gray-900"><i class="fa-solid fa-route text-primary mr-2"></i>Daftar Penerbangan</h2>
                <span class="text-xs font-semibold bg-gray-100 text-gray-600 px-3 py-1 rounded-full"><?= count($flights) ?> ditampilkan</span>
            </div>
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Penerbangan</th>
                            <th class="text-left px-4 py-3 font-semibold">Rute</th>
                            <th class="text-left px-4 py-3 font-semibold">Jadwal</th>
                            <th class="text-right px-4 py-3 font-semibold">Harga</th>
                            <th class="text-center px-4 py-3 font-semibold">Status</th>
                            <th class="text-right px-4 py-3 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (!$flights): ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Tidak ada penerbangan sesuai filter.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($flights as $flight): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900"><?= e($flight['flight_num']) ?></p>
                                    <p class="text-[11px] text-gray-500"><?= e($flight['airline_name']) ?> · <?= e($flight['aircraft']) ?></p>
                                </td>
                                <td class="px-4 py-3 text-gray-700"><?= e($flight['from_code']) ?> → <?= e($flight['to_code']) ?></td>
                                <td class="px-4 py-3 text-gray-600 text-xs">
                                    <?= e(substr((string) $flight['dep_time'], 0, 5)) ?> - <?= e(substr((string) $flight['arr_time'], 0, 5)) ?><br>
                                    <span class="text-gray-400"><?= e(format_duration((int) $flight['duration'])) ?> · <?= (int) $flight['stops'] === 0 ? 'Langsung' : (int) $flight['stops'] . ' transit' ?></span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900"><?= e(rupiah($flight['price'])) ?></td>
                                <td class="px-4 py-3 text-center">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $flight['id'] ?>">
                                        <button type="submit" class="text-[11px] font-semibold px-2.5 py-1 rounded-full border <?= (int) $flight['is_active'] === 1 ? 'bg-green-50 text-green-700 border-green-200' : 'bg-gray-100 text-gray-500 border-gray-200' ?>" title="Klik untuk ubah status"><?= (int) $flight['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></button>
                                    </form>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="<?= e(base_url('admin/flights.php?edit=' . (int) $flight['id'])) ?>" class="text-xs font-semibold text-primary hover:text-primaryDark bg-blue-50 px-2.5 py-1.5 rounded-lg"><i class="fa-solid fa-pen"></i></a>
                                        <form method="post" data-confirm="Hapus penerbangan <?= e($flight['flight_num']) ?>?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $flight['id'] ?>">
                                            <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-700 bg-red-50 px-2.5 py-1.5 rounded-lg"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Form -->
    <div class="xl:col-span-1">
        <div class="bg-white rounded-2xl border border-gray-200 p-5 xl:sticky xl:top-24 max-h-[calc(100vh-7rem)] overflow-y-auto">
            <h2 class="font-bold text-gray-900 mb-1"><i class="fa-solid <?= $editing ? 'fa-pen' : 'fa-plus' ?> text-primary mr-2"></i><?= $editing ? 'Edit Penerbangan' : 'Tambah Penerbangan' ?></h2>
            <p class="text-xs text-gray-500 mb-4"><?= $editing ? 'Perbarui jadwal dan harga.' : 'Buat jadwal penerbangan baru.' ?></p>

            <?php if ($editing): ?>
                <div class="mb-4 bg-blue-50 border border-blue-100 rounded-xl p-3 text-xs text-blue-800">
                    Mengedit <strong><?= e($editing['flight_num']) ?></strong>. <a href="<?= e(base_url('admin/flights.php')) ?>" class="underline font-semibold">Batal</a>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Maskapai</label>
                    <select name="airline_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                        <option value="">Pilih maskapai</option>
                        <?php foreach ($airlines as $airline): ?>
                            <option value="<?= (int) $airline['id'] ?>" <?= (int) ($editing['airline_id'] ?? 0) === (int) $airline['id'] ? 'selected' : '' ?>><?= e($airline['name']) ?> (<?= e($airline['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Dari</label>
                        <select name="from_city_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                            <option value="">Kota asal</option>
                            <?php foreach ($cities as $city): ?>
                                <option value="<?= (int) $city['id'] ?>" <?= (int) ($editing['from_city_id'] ?? 0) === (int) $city['id'] ? 'selected' : '' ?>><?= e($city['name']) ?> (<?= e($city['iata_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Ke</label>
                        <select name="to_city_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                            <option value="">Kota tujuan</option>
                            <?php foreach ($cities as $city): ?>
                                <option value="<?= (int) $city['id'] ?>" <?= (int) ($editing['to_city_id'] ?? 0) === (int) $city['id'] ? 'selected' : '' ?>><?= e($city['name']) ?> (<?= e($city['iata_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">No. Penerbangan</label>
                        <input type="text" name="flight_num" required maxlength="12" value="<?= e((string) ($editing['flight_num'] ?? '')) ?>" placeholder="GA-402" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Pesawat</label>
                        <input type="text" name="aircraft" maxlength="60" value="<?= e((string) ($editing['aircraft'] ?? 'Boeing 737-800')) ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Berangkat</label>
                        <input type="time" name="dep_time" required value="<?= e(substr((string) ($editing['dep_time'] ?? '08:00'), 0, 5)) ?>" class="w-full px-2 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tiba</label>
                        <input type="time" name="arr_time" required value="<?= e(substr((string) ($editing['arr_time'] ?? '10:00'), 0, 5)) ?>" class="w-full px-2 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Menit</label>
                        <input type="number" name="duration" required min="15" max="1440" value="<?= (int) ($editing['duration'] ?? 120) ?>" class="w-full px-2 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Harga (IDR)</label>
                        <input type="number" name="price" required min="10000" step="1000" value="<?= e((string) ($editing['price'] ?? '1000000')) ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Transit</label>
                        <input type="number" name="stops" min="0" max="3" value="<?= (int) ($editing['stops'] ?? 0) ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Bagasi (kg)</label>
                    <input type="number" name="baggage_kg" min="0" max="100" value="<?= (int) ($editing['baggage_kg'] ?? 20) ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div class="grid grid-cols-3 gap-2 text-xs">
                    <label class="flex items-center gap-1.5 bg-gray-50 rounded-lg p-2 cursor-pointer"><input type="checkbox" name="meal" value="1" class="w-3.5 h-3.5 text-primary rounded" <?= (int) ($editing['meal'] ?? 1) === 1 ? 'checked' : '' ?>>Makan</label>
                    <label class="flex items-center gap-1.5 bg-gray-50 rounded-lg p-2 cursor-pointer"><input type="checkbox" name="wifi" value="1" class="w-3.5 h-3.5 text-primary rounded" <?= (int) ($editing['wifi'] ?? 1) === 1 ? 'checked' : '' ?>>WiFi</label>
                    <label class="flex items-center gap-1.5 bg-gray-50 rounded-lg p-2 cursor-pointer"><input type="checkbox" name="entertainment" value="1" class="w-3.5 h-3.5 text-primary rounded" <?= (int) ($editing['entertainment'] ?? 1) === 1 ? 'checked' : '' ?>>Hiburan</label>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Kebijakan Refund</label>
                    <input type="text" name="refund_policy" maxlength="100" value="<?= e((string) ($editing['refund_policy'] ?? 'Tidak bisa refund')) ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Kebijakan Reschedule</label>
                    <input type="text" name="reschedule_policy" maxlength="100" value="<?= e((string) ($editing['reschedule_policy'] ?? 'Bisa dengan biaya')) ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 bg-gray-50 rounded-lg p-2.5 cursor-pointer"><input type="checkbox" name="is_active" value="1" class="w-4 h-4 text-primary rounded" <?= (int) ($editing['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>Penerbangan aktif</label>
                <button type="submit" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-3 rounded-xl transition"><i class="fa-solid fa-floppy-disk mr-2"></i><?= $editing ? 'Simpan Perubahan' : 'Tambah Penerbangan' ?></button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

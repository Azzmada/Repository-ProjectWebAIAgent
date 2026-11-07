<?php
/**
 * Admin — promo CRUD.
 *
 * Promo codes power the public promo page and the checkout discount box.
 * Deleting a promo that has already been redeemed is blocked so booking
 * history and promo_usage rows stay intact.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

require_admin();

/** Badge colours exposed to the public promo cards (Tailwind classes). */
$badgeColors = [
    'bg-accent',
    'bg-accentDark',
    'bg-primary',
    'bg-primaryDark',
    'bg-green-600',
    'bg-purple-600',
    'bg-blue-600',
    'bg-pink-600',
    'bg-red-600',
    'bg-gray-800',
];

if (is_post()) {
    verify_csrf();
    $action = input('action');

    if ($action === 'save') {
        $id = input_int('id');
        $code = strtoupper(clean(input('code')));
        $type = input('type');
        $value = (float) str_replace(',', '.', input('value'));
        $minTransaction = (float) str_replace(',', '.', input('min_transaction'));
        $maxUsage = input_int('max_usage');
        $validFrom = clean(input('valid_from'));
        $validUntil = clean(input('valid_until'));
        $title = clean(input('title'));
        $description = trim((string) ($_POST['description'] ?? ''));
        $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
        $badge = strtoupper(clean(input('badge')));
        $badgeColor = clean(input('badge_color'));
        $isActive = post_bool('is_active') ? 1 : 0;

        $errors = [];

        if (!preg_match('/^[A-Z0-9_-]{3,40}$/', $code)) {
            $errors[] = 'Kode promo harus 3-40 karakter (huruf, angka, - atau _).';
        }
        if (!in_array($type, ['percent', 'nominal'], true)) {
            $errors[] = 'Tipe promo tidak valid.';
        }
        if ($type === 'percent' && ($value <= 0 || $value > 100)) {
            $errors[] = 'Diskon persen harus antara 0 dan 100.';
        }
        if ($type === 'nominal' && ($value <= 0 || $value > 999999999)) {
            $errors[] = 'Potongan nominal harus lebih dari 0.';
        }
        if ($minTransaction < 0 || $minTransaction > 999999999) {
            $errors[] = 'Minimal transaksi tidak valid.';
        }
        if ($maxUsage < 0 || $maxUsage > 1000000) {
            $errors[] = 'Kuota pemakaian tidak valid (0 = tanpa batas).';
        }
        if (!valid_date($validFrom)) {
            $errors[] = 'Tanggal mulai tidak valid.';
        }
        if (!valid_date($validUntil)) {
            $errors[] = 'Tanggal berakhir tidak valid.';
        }
        if (valid_date($validFrom) && valid_date($validUntil) && $validUntil < $validFrom) {
            $errors[] = 'Tanggal berakhir tidak boleh sebelum tanggal mulai.';
        }
        if (mb_strlen($title) > 150) {
            $errors[] = 'Judul promo maksimal 150 karakter.';
        }
        if (mb_strlen($description) > 1000) {
            $errors[] = 'Deskripsi promo maksimal 1000 karakter.';
        }
        if ($badge === '') {
            $badge = 'PROMO';
        }
        if (mb_strlen($badge) > 40) {
            $errors[] = 'Label badge maksimal 40 karakter.';
        }
        if (!in_array($badgeColor, $badgeColors, true)) {
            $errors[] = 'Warna badge tidak valid.';
        }
        if ($imageUrl !== '' && mb_strlen($imageUrl) > 500) {
            $errors[] = 'URL gambar terlalu panjang.';
        }
        if ($imageUrl !== '' && !preg_match('#^https?://#i', $imageUrl) && !str_starts_with($imageUrl, '/')) {
            $errors[] = 'URL gambar harus diawali http(s):// atau /.';
        }
        if (!$errors) {
            $duplicate = Database::fetch(
                'SELECT id FROM promos WHERE code = ? AND id <> ? LIMIT 1',
                [$code, $id]
            );
            if ($duplicate) {
                $errors[] = 'Kode promo sudah digunakan.';
            }
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash('error', $error);
            }
            redirect(base_url('admin/promos.php' . ($id > 0 ? '?edit=' . $id : '')));
        }

        $params = [
            $code,
            $type,
            $value,
            $minTransaction,
            $maxUsage,
            $validFrom,
            $validUntil,
            $title,
            $description !== '' ? $description : null,
            $imageUrl !== '' ? $imageUrl : null,
            $badge,
            $badgeColor,
            $isActive,
        ];

        if ($id > 0) {
            $params[] = $id;
            Database::execute(
                'UPDATE promos SET code = ?, type = ?, value = ?, min_transaction = ?, max_usage = ?,
                    valid_from = ?, valid_until = ?, title = ?, description = ?, image_url = ?,
                    badge = ?, badge_color = ?, is_active = ?
                 WHERE id = ?',
                $params
            );
            flash('success', 'Promo berhasil diperbarui.');
        } else {
            Database::insert(
                'INSERT INTO promos (code, type, value, min_transaction, max_usage, valid_from, valid_until,
                    title, description, image_url, badge, badge_color, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $params
            );
            flash('success', 'Promo baru berhasil ditambahkan.');
        }
        redirect(base_url('admin/promos.php'));
    }

    if ($action === 'toggle') {
        $id = input_int('id');
        Database::execute('UPDATE promos SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?', [$id]);
        flash('success', 'Status promo berhasil diubah.');
        redirect(base_url('admin/promos.php'));
    }

    if ($action === 'delete') {
        $id = input_int('id');
        $promo = Database::fetch('SELECT code, used_count FROM promos WHERE id = ?', [$id]);

        if (!$promo) {
            flash('error', 'Promo tidak ditemukan.');
        } else {
            $usageCount = (int) Database::fetch(
                'SELECT COUNT(*) AS c FROM promo_usage WHERE promo_id = ?',
                [$id]
            )['c'];
            $bookingCount = (int) Database::fetch(
                'SELECT COUNT(*) AS c FROM bookings WHERE promo_id = ?',
                [$id]
            )['c'];

            if ($usageCount > 0 || $bookingCount > 0 || (int) $promo['used_count'] > 0) {
                flash('error', 'Promo ' . $promo['code'] . ' tidak dapat dihapus karena sudah pernah dipakai. Nonaktifkan saja.');
            } else {
                Database::execute('DELETE FROM promos WHERE id = ?', [$id]);
                flash('success', 'Promo ' . $promo['code'] . ' berhasil dihapus.');
            }
        }
        redirect(base_url('admin/promos.php'));
    }
}

$editId = input_int('edit');
$editing = $editId > 0
    ? Database::fetch('SELECT * FROM promos WHERE id = ?', [$editId])
    : false;

$search = clean(input('q'));
$status = input('status');
if (!in_array($status, ['active', 'inactive', 'expired'], true)) {
    $status = '';
}

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(code LIKE ? OR title LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($status === 'active') {
    $where[] = 'is_active = 1 AND valid_until >= CURDATE()';
} elseif ($status === 'inactive') {
    $where[] = 'is_active = 0';
} elseif ($status === 'expired') {
    $where[] = 'valid_until < CURDATE()';
}

$sql = 'SELECT p.* FROM promos p';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.is_active DESC, p.valid_until ASC, p.id DESC';

$promos = Database::fetchAll($sql, $params);

$stats = Database::fetch(
    'SELECT COUNT(*) AS total,
            SUM(is_active = 1 AND valid_until >= CURDATE()) AS active,
            SUM(used_count) AS used,
            SUM(CASE WHEN max_usage > 0 THEN max_usage ELSE 0 END) AS quota
     FROM promos'
) ?: ['total' => 0, 'active' => 0, 'used' => 0, 'quota' => 0];

$adminTitle = 'Promo';
$adminActive = 'promos';
require __DIR__ . '/includes/header.php';

/** Helper: human readable discount for the table. */
$discountLabel = static function (array $promo): string {
    return $promo['type'] === 'percent'
        ? (int) $promo['value'] . '%'
        : rupiah($promo['value']);
};
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Total Promo</p>
        <p class="text-2xl font-extrabold text-gray-900 mt-1"><?= (int) $stats['total'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Aktif</p>
        <p class="text-2xl font-extrabold text-green-600 mt-1"><?= (int) $stats['active'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Total Pemakaian</p>
        <p class="text-2xl font-extrabold text-primary mt-1"><?= (int) $stats['used'] ?></p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Total Kuota</p>
        <p class="text-2xl font-extrabold text-accent mt-1"><?= (int) $stats['quota'] ?></p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <h2 class="font-bold text-gray-900"><i class="fa-solid fa-tags text-primary mr-2"></i>Daftar Promo</h2>
                <span class="text-xs font-semibold bg-gray-100 text-gray-600 px-3 py-1 rounded-full"><?= count($promos) ?> promo</span>
            </div>
            <form method="get" class="mt-3 flex flex-col sm:flex-row gap-2">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari kode atau judul..." class="flex-1 px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm">
                <select name="status" class="px-3 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm">
                    <option value="">Semua status</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Kedaluwarsa</option>
                </select>
                <button type="submit" class="bg-primary hover:bg-primaryDark text-white text-sm font-bold px-4 py-2 rounded-xl transition"><i class="fa-solid fa-magnifying-glass mr-1"></i>Filter</button>
                <?php if ($search !== '' || $status !== ''): ?>
                    <a href="<?= e(base_url('admin/promos.php')) ?>" class="text-sm font-semibold text-gray-500 hover:text-gray-700 px-3 py-2">Reset</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="overflow-x-auto table-scroll">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold">Promo</th>
                        <th class="text-left px-5 py-3 font-semibold">Diskon</th>
                        <th class="text-left px-5 py-3 font-semibold">Periode</th>
                        <th class="text-center px-5 py-3 font-semibold">Pemakaian</th>
                        <th class="text-center px-5 py-3 font-semibold">Status</th>
                        <th class="text-right px-5 py-3 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (!$promos): ?>
                        <tr><td colspan="6" class="px-5 py-8 text-center text-gray-500">Belum ada promo yang cocok.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($promos as $promo):
                        $expired = $promo['valid_until'] < date('Y-m-d');
                        $active = (int) $promo['is_active'] === 1 && !$expired;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-lg bg-orange-50 text-accent flex items-center justify-center"><i class="fa-solid fa-ticket"></i></span>
                                    <div>
                                        <p class="font-mono font-bold text-gray-900"><?= e($promo['code']) ?></p>
                                        <p class="text-[11px] text-gray-500 max-w-[220px] truncate"><?= e($promo['title'] ?: 'Tanpa judul') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-gray-800"><?= e($discountLabel($promo)) ?></p>
                                <p class="text-[11px] text-gray-400">
                                    <?= $promo['min_transaction'] > 0 ? 'Min. ' . e(rupiah($promo['min_transaction'])) : 'Tanpa minimum' ?>
                                </p>
                            </td>
                            <td class="px-5 py-3 text-gray-600">
                                <p class="text-xs"><?= e(format_date_id($promo['valid_from'])) ?></p>
                                <p class="text-xs text-gray-400">s/d <?= e(format_date_id($promo['valid_until'])) ?></p>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <p class="font-semibold text-gray-800"><?= (int) $promo['used_count'] ?></p>
                                <p class="text-[11px] text-gray-400"><?= (int) $promo['max_usage'] > 0 ? 'dari ' . (int) $promo['max_usage'] : 'tanpa batas' ?></p>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <?php if ($expired): ?>
                                    <span class="text-[11px] font-bold px-2 py-1 rounded-full bg-gray-100 text-gray-500">KEDALUWARSA</span>
                                <?php elseif ($active): ?>
                                    <span class="text-[11px] font-bold px-2 py-1 rounded-full bg-green-100 text-green-700">AKTIF</span>
                                <?php else: ?>
                                    <span class="text-[11px] font-bold px-2 py-1 rounded-full bg-red-100 text-red-600">NONAKTIF</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $promo['id'] ?>">
                                        <button type="submit" class="text-xs font-semibold text-gray-700 hover:text-gray-900 bg-gray-100 px-3 py-1.5 rounded-lg" title="Aktif / nonaktifkan">
                                            <i class="fa-solid <?= (int) $promo['is_active'] === 1 ? 'fa-toggle-on text-green-600' : 'fa-toggle-off' ?> mr-1"></i><?= (int) $promo['is_active'] === 1 ? 'On' : 'Off' ?>
                                        </button>
                                    </form>
                                    <a href="<?= e(base_url('admin/promos.php?edit=' . (int) $promo['id'])) ?>" class="text-xs font-semibold text-primary hover:text-primaryDark bg-blue-50 px-3 py-1.5 rounded-lg"><i class="fa-solid fa-pen mr-1"></i>Edit</a>
                                    <form method="post" data-confirm="Hapus promo <?= e($promo['code']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $promo['id'] ?>">
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-700 bg-red-50 px-3 py-1.5 rounded-lg"><i class="fa-solid fa-trash mr-1"></i>Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="xl:col-span-1">
        <div class="bg-white rounded-2xl border border-gray-200 p-5 xl:sticky xl:top-24">
            <h2 class="font-bold text-gray-900 mb-1"><i class="fa-solid <?= $editing ? 'fa-pen' : 'fa-plus' ?> text-primary mr-2"></i><?= $editing ? 'Edit Promo' : 'Tambah Promo' ?></h2>
            <p class="text-xs text-gray-500 mb-4"><?= $editing ? 'Perbarui data promo.' : 'Buat kode promo baru untuk pelanggan.' ?></p>

            <?php if ($editing): ?>
                <div class="mb-4 bg-blue-50 border border-blue-100 rounded-xl p-3 text-xs text-blue-800">
                    Mengedit <strong><?= e($editing['code']) ?></strong>. <a href="<?= e(base_url('admin/promos.php')) ?>" class="underline font-semibold">Batal</a>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kode Promo</label>
                    <input type="text" name="code" required minlength="3" maxlength="40" value="<?= e((string) ($editing['code'] ?? '')) ?>" placeholder="TERBANG20" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none uppercase font-mono">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
                        <select name="type" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none">
                            <option value="percent" <?= ($editing['type'] ?? 'percent') === 'percent' ? 'selected' : '' ?>>Persen (%)</option>
                            <option value="nominal" <?= ($editing['type'] ?? '') === 'nominal' ? 'selected' : '' ?>>Nominal (Rp)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nilai</label>
                        <input type="number" name="value" required min="0" step="0.01" value="<?= e((string) ($editing['value'] ?? '')) ?>" placeholder="20" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Min. Transaksi</label>
                        <input type="number" name="min_transaction" min="0" step="1000" value="<?= e((string) ($editing['min_transaction'] ?? '0')) ?>" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kuota</label>
                        <input type="number" name="max_usage" min="0" step="1" value="<?= e((string) ($editing['max_usage'] ?? '0')) ?>" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none">
                        <p class="text-[11px] text-gray-400 mt-1">0 = tanpa batas</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mulai</label>
                        <input type="date" name="valid_from" required value="<?= e((string) ($editing['valid_from'] ?? date('Y-m-d'))) ?>" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Berakhir</label>
                        <input type="date" name="valid_until" required value="<?= e((string) ($editing['valid_until'] ?? date('Y-m-d', strtotime('+30 days')))) ?>" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Judul <span class="text-gray-400 font-normal">(opsional)</span></label>
                    <input type="text" name="title" maxlength="150" value="<?= e((string) ($editing['title'] ?? '')) ?>" placeholder="Diskon 20% Tiket Pesawat" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi <span class="text-gray-400 font-normal">(opsional)</span></label>
                    <textarea name="description" rows="3" maxlength="1000" placeholder="Syarat dan ketentuan singkat..." class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none"><?= e((string) ($editing['description'] ?? '')) ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">URL Gambar <span class="text-gray-400 font-normal">(opsional)</span></label>
                    <input type="text" name="image_url" maxlength="500" value="<?= e((string) ($editing['image_url'] ?? '')) ?>" placeholder="https://..." class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Badge</label>
                        <input type="text" name="badge" maxlength="40" value="<?= e((string) ($editing['badge'] ?? 'PROMO')) ?>" placeholder="PROMO" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none uppercase">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Warna Badge</label>
                        <select name="badge_color" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none">
                            <?php foreach ($badgeColors as $color): ?>
                                <option value="<?= e($color) ?>" <?= ($editing['badge_color'] ?? 'bg-accent') === $color ? 'selected' : '' ?>><?= e($color) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label class="flex items-center gap-3 text-sm font-medium text-gray-700">
                    <input type="checkbox" name="is_active" value="1" <?= !$editing || (int) $editing['is_active'] === 1 ? 'checked' : '' ?> class="w-4 h-4 rounded text-primary focus:ring-primary">
                    Aktifkan promo
                </label>

                <button type="submit" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-3 rounded-xl transition"><i class="fa-solid fa-floppy-disk mr-2"></i><?= $editing ? 'Simpan Perubahan' : 'Tambah Promo' ?></button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

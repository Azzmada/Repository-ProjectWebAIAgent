<?php
/**
 * Admin — airline CRUD.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

require_admin();

if (is_post()) {
    verify_csrf();
    $action = input('action');

    if ($action === 'save') {
        $id = input_int('id');
        $name = clean(input('name'));
        $code = strtoupper(clean(input('code')));
        $logoUrl = trim((string) ($_POST['logo_url'] ?? ''));

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Nama maskapai harus 2-100 karakter.';
        }
        if (!preg_match('/^[A-Z0-9]{2,5}$/', $code)) {
            $errors[] = 'Kode maskapai harus 2-5 huruf/angka (contoh: GA, JT).';
        }
        if ($logoUrl !== '' && mb_strlen($logoUrl) > 500) {
            $errors[] = 'URL logo terlalu panjang.';
        }
        if ($logoUrl !== '' && !preg_match('#^https?://#i', $logoUrl) && !str_starts_with($logoUrl, '/')) {
            $errors[] = 'URL logo harus diawali http(s):// atau /.';
        }
        if (!$errors) {
            $duplicate = Database::fetch(
                'SELECT id FROM airlines WHERE code = ? AND id <> ? LIMIT 1',
                [$code, $id]
            );
            if ($duplicate) {
                $errors[] = 'Kode maskapai sudah digunakan.';
            }
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash('error', $error);
            }
            redirect(base_url('admin/airlines.php' . ($id > 0 ? '?edit=' . $id : '')));
        }

        if ($id > 0) {
            Database::execute(
                'UPDATE airlines SET name = ?, code = ?, logo_url = ? WHERE id = ?',
                [$name, $code, $logoUrl !== '' ? $logoUrl : null, $id]
            );
            flash('success', 'Maskapai berhasil diperbarui.');
        } else {
            Database::insert(
                'INSERT INTO airlines (name, code, logo_url) VALUES (?, ?, ?)',
                [$name, $code, $logoUrl !== '' ? $logoUrl : null]
            );
            flash('success', 'Maskapai baru berhasil ditambahkan.');
        }
        redirect(base_url('admin/airlines.php'));
    }

    if ($action === 'delete') {
        $id = input_int('id');
        $used = (int) Database::fetch('SELECT COUNT(*) AS c FROM flights WHERE airline_id = ?', [$id])['c'];
        if ($used > 0) {
            flash('error', 'Maskapai tidak dapat dihapus karena masih memiliki ' . $used . ' penerbangan.');
        } else {
            Database::execute('DELETE FROM airlines WHERE id = ?', [$id]);
            flash('success', 'Maskapai berhasil dihapus.');
        }
        redirect(base_url('admin/airlines.php'));
    }
}

$editId = input_int('edit');
$editing = $editId > 0
    ? Database::fetch('SELECT * FROM airlines WHERE id = ?', [$editId])
    : false;

$airlines = Database::fetchAll(
    'SELECT a.*, (SELECT COUNT(*) FROM flights f WHERE f.airline_id = a.id) AS flight_count
     FROM airlines a
     ORDER BY a.name ASC'
);

$adminTitle = 'Maskapai';
$adminActive = 'airlines';
require __DIR__ . '/includes/header.php';
?>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-900"><i class="fa-solid fa-plane text-primary mr-2"></i>Daftar Maskapai</h2>
            <span class="text-xs font-semibold bg-gray-100 text-gray-600 px-3 py-1 rounded-full"><?= count($airlines) ?> maskapai</span>
        </div>
        <div class="overflow-x-auto table-scroll">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="text-left px-5 py-3 font-semibold">Maskapai</th>
                        <th class="text-left px-5 py-3 font-semibold">Kode</th>
                        <th class="text-center px-5 py-3 font-semibold">Penerbangan</th>
                        <th class="text-right px-5 py-3 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (!$airlines): ?>
                        <tr><td colspan="4" class="px-5 py-8 text-center text-gray-500">Belum ada maskapai.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($airlines as $airline): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-lg bg-blue-50 text-primary flex items-center justify-center"><i class="fa-solid fa-plane"></i></span>
                                    <div>
                                        <p class="font-semibold text-gray-900"><?= e($airline['name']) ?></p>
                                        <?php if ($airline['logo_url']): ?><p class="text-[11px] text-gray-400 truncate max-w-[220px]"><?= e($airline['logo_url']) ?></p><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3"><span class="font-mono font-bold text-gray-700 bg-gray-100 px-2 py-1 rounded"><?= e($airline['code']) ?></span></td>
                            <td class="px-5 py-3 text-center text-gray-600"><?= (int) $airline['flight_count'] ?></td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="<?= e(base_url('admin/airlines.php?edit=' . (int) $airline['id'])) ?>" class="text-xs font-semibold text-primary hover:text-primaryDark bg-blue-50 px-3 py-1.5 rounded-lg"><i class="fa-solid fa-pen mr-1"></i>Edit</a>
                                    <form method="post" data-confirm="Hapus maskapai <?= e($airline['name']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $airline['id'] ?>">
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
            <h2 class="font-bold text-gray-900 mb-1"><i class="fa-solid <?= $editing ? 'fa-pen' : 'fa-plus' ?> text-primary mr-2"></i><?= $editing ? 'Edit Maskapai' : 'Tambah Maskapai' ?></h2>
            <p class="text-xs text-gray-500 mb-4"><?= $editing ? 'Perbarui data maskapai.' : 'Tambahkan maskapai baru ke katalog.' ?></p>

            <?php if ($editing): ?>
                <div class="mb-4 bg-blue-50 border border-blue-100 rounded-xl p-3 text-xs text-blue-800">
                    Mengedit <strong><?= e($editing['name']) ?></strong>. <a href="<?= e(base_url('admin/airlines.php')) ?>" class="underline font-semibold">Batal</a>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Maskapai</label>
                    <input type="text" name="name" required minlength="2" maxlength="100" value="<?= e((string) ($editing['name'] ?? '')) ?>" placeholder="Garuda Indonesia" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kode IATA</label>
                    <input type="text" name="code" required maxlength="5" value="<?= e((string) ($editing['code'] ?? '')) ?>" placeholder="GA" class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none uppercase font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">URL Logo <span class="text-gray-400 font-normal">(opsional)</span></label>
                    <input type="text" name="logo_url" maxlength="500" value="<?= e((string) ($editing['logo_url'] ?? '')) ?>" placeholder="https://..." class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary outline-none">
                </div>
                <button type="submit" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-3 rounded-xl transition"><i class="fa-solid fa-floppy-disk mr-2"></i><?= $editing ? 'Simpan Perubahan' : 'Tambah Maskapai' ?></button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

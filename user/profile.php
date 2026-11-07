<?php
/**
 * User profile — update name, e-mail and phone.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_login();

$user = auth_user();
if (!$user) {
    redirect(base_url('auth/login.php'));
}

expire_stale_bookings();

if (is_post()) {
    verify_csrf();

    $name = clean(input('name'));
    $email = strtolower(clean(input('email')));
    $phone = clean(input('phone'));

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Nama harus 2-100 karakter.';
    }
    if (!valid_email($email)) {
        $errors[] = 'Alamat email tidak valid.';
    }
    if (!valid_phone($phone)) {
        $errors[] = 'Nomor telepon tidak valid.';
    }
    if (!$errors) {
        $exists = Database::fetch('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, (int) $user['id']]);
        if ($exists) {
            $errors[] = 'Email tersebut sudah digunakan akun lain.';
        }
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash('error', $error);
        }
        flash_input(['name' => $name, 'email' => $email, 'phone' => $phone]);
        redirect(base_url('user/profile.php'));
    }

    Database::execute(
        'UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?',
        [$name, $email, $phone, (int) $user['id']]
    );
    flash('success', 'Profil berhasil diperbarui.');
    clear_old_input();
    redirect(base_url('user/profile.php'));
}

$stats = Database::fetch(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'paid'), 0) AS paid,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN total_price ELSE 0 END), 0) AS spent
     FROM bookings WHERE user_id = ?",
    [(int) $user['id']]
);

$pageTitle = 'Profil Saya - ' . APP_NAME;
$activeNav = '';

require __DIR__ . '/../includes/header.php';
?>
<div class="bg-gray-50 py-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Profil Saya</h1>
            <p class="text-gray-500 mt-1">Kelola informasi akun dan keamanan Anda.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Summary -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
                    <div class="w-20 h-20 bg-primary text-white rounded-full flex items-center justify-center text-3xl font-extrabold mx-auto mb-4"><?= e(client_initials($user['name'])) ?></div>
                    <h2 class="text-xl font-bold text-gray-900"><?= e($user['name']) ?></h2>
                    <p class="text-sm text-gray-500 break-all"><?= e($user['email']) ?></p>
                    <p class="text-xs text-gray-400 mt-2"><i class="fa-regular fa-calendar mr-1"></i>Bergabung <?= e(format_date_id($user['created_at'])) ?></p>
                    <div class="grid grid-cols-3 gap-2 mt-6 text-center">
                        <div class="bg-gray-50 rounded-xl p-3">
                            <p class="text-xl font-extrabold text-gray-900"><?= (int) $stats['total'] ?></p>
                            <p class="text-[11px] text-gray-500 leading-tight mt-1">Total<br>Booking</p>
                        </div>
                        <div class="bg-green-50 rounded-xl p-3">
                            <p class="text-xl font-extrabold text-green-700"><?= (int) $stats['paid'] ?></p>
                            <p class="text-[11px] text-gray-500 leading-tight mt-1">Booking<br>Lunas</p>
                        </div>
                        <div class="bg-orange-50 rounded-xl p-3">
                            <p class="text-sm font-extrabold text-accent leading-tight"><?= e(rupiah($stats['spent'])) ?></p>
                            <p class="text-[11px] text-gray-500 leading-tight mt-1">Total<br>Belanja</p>
                        </div>
                    </div>
                    <a href="<?= e(base_url('user/my-bookings.php')) ?>" class="mt-5 w-full inline-flex items-center justify-center gap-2 bg-primary hover:bg-primaryDark text-white font-semibold py-2.5 rounded-xl transition"><i class="fa-solid fa-ticket"></i> Pesanan Saya</a>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="font-bold text-gray-900 mb-3"><i class="fa-solid fa-shield-halved text-primary mr-1"></i> Keamanan Akun</h3>
                    <p class="text-sm text-gray-500 mb-4">Ganti password secara berkala untuk menjaga keamanan akun Anda.</p>
                    <a href="<?= e(base_url('user/change-password.php')) ?>" class="w-full inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2.5 rounded-xl transition"><i class="fa-solid fa-lock"></i> Ganti Password</a>
                </div>
            </div>

            <!-- Edit profile -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Informasi Pribadi</h2>
                    <form method="post" class="space-y-5">
                        <?= csrf_field() ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                            <input type="text" name="name" required minlength="2" maxlength="100" value="<?= e(old('name', $user['name'])) ?>" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" required value="<?= e(old('email', $user['email'])) ?>" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                            <p class="text-xs text-gray-500 mt-1">E-tiket dan notifikasi akan dikirim ke email ini.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon</label>
                            <input type="tel" name="phone" required value="<?= e(old('phone', $user['phone'])) ?>" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3 pt-2">
                            <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-bold px-8 py-3 rounded-xl shadow transition">Simpan Perubahan</button>
                            <a href="<?= e(page_url('home')) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-6 py-3 rounded-xl transition text-center">Batal</a>
                        </div>
                    </form>
                </div>

                <div class="mt-6 bg-blue-50 border border-blue-100 rounded-2xl p-5 flex items-start gap-3">
                    <i class="fa-solid fa-circle-info text-primary mt-0.5"></i>
                    <p class="text-sm text-blue-900">Perubahan nama pada profil tidak otomatis mengubah nama pada tiket yang sudah diterbitkan. Hubungi CS jika perlu mengubah data penumpang.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
clear_old_input();
require __DIR__ . '/../includes/footer.php';

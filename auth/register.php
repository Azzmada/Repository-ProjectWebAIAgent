<?php
/**
 * User registration.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) {
    redirect(page_url('home'));
}

if (is_post()) {
    verify_csrf();

    $name = clean(input('name'));
    $email = strtolower(clean(input('email')));
    $phone = clean(input('phone'));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Nama lengkap harus 2-100 karakter.';
    }
    if (!valid_email($email)) {
        $errors[] = 'Alamat email tidak valid.';
    }
    if (!valid_phone($phone)) {
        $errors[] = 'Nomor telepon tidak valid (8-20 digit).';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password minimal 8 karakter dan harus mengandung huruf serta angka.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }
    if (!isset($_POST['terms'])) {
        $errors[] = 'Anda harus menyetujui syarat dan ketentuan.';
    }

    if (!$errors && Database::fetch('SELECT id FROM users WHERE email = ?', [$email])) {
        $errors[] = 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash('error', $error);
        }
        flash_input(['name' => $name, 'email' => $email, 'phone' => $phone]);
        redirect(base_url('auth/register.php'));
    }

    $userId = Database::insert(
        'INSERT INTO users (name, email, password, phone) VALUES (?, ?, ?, ?)',
        [$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone]
    );

    $user = Database::fetch('SELECT id, name, email, phone FROM users WHERE id = ?', [$userId]);
    if ($user) {
        login_user($user);
    }

    flash('success', 'Akun berhasil dibuat. Selamat datang di TerbangLebih, ' . $name . '!');
    redirect(intended_url(page_url('home')));
}

$pageTitle = 'Daftar Akun - ' . APP_NAME;
$metaDescription = 'Buat akun TerbangLebih gratis untuk memesan tiket pesawat, menyimpan riwayat pesanan, dan mendapatkan promo eksklusif.';
$activeNav = '';

require __DIR__ . '/../includes/header.php';
?>
<div class="min-h-[70vh] bg-gradient-to-br from-blue-50 via-white to-orange-50 py-12 px-4">
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-primary to-cyan-500 px-8 py-8 text-center">
                <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <i class="fa-solid fa-plane-departure text-white text-2xl"></i>
                </div>
                <h1 class="text-2xl font-extrabold text-white">Buat Akun Baru</h1>
                <p class="text-blue-100 text-sm mt-1">Daftar gratis dan mulai terbang lebih mudah.</p>
            </div>

            <div class="p-8">
                <form method="post" class="space-y-4" autocomplete="on">
                    <?= csrf_field() ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                        <input type="text" name="name" required minlength="2" maxlength="100" value="<?= e(old('name')) ?>" placeholder="Sesuai KTP / paspor" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" required maxlength="150" value="<?= e(old('email')) ?>" placeholder="nama@email.com" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon</label>
                        <input type="tel" name="phone" required value="<?= e(old('phone')) ?>" placeholder="08123456789" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" name="password" required minlength="8" placeholder="Min. 8 karakter (huruf + angka)" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password</label>
                        <input type="password" name="password_confirm" required minlength="8" placeholder="Ulangi password" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                    </div>
                    <label class="flex items-start gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="terms" value="1" required class="mt-0.5 w-4 h-4 text-primary rounded">
                        <span>Saya menyetujui <a href="javascript:void(0)" onclick="showToast('Syarat & Ketentuan akan segera tersedia.', 'info')" class="text-primary hover:underline">Syarat &amp; Ketentuan</a> serta <a href="javascript:void(0)" onclick="showToast('Kebijakan Privasi akan segera tersedia.', 'info')" class="text-primary hover:underline">Kebijakan Privasi</a>.</span>
                    </label>
                    <button type="submit" class="w-full bg-accent hover:bg-accentDark text-white font-bold py-3.5 rounded-xl shadow-lg transition text-lg">Daftar Sekarang</button>
                </form>

                <p class="text-center text-sm text-gray-600 mt-6">
                    Sudah punya akun?
                    <a href="<?= e(base_url('auth/login.php')) ?>" class="font-semibold text-primary hover:text-primaryDark">Masuk di sini</a>
                </p>
            </div>
        </div>
        <p class="text-center text-xs text-gray-500 mt-6"><i class="fa-solid fa-shield-halved mr-1"></i>Data Anda dilindungi dengan enkripsi dan password ter-hash.</p>
    </div>
</div>
<?php
clear_old_input();
require __DIR__ . '/../includes/footer.php';

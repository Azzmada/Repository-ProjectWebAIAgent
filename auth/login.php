<?php
/**
 * User login with rate limiting (max 5 attempts / minute per e-mail + IP).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) {
    redirect(page_url('home'));
}

if (is_post()) {
    verify_csrf();

    $email = strtolower(clean(input('email')));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        flash('error', 'Email dan password wajib diisi.');
        flash_input(['email' => $email]);
        redirect(base_url('auth/login.php'));
    }

    if (rate_limit_exceeded($email)) {
        $wait = rate_limit_retry_after($email);
        flash('error', 'Terlalu banyak percobaan masuk. Coba lagi dalam ' . $wait . ' detik.');
        flash_input(['email' => $email]);
        redirect(base_url('auth/login.php'));
    }

    $user = Database::fetch('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
    $dummyHash = '$2y$12$qCP3vHRmRbf577G2I0ne7.be2oim9B7kqSmMs68L/SmB9pxfim2Pm';

    if (!$user || !password_verify($password, $user['password'] ?: $dummyHash)) {
        record_login_attempt($email);
        flash('error', 'Email atau password salah. Silakan coba lagi.');
        flash_input(['email' => $email]);
        redirect(base_url('auth/login.php'));
    }

    clear_login_attempts($email);
    login_user($user);

    flash('success', 'Selamat datang kembali, ' . $user['name'] . '!');
    redirect(intended_url(page_url('home')));
}

$pageTitle = 'Masuk - ' . APP_NAME;
$metaDescription = 'Masuk ke akun TerbangLebih untuk melihat pesanan, mengunduh e-tiket, dan memakai promo.';
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
                <h1 class="text-2xl font-extrabold text-white">Masuk ke Akun Anda</h1>
                <p class="text-blue-100 text-sm mt-1">Lanjutkan perjalanan Anda bersama TerbangLebih.</p>
            </div>

            <div class="p-8">
                <form method="post" class="space-y-4">
                    <?= csrf_field() ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" required value="<?= e(old('email')) ?>" placeholder="nama@email.com" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-gray-700">Password</label>
                            <a href="<?= e(base_url('auth/forgot-password.php')) ?>" class="text-xs font-semibold text-primary hover:text-primaryDark">Lupa password?</a>
                        </div>
                        <input type="password" name="password" required placeholder="Masukkan password" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                    </div>
                    <button type="submit" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-3.5 rounded-xl shadow-lg transition text-lg">Masuk</button>
                </form>

                <p class="text-center text-sm text-gray-600 mt-6">
                    Belum punya akun?
                    <a href="<?= e(base_url('auth/register.php')) ?>" class="font-semibold text-primary hover:text-primaryDark">Daftar gratis</a>
                </p>

                <div class="mt-4 text-center">
                    <a href="<?= e(page_url('home')) ?>" class="text-xs text-gray-500 hover:text-primary"><i class="fa-solid fa-arrow-left mr-1"></i>Kembali ke beranda</a>
                </div>

                <?php if (APP_DEBUG): ?>
                    <div class="mt-6 bg-blue-50 border border-blue-100 rounded-xl p-3 text-xs text-blue-800">
                        <p class="font-bold mb-1"><i class="fa-solid fa-circle-info mr-1"></i>Akun demo</p>
                        <p>Email: <span class="font-mono">demo@terbanglebih.com</span></p>
                        <p>Password: <span class="font-mono">Demo#12345</span></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <p class="text-center text-xs text-gray-500 mt-6"><i class="fa-solid fa-lock mr-1"></i>Maksimal 5 percobaan masuk per menit untuk keamanan akun.</p>
    </div>
</div>
<?php
clear_old_input();
require __DIR__ . '/../includes/footer.php';

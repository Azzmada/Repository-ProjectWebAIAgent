<?php
/**
 * Request a password reset link. Tokens are stored as SHA-256 hashes and
 * expire after 60 minutes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) {
    redirect(base_url('user/profile.php'));
}

$genericMessage = 'Jika email tersebut terdaftar, kami telah mengirim tautan reset password. Silakan periksa inbox (dan folder spam) Anda.';

if (is_post()) {
    verify_csrf();

    $email = strtolower(clean(input('email')));

    if (!valid_email($email)) {
        flash('error', 'Masukkan alamat email yang valid.');
        flash_input(['email' => $email]);
        redirect(base_url('auth/forgot-password.php'));
    }

    if (rate_limit_exceeded('forgot:' . $email)) {
        flash('error', 'Terlalu banyak permintaan. Coba lagi dalam satu menit.');
        redirect(base_url('auth/forgot-password.php'));
    }
    record_login_attempt('forgot:' . $email);

    $user = Database::fetch('SELECT id, name, email FROM users WHERE email = ? LIMIT 1', [$email]);

    if ($user) {
        // Invalidate previous tokens.
        Database::execute('DELETE FROM password_resets WHERE email = ?', [$email]);

        $rawToken = bin2hex(random_bytes(32));
        Database::insert(
            'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))',
            [$email, hash('sha256', $rawToken)]
        );

        $link = absolute_url('auth/reset-password.php?token=' . urlencode($rawToken) . '&email=' . urlencode($email));
        send_password_reset_email($email, $user['name'], $link, 60);
    }

    // Same response whether or not the account exists (prevents enumeration).
    flash('info', $genericMessage);
    redirect(base_url('auth/login.php'));
}

$pageTitle = 'Lupa Password - ' . APP_NAME;
$metaDescription = 'Reset password akun TerbangLebih melalui tautan aman yang dikirim ke email Anda.';
$activeNav = '';

require __DIR__ . '/../includes/header.php';
?>
<div class="min-h-[70vh] bg-gradient-to-br from-blue-50 via-white to-orange-50 py-12 px-4">
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-primary to-cyan-500 px-8 py-8 text-center">
                <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <i class="fa-solid fa-key text-white text-2xl"></i>
                </div>
                <h1 class="text-2xl font-extrabold text-white">Lupa Password?</h1>
                <p class="text-blue-100 text-sm mt-1">Masukkan email terdaftar untuk menerima tautan reset.</p>
            </div>

            <div class="p-8">
                <form method="post" class="space-y-4">
                    <?= csrf_field() ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Terdaftar</label>
                        <input type="email" name="email" required value="<?= e(old('email')) ?>" placeholder="nama@email.com" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                    </div>
                    <button type="submit" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-3.5 rounded-xl shadow-lg transition text-lg">Kirim Tautan Reset</button>
                </form>
                <p class="text-center text-sm text-gray-600 mt-6">
                    Ingat password Anda?
                    <a href="<?= e(base_url('auth/login.php')) ?>" class="font-semibold text-primary hover:text-primaryDark">Kembali masuk</a>
                </p>
                <?php if (APP_DEBUG): ?>
                    <div class="mt-6 bg-blue-50 border border-blue-100 rounded-xl p-3 text-xs text-blue-800">
                        <p class="font-bold mb-1"><i class="fa-solid fa-circle-info mr-1"></i>Mode pengembangan</p>
                        <p>Tautan reset tidak dikirim via SMTP; buka file HTML terbaru di <span class="font-mono">storage/mail/</span> untuk menyalin tautan reset.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
clear_old_input();
require __DIR__ . '/../includes/footer.php';

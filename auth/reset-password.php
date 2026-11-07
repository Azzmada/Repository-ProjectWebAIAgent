<?php
/**
 * Consume a password reset token and set a new password.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) {
    redirect(base_url('user/profile.php'));
}

$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? $_GET['email'] ?? '')));

$isTokenShapeValid = (bool) preg_match('/^[a-f0-9]{64}$/i', $token) && valid_email($email);

$reset = false;
if ($isTokenShapeValid) {
    $reset = Database::fetch(
        'SELECT * FROM password_resets
         WHERE email = ? AND token = ? AND used_at IS NULL AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1',
        [$email, hash('sha256', $token)]
    );
}

if (!$reset) {
    flash('error', 'Tautan reset tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.');
    redirect(base_url('auth/forgot-password.php'));
}

if (is_post()) {
    verify_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    $errors = [];
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password minimal 8 karakter dan harus mengandung huruf serta angka.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash('error', $error);
        }
        redirect(base_url('auth/reset-password.php?token=' . urlencode($token) . '&email=' . urlencode($email)));
    }

    try {
        Database::begin();
        Database::execute(
            'UPDATE users SET password = ? WHERE email = ?',
            [password_hash($password, PASSWORD_DEFAULT), $email]
        );
        Database::execute('UPDATE password_resets SET used_at = NOW() WHERE email = ?', [$email]);
        clear_login_attempts($email);
        Database::commit();
    } catch (Throwable $e) {
        Database::rollback();
        error_log('[reset-password] ' . $e->getMessage());
        flash('error', 'Terjadi kesalahan saat menyimpan password baru. Silakan coba lagi.');
        redirect(base_url('auth/forgot-password.php'));
    }

    flash('success', 'Password berhasil diperbarui. Silakan masuk dengan password baru Anda.');
    redirect(base_url('auth/login.php'));
}

$pageTitle = 'Reset Password - ' . APP_NAME;
$activeNav = '';

require __DIR__ . '/../includes/header.php';
?>
<div class="min-h-[70vh] bg-gradient-to-br from-blue-50 via-white to-orange-50 py-12 px-4">
    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-primary to-cyan-500 px-8 py-8 text-center">
                <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <i class="fa-solid fa-lock text-white text-2xl"></i>
                </div>
                <h1 class="text-2xl font-extrabold text-white">Buat Password Baru</h1>
                <p class="text-blue-100 text-sm mt-1">Untuk akun <?= e($email) ?></p>
            </div>

            <div class="p-8">
                <form method="post" class="space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <input type="hidden" name="email" value="<?= e($email) ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru</label>
                        <input type="password" name="password" required minlength="8" placeholder="Min. 8 karakter (huruf + angka)" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password Baru</label>
                        <input type="password" name="password_confirm" required minlength="8" placeholder="Ulangi password baru" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                    </div>
                    <button type="submit" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-3.5 rounded-xl shadow-lg transition text-lg">Simpan Password Baru</button>
                </form>
                <p class="text-center text-sm text-gray-600 mt-6">
                    <a href="<?= e(base_url('auth/login.php')) ?>" class="font-semibold text-primary hover:text-primaryDark"><i class="fa-solid fa-arrow-left mr-1"></i>Kembali ke halaman masuk</a>
                </p>
            </div>
        </div>
    </div>
</div>
<?php
require __DIR__ . '/../includes/footer.php';

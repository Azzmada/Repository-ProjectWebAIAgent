<?php
/**
 * Change the logged-in user's password.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_login();

$user = auth_user();
if (!$user) {
    redirect(base_url('auth/login.php'));
}

if (is_post()) {
    verify_csrf();

    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirm'] ?? '');

    $row = Database::fetch('SELECT password FROM users WHERE id = ?', [(int) $user['id']]);

    $errors = [];
    if (!$row || !password_verify($current, $row['password'])) {
        $errors[] = 'Password saat ini tidak benar.';
    }
    if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
        $errors[] = 'Password baru minimal 8 karakter dan harus mengandung huruf serta angka.';
    }
    if ($new !== $confirm) {
        $errors[] = 'Konfirmasi password baru tidak cocok.';
    }
    if ($current !== '' && $new !== '' && $current === $new) {
        $errors[] = 'Password baru harus berbeda dari password saat ini.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash('error', $error);
        }
        redirect(base_url('user/change-password.php'));
    }

    Database::execute(
        'UPDATE users SET password = ? WHERE id = ?',
        [password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]
    );

    // Rotate the session and CSRF token after a credential change.
    session_regenerate_id(true);
    unset($_SESSION[CSRF_KEY]);

    flash('success', 'Password berhasil diganti. Gunakan password baru pada login berikutnya.');
    redirect(base_url('user/profile.php'));
}

$pageTitle = 'Ganti Password - ' . APP_NAME;
$activeNav = '';

require __DIR__ . '/../includes/header.php';
?>
<div class="bg-gray-50 py-10">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="<?= e(base_url('user/profile.php')) ?>" class="text-sm text-primary hover:underline"><i class="fa-solid fa-arrow-left mr-1"></i>Kembali ke profil</a>
            <h1 class="text-3xl font-bold text-gray-900 mt-2">Ganti Password</h1>
            <p class="text-gray-500 mt-1">Gunakan kombinasi huruf, angka, dan simbol yang kuat.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
            <form method="post" class="space-y-5">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password Saat Ini</label>
                    <input type="password" name="current_password" required autocomplete="current-password" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru</label>
                    <input type="password" name="new_password" required minlength="8" autocomplete="new-password" placeholder="Min. 8 karakter (huruf + angka)" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password Baru</label>
                    <input type="password" name="new_password_confirm" required minlength="8" autocomplete="new-password" placeholder="Ulangi password baru" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                </div>
                <button type="submit" class="bg-primary hover:bg-primaryDark text-white font-bold px-8 py-3 rounded-xl shadow transition">Simpan Password Baru</button>
            </form>
        </div>
    </div>
</div>
<?php
require __DIR__ . '/../includes/footer.php';

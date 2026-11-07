<?php
/**
 * Admin login (completely separate from the customer session).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (is_admin_logged_in()) {
    redirect(base_url('admin/dashboard.php'));
}

if (is_post()) {
    verify_csrf();

    $email = strtolower(clean(input('email')));
    $password = (string) ($_POST['password'] ?? '');
    $identifier = 'admin:' . $email;

    if ($email === '' || $password === '') {
        flash('error', 'Email dan password wajib diisi.');
        redirect(base_url('admin/index.php'));
    }

    if (rate_limit_exceeded($identifier)) {
        $wait = rate_limit_retry_after($identifier);
        flash('error', 'Terlalu banyak percobaan masuk. Coba lagi dalam ' . $wait . ' detik.');
        redirect(base_url('admin/index.php'));
    }

    $admin = Database::fetch('SELECT * FROM admins WHERE email = ? LIMIT 1', [$email]);
    $dummyHash = '$2y$12$qCP3vHRmRbf577G2I0ne7.be2oim9B7kqSmMs68L/SmB9pxfim2Pm';

    if (!$admin || !password_verify($password, $admin['password'] ?: $dummyHash)) {
        record_login_attempt($identifier);
        flash('error', 'Email atau password admin salah.');
        redirect(base_url('admin/index.php'));
    }

    clear_login_attempts($identifier);
    login_admin($admin);
    flash('success', 'Selamat datang, ' . $admin['name'] . '.');
    redirect(base_url('admin/dashboard.php'));
}

$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= e(APP_NAME) ?></title>
    <meta name="robots" content="noindex,nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { primary: '#0ea5e9', primaryDark: '#0284c7', accent: '#f97316' } } }
        };
    </script>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="bg-gray-900 font-sans text-gray-800 antialiased min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="inline-flex items-center gap-2 text-white">
                <span class="bg-primary p-2.5 rounded-xl"><i class="fa-solid fa-plane-departure text-2xl"></i></span>
                <span class="text-2xl font-extrabold tracking-tight">TerbangLebih</span>
            </div>
            <p class="text-gray-400 text-sm mt-2">Admin Control Panel</p>
        </div>

        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            <div class="px-7 py-6 border-b border-gray-100">
                <h1 class="text-xl font-extrabold text-gray-900">Masuk sebagai Admin</h1>
                <p class="text-sm text-gray-500 mt-1">Kelola maskapai, penerbangan, booking dan promo.</p>
            </div>

            <div class="p-7">
                <?php foreach ($flashes as $flash): ?>
                    <div class="mb-4 rounded-xl border px-4 py-3 text-sm <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($flash['type'] === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800') ?>">
                        <?= e($flash['message']) ?>
                    </div>
                <?php endforeach; ?>

                <form method="post" class="space-y-4">
                    <?= csrf_field() ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Admin</label>
                        <input type="email" name="email" required autocomplete="username" placeholder="admin@terbanglebih.com" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password admin" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                    </div>
                    <button type="submit" class="w-full bg-primary hover:bg-primaryDark text-white font-bold py-3.5 rounded-xl shadow-lg transition">Masuk ke Panel</button>
                </form>

                <?php if (APP_DEBUG): ?>
                    <div class="mt-5 bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs text-gray-600">
                        <p class="font-bold text-gray-800 mb-1"><i class="fa-solid fa-circle-info text-primary mr-1"></i>Akun demo</p>
                        <p>Email: <span class="font-mono">admin@terbanglebih.com</span></p>
                        <p>Password: <span class="font-mono">Admin#12345</span></p>
                    </div>
                <?php endif; ?>

                <a href="<?= e(page_url('home')) ?>" class="mt-5 flex items-center justify-center gap-1 text-xs text-gray-500 hover:text-primary"><i class="fa-solid fa-arrow-left"></i>Kembali ke situs</a>
            </div>
        </div>

        <p class="text-center text-xs text-gray-500 mt-5"><i class="fa-solid fa-lock mr-1"></i>Area terbatas. Maksimal 5 percobaan per menit.</p>
    </div>
</body>
</html>

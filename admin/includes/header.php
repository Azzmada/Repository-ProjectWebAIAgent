<?php
/**
 * Admin layout header.
 *
 * Expected variables (optional):
 *   $adminTitle  string  page heading
 *   $adminActive string  dashboard|airlines|flights|bookings|promos
 */
declare(strict_types=1);

$adminTitle = $adminTitle ?? 'Dashboard';
$adminActive = $adminActive ?? 'dashboard';
$admin = auth_admin();
$flashes = take_flashes();

$adminNav = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'fa-gauge-high', 'href' => base_url('admin/dashboard.php')],
    'airlines'  => ['label' => 'Maskapai', 'icon' => 'fa-plane', 'href' => base_url('admin/airlines.php')],
    'flights'   => ['label' => 'Penerbangan', 'icon' => 'fa-route', 'href' => base_url('admin/flights.php')],
    'bookings'  => ['label' => 'Booking', 'icon' => 'fa-ticket', 'href' => base_url('admin/bookings.php')],
    'promos'    => ['label' => 'Promo', 'icon' => 'fa-tags', 'href' => base_url('admin/promos.php')],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($adminTitle) ?> - Admin <?= e(APP_NAME) ?></title>
    <meta name="robots" content="noindex,nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { primary: '#0ea5e9', primaryDark: '#0284c7', accent: '#f97316', accentDark: '#ea580c' } } }
        };
    </script>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <style>.admin-nav-active{background:#0ea5e9;color:#fff;box-shadow:0 6px 18px rgba(14,165,233,.35)}</style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 antialiased">
    <!-- Sidebar overlay -->
    <div id="admin-overlay" class="fixed inset-0 bg-black/40 z-30 hidden lg:hidden" onclick="toggleAdminSidebar(false)"></div>

    <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 bg-gray-900 text-gray-300 flex flex-col transform -translate-x-full lg:translate-x-0 transition-transform duration-200">
        <div class="px-5 py-5 border-b border-gray-800 flex items-center gap-2">
            <span class="bg-primary text-white p-2 rounded-lg"><i class="fa-solid fa-plane-departure text-xl"></i></span>
            <div>
                <p class="text-white font-extrabold text-lg leading-tight">TerbangLebih</p>
                <p class="text-[11px] text-gray-400 uppercase tracking-wider">Admin Panel</p>
            </div>
            <button type="button" class="ml-auto lg:hidden text-gray-400 hover:text-white" onclick="toggleAdminSidebar(false)" aria-label="Tutup menu"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            <?php foreach ($adminNav as $key => $item): ?>
                <a href="<?= e($item['href']) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition <?= $adminActive === $key ? 'admin-nav-active' : 'text-gray-300 hover:bg-gray-800 hover:text-white' ?>">
                    <i class="fa-solid <?= e($item['icon']) ?> w-4 text-center"></i><?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="px-3 py-4 border-t border-gray-800 space-y-1">
            <a href="<?= e(page_url('home')) ?>" target="_blank" rel="noopener" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-gray-300 hover:bg-gray-800 hover:text-white transition"><i class="fa-solid fa-arrow-up-right-from-square w-4 text-center"></i>Lihat Situs</a>
            <form method="post" action="<?= e(base_url('admin/logout.php')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-red-400 hover:bg-red-500/10 hover:text-red-300 transition"><i class="fa-solid fa-right-from-bracket w-4 text-center"></i>Keluar</button>
            </form>
        </div>
    </aside>

    <div class="lg:pl-64 min-h-screen flex flex-col">
        <!-- Top bar -->
        <header class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-gray-200">
            <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center gap-3">
                <button type="button" class="lg:hidden w-10 h-10 rounded-lg bg-gray-100 text-gray-700 flex items-center justify-center" onclick="toggleAdminSidebar(true)" aria-label="Buka menu"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h1 class="text-lg font-bold text-gray-900 leading-tight"><?= e($adminTitle) ?></h1>
                    <p class="text-[11px] text-gray-500 hidden sm:block">Kelola data operasional TerbangLebih</p>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <span class="hidden sm:inline text-xs text-gray-500"><?= e(date('d M Y')) ?></span>
                    <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-full pl-1.5 pr-3 py-1.5">
                        <span class="w-7 h-7 rounded-full bg-primary text-white flex items-center justify-center text-[11px] font-bold"><?= e(client_initials($admin['name'] ?? 'Admin')) ?></span>
                        <div class="leading-tight">
                            <p class="text-xs font-bold text-gray-800 max-w-[130px] truncate"><?= e($admin['name'] ?? 'Administrator') ?></p>
                            <p class="text-[10px] text-gray-500 max-w-[130px] truncate"><?= e($admin['email'] ?? '') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6">
            <?php foreach ($flashes as $flash): ?>
                <div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($flash['type'] === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800') ?>">
                    <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-check-circle' : ($flash['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info') ?> mt-0.5"></i>
                    <span><?= e($flash['message']) ?></span>
                </div>
            <?php endforeach; ?>

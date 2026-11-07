<?php
/**
 * Public header + navbar partial.
 *
 * Expected (optional) variables before include:
 *   $pageTitle, $metaDescription, $activeNav, $bodyClass
 */

declare(strict_types=1);

$pageTitle = $pageTitle ?? (APP_NAME . ' - Pesan Tiket Pesawat & Promo');
$metaDescription = $metaDescription ?? 'TerbangLebih — platform pemesanan tiket pesawat terpercaya di Indonesia. Harga terbaik, pembayaran aman, layanan 24/7.';
$activeNav = $activeNav ?? '';
$bodyClass = $bodyClass ?? 'bg-gray-50 text-gray-800 antialiased flex flex-col min-h-screen';
$navUser = auth_user();

$navItems = [
    'home'    => ['label' => 'Home', 'page' => 'home'],
    'pesawat' => ['label' => 'Pesawat', 'page' => 'pesawat'],
    'promo'   => ['label' => 'Promo', 'page' => 'promo'],
    'bantuan' => ['label' => 'Bantuan', 'page' => 'bantuan'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: '#0ea5e9', primaryDark: '#0284c7',
                        accent: '#f97316', accentDark: '#ea580c',
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'float-slow': 'float 8s ease-in-out infinite',
                        'pulse-slow': 'pulse 4s ease-in-out infinite',
                    },
                    keyframes: {
                        float: { '0%, 100%': { transform: 'translateY(0px)' }, '50%': { transform: 'translateY(-20px)' } }
                    }
                }
            }
        }
    </script>

    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">

    <!-- NAVBAR -->
    <nav id="navbar" class="fixed w-full z-40 bg-white transition-all duration-300 py-4 top-0 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <a href="<?= e(page_url('home')) ?>" class="flex items-center gap-2 cursor-pointer">
                <div class="bg-primary text-white p-2 rounded-lg"><i class="fa-solid fa-plane-departure text-2xl"></i></div>
                <span class="text-2xl font-bold text-gray-900 tracking-tight">TerbangLebih</span>
            </a>

            <div class="hidden md:flex items-center space-x-8 font-medium text-lg" id="nav-links">
                <?php foreach ($navItems as $key => $item): ?>
                    <a href="<?= e(page_url($item['page'])) ?>"
                       class="nav-link <?= $activeNav === $key ? 'text-primary' : 'text-gray-600' ?> hover:text-primary transition-colors">
                        <?= e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
                <?php if ($navUser): ?>
                    <a href="<?= e(base_url('user/my-bookings.php')) ?>"
                       class="nav-link <?= $activeNav === 'bookings' ? 'text-primary' : 'text-gray-600' ?> hover:text-primary transition-colors">
                        Pesanan Saya
                    </a>
                <?php endif; ?>
            </div>

            <div class="flex items-center space-x-3 sm:space-x-4">
                <button type="button" onclick="showToast('Fitur pengaturan bahasa akan segera hadir!', 'info')" class="hidden xl:flex items-center gap-2 text-gray-600 hover:text-primary font-medium px-3 py-2 rounded-lg hover:bg-gray-100 transition">
                    <i class="fa-solid fa-globe"></i><span>IDR | ID</span><i class="fa-solid fa-chevron-down text-xs"></i>
                </button>

                <?php if ($navUser): ?>
                    <div class="relative hidden sm:block" id="user-menu-wrap">
                        <button type="button" id="user-menu-btn" class="flex items-center gap-2 bg-gray-50 hover:bg-gray-100 border border-gray-200 px-3 py-2 rounded-full transition">
                            <span class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center text-sm font-bold"><?= e(client_initials($navUser['name'])) ?></span>
                            <span class="hidden lg:inline font-semibold text-gray-700 max-w-[120px] truncate"><?= e($navUser['name']) ?></span>
                            <i class="fa-solid fa-chevron-down text-xs text-gray-500"></i>
                        </button>
                        <div id="user-menu" class="hidden absolute right-0 mt-2 w-60 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50">
                            <div class="px-4 py-2 border-b border-gray-100">
                                <p class="font-bold text-gray-900 truncate"><?= e($navUser['name']) ?></p>
                                <p class="text-xs text-gray-500 truncate"><?= e($navUser['email']) ?></p>
                            </div>
                            <a href="<?= e(base_url('user/profile.php')) ?>" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50"><i class="fa-regular fa-user w-4 text-primary"></i> Profil Saya</a>
                            <a href="<?= e(base_url('user/my-bookings.php')) ?>" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50"><i class="fa-solid fa-ticket w-4 text-primary"></i> Pesanan Saya</a>
                            <a href="<?= e(base_url('user/change-password.php')) ?>" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50"><i class="fa-solid fa-lock w-4 text-primary"></i> Ganti Password</a>
                            <div class="border-t border-gray-100 mt-1 pt-1">
                                <a href="<?= e(base_url('auth/logout.php')) ?>" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50"><i class="fa-solid fa-right-from-bracket w-4"></i> Keluar</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= e(base_url('auth/login.php')) ?>" class="bg-primary hover:bg-primaryDark text-white px-5 sm:px-6 py-2.5 rounded-full font-semibold text-base sm:text-lg transition shadow-md hover:shadow-lg transform hover:-translate-y-0.5 whitespace-nowrap">Login / Daftar</a>
                <?php endif; ?>

                <button type="button" id="mobile-menu-btn" class="md:hidden w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100 text-gray-700" aria-label="Menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </div>

        <!-- Mobile menu -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-gray-100 bg-white mt-3">
            <div class="px-4 py-3 space-y-1">
                <?php foreach ($navItems as $key => $item): ?>
                    <a href="<?= e(page_url($item['page'])) ?>" class="block px-3 py-2.5 rounded-lg <?= $activeNav === $key ? 'bg-blue-50 text-primary font-semibold' : 'text-gray-700 hover:bg-gray-50' ?>"><?= e($item['label']) ?></a>
                <?php endforeach; ?>
                <?php if ($navUser): ?>
                    <a href="<?= e(base_url('user/my-bookings.php')) ?>" class="block px-3 py-2.5 rounded-lg text-gray-700 hover:bg-gray-50">Pesanan Saya</a>
                    <a href="<?= e(base_url('user/profile.php')) ?>" class="block px-3 py-2.5 rounded-lg text-gray-700 hover:bg-gray-50">Profil Saya</a>
                    <a href="<?= e(base_url('auth/logout.php')) ?>" class="block px-3 py-2.5 rounded-lg text-red-600 hover:bg-red-50">Keluar</a>
                <?php else: ?>
                    <div class="flex gap-2 pt-2">
                        <a href="<?= e(base_url('auth/login.php')) ?>" class="flex-1 text-center bg-primary text-white font-semibold py-2.5 rounded-lg">Masuk</a>
                        <a href="<?= e(base_url('auth/register.php')) ?>" class="flex-1 text-center bg-gray-100 text-gray-800 font-semibold py-2.5 rounded-lg">Daftar</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="flex-grow mt-16">

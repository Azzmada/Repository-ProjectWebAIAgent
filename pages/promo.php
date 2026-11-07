<?php
/**
 * Promo page — banners and codes come from the promos table.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$promos = Database::fetchAll(
    "SELECT * FROM promos
     WHERE is_active = 1 AND valid_until >= CURDATE() AND valid_from <= CURDATE()
     ORDER BY valid_until ASC, id ASC"
);

$pageTitle = 'Promo Spesial Hari Ini - ' . APP_NAME;
$metaDescription = 'Hemat lebih banyak dengan kode promo TerbangLebih. Diskon tiket pesawat untuk rute domestik dan internasional.';
$activeNav = 'promo';

require __DIR__ . '/../includes/header.php';
?>
<div id="page-promo" class="page-view active">
    <header class="bg-gradient-to-r from-accent to-accentDark py-20 text-center">
        <h1 class="text-4xl lg:text-5xl font-extrabold text-white mb-4 tracking-tight drop-shadow-lg">Promo Spesial Hari Ini</h1>
        <p class="text-xl text-white/90 max-w-2xl mx-auto font-light">Hemat lebih banyak untuk perjalanan Anda dengan penawaran terbatas.</p>
    </header>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <?php if ($promos): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <?php foreach ($promos as $promo):
                    $isPercent = $promo['type'] === 'percent';
                    $valueLabel = $isPercent
                        ? 'Diskon ' . (int) $promo['value'] . '%'
                        : 'Potongan ' . rupiah($promo['value']);
                    $badgeColor = $promo['badge_color'] ?: 'bg-accent';
                ?>
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden flex flex-col md:flex-row border border-gray-100 hover:shadow-xl transition">
                        <div class="md:w-1/2 h-48 md:h-auto">
                            <img src="<?= e($promo['image_url'] ?: 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=600&q=80') ?>" alt="<?= e($promo['title']) ?>" class="w-full h-full object-cover">
                        </div>
                        <div class="p-6 md:w-1/2 flex flex-col justify-center">
                            <span class="<?= e($badgeColor) ?> text-white text-xs font-bold px-2 py-1 rounded mb-2 w-max"><?= e($promo['badge'] ?: 'PROMO') ?></span>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= e($promo['title'] ?: $valueLabel) ?></h3>
                            <p class="text-gray-500 mb-2"><?= e($promo['description'] ?: 'Gunakan kode promo ini untuk mendapatkan potongan harga.') ?></p>
                            <p class="text-xs text-gray-500 mb-4">
                                <?php if ((float) $promo['min_transaction'] > 0): ?>
                                    Min. transaksi <?= e(rupiah($promo['min_transaction'])) ?> &middot;
                                <?php endif; ?>
                                Berlaku s/d <?= e(format_date_id($promo['valid_until'])) ?>
                                <?php if ((int) $promo['max_usage'] > 0): ?>
                                    &middot; Kuota <?= (int) $promo['max_usage'] ?>
                                <?php endif; ?>
                            </p>
                            <div class="flex items-center gap-2 border-2 border-dashed border-gray-300 p-2 rounded-lg bg-gray-50 w-max">
                                <span class="font-mono font-bold text-gray-800"><?= e($promo['code']) ?></span>
                                <button type="button" onclick="copyCode('<?= e($promo['code']) ?>')" class="text-primary hover:text-primaryDark text-sm font-bold">Salin</button>
                            </div>
                            <a href="<?= e(page_url('pesawat', ['trip' => 'one-way', 'depart' => date('Y-m-d', strtotime('+7 days'))])) ?>" class="mt-4 text-sm font-semibold text-primary hover:text-primaryDark flex items-center gap-1 w-max">
                                Pesan sekarang <i class="fa-solid fa-arrow-right text-xs"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-12 bg-blue-50 border border-blue-100 rounded-2xl p-6 max-w-3xl mx-auto">
                <h3 class="font-bold text-gray-900 mb-2"><i class="fa-solid fa-circle-info text-primary mr-1"></i> Cara memakai kode promo</h3>
                <ol class="text-sm text-gray-600 space-y-1 list-decimal list-inside">
                    <li>Pilih penerbangan dan lengkapi data penumpang.</li>
                    <li>Masukkan kode promo pada halaman review pemesanan.</li>
                    <li>Diskon langsung dihitung dan total pembayaran diperbarui.</li>
                </ol>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center max-w-xl mx-auto">
                <i class="fa-solid fa-tags text-6xl text-gray-300 mb-4"></i>
                <h2 class="text-xl font-bold text-gray-800 mb-2">Belum ada promo aktif</h2>
                <p class="text-gray-500 mb-5">Nantikan penawaran menarik berikutnya dari TerbangLebih.</p>
                <a href="<?= e(page_url('pesawat')) ?>" class="inline-flex items-center gap-2 bg-primary hover:bg-primaryDark text-white font-semibold px-6 py-2.5 rounded-lg transition"><i class="fa-solid fa-plane-departure"></i> Cari Tiket</a>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
require __DIR__ . '/../includes/footer.php';

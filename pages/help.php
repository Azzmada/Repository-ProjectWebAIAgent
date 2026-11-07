<?php
/**
 * Help centre — FAQ entries come from the faqs table.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$faqs = Database::fetchAll('SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
$contactEmail = setting('contact_email', 'support@terbanglebih.com');
$contactPhone = setting('contact_phone', '0800-1-888-999');

$pageTitle = 'Pusat Bantuan - ' . APP_NAME;
$metaDescription = 'Temukan jawaban cepat seputar pemesanan tiket, pembayaran, bagasi, refund, dan e-tiket di TerbangLebih.';
$activeNav = 'bantuan';

require __DIR__ . '/../includes/header.php';
?>
<div id="page-bantuan" class="page-view active">
    <header class="bg-primary py-16 text-center">
        <h1 class="text-4xl font-extrabold text-white mb-4 tracking-tight">Pusat Bantuan</h1>
        <p class="text-xl text-blue-100 max-w-2xl mx-auto font-light">Ada yang bisa kami bantu?</p>
    </header>

    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
            <a href="<?= e(page_url('pesawat')) ?>" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 text-center hover:shadow-md transition cursor-pointer">
                <div class="w-16 h-16 bg-blue-50 text-primary rounded-full flex items-center justify-center mx-auto mb-4"><i class="fa-solid fa-ticket text-2xl"></i></div>
                <h3 class="text-lg font-bold text-gray-900">Pemesanan Tiket</h3><p class="text-gray-500 text-sm mt-2">Cara pesan, ubah jadwal, atau batalkan tiket.</p>
            </a>
            <a href="#faq" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 text-center hover:shadow-md transition cursor-pointer">
                <div class="w-16 h-16 bg-green-50 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fa-solid fa-credit-card text-2xl"></i></div>
                <h3 class="text-lg font-bold text-gray-900">Pembayaran</h3><p class="text-gray-500 text-sm mt-2">Metode pembayaran dan konfirmasi transaksi.</p>
            </a>
            <a href="#faq" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 text-center hover:shadow-md transition cursor-pointer">
                <div class="w-16 h-16 bg-orange-50 text-accent rounded-full flex items-center justify-center mx-auto mb-4"><i class="fa-solid fa-suitcase text-2xl"></i></div>
                <h3 class="text-lg font-bold text-gray-900">Bagasi &amp; Refund</h3><p class="text-gray-500 text-sm mt-2">Aturan bagasi dan pengajuan pengembalian dana.</p>
            </a>
        </div>

        <div class="max-w-4xl mx-auto" id="faq">
            <div class="text-center mb-10">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Pertanyaan yang Sering Diajukan</h2>
                <p class="text-lg text-gray-500">Temukan jawaban cepat untuk pertanyaan umum seputar pemesanan tiket.</p>
            </div>

            <?php if ($faqs): ?>
                <div class="space-y-4">
                    <?php foreach ($faqs as $faq): ?>
                        <div class="faq-item bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                            <button type="button" class="faq-header w-full text-left px-6 py-5 flex justify-between items-center focus:outline-none">
                                <span class="text-lg font-semibold text-gray-800"><?= e($faq['question']) ?></span>
                                <i class="fa-solid fa-chevron-down faq-icon text-gray-400 transition-transform"></i>
                            </button>
                            <div class="faq-answer px-6 text-gray-600 text-base"><p><?= nl2br(e($faq['answer'])) ?></p></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-2xl border border-gray-100 p-10 text-center text-gray-500">
                    Belum ada FAQ yang tersedia.
                </div>
            <?php endif; ?>

            <div class="mt-12 bg-gradient-to-br from-blue-50 to-white border border-blue-100 rounded-2xl p-8 text-center">
                <h3 class="text-xl font-bold text-gray-900 mb-2">Masih butuh bantuan?</h3>
                <p class="text-gray-600 mb-5">Tim layanan pelanggan kami siap membantu Anda 24/7.</p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 text-sm font-semibold">
                    <a href="mailto:<?= e($contactEmail) ?>" class="inline-flex items-center gap-2 bg-primary hover:bg-primaryDark text-white px-5 py-2.5 rounded-lg transition"><i class="fa-solid fa-envelope"></i> <?= e($contactEmail) ?></a>
                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>" class="inline-flex items-center gap-2 bg-white border border-gray-200 hover:border-primary text-gray-800 px-5 py-2.5 rounded-lg transition"><i class="fa-solid fa-phone text-primary"></i> <?= e($contactPhone) ?></a>
                </div>
            </div>
        </div>
    </section>
</div>
<?php
require __DIR__ . '/../includes/footer.php';

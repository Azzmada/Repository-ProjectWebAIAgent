<?php
/**
 * Public footer + global scripts.
 */
declare(strict_types=1);

$flashMessages = take_flashes();
$footerUser = auth_user();
$contactEmail = setting('contact_email', 'support@terbanglebih.com');
$contactPhone = setting('contact_phone', '0800-1-888-999');
?>
    </main>

    <!-- FOOTER -->
    <footer class="bg-gray-900 text-gray-300 pt-16 pb-8 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-8 mb-12">
                <div class="lg:col-span-2">
                    <a href="<?= e(page_url('home')) ?>" class="flex items-center gap-2 mb-6 w-max">
                        <div class="bg-primary text-white p-2 rounded-lg"><i class="fa-solid fa-plane-departure text-xl"></i></div>
                        <span class="text-2xl font-bold text-white tracking-tight">TerbangLebih</span>
                    </a>
                    <p class="text-gray-400 mb-6 max-w-sm leading-relaxed">Platform pemesanan tiket pesawat terpercaya di Indonesia. Terbang lebih mudah, jelajahi dunia bersama kami.</p>
                    <div class="flex space-x-4">
                        <a href="javascript:void(0)" onclick="showToast('Sosial Media belum tersedia.', 'info')" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary hover:text-white transition" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="javascript:void(0)" onclick="showToast('Sosial Media belum tersedia.', 'info')" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary hover:text-white transition" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
                        <a href="javascript:void(0)" onclick="showToast('Sosial Media belum tersedia.', 'info')" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary hover:text-white transition" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                        <a href="javascript:void(0)" onclick="showToast('Sosial Media belum tersedia.', 'info')" class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary hover:text-white transition" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
                    </div>
                </div>
                <div>
                    <h4 class="text-white font-bold text-lg mb-4">Tentang Kami</h4>
                    <ul class="space-y-3">
                        <li><a href="javascript:void(0)" onclick="showToast('Halaman sedang dikembangkan.', 'info')" class="hover:text-primary transition">Profil Perusahaan</a></li>
                        <li><a href="javascript:void(0)" onclick="showToast('Halaman sedang dikembangkan.', 'info')" class="hover:text-primary transition">Karier</a></li>
                        <li><a href="javascript:void(0)" onclick="showToast('Halaman sedang dikembangkan.', 'info')" class="hover:text-primary transition">Blog</a></li>
                        <li><a href="javascript:void(0)" onclick="showToast('Halaman sedang dikembangkan.', 'info')" class="hover:text-primary transition">Hubungi Kami</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-bold text-lg mb-4">Bantuan</h4>
                    <ul class="space-y-3">
                        <li><a href="<?= e(page_url('bantuan')) ?>" class="hover:text-primary transition">Pusat Bantuan</a></li>
                        <li><a href="<?= e(page_url('pesawat')) ?>" class="hover:text-primary transition">Cari Tiket</a></li>
                        <li><a href="<?= e($footerUser ? base_url('user/my-bookings.php') : base_url('auth/login.php')) ?>" class="hover:text-primary transition">Pesanan Saya</a></li>
                        <li><a href="<?= e(page_url('bantuan')) ?>#faq" class="hover:text-primary transition">Refund &amp; Reschedule</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-bold text-lg mb-4">Kebijakan &amp; Kontak</h4>
                    <ul class="space-y-3 mb-6">
                        <li><a href="javascript:void(0)" onclick="showToast('Halaman sedang dikembangkan.', 'info')" class="hover:text-primary transition">Kebijakan Privasi</a></li>
                        <li><a href="javascript:void(0)" onclick="showToast('Halaman sedang dikembangkan.', 'info')" class="hover:text-primary transition">Syarat &amp; Ketentuan</a></li>
                    </ul>
                    <h5 class="text-white font-semibold mb-2">Layanan Pelanggan</h5>
                    <p class="flex items-center gap-2 mb-1"><i class="fa-solid fa-envelope text-primary"></i> <?= e($contactEmail) ?></p>
                    <p class="flex items-center gap-2"><i class="fa-solid fa-phone text-primary"></i> <?= e($contactPhone) ?></p>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 mb-8">
                <p class="text-sm text-gray-400 mb-4">Mitra Pembayaran Resmi:</p>
                <div class="flex flex-wrap gap-4 items-center opacity-70">
                    <div class="bg-white px-3 py-1 rounded text-gray-800 font-bold italic text-sm">BCA</div>
                    <div class="bg-white px-3 py-1 rounded text-gray-800 font-bold italic text-sm">MANDIRI</div>
                    <div class="bg-white px-3 py-1 rounded text-gray-800 font-bold italic text-sm">BNI</div>
                    <div class="bg-white px-3 py-1 rounded text-gray-800 font-bold italic text-sm">BRI</div>
                    <div class="bg-white px-3 py-1 rounded text-blue-600 font-bold italic text-sm">VISA</div>
                    <div class="bg-white px-3 py-1 rounded text-red-600 font-bold italic text-sm">Mastercard</div>
                    <div class="bg-white px-3 py-1 rounded text-purple-600 font-bold italic text-sm">OVO</div>
                    <div class="bg-white px-3 py-1 rounded text-green-500 font-bold italic text-sm">GoPay</div>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center text-sm text-gray-500">
                <p>&copy; <?= date('Y') ?> TerbangLebih. Hak Cipta Dilindungi.</p>
                <div class="flex space-x-4 mt-4 md:mt-0">
                    <a href="javascript:void(0)" onclick="showToast('Halaman sedang dikembangkan.', 'info')" class="hover:text-white transition">Privasi</a>
                    <a href="javascript:void(0)" onclick="showToast('Halaman sedang dikembangkan.', 'info')" class="hover:text-white transition">Syarat</a>
                    <a href="javascript:void(0)" onclick="showToast('Halaman sedang dikembangkan.', 'info')" class="hover:text-white transition">Sitemap</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- TOAST CONTAINER -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-[60] space-y-2"></div>

    <script type="application/json" id="flash-data"><?= json_encode($flashMessages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
    <script>
        window.TBL = {
            base: <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES) ?>,
            autocomplete: <?= json_encode(base_url('api/autocomplete.php'), JSON_UNESCAPED_SLASHES) ?>,
            csrf: <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES) ?>
        };
    </script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
    <?php if (!empty($pageScripts)): ?>
        <?= $pageScripts /* trusted markup generated by our own pages */ ?>
    <?php endif; ?>
</body>
</html>

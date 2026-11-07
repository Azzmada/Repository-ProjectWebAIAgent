<?php
/**
 * Homepage — all dynamic blocks are loaded from MySQL.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$popularCities = Database::fetchAll(
    'SELECT * FROM cities WHERE is_popular = 1 ORDER BY sort_order ASC, name ASC LIMIT 4'
);
if (count($popularCities) < 4) {
    $popularCities = Database::fetchAll('SELECT * FROM cities ORDER BY sort_order ASC, name ASC LIMIT 4');
}

$testimonials = Database::fetchAll(
    'SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 6'
);

$themes = [
    'blue'   => ['card' => 'from-blue-50 to-white border-blue-100', 'avatar' => 'bg-primary'],
    'orange' => ['card' => 'from-orange-50 to-white border-orange-100', 'avatar' => 'bg-accent'],
    'green'  => ['card' => 'from-green-50 to-white border-green-100', 'avatar' => 'bg-green-600'],
    'purple' => ['card' => 'from-purple-50 to-white border-purple-100', 'avatar' => 'bg-purple-600'],
];

$defaultDepart = date('Y-m-d', strtotime('+7 days'));

$pageTitle = 'TerbangLebih - Pesan Tiket Pesawat & Promo';
$metaDescription = 'Pesan tiket pesawat murah ke seluruh Indonesia dan dunia. Harga terbaik, pembayaran aman, layanan 24/7 bersama TerbangLebih.';
$activeNav = 'home';

require __DIR__ . '/../includes/header.php';
?>
<div id="page-home" class="page-view active">
    <header class="relative bg-gradient-to-br from-blue-900 via-primary to-cyan-500 overflow-hidden">
        <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -mr-48 -mt-48 animate-pulse-slow"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-white/10 rounded-full -ml-40 -mb-40 animate-pulse-slow"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-sm text-white px-4 py-2 rounded-full mb-6 text-sm font-medium">
                        <i class="fa-solid fa-star text-yellow-300"></i><span>Platform Travel #1 di Indonesia</span>
                    </div>
                    <h1 class="text-4xl lg:text-6xl font-extrabold text-white mb-6 leading-tight drop-shadow-lg">Wujudkan <span class="text-yellow-300">Liburan Impian</span> Anda Bersama Kami</h1>
                    <p class="text-lg lg:text-xl text-white/90 mb-8 max-w-xl mx-auto lg:mx-0 leading-relaxed">Dapatkan harga tiket pesawat dan paket liburan terbaik. Terbang lebih mudah, jelajahi dunia tanpa ribet.</p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        <a href="<?= e(page_url('pesawat')) ?>" class="bg-accent hover:bg-accentDark text-white font-bold text-lg px-8 py-4 rounded-full shadow-lg transition-all transform hover:scale-105 flex items-center justify-center gap-2"><i class="fa-solid fa-plane-departure"></i> Pesan Tiket Sekarang</a>
                        <a href="<?= e(page_url('promo')) ?>" class="bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white font-bold text-lg px-8 py-4 rounded-full border-2 border-white/40 transition-all transform hover:scale-105 flex items-center justify-center gap-2"><i class="fa-solid fa-tags"></i> Lihat Promo</a>
                    </div>
                    <div class="mt-10 flex flex-wrap items-center justify-center lg:justify-start gap-6 text-white/90">
                        <div class="flex items-center gap-2"><i class="fa-solid fa-shield-halved text-xl text-yellow-300"></i><span class="text-sm">Pembayaran Aman</span></div>
                        <div class="flex items-center gap-2"><i class="fa-solid fa-headset text-xl text-yellow-300"></i><span class="text-sm">CS 24/7</span></div>
                        <div class="flex items-center gap-2"><i class="fa-solid fa-medal text-xl text-yellow-300"></i><span class="text-sm">Garansi Harga Terbaik</span></div>
                    </div>
                </div>
                <div class="relative hidden lg:block">
                    <div class="relative animate-float">
                        <div class="bg-white rounded-3xl shadow-2xl p-6 transform -rotate-3 hover:rotate-0 transition-transform duration-500">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white"><i class="fa-solid fa-plane"></i></div>
                                    <div><p class="text-xs text-gray-500">Boarding Pass</p><p class="font-bold text-gray-900">TerbangLebih</p></div>
                                </div>
                                <i class="fa-solid fa-check-circle text-green-500 text-2xl"></i>
                            </div>
                            <div class="flex items-center justify-between mb-4 py-4 border-y border-dashed border-gray-200">
                                <div class="text-center"><p class="text-2xl font-bold text-gray-900">CGK</p><p class="text-xs text-gray-500">Jakarta</p></div>
                                <i class="fa-solid fa-plane text-primary text-xl"></i>
                                <div class="text-center"><p class="text-2xl font-bold text-gray-900">DPS</p><p class="text-xs text-gray-500">Bali</p></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500">
                                <div><p>Maskapai</p><p class="font-bold text-gray-800">Garuda Indonesia</p></div>
                                <div class="text-right"><p>Kelas</p><p class="font-bold text-gray-800">Ekonomi</p></div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-center"><span class="text-xs text-gray-500">Total</span><span class="text-lg font-bold text-accent">Rp 1.250.000</span></div>
                        </div>
                        <div class="absolute -top-4 -right-4 bg-accent text-white p-3 rounded-2xl shadow-lg transform rotate-12 animate-float-slow"><p class="text-xs font-medium">Hemat</p><p class="text-xl font-extrabold">30%</p></div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="bg-white border-b border-gray-100 py-8 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div>
                    <p class="text-3xl lg:text-4xl font-extrabold gradient-text"><span class="counter" data-target="<?= setting_int('stat_airlines', 500) ?>">0</span></p>
                    <p class="text-sm text-gray-600 mt-1">Maskapai Partner</p>
                </div>
                <div>
                    <p class="text-3xl lg:text-4xl font-extrabold gradient-text"><span class="counter" data-target="<?= setting_int('stat_tickets', 500) ?>">0</span>RB+</p>
                    <p class="text-sm text-gray-600 mt-1">Tiket Terjual</p>
                </div>
                <div>
                    <p class="text-3xl lg:text-4xl font-extrabold gradient-text"><span class="counter" data-target="<?= setting_int('stat_rating', 49) ?>" data-decimal="true">0</span>/5</p>
                    <p class="text-sm text-gray-600 mt-1">Rating Pengguna</p>
                </div>
                <div>
                    <p class="text-3xl lg:text-4xl font-extrabold gradient-text"><span class="counter" data-target="<?= setting_int('stat_cs_hours', 24) ?>">0</span></p>
                    <p class="text-sm text-gray-600 mt-1">Jam Layanan CS</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-3">Destinasi Populer Pilihan Traveler</h2>
            <p class="text-gray-500 text-lg max-w-2xl mx-auto">Klik destinasi favorit Anda dan temukan tiket pesawat terbaik.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($popularCities as $city):
                $destUrl = page_url('pesawat', [
                    'trip'   => 'one-way',
                    'from'   => 'CGK',
                    'to'     => $city['iata_code'],
                    'depart' => $defaultDepart,
                ]);
            ?>
                <a href="<?= e($destUrl) ?>" class="bg-white rounded-2xl shadow-md overflow-hidden group cursor-pointer hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 block">
                    <div class="card-img-container h-56 w-full relative">
                        <img src="<?= e($city['image_url'] ?: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=80') ?>" alt="<?= e($city['name']) ?>" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>
                        <div class="absolute bottom-4 left-4 text-white">
                            <p class="text-xs opacity-90">Mulai dari</p>
                            <p class="text-2xl font-bold"><?= e(rupiah($city['start_price'])) ?></p>
                        </div>
                    </div>
                    <div class="p-5">
                        <h3 class="text-xl font-bold text-gray-900 mb-1"><?= e($city['name']) ?><?= $city['country'] !== 'Indonesia' ? '' : ', Indonesia' ?></h3>
                        <p class="text-gray-500 text-sm mb-3"><i class="fa-solid fa-location-dot mr-1 text-accent"></i> <?= e($city['tagline'] ?: $city['country']) ?></p>
                        <span class="w-full bg-primary group-hover:bg-primaryDark text-white font-semibold py-2.5 rounded-lg transition flex items-center justify-center gap-2">Pesan Tiket <i class="fa-solid fa-arrow-right text-sm"></i></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16"><h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-3">Mengapa Memilih TerbangLebih?</h2><p class="text-gray-500 text-lg max-w-2xl mx-auto">Ratusan ribu pelanggan telah mempercayakan perjalanannya kepada kami.</p></div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="group bg-gradient-to-br from-blue-50 to-white p-6 rounded-2xl border border-blue-100 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                    <div class="w-16 h-16 bg-primary text-white rounded-2xl flex items-center justify-center mb-5 shadow-lg group-hover:rotate-6 transition"><i class="fa-solid fa-tags text-2xl"></i></div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Harga Terbaik</h3><p class="text-gray-500 text-sm leading-relaxed">Dijamin harga paling kompetitif tanpa biaya tersembunyi.</p>
                </div>
                <div class="group bg-gradient-to-br from-orange-50 to-white p-6 rounded-2xl border border-orange-100 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                    <div class="w-16 h-16 bg-accent text-white rounded-2xl flex items-center justify-center mb-5 shadow-lg group-hover:rotate-6 transition"><i class="fa-solid fa-shield-halved text-2xl"></i></div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Pembayaran Aman</h3><p class="text-gray-500 text-sm leading-relaxed">Enkripsi tingkat tinggi dan standar keamanan internasional.</p>
                </div>
                <div class="group bg-gradient-to-br from-green-50 to-white p-6 rounded-2xl border border-green-100 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                    <div class="w-16 h-16 bg-green-600 text-white rounded-2xl flex items-center justify-center mb-5 shadow-lg group-hover:rotate-6 transition"><i class="fa-solid fa-headset text-2xl"></i></div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Layanan 24/7</h3><p class="text-gray-500 text-sm leading-relaxed">Tim CS profesional siap membantu Anda kapan saja.</p>
                </div>
                <div class="group bg-gradient-to-br from-purple-50 to-white p-6 rounded-2xl border border-purple-100 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                    <div class="w-16 h-16 bg-purple-600 text-white rounded-2xl flex items-center justify-center mb-5 shadow-lg group-hover:rotate-6 transition"><i class="fa-solid fa-plane-circle-check text-2xl"></i></div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Banyak Pilihan</h3><p class="text-gray-500 text-sm leading-relaxed">500+ maskapai domestik dan internasional terpercaya.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16"><h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-3">Pesan Tiket Hanya 3 Langkah</h2><p class="text-gray-500 text-lg max-w-2xl mx-auto">Proses simpel, cepat, dan mudah. Bahkan untuk pengguna baru.</p></div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
                <div class="text-center relative">
                    <div class="w-24 h-24 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-xl relative z-10"><i class="fa-solid fa-search text-3xl"></i></div>
                    <span class="absolute top-4 right-1/4 transform translate-x-8 bg-accent text-white w-8 h-8 rounded-full flex items-center justify-center font-bold z-20">1</span>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Cari Penerbangan</h3><p class="text-gray-500 text-sm max-w-xs mx-auto">Masukkan kota asal, tujuan, dan tanggal keberangkatan Anda.</p>
                </div>
                <div class="text-center relative">
                    <div class="w-24 h-24 bg-accent text-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-xl relative z-10"><i class="fa-solid fa-list-check text-3xl"></i></div>
                    <span class="absolute top-4 right-1/4 transform translate-x-8 bg-primary text-white w-8 h-8 rounded-full flex items-center justify-center font-bold z-20">2</span>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Pilih &amp; Bayar</h3><p class="text-gray-500 text-sm max-w-xs mx-auto">Pilih jadwal favorit, isi data penumpang, lalu bayar dengan aman.</p>
                </div>
                <div class="text-center relative">
                    <div class="w-24 h-24 bg-green-600 text-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-xl relative z-10"><i class="fa-solid fa-plane-departure text-3xl"></i></div>
                    <span class="absolute top-4 right-1/4 transform translate-x-8 bg-accent text-white w-8 h-8 rounded-full flex items-center justify-center font-bold z-20">3</span>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Terbang &amp; Jelajahi</h3><p class="text-gray-500 text-sm max-w-xs mx-auto">E-tiket langsung dikirim ke email Anda. Selamat jalan!</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12"><h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-3">Kata Mereka Tentang Kami</h2><p class="text-gray-500 text-lg">Ribuan pelanggan puas telah terbang bersama TerbangLebih.</p></div>
            <?php if ($testimonials): ?>
                <div class="max-w-3xl mx-auto relative">
                    <div id="testimonial-container" class="relative min-h-[300px]">
                        <?php foreach ($testimonials as $i => $t):
                            $theme = $themes[$t['theme']] ?? $themes['blue'];
                        ?>
                            <div class="testimonial-slide absolute inset-0 <?= $i === 0 ? 'opacity-100' : 'opacity-0 pointer-events-none' ?>" data-index="<?= $i ?>">
                                <div class="bg-gradient-to-br <?= e($theme['card']) ?> p-8 rounded-3xl shadow-lg border">
                                    <div class="flex items-center gap-1 text-yellow-400 mb-4">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <i class="<?= $s <= (int) $t['rating'] ? 'fa-solid' : 'fa-regular' ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <p class="text-lg text-gray-700 mb-6 italic leading-relaxed">"<?= e($t['comment']) ?>"</p>
                                    <div class="flex items-center gap-4">
                                        <div class="w-14 h-14 <?= e($theme['avatar']) ?> rounded-full flex items-center justify-center text-white font-bold text-xl"><?= e($t['initials'] ?: initials($t['name'])) ?></div>
                                        <div><p class="font-bold text-gray-900"><?= e($t['name']) ?></p><p class="text-sm text-gray-500"><?= e($t['role']) ?> - <?= e($t['city']) ?></p></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($testimonials) > 1): ?>
                        <div class="flex justify-center gap-3 mt-8">
                            <?php foreach ($testimonials as $i => $t): ?>
                                <button type="button" onclick="goToTestimonial(<?= $i ?>)" class="testimonial-dot h-3 rounded-full transition-all <?= $i === 0 ? 'bg-primary w-6' : 'bg-gray-300 hover:bg-gray-400 w-3' ?>" data-index="<?= $i ?>" aria-label="Testimoni <?= $i + 1 ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500">Belum ada testimoni.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="py-20 bg-gradient-to-br from-primary via-blue-600 to-cyan-500 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -mr-48 -mt-48"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-white/10 rounded-full -ml-40 -mb-40"></div>
        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl lg:text-5xl font-extrabold text-white mb-6 leading-tight">Siap Terbang ke Destinasi Impian?</h2>
            <p class="text-lg lg:text-xl text-white/90 mb-8 max-w-2xl mx-auto">Jangan tunda lagi. Dapatkan tiket pesawat terbaik sekarang dan mulailah petualangan Anda.</p>
            <a href="<?= e(page_url('pesawat')) ?>" class="bg-accent hover:bg-accentDark text-white font-bold text-lg px-10 py-4 rounded-full shadow-2xl transition-all transform hover:scale-110 flex items-center gap-3 mx-auto w-max"><i class="fa-solid fa-plane-departure"></i> Pesan Tiket Sekarang</a>
        </div>
    </section>
</div>
<?php
require __DIR__ . '/../includes/footer.php';

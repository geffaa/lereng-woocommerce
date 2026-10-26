"""Indonesian content for the store: pages, product copy, attribute terms and tasting notes.

Pages are translated segment by segment ([English, Indonesian] pairs) so the block markup stays
exactly the same; the build fails if an English segment is no longer in the page.
Writes data/i18n/content.id_ID.json, which the seeder stores on each post and term."""
import json, pathlib
ROOT = pathlib.Path(__file__).resolve().parent.parent

PAGES = {
 'subscriptions': ('Langganan', [
  ['Fresh beans before the last ones run out. 10% off every bag, free to change.', 'Biji segar sebelum yang lama habis. Diskon 10% setiap kantong, bebas diganti.'],
  ['How it works', 'Cara kerjanya'],
  ['Choose a coffee on its product page and pick <b>Subscribe &amp; save</b>, or start the <a href="/product/roasters-choice-subscription/">Roaster’s Choice</a> for a different origin each time.', 'Pilih kopi di halaman produknya lalu pilih <b>Langganan &amp; hemat</b>, atau mulai <a href="/product/roasters-choice-subscription/">Roaster’s Choice</a> untuk asal kopi yang berbeda setiap kali.'],
  ['We roast on the next Monday or Thursday and ship the same day. After that it arrives every 1, 2 or 4 weeks.', 'Kami sangrai di hari Senin atau Kamis berikutnya dan kirim di hari yang sama. Setelah itu kopi datang setiap 1, 2 atau 4 minggu.'],
  ['Each renewal is a normal order: you get the same email, tracking and receipt.', 'Setiap perpanjangan adalah pesanan biasa: Anda mendapat email, pelacakan dan struk yang sama.'],
  ['From <a href="/my-account/subscriptions/">My account</a> you can skip the next delivery, pause for up to three months, change coffee, size, grind or frequency, or cancel.', 'Dari <a href="/my-account/subscriptions/">Akun saya</a> Anda bisa melewati pengiriman berikutnya, menjeda hingga tiga bulan, mengganti kopi, ukuran, gilingan atau frekuensi, atau membatalkan.'],
  ['Questions', 'Pertanyaan'],
  ['How do I pay for renewals?', 'Bagaimana cara membayar perpanjangan?'],
  ['Each renewal creates an order and we email a payment link (QRIS or virtual account) two days before roast day. Pay it and it ships as usual; if it isn’t paid by roast day we skip that delivery and keep your subscription.', 'Setiap perpanjangan membuat pesanan dan kami mengirim tautan pembayaran (QRIS atau virtual account) lewat email dua hari sebelum hari sangrai. Bayar, lalu kopi dikirim seperti biasa; jika belum dibayar sampai hari sangrai, pengiriman itu kami lewati dan langganan Anda tetap berjalan.'],
  ['Can I change the coffee?', 'Bisakah saya mengganti kopinya?'],
  ['Yes, any time before the renewal date. Single origins and blends in the same size keep the 10% discount.', 'Bisa, kapan saja sebelum tanggal perpanjangan. Single origin dan blend dengan ukuran yang sama tetap mendapat diskon 10%.'],
  ['What if I’m away?', 'Bagaimana kalau saya sedang pergi?'],
  ['Pause the subscription and choose the date it should restart. We send a reminder a week before.', 'Jeda langganan dan pilih tanggal mulainya lagi. Kami kirim pengingat seminggu sebelumnya.'],
  ['Is there a minimum?', 'Apakah ada minimum?'],
  ['No. Cancel after the first delivery if it isn’t for you.', 'Tidak. Batalkan setelah pengiriman pertama jika ternyata tidak cocok.'],
 ]),
 'brew-guides': ('Panduan seduh', [
  ['How we brew our coffee at the tasting bar. Start with these recipes, then adjust one thing at a time: grind finer if it tastes sour or thin, coarser if it tastes bitter or dry.', 'Cara kami menyeduh kopi di tasting bar. Mulai dari resep ini, lalu ubah satu hal setiap kali: giling lebih halus jika rasanya asam atau tipis, lebih kasar jika pahit atau kering.'],
  ['Pour over (V60 or similar)', 'Pour over (V60 atau sejenisnya)'],
  ['15 g · 250 g water · 93 °C · 2:45', '15 g · 250 g air · 93 °C · 2:45'],
  ['Grind medium-fine, like table salt.', 'Giling sedang-halus, seperti garam dapur.'],
  ['Rinse the paper filter with hot water and discard the water.', 'Bilas filter kertas dengan air panas lalu buang airnya.'],
  ['Bloom: pour 40 g of water, wait 40 seconds.', 'Bloom: tuang 40 g air, tunggu 40 detik.'],
  ['Pour slowly in circles to 150 g by 1:15, then to 250 g by 1:45.', 'Tuang perlahan memutar sampai 150 g di 1:15, lalu sampai 250 g di 1:45.'],
  ['Let it drain; aim to finish around 2:45. Coarser if slower, finer if faster.', 'Biarkan menetes; targetkan selesai sekitar 2:45. Lebih kasar jika lebih lambat, lebih halus jika lebih cepat.'],
  ['Flat-bottom dripper', 'Dripper dasar datar'],
  ['16 g · 260 g water · 92 °C · 3:00', '16 g · 260 g air · 92 °C · 3:00'],
  ['Grind medium.', 'Giling sedang.'],
  ['Bloom with 50 g for 30 seconds.', 'Bloom dengan 50 g selama 30 detik.'],
  ['Pour in four equal pulses of 50–55 g, every 30 seconds.', 'Tuang dalam empat tahap sama rata, 50–55 g setiap 30 detik.'],
  ['Swirl gently at the end for a flat bed.', 'Goyangkan perlahan di akhir agar ampasnya rata.'],
  ['Immersion brewer (AeroPress style)', 'Immersion brewer (gaya AeroPress)'],
  ['15 g · 220 g water · 85 °C · 2:00', '15 g · 220 g air · 85 °C · 2:00'],
  ['Grind medium-fine.', 'Giling sedang-halus.'],
  ['Add coffee and all the water, stir three times.', 'Masukkan kopi dan semua air, aduk tiga kali.'],
  ['Put the plunger on to stop dripping; wait until 1:45.', 'Pasang plunger agar tidak menetes; tunggu sampai 1:45.'],
  ['Swirl, then press gently for 15 seconds.', 'Goyangkan, lalu tekan perlahan selama 15 detik.'],
  ['30 g · 500 g water · 95 °C · 4:00', '30 g · 500 g air · 95 °C · 4:00'],
  ['Grind coarse, like breadcrumbs.', 'Giling kasar, seperti tepung roti.'],
  ['Pour all the water, stir once.', 'Tuang semua air, aduk sekali.'],
  ['At 4:00, break the crust and skim the foam off the top.', 'Di menit 4:00, pecahkan lapisan atas dan buang busanya.'],
  ['Press slowly and pour everything out so it doesn\'t keep brewing.', 'Tekan perlahan dan tuang semuanya agar tidak terus terseduh.'],
  ['Fill the basket level · water to just under the valve', 'Isi basket rata · air sampai tepat di bawah katup'],
  ['Grind finer than pour over, coarser than espresso.', 'Giling lebih halus dari pour over, lebih kasar dari espresso.'],
  ['Use hot water in the base so the coffee heats less on the stove.', 'Pakai air panas di bagian bawah agar kopi tidak terlalu lama dipanaskan di kompor.'],
  ['Medium-low heat, lid open; take it off as soon as it starts to sputter.', 'Api sedang-kecil, tutup terbuka; angkat begitu mulai menyembur.'],
  ['Cool the base under the tap to stop extraction.', 'Dinginkan bagian bawah di bawah keran untuk menghentikan ekstraksi.'],
  ['10 g · 150 g water just off the boil', '10 g · 150 g air yang baru mendidih'],
  ['Grind very fine (our Tubruk Blend is ground for this).', 'Giling sangat halus (Tubruk Blend kami sudah digiling untuk ini).'],
  ['Put coffee (and sugar, if you like) in the glass.', 'Masukkan kopi (dan gula, jika suka) ke dalam gelas.'],
  ['Pour the water, stir once.', 'Tuang air, aduk sekali.'],
  ['Wait four minutes for the grounds to settle, then sip from the top.', 'Tunggu empat menit sampai ampasnya turun, lalu seruput dari atas.'],
  ['Iced kopi susu', 'Es kopi susu'],
  ['18 g espresso or 40 g strong pour over · 120 ml milk · 20 ml gula aren syrup', '18 g espresso atau 40 g pour over pekat · 120 ml susu · 20 ml sirup gula aren'],
  ['Dissolve palm sugar 1:1 in hot water for the syrup.', 'Larutkan gula aren 1:1 dengan air panas untuk sirupnya.'],
  ['Fill a glass with ice, add milk and syrup.', 'Isi gelas dengan es, tambahkan susu dan sirup.'],
  ['Pour the coffee over the top and stir.', 'Tuang kopi di atasnya lalu aduk.'],
  ['<h2 class="wp-block-heading">Water</h2>', '<h2 class="wp-block-heading">Air</h2>'],
  ['Coffee is 98% water. Bali tap water is hard; filtered water or a light mineral water (around 50–100 ppm) makes every coffee here taste brighter and sweeter.', 'Kopi itu 98% air. Air keran di Bali tergolong sadah; air saring atau air mineral ringan (sekitar 50–100 ppm) membuat setiap kopi di sini terasa lebih cerah dan manis.'],
 ]),
 'shipping': ('Pengiriman dan pengembalian', [
  ['We roast on Monday and Thursday. Orders in by 10:00 (Bali time) on a roast day make that day’s batch and ship the same afternoon.', 'Kami menyangrai setiap Senin dan Kamis. Pesanan yang masuk sebelum pukul 10:00 WITA di hari sangrai ikut batch hari itu dan dikirim sore harinya.'],
  ['Rates and delivery times', 'Tarif dan waktu pengiriman'],
  ['Rates are per kilogram, rounded up, including packaging. Delivery is free in Bali over Rp 300.000 and in Java over Rp 750.000 with the regular service. Cold brew travels chilled with GoSend in south Bali only, or you can collect it at the roastery.', 'Tarif dihitung per kilogram, dibulatkan ke atas, sudah termasuk kemasan. Gratis ongkir di Bali untuk belanja di atas Rp 300.000 dan di Jawa di atas Rp 750.000 dengan layanan reguler. Cold brew dikirim dingin dengan GoSend khusus Bali selatan, atau bisa diambil di roastery.'],
  ['Pick up at the roastery', 'Ambil di roastery'],
  ['Jl. Raya Kerobokan No. 88, Badung, Tuesday to Sunday, 8:00–17:00. Coffee is ready from 14:00 on its roast day.', 'Jl. Raya Kerobokan No. 88, Badung, Selasa sampai Minggu, 08.00–17.00. Kopi siap diambil mulai pukul 14:00 di hari sangrainya.'],
  ['Returns', 'Pengembalian'],
  ['Opened coffee can’t be returned, but if a bag isn’t what you expected, tell us and we’ll send a different coffee. Equipment can be returned unused within 14 days; we cover the return shipping if it arrived damaged.', 'Kopi yang sudah dibuka tidak bisa dikembalikan, tetapi jika rasanya tidak sesuai harapan, kabari kami dan kami kirim kopi lain. Peralatan bisa dikembalikan dalam 14 hari selama belum dipakai; ongkos kirim balik kami tanggung jika barang datang rusak.'],
 ]),
 'track-order': ('Lacak pesanan', [
  ['Enter your order number and the email you used at checkout.', 'Masukkan nomor pesanan dan email yang Anda pakai saat checkout.'],
 ]),
 'gift-card-balance': ('Saldo gift card', [
  ['Check how much is left on a Lereng gift card. Codes look like LRG-XXXX-XXXX.', 'Cek sisa saldo gift card Lereng. Kodenya berbentuk LRG-XXXX-XXXX.'],
  ['Send a gift card', 'Kirim gift card'],
 ]),
 'wholesale': ('Grosir untuk kafe dan kantor', [
  ['Fresh-roasted coffee for cafés, restaurants, villas and offices in Bali and beyond.', 'Kopi yang baru disangrai untuk kafe, restoran, vila dan kantor di Bali dan sekitarnya.'],
  ['Tiered prices from 5 kg a month, with invoice terms for approved accounts', 'Harga bertingkat mulai 5 kg per bulan, dengan pembayaran invoice untuk akun yang disetujui'],
  ['A fixed roast day and delivery slot for your order', 'Hari sangrai dan jadwal kirim tetap untuk pesanan Anda'],
  ['Barista training at our tasting bar, and help dialling in your grinder', 'Pelatihan barista di tasting bar kami, dan bantuan setel grinder Anda'],
  ['Your own espresso blend from 20 kg a month', 'Blend espresso khusus Anda mulai 20 kg per bulan'],
  ['Apply for an account', 'Ajukan akun'],
 ]),
 'about': ('Tentang Lereng', [
  ['Lereng means slope. Almost all of Indonesia’s best coffee grows on the slopes of volcanoes, between 1,200 and 1,900 metres, on farms of one or two hectares.', 'Hampir semua kopi terbaik Indonesia tumbuh di lereng gunung api, di ketinggian 1.200 sampai 1.900 meter, di kebun seluas satu atau dua hektare. Dari situlah nama kami.'],
  ['We buy from farmers and cooperatives on six islands, cup every lot blind, and pay above the local cherry price for the ones we choose. Every bag names the producer and the place.', 'Kami membeli dari petani dan koperasi di enam pulau, melakukan cupping blind untuk setiap lot, dan membayar di atas harga cherry setempat untuk lot yang kami pilih. Setiap kantong mencantumkan nama produsen dan tempatnya.'],
  ['Everything is roasted in small batches in Kerobokan, Bali, twice a week.', 'Semuanya disangrai dalam batch kecil di Kerobokan, Bali, dua kali seminggu.'],
  ['Lereng is a fictional brand built as a portfolio project. The origins, processes and varieties are real; producers and the roastery are invented.', 'Lereng adalah merek fiktif yang dibuat sebagai proyek portofolio. Asal, proses dan varietasnya nyata; produsen dan roastery-nya rekaan.'],
 ]),
 'terms': ('Syarat dan ketentuan', [
  ['This is a demonstration store. No real orders are fulfilled and no real payments are taken: payments run in a sandbox and emails are captured in the <a href="/demo-inbox/">demo inbox</a>.', 'Ini toko demo. Tidak ada pesanan yang benar-benar diproses dan tidak ada pembayaran sungguhan: pembayaran berjalan di sandbox dan email ditampung di <a href="/demo-inbox/">kotak masuk demo</a>.'],
  ['Prices are in Indonesian rupiah and include 11% PPN.', 'Harga dalam rupiah dan sudah termasuk PPN 11%.'],
 ]),
}

STORIES = {
 'gayo-wine-natural': 'Buah kopi dikeringkan utuh di para-para selama 28 hari sampai kulitnya berwarna seperti buah plum.',
 'gayo-washed': 'Tumbuh di sekitar Danau Lut Tawar dan dicuci dengan air mata air dari punggung bukit di atas kebun.',
 'gayo-wet-hulled': 'Giling basah adalah cara sebagian besar kopi Sumatra diproses: dikupas saat masih basah, menghasilkan body tebal seperti kayu cedar.',
 'kerinci-honey': 'Tumbuh di lereng Gunung Kerinci, gunung api tertinggi di Indonesia, bersebelahan dengan kebun teh Kayu Aro.',
 'kerinci-washed': 'Kopi washed yang bersih dan cerah dari petani kecil di sekitar Danau Gunung Tujuh.',
 'mandailing-classic': 'Nama yang membuat kopi Sumatra terkenal di luar negeri, disangrai sedikit lebih gelap untuk cangkir yang kental seperti sirup.',
 'lintong-natural': 'Tumbuh di dataran tinggi selatan Danau Toba, tempat kabut pagi baru hilang menjelang siang.',
 'sidikalang-washed': 'Dairi sudah menanam kopi sejak zaman Belanda; lot ini versi washed yang modern.',
 'solok-radjo-natural': 'Koperasi muda di perbukitan sekitar Gunung Talang, kini langganan kompetisi cupping nasional.',
 'java-malabar-washed': 'Lereng Gunung Malabar di selatan Bandung adalah tempat kebangkitan kopi specialty Jawa dimulai.',
 'java-puntang-honey': 'Kebun keluarga di Gunung Puntang yang pernah menjadi juara di kejuaraan kopi specialty Indonesia.',
 'java-halu-natural': 'Dikeringkan perlahan di udara sejuk Gunung Halu, di bawah naungan pohon kayu putih.',
 'java-papandayan-washed': 'Ditanam tinggi di dekat kawah Papandayan; tanah vulkanis, malam yang dingin, pematangan yang lambat.',
 'java-cikuray-semi-washed': 'Kopi yang ramah dan bulat, sama enaknya di V60 maupun flat white.',
 'java-sindoro-natural': 'Temanggung lebih dikenal karena tembakaunya; kini petaninya menanam arabika di sela barisan tembakau.',
 'java-ijen-washed': 'Dari perkebunan tua di dataran tinggi Ijen, dekat kawah api biru.',
 'java-argopuro-honey': 'Kopi hutan: tumbuh di bawah naungan pohon asli di lereng Argopuro.',
 'bali-kintamani-washed': 'Ditanam dengan sistem subak abian, cara tradisional Bali untuk berbagi lahan dan air secara gotong royong.',
 'bali-kintamani-natural': 'Tumbuh di antara pohon jeruk, karena itulah banyak kopi Kintamani terasa citrus.',
 'flores-bajawa-washed': 'Dari dataran tinggi Bajawa di Flores, di kaki Gunung Inerie.',
 'flores-manggarai-natural': 'Desa Colol masih merawat pohon Typica dan Juria tua yang di tempat lain sudah diganti puluhan tahun lalu.',
 'toraja-sapan-washed': 'Dari dataran tinggi Tana Toraja, tempat kopi tumbuh di sekitar rumah tongkonan berukir.',
 'toraja-kalosi-wet-hulled': 'Kalosi adalah kota dagang kopi dari Pegunungan Latimojong; body penuh dan rendah asam.',
 'mamasa-natural': 'Lembah kecil yang terpencil di barat Toraja, hanya bisa dicapai lewat satu jalan berkelok.',
 'papua-wamena-washed': 'Pohon Typica yang ditanam tahun 1950-an, tumbuh tanpa bahan kimia di Lembah Baliem.',
 'papua-moanemani-washed': 'Dari lembah Kamuu; diterbangkan keluar dari pegunungan dengan pesawat kecil sebelum sampai ke kami.',
 'lampung-robusta-fine': 'Fine robusta: dipetik merah dan dikeringkan dengan teliti, cocok untuk kopi susu dan tubruk.',
 'temanggung-robusta-natural': 'Robusta jemur dari lereng bawah Gunung Sumbing; kuat dan manis dengan susu kental manis.',
 'liberika-jambi': 'Liberika tumbuh di lahan gambut pesisir timur Jambi; bijinya besar, manisnya unik seperti nangka.',
 'lereng-house-espresso': 'Espresso andalan kami sehari-hari. Enak diminum langsung, lebih enak lagi dengan susu.',
 'pagi-filter-blend': 'Cerah dan ringan, untuk cangkir pertama di pagi hari.',
 'kopi-susu-blend': 'Arabika dan robusta, disangrai untuk es kopi susu gula aren.',
 'malam-decaf': 'Dekafeinasi dengan etil asetat dari tebu, jadi tetap terasa seperti kopi setelah makan malam.',
 'tubruk-blend': 'Digiling halus untuk kopi tubruk: air mendidih langsung dituang ke bubuk kopi, gula sesuai selera.',
 'gayo-anaerobic-natural-lot-07': 'Difermentasi dalam tangki tertutup selama 96 jam sebelum dikeringkan. Hanya 60 karung dari lot ini.',
 'kerinci-carbonic-lot-02': 'Buah kopi utuh difermentasi dengan CO₂, teknik yang dipinjam dari pembuatan wine Beaujolais.',
 'java-puntang-gesha': 'Bibit Gesha ditanam tahun 2019; ini panen pertama yang cukup banyak untuk dijual.',
 'bali-kintamani-double-ferment': 'Difermentasi dua kali, sekali sebagai cherry dan sekali sebagai gabah, lalu dikeringkan sebagai red honey.',
}

DESCRIPTIONS = {
 'drip-bag-gayo': 'Sepuluh drip bag sekali seduh. Gantung di cangkir, tuang 180 ml air yang baru mendidih dalam tiga tahap.',
 'drip-bag-kintamani': 'Sepuluh drip bag sekali seduh. Gantung di cangkir, tuang 180 ml air yang baru mendidih dalam tiga tahap.',
 'drip-bag-house': 'Sepuluh drip bag sekali seduh. Gantung di cangkir, tuang 180 ml air yang baru mendidih dalam tiga tahap.',
 'cold-brew-concentrate-500': 'Diseduh dingin selama 18 jam dari Java Cikuray. Campur 1:2 dengan air atau susu. Simpan di kulkas; dikirim dingin khusus Bali.',
 'cold-brew-concentrate-1l': 'Diseduh dingin selama 18 jam dari Java Cikuray. Campur 1:2 dengan air atau susu. Simpan di kulkas; dikirim dingin khusus Bali.',
 'ceramic-dripper-02': 'Dripper kerucut dengan rusuk spiral, ukuran 02 (1–4 cangkir). Keramik tebal menjaga suhu seduhan tetap stabil.',
 'flat-bottom-dripper': 'Tiga lubang kecil dan dasar datar untuk ekstraksi yang merata dan mudah.',
 'glass-pour-over-carafe': 'Karafe kaca borosilikat dengan kerah kayu. Menyeduh dan menyajikan dalam satu alat.',
 'immersion-brewer': 'Alat seduh tekan untuk satu cangkir penuh dalam dua menit. Mudah dibawa bepergian.',
 'french-press-600': 'French press baja dinding ganda dengan saringan mesh halus.',
 'moka-pot-3': 'Alat seduh kompor dari aluminium. Cocok dengan Tubruk Blend dan Kopi Susu Blend kami.',
 'hand-grinder-c3': 'Burr kerucut stainless steel dengan 36 klik pengaturan, dari espresso sampai French press.',
 'electric-grinder-flat': 'Flat burr 64 mm, hopper single-dose, retensi rendah. Untuk home bar.',
 'gooseneck-kettle': 'Ketel leher angsa untuk kompor, untuk tuangan yang pelan dan presisi. Bisa di kompor induksi.',
 'electric-kettle-temp': 'Menjaga air di suhu berapa pun dari 60 sampai 100 °C.',
 'brew-scale': 'Resolusi 0,1 g, timer bawaan, isi daya USB-C.',
 'paper-filters-02': 'Filter kertas tanpa pemutih untuk dripper kerucut 02.',
 'paper-filters-flat': 'Filter wave untuk dripper dasar datar.',
 'glass-server-600': 'Server tahan panas dengan tanda setiap 100 ml.',
 'enamel-mug': 'Mug enamel bintik, 350 ml. Tahan api unggun dan mesin cuci piring.',
 'canvas-tote': 'Tas kanvas tebal, muat 3 kg biji kopi dan laptop.',
 'lereng-tee': 'Kaus katun tebal dengan cetakan garis kontur Lereng.',
 'bucket-hat': 'Topi bucket katun washed untuk kunjungan ke kebun.',
 'lereng-gift-card': 'Langsung dikirim lewat email, beserta pesan Anda. Berlaku untuk semua produk di toko, termasuk langganan, dan tidak ada masa berlaku.',
 'discovery-box': 'Pilih tiga single origin dalam kantong 100 g dan hemat 15%. Cara termudah menemukan kopi favorit Anda.',
 'brew-starter-kit': 'Semua untuk pour over pertama Anda: dripper, filter, server dan 200 g Pagi Filter Blend, 12% lebih murah daripada dibeli terpisah.',
 'office-pack': 'Tiga kilo House Espresso biji utuh untuk mesin kopi kantor. Diskon 10%.',
 'roasters-choice-subscription': 'Single origin yang berbeda di setiap pengiriman, dipilih oleh roaster kami, lengkap dengan catatan rasa dan kartu resep. Jeda atau batalkan kapan saja.',
}

# Attribute terms, tasting notes and short labels, by their English name.
TERMS = {
 # roast, process, grind, island, species, taste
 'Light': 'Light', 'Medium': 'Medium', 'Medium-dark': 'Medium-dark', 'Dark': 'Dark',
 'Washed': 'Washed (cuci)', 'Natural': 'Natural', 'Honey': 'Honey', 'Semi-washed': 'Semi-washed', 'Wet-hulled': 'Giling basah',
 'Anaerobic natural': 'Natural anaerob', 'Carbonic maceration': 'Maserasi karbonik', 'Double-fermented honey': 'Honey fermentasi ganda',
 'Whole bean': 'Biji utuh', 'Pour over': 'Pour over', 'Espresso': 'Espresso', 'French press': 'French press', 'Moka pot': 'Moka pot',
 'Java': 'Jawa', 'Nusa Tenggara': 'Nusa Tenggara', 'Sulawesi': 'Sulawesi', 'Sumatra': 'Sumatra', 'Bali': 'Bali', 'Papua': 'Papua', 'Blend': 'Blend',
 'Chocolatey': 'Cokelat', 'Citrus': 'Citrus', 'Earthy': 'Earthy', 'Floral': 'Floral', 'Fruity': 'Buah', 'Nutty': 'Kacang', 'Spicy': 'Rempah', 'Sweet': 'Manis', 'Winey': 'Winey',
 'Island': 'Pulau', 'Roast': 'Roast', 'Taste': 'Rasa', 'Species': 'Spesies', 'Size': 'Ukuran', 'Grind': 'Gilingan', 'Process': 'Proses', 'Colour': 'Warna',
 # tasting notes
 'Almond': 'Almond', 'Apricot': 'Aprikot', 'Bergamot': 'Bergamot', 'Black tea': 'Teh hitam', 'Blueberry': 'Bluberi', 'Brown sugar': 'Gula merah',
 'Cacao': 'Kakao', 'Cacao nib': 'Nib kakao', 'Candied orange': 'Jeruk manisan', 'Cane sugar': 'Gula tebu', 'Caramel': 'Karamel', 'Cedar': 'Kayu cedar',
 'Cinnamon': 'Kayu manis', 'Clove': 'Cengkih', 'Cocoa': 'Kokoa', 'Dark berries': 'Beri gelap', 'Dark cherry': 'Ceri hitam', 'Dark chocolate': 'Cokelat hitam',
 'Dark sugar': 'Gula hitam', 'Earth': 'Tanah', 'Fudge': 'Fudge', 'Grape': 'Anggur', 'Green apple': 'Apel hijau', 'Hazelnut': 'Hazelnut', 'Hibiscus': 'Rosela',
 'Jackfruit': 'Nangka', 'Jasmine': 'Melati', 'Lemon': 'Lemon', 'Lemongrass': 'Serai', 'Lime': 'Jeruk nipis', 'Mandarin': 'Jeruk mandarin', 'Mango': 'Mangga',
 'Milk chocolate': 'Cokelat susu', 'Molasses': 'Molase', 'Nutmeg': 'Pala', 'Orange': 'Jeruk', 'Orange peel': 'Kulit jeruk', 'Palm sugar': 'Gula aren',
 'Passion fruit': 'Markisa', 'Peach': 'Persik', 'Peanut': 'Kacang tanah', 'Pineapple': 'Nanas', 'Plum': 'Plum', 'Raspberry': 'Raspberi', 'Red apple': 'Apel merah',
 'Red currant': 'Kismis merah', 'Red wine': 'Anggur merah', 'Roasted grain': 'Biji sangrai', 'Roasted nut': 'Kacang sangrai', 'Rose': 'Mawar', 'Rum': 'Rum',
 'Smoke': 'Asap', 'Spice': 'Rempah', 'Strawberry': 'Stroberi', 'Strawberry yoghurt': 'Yoghurt stroberi', 'Tamarind': 'Asam jawa', 'Tobacco': 'Tembakau',
 'Toffee': 'Toffee', 'Vanilla': 'Vanila',
 "Roaster's pick": 'Pilihan roaster', 'Tasting card': 'Kartu rasa', 'Brew recipe': 'Resep seduh',
 'Colour': 'Warna', 'Black': 'Hitam', 'Sand': 'Pasir', 'Clay': 'Tanah liat', 'Forest': 'Hijau hutan',
}

# Applied in order to regions, recipes, product names and short descriptions.
PHRASES = [
 [' coffee to ', ' kopi untuk '], [' water at ', ' air, '], [' total', ' total'], ['great with milk', 'enak dengan susu'],
 [', or espresso ', ', atau espresso '], ['just-off-boil water', 'air yang baru mendidih'], ['let it settle 4 minutes', 'diamkan 4 menit'],
 ['Tubruk or moka pot', 'Tubruk atau moka pot'], [' g in, ', ' g masuk, '], [' g out, ', ' g keluar, '],
 ['West Bandung', 'Bandung Barat'], ['Mount ', 'Gunung '], ['Highlands', 'Dataran Tinggi'], ['Ijen Plateau', 'Dataran Tinggi Ijen'], ['Baliem Valley', 'Lembah Baliem'],
 [' and neighbours', ' dan tetangga'], [' and family', ' dan keluarga'],
 [' roast.', ' roast.'], ['Paper Filters', 'Filter Kertas'], [' pcs', ' lembar'], ['Drip Bags', 'Drip Bag'], ['Cold Brew Concentrate', 'Konsentrat Cold Brew'],
 ['Gift Card', 'Gift Card'], ['Pick 3', 'Pilih 3'],
]

def build():
    pages = json.loads((ROOT / 'data/pages.json').read_text())
    out = {'pages': {}, 'products': {}, 'terms': TERMS, 'phrases': PHRASES}
    for slug, (title, pairs) in PAGES.items():
        html = pages[slug]['content']
        for en, id_ in pairs:
            assert en in html, f'{slug}: segment not in page: {en[:60]}'
            html = html.replace(en, id_)
        out['pages'][slug] = {'title': title, 'content': html}
    missing = [s for s in pages if s not in PAGES]
    assert not missing, f'pages without a translation: {missing}'
    catalog = json.loads((ROOT / 'data/catalog.json').read_text())
    for p in catalog['products']:
        entry = {}
        if p['slug'] in STORIES:
            entry['story'] = STORIES[p['slug']]
        if p['slug'] in DESCRIPTIONS:
            entry['description'] = DESCRIPTIONS[p['slug']]
        if not entry:
            raise SystemExit(f'no Indonesian copy for {p["slug"]}')
        for n in p.get('notes', []):
            assert n in TERMS, f'tasting note without a translation: {n}'
        out['products'][p['slug']] = entry
    (ROOT / 'data/i18n/content.id_ID.json').write_text(json.dumps(out, ensure_ascii=False, indent=1))
    print(f"{len(out['pages'])} pages, {len(out['products'])} products, {len(TERMS)} terms")

if __name__ == '__main__':
    build()

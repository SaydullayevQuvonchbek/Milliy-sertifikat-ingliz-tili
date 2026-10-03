# Multilevel Mock — ingliz tili milliy sertifikati uchun mock platforma

Ingliz tili milliy sertifikati (Multilevel) formatida onlayn mock imtihon o'tkazish uchun web platforma.
To'rt bo'lim: **Listening** (6 qism, 35 savol), **Reading** (5 qism, 35 savol), **Writing** (3 topshiriq) va **Speaking** (8 savol).
Imtihon oynasi IELTS CD (computer-delivered) uslubida qurilgan.

- Backend: PHP 8.1+ (freymvorksiz), SQLite yoki MySQL/MariaDB.
- Frontend: sof JavaScript (ES modullar, kutubxonasiz, yig'ish bosqichi yo'q).
- Oddiy hostingda (Apache + PHP, masalan reg.ru) ishlaydi — fayllarni yuklash kifoya.

---

## Imkoniyatlar

### O'quvchi uchun

| Imkoniyat | Qanday ishlaydi |
| --- | --- |
| IELTS CD uslubidagi oyna | Reading'da chapda matn, o'ngda savollar; ajratgichni surib o'lchamni o'zgartirish; pastda qismlar va savol raqamlari navigatori; "Belgilash" (review) bayrog'i; matn o'lchami va rang rejimi (qora fonda sariq, krem fon) |
| Matnni belgilash | Reading matnida so'zlarni belgilash (highlight) va izoh qoldirish — sichqonchaning o'ng tugmasi yoki tanlov menyusi orqali |
| Listening | Audio test boshlanishidan oldin qurilmaga yuklab olinadi; har bir matn 2 marta eshittiriladi; to'xtatish, orqaga surish va tezlatish yo'q; savollarni ko'rib chiqish va javoblarni tekshirish vaqti bor |
| Writing | So'z hisoblagichi (talab bo'yicha rangli), imlo tekshiruvi va avtoto'ldirish o'chirilgan, paste bloklangan |
| Speaking | Mikrofon tekshiruvi (5 soniyalik sinov yozuvi), har savol uchun tayyorlanish va javob taymerlari, signal, avtomatik to'xtash va yuklash |
| Qotib qolmaslik | Javoblar darhol qurilmaga (localStorage), keyin navbat bilan serverga yoziladi. Internet uzilsa ishlash davom etadi, aloqa tiklanganda yuboriladi |
| Sahifa o'z-o'zidan qayta chizilmaydi | Ekran faqat bo'lim almashganda yangilanadi; taymer, saqlash holati va navigator joyida yangilanadi. Sahifa yangilansa ham javoblar, belgilar va Listening pozitsiyasi tiklanadi |
| Server taymeri | Vaqt serverda hisoblanadi; sahifani yangilash yoki kompyuterni qayta yoqish taymerni to'xtatmaydi. Muddat o'tsa, bo'lim avtomatik yopiladi (sekin internet uchun 45 soniyalik zaxira). Listening audiosi yuklab olingach, bo'lim ko'pi bilan 3 daqiqada o'zi boshlanadi — audioni oldindan tinglab bo'lmaydi |
| Natijalar | 4 ko'nikma balli (75 ballik), umumiy ball va daraja (B1/B2/C1), qismlar bo'yicha to'g'ri javoblar, ekspert izohlari. To'g'ri javoblar kaliti o'quvchiga ko'rsatilmaydi (2-urinish uchun sir qoladi) |

### Imtihon xavfsizligi (lockdown)

- To'liq ekran rejimi majburiy; undan chiqilsa, ekran bloklanadi va qoidabuzarlik yoziladi.
- Boshqa oyna yoki dasturga o'tish, sahifani uzoq muddat (20 soniyadan ortiq) yopib qo'yish — qoidabuzarlik.
- Nusxa olish, kesish, joylashtirish, fayl tashlash, sichqonchaning o'ng tugmasi, chop etish va klaviatura yorliqlari (Ctrl+C/V/S/P/U/F, F12, F5 va boshqalar) o'chirilgan. Chrome va Edge'da to'liq ekran rejimida **Keyboard Lock API** ham yoqiladi: Esc va ayrim tizim tugmalari sahifada ushlab qolinadi.
- Bitta imtihon — bitta oyna: ikkinchi oyna yoki qurilmada ochilsa, birinchisi yopiladi va bu qoidabuzarlik sifatida yoziladi.
- Speaking'da keyingi savol oldingisining vaqti tugamaguncha ochilmaydi; ruxsat etilgan vaqtdan uzun yozuv qabul qilinmaydi.
  Mock sozlamasida **"javobni erta tugatib keyingi savolga o'tish"** yoqilsa — o'quvchi tayyorlanishni o'tkazib yuborib,
  javobini aytib bo'lgach keyingi savolga o'tadi. **"Bo'limni vaqt tugamasdan yakunlash"** ni o'chirib qo'yish ham mumkin —
  unda bo'lim faqat vaqt tugaganda yopiladi (serverda ham tekshiriladi).
- **Video nazorat.** Listening/Reading/Writing'da o'quvchi ekrani va kamerasi bitta videoga, Speaking'da kamera va ovoz
  yoziladi; 15 soniyalik bo'laklarda serverga, u yerdan 10 daqiqalik fayllar bilan Telegram kanalga yuboriladi (yozma qism
  videolari yuborilgach serverdan o'chadi, Speaking videolari qoladi). Har mock uchun: o'chiq / bo'lsa yoziladi (kamerasiz
  ham topshiradi, natijada belgi) / majburiy. Ekran ulashishni to'xtatish yoki kamerani uzish jurnalga yoziladi (majburiy
  bo'lsa — qoidabuzarlik), bir nechta monitor ham belgilanadi. Sozlash: `deploy/SERVERGA-JOYLASH.md`, 10-bo'lim.
- Safe Exam Browser va telefon cheklovi imtihon davomidagi har bir so'rovda tekshiriladi.
- Qoidabuzarliklar soni chegaradan oshsa (sozlamada, standart — 3 ta), imtihon avtomatik to'xtatiladi. "Faqat jurnalga yozish" rejimi ham bor.
- Writing'da yozish jarayoni statistikasi saqlanadi: bosilgan tugmalar soni, bloklangan paste urinishlari, birdaniga paydo bo'lgan katta matn bo'laklari.
- Administrator jonli nazorat sahifasida kim onlayn, qaysi bo'limda, qancha vaqt qolgani va qoidabuzarliklarni ko'radi.

> **Muhim:** oddiy veb-sahifa operatsion tizimni to'liq boshqara olmaydi — masalan, o'quvchi telefonni qo'lga olishini yoki
> Ctrl+Alt+Del bosishini to'xtata olmaydi. Platforma har bir chiqishni aniqlaydi, bloklaydi, jurnalga yozadi va chegaradan
> oshsa imtihonni to'xtatadi. Yuqori talabli imtihonlar uchun mock sozlamasida **"Faqat Safe Exam Browser orqali"** rejimini
> yoqing (bepul dastur: Windows, macOS, iPad — kompyuterni to'liq qulflaydi) yoki mockni markazda nazoratchi bilan o'tkazing.

### Administrator uchun

- **Mock quruvchi.** Rasmiy format shablonlari: bir tugma bilan Listening (6 qism), Reading (5 qism), Writing (3 topshiriq)
  va Speaking (8 savol) tuzilmasi standart inglizcha ko'rsatmalar va to'g'ri raqamlar bilan yaratiladi. Savol turlari:
  test (A–D), True/False/Not Given, moslashtirish (A–J yoki I–VIII sarlavhalar), bo'sh joy to'ldirish (`[[9]]` belgisi bilan,
  muqobil javoblar `colour|color`).
- **Tekshiruv va imlo.** Faollashtirishdan oldin format tekshiriladi (savol raqamlari uzluksizligi, javobsiz savol, audio
  yo'qligi va h.k.). Imlo va yozuv xatolari aniqlanadi: lotin va kirill harflari aralash so'z (masalan, "Lоndon" ichidagi kirill
  "о"), takrorlangan so'z ("the the"), ortiqcha bo'sh joy, tinish belgisidan oldin bo'sh joy, juft bo'lmagan qavs va
  qo'shtirnoq, vaqtinchalik matn (TODO). Barcha matn maydonlarida brauzerning inglizcha imlo tekshiruvi yoqilgan.
- **O'quvchi ko'rinishi.** Mockni saqlamasdan turib ham o'quvchi ko'rgandek ko'rish (to'g'ri javoblarni ko'rsatish bilan).
- **Mockni muzlatish.** Muzlatilgan mock o'quvchilarga ko'rinmaydi va yangi urinish boshlanmaydi; boshlangan imtihonlar
  oxirigacha davom etadi. Istalgan vaqtda qayta faollashtiriladi. Arxivlash, nusxa olish, JSON import/eksport ham bor.
- **Urinishlar chegarasi.** Bitta o'quvchi bitta mockni **ko'pi bilan 2 marta** ishlaydi. Bu serverda tranzaksiya va baza
  cheklovi (`UNIQUE(mock_id, user_id, attempt_no)`) bilan qat'iy ta'minlanadi — bir vaqtda ikki marta "Boshlash" bosilsa ham
  chegara buzilmaydi. "Tasodifiy mock" tugmasi avval o'quvchi hali ishlamagan mocklarni tanlaydi.
- **Natijalar.** Jadval, Excel (CSV), qayta hisoblash, Rasch tahlili, natijalarni e'lon qilish. Savollar tahlili: har savol bo'yicha
  to'g'ri javoblar ulushi, qiyinlik va eng ko'p uchragan noto'g'ri javoblar; gap-fill javobini bir tugma bilan kalitga
  qo'shish (barcha natijalar avtomatik qayta hisoblanadi).
- **Urinish tafsiloti.** Har savolga berilgan javob, Writing matnlari, Speaking yozuvlari, video yozuvlar (ko'rish, yuklab
  olish, Telegram'dagi xabarga havola), ekspert baholari va hodisalar jurnali.
  Texnik nosozlikda urinishni sababi bilan bekor qilish (o'quvchiga urinish qaytariladi) yoki imtihonni to'xtatish.
- **Foydalanuvchilar.** O'quvchilarni ro'yxat bilan qo'shish (Excel'dan nusxalash), parollarni chop etish, bloklash.

### Ekspert uchun

- Writing va Speaking ishlari anonim kod bilan navbatdan beriladi. Rasmiy mezonlar: Writing 1.1 (0–5), 1.2 (0–5), 2 (0–6);
  Speaking 1.1, 1.2, 2 (0–5), 3 (0–6). Maxsus holatlar (mavzuga mos emas, yodlangan, ko'chirilgan, asosan ona tilida) rasmiy
  qoida bo'yicha avtomatik qo'llanadi.
- Har ishni 1 yoki 2 ekspert baholaydi (sozlamada); ikki baho farqi chegaradan oshsa, 3-ekspertga yuboriladi.

## Baholash

| Ko'nikma | Hisoblash |
| --- | --- |
| Listening, Reading | Avtomatik. Rasch hisoblanmaguncha taxminiy shkala (agentlik chegaralari: 10 → 38, 18 → 51, 28 → 65, 35 → 75). Rasch'dan keyin: T = 50 + 10·(θ − μ)/σ, 0–75 oralig'ida (+2.5 va undan yuqori → 75). Kichik MOC uchun μ va σ ni qotirish mumkin |
| Writing | Ekspertlar o'rtachasi (0–16) → rasmiy jadval bilan 0–75 |
| Speaking | Ekspertlar o'rtachasi (0–21) → rasmiy jadval bilan 0–75 |
| Umumiy | 4 ko'nikma o'rtachasi. Daraja: B1 38–50, B2 51–64, C1 65–75 (butun songa yaxlitlangan umumiy ball bo'yicha) |

Bo'sh Writing yoki yozuvsiz Speaking avtomatik 0 bilan baholanadi va ekspert navbatiga tushmaydi.

---

## O'rnatish

### Talablar

- PHP 8.1 yoki yangiroq: `pdo_sqlite` yoki `pdo_mysql`, `mbstring`, `fileinfo`, `intl` (tavsiya), `gd` (faqat namunaviy rasmlar uchun).
- Apache (`mod_rewrite`) yoki Nginx.
- HTTPS (mikrofon va to'liq ekran uchun brauzerlar xavfsiz ulanishni talab qiladi; `localhost` bundan mustasno).

### Lokal ishga tushirish

```bash
cp config/config.example.php config/config.php      # kerak bo'lsa tahrirlang
php bin/install.php --admin-login=admin --admin-password=KuchliParol123 --content --demo
php -S 127.0.0.1:8080 -t public bin/dev-router.php
```

- O'quvchi sahifasi: http://127.0.0.1:8080/
- Boshqaruv paneli: http://127.0.0.1:8080/admin/

- `--content` — `content/mocks/` dagi **3 ta tayyor mock**ni (audio, rasm va savollari bilan) joylaydi va faollashtiradi
  (pastdagi "Tayyor mocklar" bo'limiga qarang). Ishlab chiqarish serverida ham shuni ishlating.
- `--demo` — faqat sinov uchun: qisqa mock (5–10 daqiqa) va to'liq namuna mock (audiosi oddiy signal ovozlari), ekspert
  (`expert` / `expert123`) va o'quvchi (`+998900000001` / `student123`) hisoblarini qo'shadi. Haqiqiy serverda **ishlatmang**.

### Hostingga joylash (Apache, masalan reg.ru)

Batafsil yo'riqnoma (joylashuv variantlari, IP va proksi, zaxira, muammolar): `deploy/SERVERGA-JOYLASH.md`.
Serverga yuklash paketi (keraksiz va xavfli fayllarsiz, `MANIFEST.txt` bilan): `python deploy/build.py`.

1. Barcha fayllarni serverga yuklang. **Veb-ildiz (document root) `public/` papkasi bo'lishi kerak.** Agar hostingda
   veb-ildizni o'zgartirib bo'lmasa, `public/` ichidagini (yashirin `.htaccess` bilan) `public_html/` ga, qolgan papkalarni
   (`src`, `config`, `database`, `bin`, `content`, `storage`) bir daraja yuqoriga joylang.
2. `config/config.php` yarating (`config.example.php` dan nusxa): baza turi, MySQL ma'lumotlari, `storage_path`.
   Fayl bo'lmasa API ishlamaydi va "Sozlama fayli topilmadi" xabarini qaytaradi (namunaviy sozlama bilan indamay ishlab ketmaydi).
3. `php bin/install.php --admin-login=admin --admin-password=... --content` ni bir marta ishga tushiring (SSH yoki hosting panelidagi cron orqali).
   `--content` 3 ta tayyor mockni joylaydi; keyin ham `php bin/seed-content.php` bilan qo'shsa bo'ladi (bor mocklar o'tkazib yuboriladi).
4. `storage/` papkasiga yozish huquqi bering. U veb-ildizdan tashqarida bo'lsin (ichida bo'lsa, `storage/.htaccess` kirishni taqiqlaydi).
5. PHP sozlamalari: `upload_max_filesize = 64M`, `post_max_size = 64M` (Listening audiolari uchun), `max_execution_time = 60`.

Ko'p o'quvchi bir vaqtda ishlaydigan server uchun MySQL tavsiya etiladi (`'driver' => 'mysql'`). SQLite kichik markaz
uchun yetarli. Ikkalasi ham sinalgan: barcha testlar SQLite va MariaDB 10.11 da o'tadi; 150 o'quvchi bir vaqtda
ishlaganda (yuklama sinovi) ikkala bazada ham xato yo'q, javoblarni saqlash so'rovi p95 < 100 ms.

**Kirish (login) CPU'ni band qiladi** (parol xeshi ataylab sekin; narxi `config.php` dagi `password_cost`, standart 10).
4 yadroli mashinada 150 o'quvchi bir vaqtda kirganda o'rtacha kutish ≈ 1,6 s, eng sekini ≈ 3,5 s — imtihon boshlanishida
o'quvchilarni 5–10 daqiqa oralig'ida kiritish yoki oldindan kirib turishni so'rash tavsiya etiladi. Imtihon davomidagi
so'rovlar (javoblarni saqlash) engil: p95 < 20 ms.

**Kirish cheklovlari va IP.** 15 daqiqada bitta login + IP uchun 8 ta xato urinish; bitta IP'dan jami xatolar —
`login_ip_limit` (standart 300, `0` — o'chirilgan), ro'yxatdan o'tish — `register_ip_limit` (standart 100). Markazdagi
barcha kompyuterlar bitta tashqi IP orqali chiqsa, `login_ip_limit` ni oshiring. Administrator va ekspertga umumiy IP
chegarasi qo'llanmaydi; bloklarni **Sozlamalar → Kirish bloklari** ko'rsatadi va tozalaydi (u yerda server sizni qaysi IP
bilan ko'rayotgani ham chiqadi).

Sayt **teskari proksi** ortida bo'lsa (hosting nginx'i, Cloudflare) va hamma bitta IP bo'lib ko'rinsa, `config.php` da
`'client_ip_header' => 'HTTP_X_FORWARDED_FOR'` qo'ying. Sarlavha faqat so'rov `trusted_proxies` dagi manzildan kelganda
o'qiladi (standart `['private']` — shu host va ichki tarmoq; Cloudflare uchun `['private', 'cloudflare']`) va o'ngdan
chapga tekshiriladi, shuning uchun o'quvchi uni soxtalashtirib cheklovni chetlab o'ta olmaydi. Proksi bo'lmasa, sarlavhani
bo'sh qoldiring.

### Nginx

```nginx
root /var/www/mock/public;
index index.html;
location /api/ { rewrite ^/api/(.*)$ /api.php?route=$1 last; }
location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
location / { try_files $uri $uri/ =404; }
client_max_body_size 64m;
location ~ /\.(?!well-known/) { deny all; }   # .user.ini va boshqa yashirin fayllar

# Xavfsizlik sarlavhalari (Apache'da public/.htaccess qo'yadi; matn src/Http/Security.php dagi bilan bir xil bo'lsin)
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'" always;
add_header X-Frame-Options DENY always;
add_header X-Content-Type-Options nosniff always;
add_header Permissions-Policy "camera=(self), display-capture=(self), geolocation=(), microphone=(self), fullscreen=(self)" always;
```

### Zaxira nusxa

```bash
php bin/backup.php --keep=4                   # baza + audio/rasmlar/Speaking yozuvlari → storage/backups/backup-<sana>.zip
php bin/backup.php --no-uploads --keep=14     # faqat baza → storage/backups/backup-db-<sana>.zip
```

Har tur o'zicha saqlanadi (`--keep` — shu turdagi eng yangi nusxalar soni). Cron: har kecha faqat baza
(`30 3 * * * php /yo'l/bin/backup.php --no-uploads --keep=14`), har hafta to'liq (`45 3 * * 0 php /yo'l/bin/backup.php --keep=4`)
— to'liq nusxa yuklangan fayllar hajmicha joy oladi. SQLite'da imtihon davomida ham xavfsiz. MySQL'da `mysqldump`
(yoki `mariadb-dump`) bo'lsa u ishlatiladi, bo'lmasa yoki hostingda `exec()` o'chiq bo'lsa — PHP'ning o'zi izchil SQL dump
yozadi. Tiklash: ZIP'ni oching, `database.sqlite` (yoki `dump.sql`) va `uploads/` ni joyiga qo'ying.
Video yozuvlar (`storage/recordings/`) zaxira nusxaga kirmaydi — ular Telegram kanalda saqlanadi.

### Video nazorat navbati (Telegram)

```bash
php bin/recordings.php            # cron: har daqiqada — uzilgan yozuvlarni yig'adi, eskilarini o'chiradi, Telegram'ga yuboradi
php bin/recordings.php --test     # kanal(lar)ga sinov xabari
php bin/recordings.php --status   # navbat holati
```

`config/config.php` dagi `telegram` bo'limi: `bot_token`, `chat_id`, ixtiyoriy `speaking_chat_id`, `api_base` (relay) va
`proxy`. Server Rossiyada bo'lsa `api.telegram.org` bloklangan — relay yoki proxy kerak (yo'riqnomaning 10-bo'limi).

---

## Mock qo'shish (qisqa yo'riqnoma)

1. **Mocklar → Yangi mock**, nomini yozing va saqlang.
2. **Fayllar** bo'limida Listening audiolari va Speaking rasmlarini yuklang (audio davomiyligi avtomatik o'lchanadi).
3. **Listening / Reading / Writing / Speaking** bo'limlarida **"Rasmiy format shablonini yaratish"** tugmasini bosing va matnlar,
   variantlar, to'g'ri javoblarni to'ldiring. Listening qismlarida audio yozuvlarni ijro tartibida tanlang.
4. **Tekshirish** bo'limida xatolar va imlo ogohlantirishlarini tuzating, **O'quvchi ko'rinishi** da natijani ko'ring.
5. **Faollashtirish** — mock o'quvchilarga ochiladi. Kerak bo'lsa **Muzlatish**.
6. Imtihondan keyin: **Tekshiruv** (ekspertlar), **Natijalar → Rasch** (kamida 10 qatnashchi), **Natijalarni e'lon qilish**.

Matnlarda yengil belgilash ishlatiladi: bo'sh qator — yangi xatboshi, `**qalin**`, `*kursiv*`, `## sarlavha`, `- ro'yxat`,
xatboshi belgisi `[A]` yoki `[7]` bilan boshlanadi, bo'sh joy `[[9]]`.

---

## Tayyor mocklar

`content/mocks/` da rasmiy formatdagi **3 ta to'liq mock** bor (`bin/install.php --content` yoki `php bin/seed-content.php`
bilan joylanadi). Barcha matnlar, savollar va audio skriptlari shu loyiha uchun original yozilgan.

| Mock | Mavzular | Listening (audio / imtihon jadvali) |
| --- | --- | --- |
| Multilevel Mock 1 | ta'lim, texnologiya, sayohat, shahar hayoti | 16.2 daq / 39.7 daq |
| Multilevel Mock 2 | tabiat, ish va kasb, tarix va madaniyat, ko'ngillilik | 17.7 daq / 42.7 daq |
| Multilevel Mock 3 | fan, ovqat va turmush, sport, ommaviy axborot | 15.4 daq / 38.2 daq |

Har bir mock: Listening 6 qism / 35 savol (har yozuv ikki marta eshittiriladi), Reading 5 qism / 35 savol, Writing 3 topshiriq,
Speaking 8 savol (savollar ekzaminator ovozida o'qiladi) va 3 ta rasm. Qiyinlik B1 dan C1 gacha oshib boradi.

- **Audio** — Kokoro neyron TTS (ochiq litsenziya) bilan sintez qilingan, turli amerikacha va inglizcha ovozlar. Tayyor `.mp3`
  fayllar repoda, shuning uchun oddiy o'rnatishda TTS kerak emas. Diktor yozuvi bilan almashtirish istalsa: admin panelda
  **Fayllar** bo'limidan yuklang (transkriptlar admin panelda turibdi).
- **Rasmlar** — SVG'da chizilgan illyustratsiyalar (fotosurat emas); istalgan payt admin paneldan almashtiriladi.
- **Sifat nazorati** — har mockning kaliti savol-savol dalillar bilan tekshirilgan (to'g'ri javob variantlari A–D bo'yicha
  teng taqsimlangan, muqobil javoblar kiritilgan); `php bin/check-content.php` xato ham, ogohlantirish ham bermaydi.
  Audio talaffuzi quloq bilan tekshirilmagan (inson tinglab ko'rishi tavsiya etiladi — ayniqsa ism va joy nomlari).
- Yangi mock yaratish, audioni qayta sintez qilish: `content/README.md`.

## Testlar

```bash
php tests/php/run.php            # 102 ta PHP testi: baholash, Rasch, urinishlar, taymerlar, ekspertlar, xavfsizlik, zaxira, video nazorat va Telegram navbati
MOCK_TEST_DB=mysql php tests/php/run.php   # xuddi shular MySQL/MariaDB da (MOCK_TEST_MYSQL_HOST/PORT/DB/USER/PASS; bazani tozalaydi!)
node --test tests/js/*.test.mjs  # JS testlari: Listening vaqt jadvali (server bilan bir xil), so'z sanash, video format va kadr joylashuvi
npm install && npm run test:e2e  # brauzerda to'liq ssenariy (Playwright, soxta kamera/ekran va soxta Telegram): o'quvchi, admin, ekspert (MOCK_E2E_DB=mysql ham mumkin; MOCK_E2E_SHOTS=papka — ekran rasmlari)
npm run test:content             # tayyor 3 mock brauzerda: 78 audio ochiladi va davomiyligi mos, rasmlar, Range, javob kaliti sirtqi
npm run test:load -- --students 150 --video 250   # yuklama sinovi: ko'p o'quvchi bir vaqtda, video bo'laklari bilan (MOCK_LOAD_DB=mysql ham mumkin)
```

Barchasi GitHub Actions'da ham ishlaydi (SQLite, MySQL, E2E, tayyor mocklar, yuklama). Sinalmagan: Firefox va Safari
(Playwright brauzerlari faqat Chromium'da sinalgan); Keyboard Lock API faqat Chrome/Edge'da ishlaydi, boshqalarida
lockdown qolgan qoidalar bilan ishlaydi.

## Tuzilma

```
public/                 veb-ildiz
  index.html            o'quvchi ilovasi
  admin/index.html      boshqaruv paneli
  api.php               API kirish nuqtasi
  assets/js/lib/        umumiy modullar (API, DOM, matn belgilash, vaqt jadvali, o'zbekcha matnlar)
  assets/js/student/    o'quvchi ilovasi va imtihon dvigateli (exam/)
  assets/js/admin/      boshqaruv paneli
src/                    PHP: marshrutlar, kontrollerlar, xizmatlar (urinishlar, baholash, Rasch, ekspertlar)
database/               SQLite va MySQL sxemalari
content/                tayyor mocklar (mocks/mock-01…03: savollar, audio, rasmlar), TTS va rasm vositalari (tools/)
bin/                    o'rnatish, tayyor/namunaviy mocklarni joylash, zaxira nusxa, video navbati (Telegram), lokal server marshrutlovchisi
deploy/                 serverga yuklash paketi: build.py, yo'riqnoma, ildiz .htaccess va public/.user.ini
demo/                   namunaviy (sinov uchun) mocklar
tests/                  PHP, JS, E2E, tayyor mocklar va yuklama testlari
storage/                baza, audio, rasmlar, Speaking yozuvlari, video yozuvlar (recordings/) (git'ga qo'shilmaydi)
```

## Rejada (hozircha kerak emas)

- Markaz (qog'oz) rejimi: Listening/Reading titullari va OMR skaner, Writing sahifalarini rasmga olish.
- Speaking uchun Telegram bot orqali voice xabar bilan topshirish (zaxira kanal).
- Sertifikat va QR orqali tekshirish, apellyatsiya.
- To'lov (kirish kodlari yoki Telegram Stars).

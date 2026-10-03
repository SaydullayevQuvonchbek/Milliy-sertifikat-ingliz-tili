# Multilevel Mock — serverga joylash yo'riqnomasi

Manba: https://github.com/SaydullayevQuvonchbek/Milliy-sertifikat-ingliz-tili — aniq branch, commit va har bir faylning
SHA-256 xeshi paket ichidagi `MANIFEST.txt` da.

Paket = repo'ning serverda kerak bo'ladigan qismi + uchta qo'shimcha fayl (repo'ning `deploy/` papkasidan):
loyiha ildizidagi `.htaccess` (C joylashuvi uchun), `public/.user.ini` (PHP sozlamalari) va shu yo'riqnoma.
Paketga **ataylab kiritilmagan**: `demo/` va `bin/seed-demo.php` (hammaga ma'lum parolli sinov hisoblarini yaratadi),
`bin/dev-router.php`, `bin/check-content.php`, `content/tools/`, testlar, `.git`, `.github`, `deploy/`.

**Nima sinalgan (2026-10-03):** PHP 8.4 da 102 ta PHP testi (SQLite va MariaDB 10.11), 11 ta JS testi; brauzerda
to'liq ssenariy (o'quvchi, admin, ekspert; soxta kamera va ekran bilan video nazorat, sahifani yangilash, soxta
Telegram serveriga yuborish — SQLite va MariaDB); 150 o'quvchi bir vaqtda ishlagan yuklama sinovi, har biri video
bo'laklari bilan (MariaDB, 0 xato, bo'lak p95 25 ms). 2026-10-02 dagi sinovlar (PHP 8.3, Apache + mod_php, tayyor
mocklar) avvalgidek. Apache 2.4.58 + mod_php 8.3 da paket A, B va C (pastki papkada) joylashuvlarida
o'rnatildi va 56 ta HTTP tekshiruvidan o'tdi: kirish, teskari proksi ortida IP aniqlash, IP bloki va uni ochish, yopiq
fayllar, zaxira nusxa (SQLite, mysqldump, PHP dump — qayta yuklanganda jadvallar nazorat yig'indisi bir xil).

## 1. Server talablari

- **PHP 8.1 yoki yangiroq** (8.3 va 8.4 sinalgan). Kengaytmalar: `mbstring`, `fileinfo`, baza turiga qarab `pdo_mysql`
  yoki `pdo_sqlite`; `zip` — zaxira nusxa uchun. `intl` ixtiyoriy, `gd` kerak emas.
- **Apache yoki LiteSpeed, `.htaccess` ruxsati bilan** (`AllowOverride All`). `mod_rewrite` **majburiy** — usiz `/api/...`
  ishlamaydi; `mod_headers` tavsiya — usiz sayt ishlaydi, lekin CSP va kesh sarlavhalari yuborilmaydi. Nginx orqasidagi
  Apache ham bo'ladi (masud-mamurovich.uz hostingi shunday — `.htaccess` qoidalari bajariladi).
  **Faqat nginx** bo'lgan hosting `.htaccess` ni umuman o'qimaydi: u yerda faqat A joylashuvi va README dagi nginx
  sozlamasi bilan ishlating (sinovda `.htaccess` siz C joylashuvida `.user.ini` va mocklarning transkriptlari ochilib qoldi).
- **HTTPS majburiy**: mikrofon (Speaking), kamera va ekran yozuvi (video nazorat) hamda klaviatura qulfi (lockdown)
  brauzerlarda faqat HTTPS da ishlaydi. Sayt http:// da ochilsa, o'quvchiga «sayt https:// emas» deb aytiladi, admin
  panelda ogohlantirish chiqadi.
- **MySQL 8 / MariaDB 10.x tavsiya etiladi** (50–150 o'quvchi bir vaqtda). SQLite faqat kichik guruh uchun: unda bir vaqtda
  bitta yozuvchi bo'ladi. Sxema `utf8mb4_unicode_ci`, InnoDB; JSON/CHECK/DATETIME ishlatilmagan.
- PHP sozlamalari: paketdagi `public/.user.ini` (PHP-FPM, CGI va LiteSpeed hostinglarda avtomatik o'qiladi)
  `upload_max_filesize` va `post_max_size` = 64M, `max_execution_time` = 60, `memory_limit` = 256M,
  `display_errors` = Off, `log_errors` = On qiladi. Hosting mod_php ishlatsa, shu qiymatlarni panelidagi PHP sozlamalariga
  kiriting. Fayl brauzerdan ochilmaydi (`public/.htaccess` yashirin fayllarni 403 bilan yopadi).

## 2. O'quvchilar uchun talablar

- **Kompyuter yoki noutbuk.** Telefon va Android planshetdan kirilsa: "Bu mockni telefonda ishlab bo'lmaydi. Kompyuter yoki
  noutbukdan kiring." Kerak bo'lsa, mock sozlamasida ruxsat beriladi: **Mocklar → mock → Umumiy → Imtihon xavfsizligi →
  "Telefon va planshetdan ishlashga ruxsat"**. (iPad'dagi Safari odatda o'zini kompyuter deb ko'rsatadi va rad etilmaydi.)
- **Brauzer: Google Chrome yoki Microsoft Edge**, yangi versiyasi. Klaviatura qulfi (Esc, Alt+Tab ni ushlab qolish) faqat
  shularda ishlaydi; Firefox va Safari sinalmagan.
- **Mikrofon.** Speaking oldidan "Mikrofonni tekshirish" bosqichi bor: brauzer ruxsat so'raganda **"Ruxsat berish"** bosilishi
  shart. Rad etilsa: "Mikrofonga ruxsat berilmadi…" — brauzer sozlamalarida mikrofonga ruxsat berib, qayta urinish kerak.
- **Quloqchin.** Kompyuter sinfida har o'quvchiga mikrofonli quloqchin: Listening audiosi va boshqalarning ovozi Speaking
  yozuviga aralashmasligi uchun.
- **Kamera va ekran (video nazorat yoqilgan mocklarda — 10-bo'lim).** Imtihon boshida "Kamerani yoqish" (brauzer ruxsat
  so'raydi → "Ruxsat berish") va "Ekranni ulashish" bosiladi; ochilgan oynada **"Butun ekran" (Entire screen)** tanlanib,
  "Ulashish" bosilishi shart — oyna yoki vkladka tanlansa qabul qilinmaydi. Bu brauzer qoidasi: sahifa ekranni o'zi yoza
  olmaydi. Kamerasi yo'q kompyuterda (mock sozlamasi "Bo'lsa yoziladi" bo'lsa) imtihon davom etadi, natijada
  "kamerasiz" belgisi turadi. Videoga internet kerak: bir o'quvchiga ~0.3 Mbit/s yuklash (upload) — 30 kompyuterli sinfga
  ~8–10 Mbit/s.
- **Internet.** Javoblar serverga avtomatik saqlanadi. Aloqa uzilsa, ekranda "Internet yo'q — javoblar qurilmada
  saqlanmoqda" chiqadi; aloqa tiklangach yuboriladi.
- Sayt faqat `https://` manzili bilan ochilsin. Bitta markazdan ko'p o'quvchi kirsa — 5-bo'limga qarang.

## 3. Joylashuv — uchta variant

**A. Tavsiya: veb-ildiz = `public/`.** Subdomen yaratganda (masalan `mock.masud-mamurovich.uz`) uning document root'ini
`.../mock/public` deb ko'rsating. Kod, baza, kontent va `storage/` veb'dan tashqarida qoladi.

**B. Subdomen ildizini o'zgartirib bo'lmasa:** `public/` ichidagi hamma narsani (`.htaccess` va `.user.ini` bilan — ular
yashirin fayllar!) subdomenning `public_html` papkasiga, qolgan papkalarni — `src`, `config`, `database`, `bin`,
`content`, `storage` — bir daraja yuqoriga, `public_html` bilan yonma-yon qo'ying. `content/` o'rnatishda kerak
(`--content` shu yerdan o'qiydi); keyin uni o'chirsa bo'ladi (audio va rasmlar `storage/uploads` ga nusxalanadi).

**C. Eng oddiy, lekin kamroq tavsiya:** butun paketni veb-ildizga (yoki uning ichidagi papkaga, masalan `/mock/`) oching.
Paketdagi ildiz `.htaccess` so'rovlarni `public/` ga yo'naltiradi va `src`, `config`, `database`, `bin`, `content`,
`storage` ni yopadi (403 yoki 404). Faqat `.htaccess` ishlaydigan serverda (1-bo'lim). `bin/*.php` skriptlari esa
`.htaccess` ishlamay qolsa ham veb orqali hech narsa qilmaydi (404).

## 4. Qadamlar

1. `ingliz-tili-mock.zip` ni hosting fayl menejerida kerakli papkaga yuklab, o'sha yerda oching. Yashirin fayllar
   (`.htaccess`, `public/.htaccess`, `public/.user.ini`) ham ochilganini tekshiring (fayl menejerida "yashirin fayllarni
   ko'rsatish").
2. **Baza.** Hosting panelida MySQL baza + foydalanuvchi yarating (kodlash `utf8mb4`, foydalanuvchiga shu bazada to'liq
   huquq). Kod `CREATE DATABASE` yoki maxsus imtiyoz talab qilmaydi.
3. **Sozlama — o'rnatishdan OLDIN.** `config/config.example.php` dan nusxa olib `config/config.php` yarating va o'zgartiring:

   ```php
   'db' => [
       'driver' => 'mysql',                 // yoki 'sqlite'
       'mysql' => [
           'host' => 'localhost',           // panel boshqa xost ko'rsatsa — o'shani
           'port' => 3306,
           'database' => 'BAZA_NOMI',
           'username' => 'BAZA_FOYDALANUVCHISI',
           'password' => 'BAZA_PAROLI',
           'charset' => 'utf8mb4',
       ],
   ],
   'secure_cookies' => true,                // sayt faqat HTTPS bo'lsa (tavsiya)
   'password_cost' => 10,
   'login_ip_limit' => 300,                 // kompyuter sinfi bo'lsa — 5-bo'lim
   'client_ip_header' => '',                // proksi ortida bo'lsa — 5-bo'lim
   'trusted_proxies' => ['private'],
   'debug' => false,
   ```

   `config/config.php` bo'lmasa API ishlamaydi va "Sozlama fayli topilmadi: config/config.php…" xabarini qaytaradi
   (namunaviy sozlama bilan indamay ishlab ketmaydi). Buyruq qatorida esa "Diqqat: config/config.php topilmadi" deb
   ogohlantiradi — MySQL mo'ljallangan bo'lsa, o'rnatish chiqishida aynan `Jadvallar tayyor (mysql).` bo'lishi kerak.
4. **O'rnatish (bir marta).** Jadvallar, administrator va 3 ta tayyor mock — SSH orqali:

   ```bash
   php /yol/mock/bin/install.php --admin-login=admin --admin-password='KuchliParol123' --content
   ```

   Kutilgan chiqish: `Jadvallar tayyor (mysql).`, `Administrator yaratildi: admin`, keyin
   `✓ mock-01: #1 faollashtirildi — Multilevel Mock 1` (02, 03 ham). 24 MB audio/rasm `storage/uploads` ga nusxalanadi.
   SSH bo'lmasa, xuddi shu buyruqni hosting panelidagi **Cron** ga bir martalik vazifa qilib qo'ying va bajarilgach
   o'chiring. PHP yo'lini to'liq yozing (hostingga qarab `/usr/bin/php`, `/usr/local/bin/php`, `/opt/php83/bin/php` yoki
   `/opt/cpanel/ea-php83/root/usr/bin/php`), skript yo'lini ham to'liq yozing.
   Qayta ishga tushirish xavfsiz: jadvallar `IF NOT EXISTS`, bor mocklar o'tkazib yuboriladi, admin paroli yangilanadi.
5. **Huquqlar.** `storage/` va undagi hamma narsa PHP uchun yozuvchan bo'lsin: baza (SQLite bo'lsa, papkaning o'zi ham),
   audio, rasmlar, Speaking yozuvlari, zaxira nusxalar shu yerda. PHP sizning foydalanuvchingiz nomidan ishlasa (PHP-FPM,
   suPHP, LiteSpeed — ko'p hostinglar) 755 yetarli. mod_php da PHP boshqa foydalanuvchi (`www-data`, `apache`, `nobody`)
   nomidan ishlaydi — u holda `storage/` ga ichidagilari bilan 775 (yordam bermasa 777) bering. Tekshirish: 6-qadamdagi
   fayl yuklash sinovi.
6. **Tekshirish.**
   - `https://SAYT/api/auth/me` → JSON `{"user":null,"csrf":"...","site":{...}}` (mod_rewrite ishlayapti, baza ulangan).
   - `https://SAYT/` — o'quvchi kirish oynasi; `https://SAYT/admin/` — boshqaruv paneli. `curl -I https://SAYT/` javobida
     `Content-Security-Policy` bo'lsa, `mod_headers` ham ishlayapti.
   - Quyidagilar **403 yoki 404** bo'lishi shart: `https://SAYT/.user.ini`, `https://SAYT/config/config.php`,
     `https://SAYT/src/Db.php`, `https://SAYT/storage/`, `https://SAYT/bin/install.php`,
     `https://SAYT/content/mocks/mock-01/audio.json`.
   - Admin panel → **Sozlamalar → Kirish bloklari**: "Server sizni shu manzil bilan ko'rmoqda" qatoridagi IP sizning
     haqiqiy tashqi IP manzilingiz bilan bir xil bo'lishi kerak (Google'da "my ip" deb qidirib solishtiring). Farq qilsa —
     5-bo'lim.
   - Bitta mockni ochib saqlang (PUT so'rovi), **Fayllar** bo'limida rasm yuklab, keyin o'chiring (POST va DELETE).
     Xato bersa — 9-bo'lim.
   - "O'quvchi ko'rinishi"da Listening audiosini ijro eting (Range so'rovlari ishlashi kerak).
7. **Birinchi imtihondan oldin (majburiy).**
   - **Sozlamalar → "O'quvchilar o'zi ro'yxatdan o'ta oladi" belgisini olib tashlang va Saqlang.** O'rnatishdan keyin u
     yoqilgan: istalgan odam telefon raqami bilan hisob ochib, mocklarni ishlab yuborishi mumkin. Faqat rejalashtirilgan
     ro'yxat oynasida yoqing.
   - Admin parolini o'zgartiring (Sozlamalar → Parolni almashtirish), ekspert va o'quvchilarni qo'shing (ro'yxatni
     Excel'dan nusxalash mumkin).
   - Har bir mockning o'z sozlamalari (**Mocklar → mock → Umumiy**): "Qoidabuzarliklar chegarasi" va "Chegaradan oshsa",
     "Har ishni nechta ekspert baholaydi", "Bitta o'quvchi necha marta ishlay oladi", ochilish/yopilish vaqti,
     **"Video nazorat"** (kamera va ekran: o'chiq / bo'lsa yoziladi / majburiy — 10-bo'lim), **"Bo'limni vaqt tugamasdan
     yakunlashga ruxsat"** va **"Speaking: … javobni erta tugatib keyingi savolga o'tish"**.
   - Hosting panelida "Force HTTPS" (HTTP → HTTPS yo'naltirish) ni yoqing — kod o'zi yo'naltirmaydi.
   - Zaxira nusxa uchun cron qo'ying (7-bo'lim).

## 5. Kirish cheklovlari va IP

**Qoidalar (15 daqiqalik oynada):** bitta login + IP uchun 8 ta xato parol; bitta IP'dan jami `login_ip_limit` ta xato
(standart 300); bitta IP'dan `register_ip_limit` ta ro'yxatdan o'tish (standart 100). Chegaradan keyin: "Juda ko'p urinish.
15 daqiqadan keyin qayta urinib ko'ring." Administrator va ekspertga umumiy IP chegarasi qo'llanmaydi (ularning
login + IP chegarasi ishlaydi) — o'quvchilar xatolari tufayli bloklangan markazdan ham admin kirib, blokni ocha oladi.

**Kompyuter sinfi.** Markazdagi barcha kompyuterlar internetga bitta tashqi IP orqali chiqadi: 300 ta xato parol butun
markazni 15 daqiqaga bloklaydi. Imtihon markazda bo'lsa, `config.php` da `'login_ip_limit' => 1000` qiling (yoki `0` —
chegarasiz; login + IP chegarasi baribir qoladi).

**Blokni ochish:** admin panel → **Sozlamalar → Kirish bloklari**: eng ko'p xato qilgan IP'lar ro'yxati, har biri yonida
"Blokni ochish"; "Mening IP'imni ochish"; "Hammasini tozalash". Har amal jurnalga yoziladi. Admin panelga ham kirib
bo'lmasa — 15 daqiqa kuting yoki phpMyAdmin'da: `DELETE FROM login_throttle;`

**Teskari proksi (hosting nginx'i, Cloudflare).** Kirish bloklari kartasida "Server sizni shu manzil bilan ko'rmoqda"
qatorida `127.0.0.1`, `10.…`, `192.168.…` yoki serverning o'z IP'si turgan bo'lsa (sayt internetda bo'la turib), sayt proksi
ortida va **hamma o'quvchi bitta IP** bo'lib ko'rinadi — bitta o'quvchining xatolari hammani bloklaydi. Shunda
`config.php` da:

```php
'client_ip_header' => 'HTTP_X_FORWARDED_FOR',
'trusted_proxies' => ['private'],                 // Cloudflare ortida: ['private', 'cloudflare']
```

Keyin kartani yangilang: yashil "Haqiqiy IP ishonchli proksi qo'shgan … sarlavhasidan olinmoqda" va to'g'ri IP chiqishi
kerak. Sariq "…bu so'rovda ishlatilmadi" chiqsa — kartadagi REMOTE_ADDR manzilini (proksining manzili) `trusted_proxies`
ro'yxatiga qo'shing, masalan `['private', '185.1.2.3']`. Sarlavha faqat ishonchli proksidan kelgan so'rovda o'qiladi va
o'ngdan chapga tekshiriladi, shuning uchun o'quvchi uni soxtalashtirib cheklovni chetlab o'ta olmaydi. Proksi bo'lmasa
(kartada haqiqiy IP chiqsa) sarlavhani **bo'sh qoldiring**. Server markazning ichki tarmog'ida proksi ortida tursa
(o'quvchilar ham ichki tarmoqda), `'private'` o'rniga faqat proksi manzilini yozing, masalan `['127.0.0.1']`.

## 6. Nima QILMASLIK kerak

- `--demo` bilan o'rnatmang: paketda demo fayllari yo'q, buyruq xato bilan to'xtaydi. Repo'dan to'liq nusxa ko'chirsangiz
  ham serverda `--demo` ishlatmang — u `expert / expert123` va `+998900000001 / student123` hisoblarini yaratadi.
- `config/config.php` da `'debug' => true` qoldirmang — API xatoliklarida fayl nomi va qator raqami ko'rinadi.
- `config/config.php` ni git'ga yoki paketga qo'shmang (unda baza paroli bor).
- `content/mocks/*/mock.php` va `audio.json` da javob kalitlari va transkriptlar bor — B/C joylashuvda ular veb'dan
  ochilmasligini (403/404) tekshiring (4-bo'lim, 6-qadam).
- Paketni yangilashda `storage/` va `config/config.php` ustidan yozmang.

## 7. Zaxira nusxa

```bash
php /yol/mock/bin/backup.php --no-uploads --keep=14   # faqat baza → storage/backups/backup-db-<sana>.zip
php /yol/mock/bin/backup.php --keep=2                 # baza + barcha fayllar → storage/backups/backup-<sana>.zip
```

Ikki tur bir-biridan alohida saqlanadi: `--keep` — shu turdagi eng yangi nusxalar soni, eskilari o'chiriladi.
Tavsiya etilgan cron (PHP va skript yo'lini to'liq yozing):

```
30 3 * * *  /usr/bin/php /yol/mock/bin/backup.php --no-uploads --keep=14     # har kecha, faqat baza
45 3 * * 0  /usr/bin/php /yol/mock/bin/backup.php --keep=2                   # har yakshanba, to'liq
```

- **Disk.** Har tugallangan urinish taxminan 1–2 MB Speaking yozuvi qoldiradi (o'zi o'chmaydi); to'liq nusxa ularning
  hammasini o'z ichiga oladi. Shuning uchun to'liq nusxa kam saqlanadi (`--keep=2`), baza nusxasi esa kichik.
- **SQLite**: `VACUUM INTO` — imtihon davomida ham xavfsiz, qo'shimcha dastur kerak emas.
- **MySQL/MariaDB**: `mysqldump` (yoki `mariadb-dump`) PATH'da va odatiy papkalarda qidiriladi. Topilmasa, ishlamasa yoki
  hostingda `exec()` o'chiq bo'lsa, skript "Eslatma: … — baza PHP orqali eksport qilinadi" deydi va bazani PHP'ning o'zi
  bitta izchil tranzaksiyada `dump.sql` ga yozadi. Ikkala usul ham sinaldi: qayta yuklangan baza aslidan farq qilmaydi.
- Server ichidagi nusxa server yo'qolsa yordam bermaydi: haftada bir marta `storage/backups/` dagi oxirgi to'liq nusxani
  o'z kompyuteringizga yuklab oling. Hosting panelining o'z zaxirasini ham yoqib qo'ying.
- **Tiklash:** ZIP'ni oching; `database.sqlite` ni `sqlite_path` joyiga, yoki `dump.sql` ni phpMyAdmin (Import) yoki
  `mysql BAZA < dump.sql` orqali bazaga; `uploads/` ni `storage/` ichiga qo'ying. MariaDB'ning yangi `mysqldump` i faylning
  birinchi qatoriga `/*M!999999\- enable the sandbox mode */` yozadi — MySQL yoki phpMyAdmin shu qatorda xato bersa,
  uni o'chirib qayta import qiling.

## 8. Yangilash

Repo yangilanganda: lokal klonda `git pull`, so'ng repo papkasida `python deploy/build.py` — yangi zip repo yonida paydo
bo'ladi (`MANIFEST.txt` da commit). Serverda yangi zip'ni ochib, eski fayllar ustidan yozing — **`config/config.php` va
`storage/` dan tashqari**. Keyin `https://SAYT/api/auth/me` va admin panelni tekshiring; brauzer eski JS ni ko'rsatsa,
Ctrl+F5. Yangi tayyor mocklar bo'lsa: `php bin/seed-content.php`.
**Yangi jadvallar o'zi yaratiladi:** yangilangan saytga birinchi so'rov kelganda yetishmayotgan jadvallar
(`CREATE TABLE IF NOT EXISTS` — mavjudlariga tegilmaydi) yaratiladi va `storage/schema.v2` belgisi yoziladi. Bu
versiyada yangi jadval — `recordings` (video yozuvlar); buyruq qatori shart emas. Mavjud jadvalga ustun qo'shilganda esa
`ALTER TABLE` ni phpMyAdmin'da qo'lda bajarish kerak bo'ladi (joriy versiyada bunday o'zgarish yo'q).
`config.php` ga yangi kalitlar (`login_ip_limit`, `register_ip_limit`, `trusted_proxies`, `telegram`) qo'shilmasa ham
ishlaydi — standart qiymatlar olinadi (Telegram'siz videolar faqat serverda saqlanadi).
**Bu versiyadan keyin** `public/.htaccess` ham yangilanishi shart: unda kamera va ekranni ulashishga ruxsat
(`Permissions-Policy: camera=(self), display-capture=(self)…`) bor — eski fayl qolsa, brauzer kamerani bloklaydi.

## 9. Muammolar

| Belgisi | Sababi / yechimi |
| --- | --- |
| Butun sayt 500 beradi | `public/.htaccess` dagi `Options -Indexes` ga ruxsat yo'q (`AllowOverride` da `Options` yo'q). Shu qatorni o'chiring — `public/` da `index.html` bor |
| `/api/...` 404 | `mod_rewrite` o'chiq yoki `public/.htaccess` yuklanmagan (yashirin fayl!) |
| "Sozlama fayli topilmadi: config/config.php" | 4-bo'lim, 3-qadam: `config/config.php` ni yarating |
| "Serverda xatolik yuz berdi" (500) | Sababi PHP xatolar jurnalida, `[mock]` bilan boshlanadigan qatorda: hosting panelidagi "Error log" yoki `.user.ini` dagi `error_log` fayli (namunasi faylda). Ko'pincha baza ma'lumotlari noto'g'ri yoki `storage/` ga yozib bo'lmaydi |
| Admin panel "Yuklanmoqda…" da qoladi | Brauzer konsolini (F12) oching: API 500 bo'lsa — yuqoridagi qator |
| Kirishda doim "Sahifa eskirgan…" | PHP sessiyasi saqlanmayapti (hostingdagi sessiya papkasi yozilmaydi). `storage/sessions` papkasini yarating va `.user.ini` dagi `session.save_path` qatorini yoqing |
| Hamma o'quvchi "Juda ko'p urinish…" oladi | 5-bo'lim: bloklarni "Kirish bloklari"da oching; `login_ip_limit` ni oshiring; proksi ortida bo'lsa `client_ip_header` ni sozlang |
| Bitta o'quvchi "Juda ko'p urinish…" oladi | 8 marta xato parol: 15 daqiqa kuting yoki uning parolini yangilab, "Kirish bloklari"da IP'ni oching |
| `curl -I` da CSP yo'q | `mod_headers` yo'q — sayt ishlaydi, hostingdan yoqishni so'rang; yangilashdan keyin brauzerda Ctrl+F5 |
| Mockni saqlash/o'chirish ishlamaydi, qolgani ishlaydi | Hosting PUT/DELETE metodlarini bloklagan (ModSecurity) — hostingdan subdomen uchun ochishni so'rang |
| Audio yoki rasm yuklanmaydi (admin) | `upload_max_filesize` / `post_max_size` kichik (`.user.ini` o'qilmayapti — panelda 64M qiling); "413 Request Entity Too Large" — hosting nginx'ining `client_max_body_size` chegarasi, hostingdan oshirishni so'rang; aks holda `storage/` huquqlari (4-bo'lim, 5-qadam) |
| Mikrofon, kamera yoki ekran ishlamaydi («sayt https:// emas»; natijada «kamerasiz/ekransiz» — sababi «sayt HTTPS emas») | Sayt HTTP da ochilgan — SSL sertifikatini (Let's Encrypt) o'rnating va "Force HTTPS" ni yoqing; yoki brauzerda mikrofon/kameraga ruxsat berilmagan |
| "Bu mockni telefonda ishlab bo'lmaydi…" | Telefon/Android planshet; ruxsat berish — 2-bo'lim |
| Kirish sekin (imtihon boshida) | Parol xeshi ataylab sekin (`password_cost`); o'quvchilarni 5–10 daqiqa oralig'ida kiriting |
| Brauzer konsolida `419 Page Expired` | Sessiya muddati o'tgan (12 soat) yoki boshqa oynada chiqilgan — sahifani yangilang. Xatolik emas |
| Ismlar bo'yicha qidiruv kirillcha katta/kichik harfga sezgir | Faqat SQLite'da shunday; MySQL'da qidiruv registrga bog'liq emas |
| "Kameraga ruxsat berilmadi" hammada | Sayt HTTP da ochilgan; yoki serverda eski `public/.htaccess` (`camera=()`) qolgan — yangisini yuklang (8-bo'lim); yoki brauzerda kamera bloklangan (manzil satridagi belgi) |
| "Ekranni ulashish" oynasi chiqmaydi | Chrome/Edge emas, yoki HTTP; Safe Exam Browser rejimida ekran yozilmaydi |
| Video bo'laklari "413" bilan qaytadi | Hosting nginx'ining `client_max_body_size` 16 MB dan kichik — hostingdan oshirishni so'rang |
| Telegram: "ulanib bo'lmadi… timeout" | Server Rossiyada — `api.telegram.org` bloklangan; 10-bo'lim, relay yoki proxy |
| Telegram: "chat not found" yoki "not enough rights" | Bot kanalga qo'shilmagan yoki administrator emas; `chat_id` noto'g'ri (`-100…` bilan boshlanishi kerak) |
| Admin panelda "cron ishlamayapti shekilli" | `bin/recordings.php` cron'i qo'yilmagan (10-bo'lim); vaqtincha "Navbatni hozir yuborish" tugmasi |

## 10. Video nazorat (kamera va ekran) va Telegram kanal

**Nima yoziladi.** Listening, Reading va Writing davomida — o'quvchi ekrani va kamerasi bitta videoda (ekran 1280×720
gacha, kamera pastki o'ng burchakda, ustida nomzod kodi, bo'lim va vaqt). Speaking'da — kamera va ovoz. Video
10 daqiqalik fayllarga bo'linadi (sozlanadi) va har 15 soniyada bo'lak-bo'lak serverga yuboriladi: internet uzilsa
brauzer kutadi, sahifa yangilansa yozuv yangi faylda davom etadi (oxirgi ~15 soniya yo'qolishi mumkin). Har mock uchun alohida yoqiladi
(**Mocklar → mock → Umumiy → Video nazorat**): kamera va ekran — "O'chiq", "Bo'lsa yoziladi" (rad etsa yoki kamera yo'q
bo'lsa imtihon davom etadi, natijalar jadvalida "kamerasiz"/"ekransiz" belgisi turadi — bo'lim boshlanganda yoki bo'lim
davomida qurilma ishlamagan bo'lsa) yoki "Majburiy" (ishlamasa boshlash tugmasi ochilmaydi; ekran ulashishni to'xtatish
va kamerani uzish qoidabuzarlik hisoblanadi). Eslatma: qoidalar sahifasida berilgan vaqt (odatda 30 daqiqa) tugaguncha
kutib qolgan o'quvchining birinchi bo'limi "Majburiy" bo'lsa ham o'zi boshlanadi (vaqt bilan o'ynashning oldini olish
uchun) — bunda natijada "kamerasiz"/"ekransiz" belgisi turadi. Speaking'da faqat kamera so'raladi. **Standart — "Bo'lsa yoziladi", eski
mocklarda ham:** yangilanishdan keyin barcha imtihonlarda kamera va ekran so'raladi; kerak bo'lmagan mockda "O'chiq"
qiling. Ekranni ulashish to'xtatilsa — ogohlantirish va "Ekranni qayta ulashish" tugmasi chiqadi; sahifa yangilansa
(tanaffusda ham) kamera va ekran qayta so'raladi. **Faqat HTTPS saytda ishlaydi:** sayt http:// da ochilsa, brauzer
kamera va ekranga umuman ruxsat bermaydi — o'quvchiga sababi aytiladi, "Bo'lsa yoziladi" rejimida imtihon videosiz
davom etadi (belgi ustida «sayt HTTPS emas»), "Majburiy" rejimida boshlash tugmasi ochilmaydi.

**Qayerga ketadi.** Server videolarni navbat bilan Telegram kanalga yuboradi (yozuv ostida: mock, o'quvchi, kod,
bo'lim, vaqt, belgilar). **Yozma qism videolari kanalga yuborilgach serverdan o'chiriladi; Speaking videolari serverda
ham qoladi.** Admin panelda (urinish sahifasi → "Video yozuvlar") har fayl holati, "Ko'rish", "Yuklab olish" va kanal
xabariga "Telegram" havolasi bor. Telegram sozlanmagan yoki ishlamasa, videolar serverda turadi va
`rec_keep_days` kundan (standart 30) keyin o'chiriladi. Hosting diski to'lib sayt ishdan chiqmasligi uchun
Telegram'ni kutayotgan videolarga ajratilgan joy cheklangan (**"Yuborilmagan videolar uchun joy"**, standart
10 240 MB): to'lsa, yangi yozuv boshlanmaydi va imtihon videosiz davom etadi (admin panelda ogohlantirish chiqadi;
videolar Telegram'ga ketib joy bo'shagach yozuv o'zi qayta boshlanadi). Hosting tarifingizdagi disk hajmiga qarab
o'zgartiring. Serverda qoladigan Speaking videolari bu chegaraga kirmaydi — ularni "Speaking videolari (kun)" boshqaradi.
Internet uzilsa brauzer videoni xotirada saqlab turadi (taxminan 1 soatgacha) va aloqa tiklangach yuboradi.

**Hajm.** 250 kbit/s (standart) ≈ 1.9 MB/daqiqa: yozma qism (~2 soat 45 daqiqa) ≈ 300 MB, Speaking ≈ 20–30 MB bir
o'quvchiga. 100 o'quvchi bir vaqtda ≈ 25 Mbit/s serverga kirish va Telegram'ga chiqish. Telegram cheklovlari: bitta fayl
50 MB gacha (10 daqiqalik fayl ≈ 19 MB), bitta kanalga daqiqasiga 20 tagacha xabar — navbat shunga moslab yuboradi
(100 o'quvchida ham imtihon davomida yetib boradi, ko'p bo'lsa imtihondan keyin tugatadi). Sifat va saqlash muddati:
**Sozlamalar → Video yozuvlar va Telegram**.

### Telegram'ni sozlash

1. Telegram'da **@BotFather** → `/newbot` → bot nomi → **token** (`123456789:AA…`). Tokenni hech kimga yubormang.
2. **Yopiq kanal** yarating (masalan "Mock — video nazorat"), botni kanalga **administrator** qilib qo'shing
   ("Xabar joylash" huquqi bilan). Speaking uchun alohida kanal kerak bo'lsa — ikkinchisini ham.
3. Kanal ID'si: kompyuterda **web.telegram.org/a** da kanalni oching — manzil satrining oxirida `#-100…` raqami ko'rinadi
   (`-100` bilan birga oling). Yoki kanaldagi xabarni @JsonDumpBot ga forward qiling (`forward_from_chat.id`).
   Ochiq kanal bo'lsa `@kanal_nomi` ham bo'ladi.
4. `config/config.php` ga:

   ```php
   'telegram' => [
       'bot_token' => '123456789:AA…',
       'chat_id' => '-1001234567890',
       'speaking_chat_id' => '',        // ixtiyoriy, Speaking uchun alohida kanal
       'api_base' => '',                // relay bo'lsa: 'https://tg.sizning-domen.uz'
       'proxy' => '',                   // yoki: 'socks5h://user:parol@host:1080'
   ],
   ```

5. **Cron (har daqiqa)** — hosting panelida (ispmanager → «Планировщик CRON»): PHP va skript yo'lini to'liq yozing,
   masalan reg.ru'da:

   ```
   * * * * *  /opt/php/8.3/bin/php /var/www/FOYDALANUVCHI/data/www/SAYT/bin/recordings.php
   ```

   U uzilib qolgan yozuvlarni yig'adi, muddati o'tganlarini o'chiradi va navbatni ~50 soniya davomida yuboradi.
   Bir vaqtda faqat bitta nusxasi ishlaydi. Cron bo'lmasa — admin panelda "Navbatni hozir yuborish" tugmasi.
6. Tekshirish: **Sozlamalar → Video yozuvlar va Telegram → "Sinov xabari"** — kanal(lar)da "✅ … Telegram ulanishi
   ishlayapti" chiqishi kerak. Yoki SSH'da: `php bin/recordings.php --test` va `php bin/recordings.php --status`.

### Server Rossiyada bo'lsa (reg.ru va boshqalar)

2026-yil martidan Rossiyadagi hosting serverlaridan `api.telegram.org` ga ulanish bloklangan (botlar "timeout" beradi).
Bunday hostingda "Sinov xabari" `Telegram'ga ulanib bo'lmadi… timeout` deydi. Yechimlar:

- **Relay** — Rossiyadan tashqaridagi kichik VPS'da (O'zbekiston, Yevropa) teskari proksi; `config.php` da
  `'api_base' => 'https://tg.sizning-domen.uz'`. Caddy bilan (avtomatik HTTPS):

  ```
  tg.sizning-domen.uz {
      @begona not remote_ip SAYT_SERVERI_IP
      respond @begona 403
      request_body {
          max_size 60MB
      }
      reverse_proxy https://api.telegram.org {
          header_up Host api.telegram.org
      }
  }
  ```

  `SAYT_SERVERI_IP` — sayt serverining IP manzili (relay'dan faqat u foydalansin). Videolar va token relay orqali o'tadi,
  shuning uchun VPS sizniki bo'lsin.
- **Proxy** — tashqaridagi SOCKS5/HTTP proxy: `'proxy' => 'socks5h://user:parol@host:1080'`.
- **Hostingni O'zbekistondagi serverga ko'chirish** — Telegram to'g'ridan-to'g'ri ishlaydi; qo'shimcha afzalligi:
  o'quvchilarning shaxsiy ma'lumotlari (ism, telefon, video) O'zbekiston hududidagi serverda saqlanadi.

Relay sozlanguncha videolar serverda navbatda turadi va admin panelda ko'rinadi; Telegram ishlay boshlagach
navbat o'zi yuboriladi.

### Maxfiylik

- O'quvchilarga imtihondan oldin video yozilishini ayting — imtihon qoidalarida va boshlash oynasida bu yozilgan.
- Kanal **yopiq** bo'lsin, unga faqat tekshiruvchilarni qo'shing; havolani tarqatmang. Kanal xabarlarida o'quvchi ismi
  va kodi bor (telefon raqami yo'q).
- Voyaga yetmagan o'quvchilar bo'lsa, ota-onasining roziligi haqida markaz qoidasini belgilang.

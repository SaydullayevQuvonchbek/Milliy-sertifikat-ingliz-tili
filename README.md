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
| Server taymeri | Vaqt serverda hisoblanadi; sahifani yangilash yoki kompyuterni qayta yoqish taymerni to'xtatmaydi. Muddat o'tsa, bo'lim avtomatik yopiladi |
| Natijalar | 4 ko'nikma balli (75 ballik), umumiy ball va daraja (B1/B2/C1), qismlar bo'yicha to'g'ri javoblar, ekspert izohlari. To'g'ri javoblar kaliti o'quvchiga ko'rsatilmaydi (2-urinish uchun sir qoladi) |

### Imtihon xavfsizligi (lockdown)

- To'liq ekran rejimi majburiy; undan chiqilsa, ekran bloklanadi va qoidabuzarlik yoziladi.
- Boshqa oyna yoki dasturga o'tish, sahifani uzoq muddat (20 soniyadan ortiq) yopib qo'yish — qoidabuzarlik.
- Nusxa olish, kesish, joylashtirish, fayl tashlash, sichqonchaning o'ng tugmasi, chop etish va klaviatura yorliqlari (Ctrl+C/V/S/P/U/F, F12, F5 va boshqalar) o'chirilgan. Chrome va Edge'da to'liq ekran rejimida **Keyboard Lock API** ham yoqiladi: Esc va ayrim tizim tugmalari sahifada ushlab qolinadi.
- Bitta imtihon — bitta oyna: ikkinchi oyna yoki qurilmada ochilsa, birinchisi yopiladi va bu qoidabuzarlik sifatida yoziladi.
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
- **Urinish tafsiloti.** Har savolga berilgan javob, Writing matnlari, Speaking yozuvlari, ekspert baholari va hodisalar jurnali.
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
php bin/install.php --admin-login=admin --admin-password=KuchliParol123 --demo
php -S 127.0.0.1:8080 -t public bin/dev-router.php
```

- O'quvchi sahifasi: http://127.0.0.1:8080/
- Boshqaruv paneli: http://127.0.0.1:8080/admin/

`--demo` ikki namunaviy mock (to'liq format va 5–10 daqiqalik qisqa mock), ekspert (`expert` / `expert123`) va o'quvchi
(`+998900000001` / `student123`) hisoblarini qo'shadi. Namunadagi audio — oddiy signal ovozlari, rasmlar — shartli rasmlar;
transkriptlar admin panelda turibdi, ular asosida haqiqiy audio yozib almashtiring.

### Hostingga joylash (Apache, masalan reg.ru)

1. Barcha fayllarni serverga yuklang. **Veb-ildiz (document root) `public/` papkasi bo'lishi kerak.** Agar hostingda
   veb-ildizni o'zgartirib bo'lmasa, `public/` ichidagini `public_html/` ga, qolgan papkalarni (`src`, `config`, `database`,
   `bin`, `storage`) bir daraja yuqoriga joylang.
2. `config/config.php` yarating (`config.example.php` dan nusxa): baza turi, MySQL ma'lumotlari, `storage_path`.
3. `php bin/install.php --admin-login=admin --admin-password=...` ni bir marta ishga tushiring (SSH yoki hosting panelidagi cron orqali).
4. `storage/` papkasiga yozish huquqi bering. U veb-ildizdan tashqarida bo'lsin (ichida bo'lsa, `storage/.htaccess` kirishni taqiqlaydi).
5. PHP sozlamalari: `upload_max_filesize = 64M`, `post_max_size = 64M` (Listening audiolari uchun), `max_execution_time = 60`.

Ko'p o'quvchi bir vaqtda ishlaydigan server uchun MySQL tavsiya etiladi (`'driver' => 'mysql'`). SQLite kichik markaz
uchun (taxminan bir necha o'nlab bir vaqtdagi o'quvchi) yetarli.

### Nginx

```nginx
root /var/www/mock/public;
index index.html;
location /api/ { rewrite ^/api/(.*)$ /api.php?route=$1 last; }
location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
location / { try_files $uri $uri/ =404; }
client_max_body_size 64m;
```

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

## Testlar

```bash
php tests/php/run.php            # 45 ta PHP testi: baholash, Rasch, urinishlar chegarasi, muzlatish, taymerlar, ekspertlar
node --test tests/js/*.test.mjs  # JS testlari: Listening vaqt jadvali (server bilan bir xil), so'z sanash
npm install && npm run test:e2e  # brauzerda to'liq ssenariy (Playwright): o'quvchi, admin va ekspert
```

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
bin/                    o'rnatish, namunaviy ma'lumotlar, lokal server marshrutlovchisi
demo/                   namunaviy mocklar
tests/                  PHP, JS va E2E testlar
storage/                baza, audio, rasmlar, Speaking yozuvlari (git'ga qo'shilmaydi)
```

## Keyingi bosqichlar (rejadan)

- Markaz (qog'oz) rejimi: Listening/Reading titullari va OMR skaner, Writing sahifalarini rasmga olish.
- Speaking uchun Telegram bot orqali voice xabar bilan topshirish (zaxira kanal).
- Sertifikat va QR orqali tekshirish, apellyatsiya.
- To'lov (kirish kodlari yoki Telegram Stars).

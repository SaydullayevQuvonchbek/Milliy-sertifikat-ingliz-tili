<?php

// Nusxa oling: config/config.php — va o'z qiymatlaringizni yozing.
// config/config.php git'ga qo'shilmaydi.

return [
    'app_name' => 'Multilevel Mock',

    'db' => [
        // 'sqlite' — tez ishga tushirish va kichik markazlar uchun.
        // 'mysql' — ko'p o'quvchi bir vaqtda ishlaydigan server uchun tavsiya etiladi.
        'driver' => 'sqlite',
        'sqlite_path' => __DIR__ . '/../storage/database.sqlite',
        'mysql' => [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'multilevel_mock',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
    ],

    // Yuklangan audio, rasm va Speaking yozuvlari shu yerda saqlanadi.
    // Iloji bo'lsa, veb-ildiz (public) papkasidan tashqarida bo'lsin.
    'storage_path' => __DIR__ . '/../storage',

    'session_name' => 'mlmock_sid',
    // true — faqat HTTPS; false — HTTP ham; 'auto' — so'rovga qarab.
    'secure_cookies' => 'auto',

    // Parol xeshi narxi (bcrypt cost, 10–14). Kattasi xavfsizroq, lekin imtihon boshida ommaviy kirishni sekinlashtiradi.
    'password_cost' => 10,

    'timezone' => 'Asia/Tashkent',

    // Kirish cheklovlari (15 daqiqalik oynada). Har bir login+IP — 8 ta xato urinish (o'zgarmas).
    // Bitta IP'dan jami xato kirishlar: 'login_ip_limit' (0 — chegara yo'q). Markazdagi barcha kompyuterlar bitta
    // tashqi IP orqali chiqadi: kompyuter sinfida imtihon bo'lsa oshiring (masalan 1000) yoki 0 qiling.
    // Administrator va ekspertga bu umumiy chegara qo'llanmaydi; bloklarni "Sozlamalar → Kirish bloklari" ochadi.
    'login_ip_limit' => 300,
    // Bitta IP'dan 15 daqiqada o'zi ro'yxatdan o'tishlar soni (0 — chegara yo'q).
    'register_ip_limit' => 100,

    // Sayt teskari proksi (hosting nginx'i, Cloudflare) ortida bo'lsa va hamma bitta IP bo'lib ko'rinsa,
    // haqiqiy IP shu sarlavhadan olinadi — odatda 'HTTP_X_FORWARDED_FOR'. Sarlavha faqat so'rov
    // 'trusted_proxies' dagi manzildan kelganda o'qiladi va o'ngdan chapga tekshiriladi, shuning uchun mijoz uni
    // soxtalashtira olmaydi. Bo'sh qoldirilsa — REMOTE_ADDR (eng xavfsiz; proksi bo'lmasa shunday qoldiring).
    'client_ip_header' => '',
    // Ishonchli proksilar: IP yoki CIDR, hamda kalit so'zlar 'private' (127.0.0.0/8, 10/8, 172.16/12, 192.168/16,
    // ::1, fc00::/7 — shu hostdagi yoki ichki tarmoqdagi proksi) va 'cloudflare' (Cloudflare manzillari).
    // Masalan: ['private'] yoki ['private', 'cloudflare']. Server markazning ichki tarmog'ida proksi ortida tursa
    // (o'quvchilar ham ichki tarmoqda), 'private' o'rniga faqat proksining aniq manzilini yozing, masalan ['127.0.0.1'] —
    // aks holda o'quvchi sarlavhani soxtalashtira oladi.
    'trusted_proxies' => ['private'],

    // Video nazorat (ekran + kamera) yozuvlarini Telegram kanalga yuborish. Bo'sh qoldirilsa, yozuvlar faqat serverda
    // saqlanadi (admin panelda ko'rinadi). Sozlash: @BotFather'da bot yarating; yopiq kanal oching va botni kanalga
    // administrator qilib qo'shing (xabar yuborish huquqi bilan); kanal ID'si -100 bilan boshlanadi.
    // Navbatni cron yuboradi: har daqiqada `php bin/recordings.php` (yo'riqnoma: deploy/SERVERGA-JOYLASH.md).
    'telegram' => [
        'bot_token' => '',
        'chat_id' => '',            // masalan '-1001234567890'
        'speaking_chat_id' => '',   // ixtiyoriy: Speaking videolari uchun alohida kanal (bo'sh — chat_id)
        // Server Rossiyada bo'lsa (masalan, reg.ru), api.telegram.org bloklangan: chet eldagi relay manzili
        // (https://relay.example.com — so'rovni api.telegram.org ga uzatadi) yoki proxy (socks5h://user:pass@host:1080).
        'api_base' => '',
        'proxy' => '',
    ],

    // Xatoliklar tafsilotini javobda ko'rsatish (faqat ishlab chiqishda yoqing).
    'debug' => false,
];

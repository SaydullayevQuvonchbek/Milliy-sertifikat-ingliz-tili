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

    // Sayt teskari proksi (Cloudflare, Nginx) ortida bo'lsa, haqiqiy IP shu sarlavhadan olinadi
    // (masalan 'HTTP_CF_CONNECTING_IP' yoki 'HTTP_X_FORWARDED_FOR'). Faqat proksi ishonchli bo'lsa yoqing.
    'client_ip_header' => '',

    // Xatoliklar tafsilotini javobda ko'rsatish (faqat ishlab chiqishda yoqing).
    'debug' => false,
];

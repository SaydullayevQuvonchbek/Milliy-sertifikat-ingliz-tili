# content/ — tayyor kontentli mocklar

Har bir mock — `content/mocks/<slug>/` papkasi. Bazaga joylash: `php bin/seed-content.php` (yoki `bin/install.php --content`).

```
content/mocks/mock-01/
  mock.php          savollar, matnlar, javoblar (demo/mock-full.php bilan bir xil format)
  audio.json        barcha audio treklarning skripti (Listening + Speaking savollari ovozi) — TTS manbasi
  audio/*.mp3       tools/synth.py yaratadi (git'ga qo'shiladi)
  audio/manifest.json   treklar davomiyligi va xeshi (seeder shundan o'qiydi)
  images/*.svg      Speaking rasmlari manbasi
  images/*.png      tools/render_images.py yaratadi (git'ga qo'shiladi)
```

## Audio yaratish

Ovoz — Kokoro (ochiq neyron TTS, Apache-2.0). Model fayllari repoga kirmaydi:

```bash
mkdir -p /tmp/claude-0/tts && cd /tmp/claude-0/tts
curl -LO https://github.com/thewh1teagle/kokoro-onnx/releases/download/model-files-v1.0/kokoro-v1.0.onnx
curl -LO https://github.com/thewh1teagle/kokoro-onnx/releases/download/model-files-v1.0/voices-v1.0.bin
pip install kokoro-onnx soundfile pillow cairosvg numpy      # + ffmpeg, espeak-ng
python3 content/tools/synth.py content/mocks/mock-01 --threads 2      # o'zgarmagan treklar o'tkazib yuboriladi
python3 content/tools/render_images.py content/mocks/mock-01
```

Tayyor `audio/*.mp3` va `images/*.png` repoda bor — oddiy o'rnatish uchun Kokoro kerak emas.

## Tekshirish

```bash
php bin/check-content.php mock-01     # vaqtinchalik bazada joylaydi, tekshiradi; 0 = xato ham, ogohlantirish ham yo'q
```

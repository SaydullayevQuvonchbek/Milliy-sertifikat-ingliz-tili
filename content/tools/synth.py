#!/usr/bin/env python3
"""Mock audio sintezi (Kokoro TTS, ochiq litsenziya, oflayn ishlaydi).

    python3 content/tools/synth.py content/mocks/<slug> [--only l1_1,l2] [--force] [--threads 2]

`<slug>/audio.json` dagi har bir trek uchun `<slug>/audio/<trek>.mp3` yaratadi va
`<slug>/audio/manifest.json` ga davomiylikni yozadi (seeder shu fayldan o'qiydi).
O'zgarmagan treklar qayta sintez qilinmaydi (spetsifikatsiya xeshi bo'yicha).

audio.json tuzilmasi:
{
  "defaults": {"speed": 0.95, "turn_gap": 0.5, "lead": 0.5, "tail": 0.7},
  "voices": {"M": "am_michael", "W": "af_heart", "N": "bf_emma"},       # umumiy ovozlar
  "tracks": {
    "l1_1": {
      "label": "Conversation 1",
      "voices": {"M": "bm_george", "W": "bf_emma"},                     # ixtiyoriy: shu trek uchun
      "speed": 0.95,                                                    # ixtiyoriy
      "script": [
        ["W", "Good morning. Can I help you?"],
        ["pause", 0.6],
        ["M", "Yes, I'd like a ticket to Samarkand."]
      ]
    }
  }
}

Matn qoidalari (talaffuz to'g'ri chiqishi uchun): vaqt va raqamlarni so'z bilan yozing ("seven thirty",
"fourteenth of March"); telefon raqami — "oh seven, six two, four"; harflab aytish — "S, M, I, T, H";
pul — "twelve pounds fifty". Bo'sh qator (\\n\\n) — uzunroq pauza.
"""
from __future__ import annotations

import argparse
import hashlib
import io
import json
import re
import subprocess
import sys
from pathlib import Path

SYNTH_VERSION = "1"
SAMPLE_RATE = 24000
MODEL_DIR = Path("/tmp/claude-0/tts")


def load_engine(threads: int):
    import numpy as np  # noqa: F401
    import onnxruntime as rt
    from kokoro_onnx import Kokoro

    model = MODEL_DIR / "kokoro-v1.0.onnx"
    voices = MODEL_DIR / "voices-v1.0.bin"
    if not model.exists() or not voices.exists():
        sys.exit(
            f"Kokoro model topilmadi ({MODEL_DIR}). Yuklab oling:\n"
            "  https://github.com/thewh1teagle/kokoro-onnx/releases/download/model-files-v1.0/kokoro-v1.0.onnx\n"
            "  https://github.com/thewh1teagle/kokoro-onnx/releases/download/model-files-v1.0/voices-v1.0.bin"
        )
    options = rt.SessionOptions()
    options.intra_op_num_threads = threads
    options.inter_op_num_threads = 1
    session = rt.InferenceSession(str(model), sess_options=options, providers=["CPUExecutionProvider"])
    session._model_path = str(model)  # kokoro-onnx shuni kutadi
    return Kokoro.from_session(session, str(voices))


def split_sentences(text: str) -> list[tuple[str, float]]:
    """Matnni gaplarga ajratadi; har gap uchun undan keyingi pauza (soniya)."""
    out: list[tuple[str, float]] = []
    for para_index, paragraph in enumerate(re.split(r"\n\s*\n", text.strip())):
        sentences = [s.strip() for s in re.split(r"(?<=[.!?…])\s+(?=[A-Z\"'“‘(\[0-9])", paragraph.replace("\n", " ")) if s.strip()]
        for i, sentence in enumerate(sentences):
            last = i == len(sentences) - 1
            if last:
                pause = 0.75
            elif sentence.endswith("?"):
                pause = 0.4
            elif sentence.endswith(":"):
                pause = 0.45
            else:
                pause = 0.3
            out.append((sentence, pause))
    return out


def track_hash(spec: dict, voices: dict, defaults: dict) -> str:
    blob = json.dumps({"spec": spec, "voices": voices, "defaults": defaults, "v": SYNTH_VERSION}, sort_keys=True, ensure_ascii=False)
    return hashlib.sha1(blob.encode("utf-8")).hexdigest()


def render_track(engine, spec: dict, voices: dict, defaults: dict):
    import numpy as np

    speed_default = float(spec.get("speed", defaults.get("speed", 0.95)))
    turn_gap = float(spec.get("turn_gap", defaults.get("turn_gap", 0.5)))
    lead = float(spec.get("lead", defaults.get("lead", 0.5)))
    tail = float(spec.get("tail", defaults.get("tail", 0.7)))

    def silence(seconds: float):
        return np.zeros(int(SAMPLE_RATE * max(0.0, seconds)), dtype=np.float32)

    chunks = [silence(lead)]
    previous_speaker = None
    for item in spec["script"]:
        if isinstance(item, dict):
            item = [item.get("s") or item.get("speaker"), item.get("t") or item.get("text") or item.get("pause")]
        who, payload = item[0], item[1]
        if who == "pause":
            chunks.append(silence(float(payload)))
            previous_speaker = None
            continue
        if who not in voices:
            raise KeyError(f"'{who}' uchun ovoz belgilanmagan (voices ichida yo'q)")
        voice = voices[who]
        lang = "en-gb" if voice.startswith("b") else "en-us"
        speed = float(item[2]) if len(item) > 2 else speed_default
        if previous_speaker is not None and previous_speaker != who:
            chunks.append(silence(turn_gap))
        previous_speaker = who
        for sentence, pause in split_sentences(str(payload)):
            samples, rate = engine.create(sentence, voice=voice, speed=speed, lang=lang)
            assert rate == SAMPLE_RATE, rate
            chunks.append(samples.astype(np.float32))
            chunks.append(silence(pause))
    chunks.append(silence(tail))
    return np.concatenate(chunks)


def encode_mp3(samples, out_path: Path) -> None:
    import soundfile as sf

    buf = io.BytesIO()
    sf.write(buf, samples, SAMPLE_RATE, format="WAV", subtype="PCM_16")
    cmd = [
        "ffmpeg", "-y", "-loglevel", "error", "-f", "wav", "-i", "pipe:0",
        "-af", "loudnorm=I=-19:TP=-2:LRA=9",
        "-ac", "1", "-ar", str(SAMPLE_RATE), "-c:a", "libmp3lame", "-b:a", "56k", str(out_path),
    ]
    subprocess.run(cmd, input=buf.getvalue(), check=True)


def probe_duration(path: Path) -> float:
    result = subprocess.run(
        ["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", str(path)],
        check=True, capture_output=True, text=True,
    )
    return round(float(result.stdout.strip()), 3)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("mock_dir")
    parser.add_argument("--only", default="", help="vergul bilan ajratilgan trek nomlari")
    parser.add_argument("--force", action="store_true")
    parser.add_argument("--threads", type=int, default=2)
    args = parser.parse_args()

    root = Path(args.mock_dir)
    spec_path = root / "audio.json"
    if not spec_path.exists():
        sys.exit(f"{spec_path} topilmadi")
    spec = json.loads(spec_path.read_text(encoding="utf-8"))
    defaults = spec.get("defaults", {})
    global_voices = spec.get("voices", {})
    out_dir = root / "audio"
    out_dir.mkdir(exist_ok=True)
    manifest_path = out_dir / "manifest.json"
    manifest = json.loads(manifest_path.read_text(encoding="utf-8")) if manifest_path.exists() else {}

    wanted = [k for k in args.only.split(",") if k] or list(spec["tracks"].keys())
    unknown = [k for k in wanted if k not in spec["tracks"]]
    if unknown:
        sys.exit(f"audio.json da yo'q treklar: {', '.join(unknown)}")

    engine = None
    for key in wanted:
        track = spec["tracks"][key]
        voices = {**global_voices, **track.get("voices", {})}
        digest = track_hash(track, voices, defaults)
        target = out_dir / f"{key}.mp3"
        entry = manifest.get(key)
        if not args.force and entry and entry.get("hash") == digest and target.exists():
            print(f"= {key}: o'zgarmagan ({entry['duration']:.1f} s)")
            continue
        if engine is None:
            engine = load_engine(args.threads)
        samples = render_track(engine, track, voices, defaults)
        encode_mp3(samples, target)
        duration = probe_duration(target)
        manifest[key] = {"duration": duration, "hash": digest, "label": track.get("label", key)}
        manifest_path.write_text(json.dumps(manifest, indent=2, ensure_ascii=False, sort_keys=True) + "\n", encoding="utf-8")
        print(f"+ {key}: {duration:.1f} s, {target.stat().st_size // 1024} KB")

    # audio.json dan o'chirilgan treklarni manifestdan ham olib tashlaymiz.
    stale = [k for k in manifest if k not in spec["tracks"]]
    for key in stale:
        manifest.pop(key)
        (out_dir / f"{key}.mp3").unlink(missing_ok=True)
    if stale:
        manifest_path.write_text(json.dumps(manifest, indent=2, ensure_ascii=False, sort_keys=True) + "\n", encoding="utf-8")
    total = sum(v["duration"] for v in manifest.values())
    print(f"Jami: {len(manifest)} trek, {total / 60:.1f} daqiqa")


if __name__ == "__main__":
    main()

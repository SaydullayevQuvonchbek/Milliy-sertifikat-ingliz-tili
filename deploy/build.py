#!/usr/bin/env python3
"""Serverga yuklash paketini (zip) yig'ish.

    python deploy/build.py                  # HEAD dagi (commit qilingan) holatdan — odatiy usul
    python deploy/build.py --ref main       # boshqa branch yoki commit'dan
    python deploy/build.py --worktree       # ishchi papkadagi holatdan (commit qilinmagan o'zgarishlar bilan; sinov uchun)
    python deploy/build.py --out D:/joy/ingliz-tili-mock.zip

Natija (standart): repo papkasi yonida ../ingliz-tili-mock.zip. Zip ildizi = loyiha ildizi (public/, src/, ...).
Fayllar git obyektlaridan o'qiladi, shuning uchun Windows'dagi CRLF qatorlar paketga o'tmaydi va har bir faylning
SHA-256 xeshi GitHub'dagi fayl bilan bir xil bo'ladi (MANIFEST.txt da).

Git'ga fayl yozmaydi (index.lock yaratmaydi) — o'chirish taqiqlangan papkalarda ham xavfsiz.
"""
import argparse
import datetime
import hashlib
import os
import subprocess
import sys
import zipfile

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HERE)
DEFAULT_OUT = os.path.normpath(os.path.join(REPO, '..', 'ingliz-tili-mock.zip'))

# Repo'dan olinadigan yo'llar (papkalar rekursiv).
INCLUDE = [
    'public', 'src', 'database',
    'bin/install.php', 'bin/seed-content.php', 'bin/backup.php',
    'content/lib.php', 'content/mocks',
    'config/config.example.php',
    'storage/.htaccess', 'storage/README.md',
    'README.md',
]
# Ataylab kiritilmaydi: demo/ va bin/seed-demo.php (hammaga ma'lum parolli sinov hisoblari), bin/dev-router.php va
# bin/check-content.php (faqat ishlab chiqish uchun), content/tools/ va rasmlarning .svg manbalari, tests/, .github/,
# deploy/ (o'zi), package.json, .gitignore.
EXCLUDE_PARTS = {'.git', '.github', 'tests', 'node_modules', '__pycache__', 'tools'}
EXCLUDE_FILES = {'config/config.php', '.gitignore', 'package.json', 'package-lock.json'}
EXCLUDE_EXT = {'.pyc', '.log', '.sqlite', '.DS_Store', '.svg'}
KEEP_SVG = {'public/assets/img/favicon.svg'}

# deploy/ dagi qo'shimcha fayllar: (repo ichidagi yo'l, zip ichidagi yo'l).
EXTRA = [
    ('deploy/root.htaccess', '.htaccess'),
    ('deploy/public.user.ini', 'public/.user.ini'),
    ('deploy/SERVERGA-JOYLASH.md', 'SERVERGA-JOYLASH.md'),
]
# Paketda bo'lishi shart bo'lgan fayllar (yig'ishdan keyin tekshiriladi).
REQUIRED = [
    '.htaccess', 'public/.htaccess', 'public/.user.ini', 'public/api.php', 'public/index.html', 'public/admin/index.html',
    'src/bootstrap.php', 'config/config.example.php', 'database/schema.mysql.sql', 'database/schema.sqlite.sql',
    'bin/install.php', 'bin/backup.php', 'storage/.htaccess', 'SERVERGA-JOYLASH.md',
]
FORBIDDEN_PARTS = {'demo', 'tests', '.git', '.github', 'tools', 'node_modules'}
FORBIDDEN_FILES = {'config/config.php', 'bin/seed-demo.php', 'bin/dev-router.php', 'bin/check-content.php'}


def git(*args, binary=False):
    env = dict(os.environ, GIT_OPTIONAL_LOCKS='0')
    out = subprocess.run(['git', '--no-optional-locks', '-C', REPO, *args], capture_output=True, env=env, check=True).stdout
    return out if binary else out.decode('utf-8').strip()


def wanted(rel):
    parts = rel.split('/')
    if any(p in EXCLUDE_PARTS for p in parts) or rel in EXCLUDE_FILES:
        return False
    if os.path.splitext(rel)[1] in EXCLUDE_EXT and rel not in KEEP_SVG:
        return False
    return any(rel == item or rel.startswith(item + '/') for item in INCLUDE)


def tree_files(ref):
    """{yo'l: blob_sha} — berilgan commit'dagi oddiy fayllar."""
    out = git('ls-tree', '-r', '-z', '--full-tree', ref, binary=True)
    files = {}
    for entry in out.split(b'\0'):
        if not entry:
            continue
        meta, path = entry.split(b'\t', 1)
        mode, kind, sha = meta.decode().split(' ')
        if kind == 'blob' and mode in ('100644', '100755'):
            files[path.decode('utf-8')] = sha
    return files


def read_blobs(shas):
    """Blob'larni bitta `git cat-file --batch` jarayoni orqali o'qish (filtrlarsiz, aynan GitHub'dagidek)."""
    env = dict(os.environ, GIT_OPTIONAL_LOCKS='0')
    proc = subprocess.Popen(['git', '--no-optional-locks', '-C', REPO, 'cat-file', '--batch'],
                            stdin=subprocess.PIPE, stdout=subprocess.PIPE, env=env)
    data = {}
    for sha in shas:
        proc.stdin.write((sha + '\n').encode())
        proc.stdin.flush()
        header = proc.stdout.readline().decode().split()
        if len(header) != 3 or header[1] != 'blob':
            sys.exit(f"Git obyektini o'qib bo'lmadi: {sha} {header}")
        size = int(header[2])
        data[sha] = proc.stdout.read(size)
        proc.stdout.read(1)  # yakunlovchi "\n"
    proc.stdin.close()
    proc.wait()
    return data


def collect_from_ref(ref):
    tree = tree_files(ref)
    rels = sorted(p for p in tree if wanted(p))
    for item in INCLUDE:
        if not any(p == item or p.startswith(item + '/') for p in rels):
            sys.exit(f"Commit'da topilmadi: {item}")
    pairs = [(rel, rel) for rel in rels]
    for src, dst in EXTRA:
        if src not in tree:
            sys.exit(f"Commit'da qo'shimcha fayl yo'q: {src}")
        pairs.append((src, dst))
    blobs = read_blobs(sorted({tree[src] for src, _ in pairs}))
    return [(dst, blobs[tree[src]], src) for src, dst in pairs]


def collect_from_worktree():
    files = []
    for item in INCLUDE:
        top = os.path.join(REPO, item)
        if os.path.isfile(top):
            candidates = [item]
        elif os.path.isdir(top):
            candidates = []
            for dp, dn, fn in os.walk(top):
                dn[:] = sorted(d for d in dn if d not in EXCLUDE_PARTS)
                candidates += [os.path.relpath(os.path.join(dp, f), REPO).replace(os.sep, '/') for f in sorted(fn)]
        else:
            sys.exit(f'Topilmadi: {top}')
        for rel in candidates:
            if wanted(rel):
                files.append((rel, open(os.path.join(REPO, rel), 'rb').read(), rel))
    for src, dst in EXTRA:
        files.append((dst, open(os.path.join(REPO, src), 'rb').read(), src))
    return files


def uncommitted(files):
    """Ishchi papkadagi fayl commit'dagidan farq qilsa (CRLF farqi hisobga olinmaydi) — ro'yxat."""
    changed = []
    for _dst, data, src in files:
        path = os.path.join(REPO, src)
        if not os.path.isfile(path):
            changed.append(src + " (ishchi papkada yo'q)")
            continue
        local = open(path, 'rb').read()
        if local != data and local.replace(b'\r\n', b'\n') != data.replace(b'\r\n', b'\n'):
            changed.append(src)
    return changed


def main():
    ap = argparse.ArgumentParser(description="Serverga yuklash paketini yig'ish")
    ap.add_argument('--ref', default='HEAD', help="branch yoki commit (standart: HEAD)")
    ap.add_argument('--worktree', action='store_true', help="ishchi papkadan yig'ish (commit qilinmagan o'zgarishlar bilan)")
    ap.add_argument('--out', default=DEFAULT_OUT, help=f'zip fayl yo\'li (standart: {DEFAULT_OUT})')
    args = ap.parse_args()

    commit = git('rev-parse', '--verify', args.ref + '^{commit}')
    branch = git('rev-parse', '--abbrev-ref', 'HEAD') if args.ref == 'HEAD' else args.ref
    if args.worktree:
        files = collect_from_worktree()
        source = f'{branch} @ {commit[:7]} + ishchi papkadagi o\'zgarishlar'
    else:
        files = collect_from_ref(args.ref)
        source = f'{branch} @ {commit[:7]}'
        if args.ref == 'HEAD':
            changed = uncommitted(files)
            if changed:
                print("DIQQAT: quyidagi fayllar ishchi papkada o'zgargan, lekin commit qilinmagan — paketga KIRMAYDI:")
                for c in changed:
                    print('  ' + c)
    files.sort(key=lambda t: t[0])

    names = {dst for dst, _, _ in files}
    missing = [r for r in REQUIRED if r not in names]
    bad = sorted(n for n in names if n in FORBIDDEN_FILES or set(n.split('/')) & FORBIDDEN_PARTS)
    if missing or bad:
        sys.exit(f"Paket noto'g'ri. Yo'q: {missing}. Kirmasligi kerak: {bad}")

    stamp = int(git('show', '-s', '--format=%ct', commit))
    date_time = datetime.datetime.fromtimestamp(stamp).timetuple()[:6]
    manifest = [
        '# Milliy sertifikat (ingliz tili) mock — serverga yuklash paketi',
        f'# manba: github.com/SaydullayevQuvonchbek/Milliy-sertifikat-ingliz-tili  {source}',
        f'# commit: {commit}',
        f"# yig'ildi: {datetime.date.today().isoformat()}",
        '# ustunlar: sha256  hajm(bayt)  yo\'l',
        '',
    ]
    total = 0
    tmp = args.out + '.tmp'
    with zipfile.ZipFile(tmp, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for dst, data, _src in files:
            total += len(data)
            manifest.append(f'{hashlib.sha256(data).hexdigest()}  {len(data):>9}  {dst}')
            info = zipfile.ZipInfo(dst, date_time=date_time)
            info.external_attr = 0o644 << 16
            info.compress_type = zipfile.ZIP_STORED if dst.endswith(('.mp3', '.png')) else zipfile.ZIP_DEFLATED
            z.writestr(info, data)
        info = zipfile.ZipInfo('MANIFEST.txt', date_time=date_time)
        info.external_attr = 0o644 << 16
        info.compress_type = zipfile.ZIP_DEFLATED
        z.writestr(info, '\n'.join(manifest) + '\n')
    os.replace(tmp, args.out)
    print(f'{args.out}\n  {len(files)} fayl, {total / 1048576:.1f} MB ochiq, {os.path.getsize(args.out) / 1048576:.1f} MB zip, {source}')


if __name__ == '__main__':
    main()

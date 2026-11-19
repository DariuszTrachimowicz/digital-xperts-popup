"""Buduje paczkę wydania: python build.py -> dist/digital-xperts-popup-<wersja>.zip i dist/digital-xperts-popup.zip
Wydanie: gh release create v<wersja> dist/digital-xperts-popup-<wersja>.zip dist/digital-xperts-popup.zip --title <wersja> --notes "..."
Paczka bez numeru wersji ma stały adres releases/latest/download/digital-xperts-popup.zip, z którego instaluje ją
konektor Menedżera Strony AI (plugin.install). Aktualizacje biorą paczkę z numerem wersji.
"""
import hashlib, os, re, shutil, subprocess, zipfile

root = os.path.dirname(os.path.abspath(__file__))
slug = 'digital-xperts-popup'
version = re.search(r"const VERSION = '([\d.]+)';", open(os.path.join(root, slug + '.php'), encoding='utf-8').read()).group(1)
# Tylko pliki z repozytorium: nic lokalnego (notatki, zrzuty) nie trafi na strony.
files = subprocess.run(['git', 'ls-files'], cwd=root, capture_output=True, text=True, check=True).stdout.split()
skip = ('tests/', 'build.py', '.gitignore', 'AGENTS.md', 'CLAUDE.md', 'README.md')
os.makedirs(os.path.join(root, 'dist'), exist_ok=True)
out = os.path.join(root, 'dist', f'{slug}-{version}.zip')
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for name in sorted(files):
        if not name.startswith(skip):
            z.write(os.path.join(root, name), slug + '/' + name)
shutil.copyfile(out, os.path.join(root, 'dist', slug + '.zip'))
print(out, hashlib.sha256(open(out, 'rb').read()).hexdigest())

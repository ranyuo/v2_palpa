#!/usr/bin/env python3
"""Empaqueta una instalación nueva de la intranet usando solo archivos versionados."""
import argparse
import hashlib
from pathlib import Path
import subprocess
import zipfile

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('output', type=Path, help='ZIP de salida fuera del repositorio')
args = parser.parse_args()
output = args.output.resolve()
if output.is_relative_to(root):
    parser.error('Guarda el entregable fuera del checkout para no versionar el ZIP generado.')
commit = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=root, text=True).strip()
if subprocess.check_output(['git', 'status', '--porcelain'], cwd=root):
    parser.error('El checkout debe estar limpio para identificar exactamente la versión empaquetada.')
tracked = subprocess.check_output(['git', 'ls-files', '-z'], cwd=root).decode().split('\0')
docs = {'HOSTINGER-INSTALACION.md', 'OPERACION-INTRANET.md', 'ALCANCE-INTRANET.md',
        'ACTUALIZACION-QA-HOSTINGER.md', 'ACTUALIZACION-MEJORAS.md', 'QA-CORRECCIONES-INTRANET.md'}
files = []
for name in filter(None, tracked):
    path = Path(name)
    selected = name in docs or name.startswith(('public_html/', 'portal-private/'))
    if not selected:
        continue
    if name.startswith(('public_html/assets/chincha/', 'public_html/assets/nasca/')):
        continue
    if name in {'portal-private/NascaContent.php', 'portal-private/PalpaContent.php',
                'portal-private/data/chincha-sources.json', 'portal-private/data/nasca-sources.json'}:
        continue
    if path.name != 'config.example.php' and (path.name.startswith('config.') or path.suffix in {'.log', '.lock', '.env', '.intranetbak', '.piscobak'}):
        raise SystemExit(f'Archivo privado inesperado entre los versionados: {name}')
    if any(part in {'backups', '.backups', '.results', '.git'} for part in path.parts):
        raise SystemExit(f'Ruta privada inesperada: {name}')
    files.append(name)
required = {'public_html/index.php', 'public_html/intranet.js', 'public_html/intranet.css',
            'public_html/.htaccess', 'portal-private/IntranetScope.php', 'portal-private/.htaccess',
            'portal-private/config.example.php', 'portal-private/schema.sql', 'portal-private/data/portal.json',
            'public_html/assets/vendor/bootstrap/bootstrap-grid.min.css'}
if required - set(files):
    raise SystemExit(f'Faltan archivos: {sorted(required - set(files))}')
output.parent.mkdir(parents=True, exist_ok=True)
manifest = []
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as archive:
    for name in sorted(files):
        archive.write(root / name, name)
        manifest.append(hashlib.sha256((root / name).read_bytes()).hexdigest() + '  ' + name)
    archive.writestr('VERSION.txt', 'Intranet Palpa\nRepositorio: https://github.com/ranyuo/app\nCommit: ' + commit + '\nPaquete para instalación nueva.\n')
    archive.writestr('SHA256SUMS.txt', '\n'.join(manifest) + '\n')
with zipfile.ZipFile(output) as archive:
    if archive.testzip() is not None:
        raise SystemExit('El ZIP no supera la comprobación de integridad.')
checksum = hashlib.sha256(output.read_bytes()).hexdigest()
output.with_suffix('.zip.sha256').write_text(checksum + '  ' + output.name + '\n')
print(f'ZIP: {output}\nCommit: {commit}\nArchivos: {len(files)}\nTamaño: {output.stat().st_size} bytes\nSHA-256: {checksum}')

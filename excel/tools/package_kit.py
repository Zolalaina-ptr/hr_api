"""Auteur : Arena | 2026-09-08. Archive de livraison sans récursion ZIP ni fichiers d'exploitation."""
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

ROOT = Path(__file__).resolve().parents[1]
TOP_LEVEL = {
    'README.md', 'LIRE-AVANT-INSTALLATION.txt', 'Creer-le-classeur.cmd',
    'requirements-dev.txt', 'VERSION.txt',
}
DIRECTORIES = {'src', 'tools', 'tests', 'examples', 'docs'}
EXTENSIONS = {'.bas', '.vba', '.ps1', '.json', '.py', '.xlsx', '.csv', '.md', '.pdf'}


def package():
    """Produit le ZIP public du kit uniquement ; jamais de livraison/*.xlsm ni d'archive imbriquée."""
    files = []
    for path in sorted(ROOT.rglob('*')):
        if not path.is_file():
            continue
        rel = path.relative_to(ROOT)
        if any(part.startswith('.') or part == '__pycache__' for part in rel.parts):
            continue
        if len(rel.parts) == 1 and rel.name in TOP_LEVEL:
            files.append(path)
        elif len(rel.parts) > 1 and rel.parts[0] in DIRECTORIES and path.suffix in EXTENSIONS:
            files.append(path)
    output = ROOT/'GestionnaireRH-kit-Windows.zip'
    with ZipFile(output, 'w', ZIP_DEFLATED) as archive:
        for path in files:
            archive.write(path, Path('GestionnaireRH')/path.relative_to(ROOT))
    with ZipFile(output) as archive:
        assert archive.testzip() is None
        assert not any(name.endswith('.zip') for name in archive.namelist())
        assert archive.read('GestionnaireRH/VERSION.txt').strip() == b'1.0.1'
        assert archive.read('GestionnaireRH/tools/Build-Workbook.ps1') == (ROOT/'tools/Build-Workbook.ps1').read_bytes()
        assert 'GestionnaireRH/tools/Initialize-Workbook.ps1' in archive.namelist()
        assert 'GestionnaireRH/tools/workbook-data.json' in archive.namelist()
    print(f'{output.name} : {len(files)} fichiers, {output.stat().st_size} octets, intégrité vérifiée.')


if __name__ == '__main__':
    package()

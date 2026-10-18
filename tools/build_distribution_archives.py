#!/usr/bin/env python3
"""
Скрипт сборки дистрибутивных архивов:
1. theme4.zip (тема оформления) с корнем 'theme4/'
2. tchp-content.zip (плагин контентного ядра) с корнем 'tchp-content/'
Использует POSIX-пути (прямые слэши '/') для 100% совместимости с Linux/WordPress.
"""

import os
import zipfile
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent

def pack_folder(src_dir: str, zip_out: str, arc_root: str):
    source_path = BASE_DIR / src_dir
    output_path = BASE_DIR / zip_out

    if not source_path.exists():
        raise FileNotFoundError(f"Source folder not found: {source_path}")

    if output_path.exists():
        output_path.unlink()

    print(f"Packaging '{source_path}' -> '{output_path.name}' (root: '{arc_root}/')...")
    file_count = 0
    with zipfile.ZipFile(output_path, 'w', zipfile.ZIP_DEFLATED) as z:
        for root, dirs, files in os.walk(source_path):
            dirs[:] = [d for d in dirs if not d.startswith('.') and d != '__pycache__']
            for file in sorted(files):
                if file.startswith('.') and file != '.htaccess':
                    continue
                if file.endswith('.pyc'):
                    continue
                full_path = Path(root) / file
                rel_path = full_path.relative_to(source_path).as_posix()
                arcname = f"{arc_root}/{rel_path}"
                z.write(full_path, arcname=arcname)
                file_count += 1

    size_bytes = output_path.stat().st_size
    print(f"  [OK] {output_path.name} created: {file_count} files ({size_bytes} bytes)")
    return output_path

def build_all():
    pack_folder("theme4", "theme4.zip", "theme4")
    pack_folder("wp-content/plugins/tchp-content", "tchp-content.zip", "tchp-content")

if __name__ == "__main__":
    build_all()

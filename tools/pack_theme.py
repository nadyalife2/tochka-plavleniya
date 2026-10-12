"""
Скрипт сборки WordPress темы theme.zip с правильными POSIX-путями (forward slashes '/')
для совместимости с серверами Linux / хостингами Beget, Timeweb и встроенным распаковщиком WordPress.
"""
import os
import zipfile

def pack_theme(src_dir='theme', zip_out='theme.zip'):
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    theme_path = os.path.join(base_dir, src_dir)
    zip_path = os.path.join(base_dir, zip_out)
    
    print(f"Упаковка темы из {theme_path} в {zip_path}...")
    file_count = 0
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as z:
        for root, dirs, files in os.walk(theme_path):
            # Игнорировать временные файлы и скрытые директории
            dirs[:] = [d for d in dirs if not d.startswith('.')]
            for file in files:
                if file.startswith('.') and file != '.htaccess':
                    continue
                full_path = os.path.join(root, file)
                rel_path = os.path.relpath(full_path, base_dir)
                # Всегда использовать прямой слэш для совместимости с Linux/WordPress
                posix_path = rel_path.replace('\\', '/')
                z.write(full_path, arcname=posix_path)
                file_count += 1
                
    print(f"Готово! Добавлено файлов: {file_count}. Архив успешно создан: {zip_path}")

if __name__ == '__main__':
    pack_theme()

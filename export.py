import os
import sys
import zipfile
from pathlib import Path

files = ["src", "static", "custom-map-plugin.php"]

target = sys.argv[1] if len(sys.argv) > 1 else "dist"
Path(target).mkdir(parents=True, exist_ok=True)

zip_path = Path(target) / "custom-map-plugin.zip"
if zip_path.exists():
    print(f"deleting existing {zip_path}")
    os.remove(zip_path)

with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zipf:
    for file in files:
        file = Path(file)
        if not file.is_dir():
            zipf.write(str(file), str(file))

        for root, _, files in os.walk(file):
            for file in files:
                filepath = Path(root) / file
                zipf.write(filepath, filepath)

print(f"Plugin zipped at: {zip_path}")

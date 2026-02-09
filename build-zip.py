"""Build a cross-platform ZIP of the community-directory plugin."""
import os
import zipfile

PLUGIN_DIR = os.path.join('plugin', 'community-directory')
OUTPUT_ZIP = os.path.join('plugin', 'community-directory.zip')

# Directories/files to skip
SKIP = {'.git', '__pycache__', '.DS_Store', 'Thumbs.db'}

def build_zip():
    if os.path.exists(OUTPUT_ZIP):
        os.remove(OUTPUT_ZIP)

    with zipfile.ZipFile(OUTPUT_ZIP, 'w', zipfile.ZIP_DEFLATED) as zf:
        for root, dirs, files in os.walk(PLUGIN_DIR):
            # Filter out skipped directories
            dirs[:] = [d for d in dirs if d not in SKIP]

            for f in files:
                if f in SKIP:
                    continue
                file_path = os.path.join(root, f)
                # Create archive name relative to 'plugin/' with forward slashes
                arc_name = os.path.relpath(file_path, 'plugin').replace('\\', '/')
                zf.write(file_path, arc_name)

    # Verify
    with zipfile.ZipFile(OUTPUT_ZIP, 'r') as zf:
        names = zf.namelist()
        print(f"Created {OUTPUT_ZIP} with {len(names)} files")
        # Show first few entries
        for name in names[:10]:
            print(f"  {name}")
        if len(names) > 10:
            print(f"  ... and {len(names) - 10} more")

if __name__ == '__main__':
    build_zip()

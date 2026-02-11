import os
import zipfile

def zip_directory(folder_path, zip_path):
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(folder_path):
            for file in files:
                file_path = os.path.join(root, file)
                arcname = os.path.relpath(file_path, os.path.dirname(folder_path))
                arcname = arcname.replace("\\", "/")
                print(f"Adding: {arcname}")
                zipf.write(file_path, arcname)

if __name__ == "__main__":
    plugin_dir = os.path.dirname(os.path.abspath(__file__))
    source_dir = os.path.join(plugin_dir, "community-directory")
    output_zip = os.path.join(plugin_dir, "community-directory-0.3.89.zip")
    
    if os.path.exists(output_zip):
        os.remove(output_zip)
        print(f"Removed existing {output_zip}")
        
    print(f"Zipping {source_dir} to {output_zip}...")
    zip_directory(source_dir, output_zip)
    print("Done.")

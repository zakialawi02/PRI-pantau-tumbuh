#!/usr/bin/env python3
import sys
import os
import time
import traceback
from PIL import Image, ImageEnhance


def process_image(file_path, imagery_id):
    print(f"[PYTHON] Starting processing for imagery {imagery_id}")

    # Base path Laravel project (folder parent dari /scripts)
    base_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))

    # Pastikan path absolut
    abs_path = os.path.abspath(os.path.join(base_dir, file_path)) if not os.path.isabs(file_path) else file_path
    abs_path = os.path.normpath(abs_path)

    print(f"[DEBUG] Base dir: {base_dir}")
    print(f"[DEBUG] Resolved file path: {abs_path}")

    # Pastikan file ada
    if not os.path.exists(abs_path):
        print(f"[ERROR] File not found: {abs_path}")
        sys.exit(1)

    try:
        print("[PYTHON] Loading image...")
        img = Image.open(abs_path)
        print(f"[PYTHON] Original image mode: {img.mode}")
        time.sleep(1)

        # Convert ke mode RGB biar kompatibel
        if img.mode not in ("RGB", "L"):
            print("[PYTHON] Converting image mode to RGB...")
            img = img.convert("RGB")

        print("[PYTHON] Enhancing contrast...")
        enhancer = ImageEnhance.Contrast(img)
        enhanced_img = enhancer.enhance(1.8)
        time.sleep(2)

        print("[PYTHON] Simulating heavy computation (mock delay)...")
        time.sleep(3)

        # Simpan hasil ke folder processed/
        output_dir = os.path.join(os.path.dirname(abs_path), "processed")
        os.makedirs(output_dir, exist_ok=True)

        # Pastikan nama file aman di semua OS
        filename_only = os.path.basename(file_path).replace("\\", "/").split("/")[-1]

        name, ext = os.path.splitext(filename_only)
        output_filename = f"{name}_processed{ext}"
        output_path = os.path.join(output_dir, output_filename)
        output_path = os.path.normpath(output_path)

        enhanced_img.save(output_path)
        print(f"[PYTHON] Processing complete. Output saved at: {output_path}")

        sys.exit(0)

    except Exception as e:
        print(f"[ERROR] Exception occurred during processing: {e}")
        traceback.print_exc()
        sys.exit(1)


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: process_imagery.py <file_path> <imagery_id>")
        sys.exit(1)

    file_path = sys.argv[1]
    imagery_id = sys.argv[2]
    process_image(file_path, imagery_id)

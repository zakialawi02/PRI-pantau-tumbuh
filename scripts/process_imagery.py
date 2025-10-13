#!/usr/bin/env python3
"""Script pemrosesan citra satelit menggunakan model TensorFlow."""

import gc
import os
import time

import joblib
import matplotlib.pyplot as plt  # noqa: F401  # Dipertahankan jika diperlukan untuk debugging
import numpy as np
import rasterio
from rasterio.windows import Window
import tensorflow as tf
from tensorflow.keras.models import load_model

os.environ["TF_CPP_MIN_LOG_LEVEL"] = "2"
os.environ.setdefault("OMP_NUM_THREADS", "4")
os.environ.setdefault("MKL_NUM_THREADS", "4")
os.environ.setdefault("GDAL_CACHEMAX", "512")


def resolve_path(env_key: str, default: str = "") -> str:
    """Ambil path dari environment variable dengan fallback ke default."""
    path = os.environ.get(env_key, default)
    return path if path else default


def main() -> None:
    # === 1. Konfigurasi TensorFlow ===
    print("⚙️  Konfigurasi TensorFlow...")
    gpus = tf.config.list_physical_devices('GPU')
    if gpus:
        for gpu in gpus:
            tf.config.experimental.set_memory_growth(gpu, True)
        from tensorflow.keras import mixed_precision

        mixed_precision.set_global_policy('mixed_float16')
        tf.config.optimizer.set_jit(True)
        print(f"✅ GPU terdeteksi: {len(gpus)} unit, mixed precision + XLA aktif")
    else:
        print("⚠️ Tidak ada GPU, fallback ke CPU")

    # === 2. Path input/output ===
    base_dir = os.path.dirname(os.path.abspath(__file__))
    default_input = os.path.join(base_dir, "..", "data", "Source_citrasatelitpri.tif")
    default_output = os.path.join(base_dir, "..", "data", "Mosaic_Predicted.tif")
    default_model = os.path.join(base_dir, "Data Model", "Best_Model2.h5")
    default_scaler = os.path.join(base_dir, "Data Model", "Best_Scaler2.pkl")

    input_path = resolve_path("IMAGERY_INPUT_PATH", default_input)
    output_path = resolve_path("IMAGERY_OUTPUT_PATH", default_output)
    model_path = resolve_path("IMAGERY_MODEL_PATH", default_model)
    scaler_path = resolve_path("IMAGERY_SCALER_PATH", default_scaler)

    if not input_path or not os.path.exists(input_path):
        raise FileNotFoundError(
            f"Input raster tidak ditemukan. Set variabel lingkungan IMAGERY_INPUT_PATH. ({input_path})"
        )

    if not model_path or not os.path.exists(model_path):
        raise FileNotFoundError(
            f"Model tidak ditemukan. Set variabel lingkungan IMAGERY_MODEL_PATH. ({model_path})"
        )

    if not scaler_path or not os.path.exists(scaler_path):
        raise FileNotFoundError(
            f"Scaler tidak ditemukan. Set variabel lingkungan IMAGERY_SCALER_PATH. ({scaler_path})"
        )

    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    print(f"Input raster: {input_path}")
    print(f"Output raster: {output_path}")

    # === 3. Parameter tile ===
    TILE_SIZE = int(os.environ.get("IMAGERY_TILE_SIZE", 2000))
    print(f"📌 Ukuran tile maksimal {TILE_SIZE}x{TILE_SIZE}")

    # === 4. Load model & scaler ===
    print("\n📥 Loading model dan scaler...")
    model = load_model(model_path, compile=False)
    scaler = joblib.load(scaler_path)
    print("✅ Model & scaler dimuat")

    # === 5. Prediksi tile streaming langsung ke file ===
    with rasterio.open(input_path) as src:
        H, W = src.height, src.width
        print(f"🗺️  Ukuran raster asli: {W} x {H}")

        out_meta = src.meta.copy()
        out_meta.update({
            "height": H,
            "width": W,
            "transform": src.transform,
            "driver": "GTiff",
            "dtype": "float32",
            "count": 1,
        })

        with rasterio.open(output_path, "w+", **out_meta) as dst:
            tile_idx = 0
            total_tiles = (H // TILE_SIZE + 1) * (W // TILE_SIZE + 1)

            for row_off in range(0, H, TILE_SIZE):
                for col_off in range(0, W, TILE_SIZE):
                    tile_idx += 1
                    win_width = min(TILE_SIZE, W - col_off)
                    win_height = min(TILE_SIZE, H - row_off)
                    window = Window(col_off, row_off, win_width, win_height)

                    t0 = time.time()
                    print(
                        f"\n[{tile_idx}/{total_tiles}] 📍 Window (x={col_off}, y={row_off}, "
                        f"w={win_width}, h={win_height})"
                    )

                    bands = [
                        src.read(b, window=window)
                        for b in range(1, 13)
                    ]
                    arr = np.stack(bands, axis=0)
                    h, w = arr.shape[1:]
                    del bands
                    gc.collect()

                    input_img = arr.reshape(12, h * w).T
                    del arr
                    gc.collect()

                    input_img_scaled = scaler.transform(input_img)
                    del input_img
                    gc.collect()

                    pred = model.predict(input_img_scaled, verbose=0, batch_size=1024)
                    pred = pred.reshape(h, w).astype("float32")
                    del input_img_scaled
                    gc.collect()

                    dst.write(pred, 1, window=window)

                    elapsed = time.time() - t0
                    print(
                        f"   🤖 Prediksi selesai untuk {h * w:,} piksel (durasi {elapsed:.2f} dtk)"
                    )

                    del pred
                    gc.collect()

    print(f"\n✅ Mosaic final langsung tersimpan: {output_path}")

    del model, scaler
    gc.collect()


if __name__ == "__main__":
    main()

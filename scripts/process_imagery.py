#!/usr/bin/env python3
"""Process satellite imagery using a TensorFlow model.

This script expects the following environment variables to be set by the caller:

- IMAGERY_INPUT_PATH:  Path to the source GeoTIFF imagery file.
- IMAGERY_OUTPUT_PATH: Path where the predicted GeoTIFF should be written.
- IMAGERY_MODEL_PATH:  Path to the trained Keras model (.h5).
- IMAGERY_SCALER_PATH: Path to the fitted scaler (.pkl).
- IMAGERY_TILE_SIZE:   Optional. Tile size to use during streaming inference.
- IMAGERY_BATCH_SIZE:  Optional. Batch size passed to ``model.predict``.

The implementation is adapted from the "core" processing code provided by the
product team while keeping the dynamic configuration driven by environment
variables so it can be orchestrated from the Laravel job queue.
"""

from __future__ import annotations

import gc
import os
import sys
import time
from pathlib import Path

import joblib
import numpy as np
import rasterio
from rasterio.windows import Window
import tensorflow as tf
from tensorflow.keras.models import load_model

# Optional dependency; kept to stay aligned with the reference core code.
try:  # pragma: no cover - optional dependency
    import matplotlib.pyplot as plt  # noqa: F401
except Exception:  # pragma: no cover - ignore if matplotlib is unavailable
    plt = None


# ---------------------------------------------------------------------------
# 0. Environment configuration
# ---------------------------------------------------------------------------
os.environ["TF_CPP_MIN_LOG_LEVEL"] = "2"
os.environ.setdefault("OMP_NUM_THREADS", "4")
os.environ.setdefault("MKL_NUM_THREADS", "4")
os.environ.setdefault("GDAL_CACHEMAX", "512")

BASE_DIR = Path(__file__).resolve().parent

INPUT_PATH = os.environ.get("IMAGERY_INPUT_PATH")
OUTPUT_PATH = os.environ.get("IMAGERY_OUTPUT_PATH")
MODEL_PATH = Path(os.environ.get("IMAGERY_MODEL_PATH", BASE_DIR / "Data Model" / "Best_Model2.h5"))
SCALER_PATH = Path(os.environ.get("IMAGERY_SCALER_PATH", BASE_DIR / "Data Model" / "Best_Scaler2.pkl"))

DEFAULT_TILE_SIZE = 2000
TILE_SIZE = int(os.environ.get("IMAGERY_TILE_SIZE", DEFAULT_TILE_SIZE))
PRED_BATCH_SIZE = int(os.environ.get("IMAGERY_BATCH_SIZE", 1024))


def error_and_exit(message: str, *, code: int = 1) -> None:
    """Print an error message and exit the script."""
    print(message)
    sys.exit(code)


if not INPUT_PATH:
    error_and_exit("IMAGERY_INPUT_PATH belum ditetapkan.")

if not OUTPUT_PATH:
    error_and_exit("IMAGERY_OUTPUT_PATH belum ditetapkan.")

if not Path(INPUT_PATH).exists():
    error_and_exit(f"File input tidak ditemukan: {INPUT_PATH}")

if not MODEL_PATH.exists():
    error_and_exit(f"Model tidak ditemukan pada path: {MODEL_PATH}")

if not SCALER_PATH.exists():
    error_and_exit(f"Scaler tidak ditemukan pada path: {SCALER_PATH}")

os.makedirs(Path(OUTPUT_PATH).parent, exist_ok=True)


# ---------------------------------------------------------------------------
# 1. TensorFlow configuration
# ---------------------------------------------------------------------------
def configure_tensorflow() -> None:
    print("Konfigurasi TensorFlow...")
    try:
        gpus = tf.config.list_physical_devices("GPU")
        if gpus:
            for gpu in gpus:
                tf.config.experimental.set_memory_growth(gpu, True)
            from tensorflow.keras import mixed_precision  # type: ignore

            mixed_precision.set_global_policy("mixed_float16")
            tf.config.optimizer.set_jit(True)
            print(f"GPU terdeteksi: {len(gpus)} unit, mixed precision + XLA aktif")
        else:
            print("Tidak ada GPU, fallback ke CPU")
            tf.config.threading.set_intra_op_parallelism_threads(4)
            tf.config.threading.set_inter_op_parallelism_threads(4)
    except Exception as exc:  # pragma: no cover - defensive logging
        print(f"Gagal mengkonfigurasi TensorFlow: {exc}")


configure_tensorflow()


# ---------------------------------------------------------------------------
# 2. Load model and scaler
# ---------------------------------------------------------------------------
def load_resources():
    print("\nLoading model dan scaler...")
    start = time.time()
    model_obj = load_model(MODEL_PATH, compile=False)
    scaler_obj = joblib.load(SCALER_PATH)
    print(f"Model & scaler dimuat ({time.time() - start:.2f} dtk)")
    return model_obj, scaler_obj


model, scaler = load_resources()


# ---------------------------------------------------------------------------
# 3. Streaming prediction across tiles
# ---------------------------------------------------------------------------
def process_tiles() -> None:
    print(f"\nUkuran tile maksimal {TILE_SIZE}x{TILE_SIZE}")
    with rasterio.open(INPUT_PATH) as src:
        height, width = src.height, src.width
        print(f"Ukuran raster asli: {width} x {height}")

        out_meta = src.meta.copy()
        out_meta.update(
            {
                "height": height,
                "width": width,
                "transform": src.transform,
                "driver": "GTiff",
                "dtype": "float32",
                "count": 1,
            }
        )

        with rasterio.open(OUTPUT_PATH, "w+", **out_meta) as dst:
            total_tiles = (height // TILE_SIZE + 1) * (width // TILE_SIZE + 1)
            tile_idx = 0

            for row_off in range(0, height, TILE_SIZE):
                for col_off in range(0, width, TILE_SIZE):
                    tile_idx += 1
                    win_width = min(TILE_SIZE, width - col_off)
                    win_height = min(TILE_SIZE, height - row_off)
                    window = Window(col_off, row_off, win_width, win_height)

                    t0 = time.time()
                    print(
                        f"\n[{tile_idx}/{total_tiles}] Window (x={col_off}, y={row_off}, w={win_width}, h={win_height})"
                    )

                    bands = [src.read(b, window=window) for b in range(1, 13)]
                    arr = np.stack(bands, axis=0)
                    h, w = arr.shape[1:]
                    del bands
                    gc.collect()

                    input_img = arr.reshape(12, h * w).T
                    del arr
                    gc.collect()

                    input_scaled = scaler.transform(input_img)
                    del input_img
                    gc.collect()

                    pred = model.predict(input_scaled, verbose=0, batch_size=PRED_BATCH_SIZE)
                    pred = pred.reshape(h, w).astype("float32")
                    del input_scaled
                    gc.collect()

                    dst.write(pred, 1, window=window)

                    elapsed = time.time() - t0
                    print(f"   Prediksi selesai untuk {h * w:,} piksel (durasi {elapsed:.2f} dtk)")

                    del pred
                    gc.collect()

    print(f"\nMosaic final langsung tersimpan: {OUTPUT_PATH}")


try:
    process_tiles()
finally:
    del model
    del scaler
    gc.collect()


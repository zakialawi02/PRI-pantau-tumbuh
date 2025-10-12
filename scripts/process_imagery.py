#!/usr/bin/env python3
import sys
import os
import time
import traceback
os.environ["TF_CPP_MIN_LOG_LEVEL"] = "2"
os.environ.setdefault("OMP_NUM_THREADS", "4")
os.environ.setdefault("MKL_NUM_THREADS", "4")
os.environ.setdefault("GDAL_CACHEMAX", "512")

import gc
import tensorflow as tf
from tensorflow.keras import backend as K
import numpy as np
import rasterio
from rasterio.windows import Window
import joblib
import tensorflow as tf
from tensorflow.keras.models import load_model
import matplotlib.pyplot as plt
import time

# =====================
# 0. KONFIGURASI
# =====================
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

INPUT_PATH      = os.environ.get("IMAGERY_INPUT_PATH", "")
MODEL_PATH      = os.environ.get("IMAGERY_MODEL_PATH", os.path.join(BASE_DIR, "Data Model", "Best_Model2.h5"))
SCALER_PATH     = os.environ.get("IMAGERY_SCALER_PATH", os.path.join(BASE_DIR, "Data Model", "Best_Scaler2.pkl"))
OUT_TIF         = os.environ.get("IMAGERY_OUTPUT_PATH", "")

if not INPUT_PATH or not OUT_TIF:
    print("[ERROR] IMAGERY_INPUT_PATH atau IMAGERY_OUTPUT_PATH belum ditetapkan.")
    sys.exit(1)

if not os.path.exists(MODEL_PATH):
    print(f"[ERROR] Model tidak ditemukan pada path: {MODEL_PATH}")
    sys.exit(1)

if not os.path.exists(SCALER_PATH):
    print(f"[ERROR] Scaler tidak ditemukan pada path: {SCALER_PATH}")
    sys.exit(1)

TILE_W = 2048
TILE_H = 2048
BATCH_PIXELS   = 262_144
PRED_BATCH_SIZE = 2048

def cleanup_memory():
    gc.collect()
    K.clear_session()
    tf.compat.v1.reset_default_graph()

def setup_tf_acceleration():
    print("[DEBUG]  Mengatur konfigurasi TensorFlow...")
    try:
        gpus = tf.config.list_physical_devices('GPU')
        if gpus:
            for gpu in gpus:
                tf.config.experimental.set_memory_growth(gpu, True)
            try:
                from tensorflow.keras import mixed_precision
                mixed_precision.set_global_policy('mixed_float16')
                print("[INFO] Mixed precision diaktifkan")
            except Exception:
                print("[DEBUG] Mixed precision tidak tersedia")
            tf.config.optimizer.set_jit(True)
            print("[INFO] GPU terdeteksi: {len(gpus)} unit. XLA JIT diaktifkan.")
        else:
            print("[INFO] Tidak ada GPU, fallback ke CPU")
            tf.config.threading.set_intra_op_parallelism_threads(4)
            tf.config.threading.set_inter_op_parallelism_threads(4)
    except Exception as e:
        print("[ERROR] TF accel config error: {e}")

setup_tf_acceleration()

# =====================
# 1. MUAT MODEL & SCALER
# =====================
print("[DEBUG] Memuat model dan scaler...")
start = time.time()
model = load_model(MODEL_PATH, compile=False)
scaler = joblib.load(SCALER_PATH)
print(f"[INFO] Model & scaler berhasil dimuat. ({time.time()-start:.2f} dtk)")

def fast_scale(X, scaler_obj):
    if hasattr(scaler_obj, "mean_") and hasattr(scaler_obj, "scale_"):
        mean = np.asarray(scaler_obj.mean_, dtype=np.float32)
        scale = np.asarray(scaler_obj.scale_, dtype=np.float32)
        return (X - mean) / scale
    return scaler_obj.transform(X)

# =====================
# 2. PREDIKSI STREAMING
# =====================
print("[DEBUG] Memulai prediksi streaming...")
with rasterio.open(INPUT_PATH) as src:
    src_profile = src.profile.copy()
    height, width = src.height, src.width

    out_profile = src_profile.copy()
    out_profile.update({
        "count": 1,
        "dtype": "float32",
        "driver": "GTiff",
        "compress": "lzw"
    })

    with rasterio.open(OUT_TIF, "w", **out_profile) as dst:
        total_windows = ((height+TILE_H-1)//TILE_H) * ((width+TILE_W-1)//TILE_W)
        win_idx = 1

        for y in range(0, height, TILE_H):
            h = min(TILE_H, height - y)
            for x in range(0, width, TILE_W):
                w = min(TILE_W, width - x)
                window = Window(x, y, w, h)

                print(f"\n[{win_idx}/{total_windows}] [INFO PROCESS] Window posisi (x={x}, y={y}, w={w}, h={h})")
                win_idx += 1
                t0 = time.time()

                bands = src.read(indexes=list(range(1, 13)), window=window).astype(np.float32)
                print(f"   [INFO] Dibaca 12 band → shape: {bands.shape}")

                N = h * w
                flat = bands.reshape(12, N).T
                out_flat = np.empty((N,), dtype=np.float32)

                # Batch processing
                for s in range(0, N, BATCH_PIXELS):
                    e = min(s + BATCH_PIXELS, N)
                    Xb = flat[s:e]
                    Xb = fast_scale(Xb, scaler)

                    yb = model.predict(Xb, batch_size=PRED_BATCH_SIZE, verbose=0)
                    yb = np.asarray(yb).reshape(-1).astype(np.float32)
                    out_flat[s:e] = yb
                print(f"   [INFO] Prediksi selesai untuk {N} piksel")

                out_tile = out_flat.reshape(h, w)
                dst.write(out_tile, 1, window=window)
                print(f"   [INFO] Ditulis ke output (durasi {time.time()-t0:.2f} dtk)")

print(f"\n[INFO] Semua window selesai diproses → {OUT_TIF}")



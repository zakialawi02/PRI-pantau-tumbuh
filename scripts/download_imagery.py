#!/usr/bin/env python
"""Download a remote Sentinel imagery archive and emit metadata as JSON."""
from __future__ import annotations

import argparse
import json
import os
import sys
from typing import Any, Dict

import requests


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Download imagery archive to a target path.")
    parser.add_argument("--url", required=True, help="Source URL for the download.")
    parser.add_argument(
        "--output",
        required=True,
        help="Destination file path where the downloaded archive should be stored.",
    )
    parser.add_argument(
        "--chunk-size",
        type=int,
        default=1024 * 1024,
        help="Chunk size in bytes for streaming download (default: 1 MiB).",
    )
    parser.add_argument(
        "--timeout",
        type=int,
        default=600,
        help="Request timeout in seconds (default: 600).",
    )
    return parser.parse_args()


def ensure_parent_directory(path: str) -> None:
    directory = os.path.dirname(path)
    if directory:
        os.makedirs(directory, exist_ok=True)


def build_result(path: str, size_bytes: int) -> Dict[str, Any]:
    size_kb = size_bytes / 1024 if size_bytes else 0
    extension = os.path.splitext(path)[1].lstrip(".").lower() or "zip"
    return {
        "status": "done",
        "path": path,
        "filename": os.path.basename(path),
        "format": extension,
        "size_kb": size_kb,
        "size_bytes": size_bytes,
    }


def download_file(url: str, destination: str, chunk_size: int, timeout: int) -> Dict[str, Any]:
    ensure_parent_directory(destination)
    temp_path = f"{destination}.part"

    if os.path.exists(temp_path):
        os.remove(temp_path)

    if os.path.exists(destination):
        os.remove(destination)

    with requests.get(url, stream=True, timeout=timeout) as response:
        response.raise_for_status()
        with open(temp_path, "wb") as file_handle:
            for chunk in response.iter_content(chunk_size=chunk_size):
                if chunk:
                    file_handle.write(chunk)

    os.replace(temp_path, destination)

    file_size = os.path.getsize(destination) if os.path.exists(destination) else 0
    return build_result(destination, file_size)


def main() -> int:
    args = parse_args()

    try:
        result = download_file(args.url, args.output, args.chunk_size, args.timeout)
    except requests.RequestException as exc:  # pragma: no cover - thin wrapper
        if os.path.exists(args.output):
            os.remove(args.output)
        sys.stderr.write(f"Download failed: {exc}\n")
        return 1
    except Exception as exc:  # pragma: no cover - safety net
        if os.path.exists(args.output):
            os.remove(args.output)
        sys.stderr.write(f"Unexpected error: {exc}\n")
        return 1

    sys.stdout.write(json.dumps(result))
    return 0


if __name__ == "__main__":
    sys.exit(main())

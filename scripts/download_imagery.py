#!/usr/bin/env python
"""Download a remote Sentinel imagery archive and emit metadata as JSON."""
from __future__ import annotations

import argparse
import json
import os
import sys
import time
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
        "--timeout",
        type=int,
        default=600,
        help="Request timeout in seconds (default: 600).",
    )
    parser.add_argument(
        "--retries",
        type=int,
        default=2,
        help="Number of retry attempts for transient failures (default: 2).",
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


def _stream_download(
    url: str,
    destination: str,
    timeout: int,
) -> Dict[str, Any]:
    ensure_parent_directory(destination)
    temp_path = f"{destination}.part"
    forced_restart = False

    while True:
        resume_bytes = os.path.getsize(temp_path) if os.path.exists(temp_path) else 0
        headers = {"Range": f"bytes={resume_bytes}-"} if resume_bytes else {}
        mode = "ab" if resume_bytes else "wb"

        with requests.get(url, stream=True, timeout=timeout, headers=headers) as response:
            if resume_bytes and response.status_code == 416:
                os.remove(temp_path)
                resume_bytes = 0
                forced_restart = False
                continue

            if resume_bytes and response.status_code == 200 and not forced_restart:
                os.remove(temp_path)
                forced_restart = True
                continue

            response.raise_for_status()

            with open(temp_path, mode) as file_handle:
                file_handle.write(response.content)

        break

    os.replace(temp_path, destination)

    file_size = os.path.getsize(destination) if os.path.exists(destination) else 0
    return build_result(destination, file_size)


def download_file(
    url: str,
    destination: str,
    timeout: int,
    retries: int,
) -> Dict[str, Any]:
    attempt = 0
    max_retries = max(retries, 0)
    last_error: Exception | None = None

    while attempt <= max_retries:
        try:
            return _stream_download(url, destination, timeout)
        except requests.RequestException as exc:
            last_error = exc
            attempt += 1
            if attempt > max_retries:
                raise
            time.sleep(min(2**attempt, 30))

    if last_error:
        raise last_error

    return build_result(destination, 0)


def main() -> int:
    args = parse_args()

    temp_path = f"{args.output}.part"

    try:
        result = download_file(
            args.url,
            args.output,
            args.timeout,
            args.retries,
        )
    except requests.RequestException as exc:  # pragma: no cover - thin wrapper
        if os.path.exists(args.output):
            os.remove(args.output)
        if os.path.exists(temp_path):
            os.remove(temp_path)
        sys.stderr.write(f"Download failed: {exc}\n")
        return 1
    except Exception as exc:  # pragma: no cover - safety net
        if os.path.exists(args.output):
            os.remove(args.output)
        if os.path.exists(temp_path):
            os.remove(temp_path)
        sys.stderr.write(f"Unexpected error: {exc}\n")
        return 1

    sys.stdout.write(json.dumps(result))
    return 0


if __name__ == "__main__":
    sys.exit(main())

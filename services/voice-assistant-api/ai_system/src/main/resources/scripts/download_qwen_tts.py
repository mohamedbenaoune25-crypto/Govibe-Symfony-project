#!/usr/bin/env python3
"""
GoVibe — Qwen3-TTS model weight downloader.

The Qwen3-TTS-12Hz-0.6B-CustomVoice (Vivian voice) model weights
are NOT bundled with GoVibe (they are ~1.2 GB).

Run this script once to download them into the HuggingFace cache:

    .venv\\Scripts\\python.exe src\\main\\resources\\scripts\\download_qwen_tts.py

After this completes, the voice agent will use the full Vivian voice
instead of the Microsoft edge-tts / SAPI fallback.

Requires: internet connection, qwen_tts package installed (pip install qwen_tts)
"""

import sys

MODEL_ID = "Qwen/Qwen3-TTS-12Hz-0.6B-CustomVoice"


def main():
    print(f"Downloading Qwen3-TTS model weights: {MODEL_ID}")
    print("This is a ~1.2 GB download — please be patient...\n")

    try:
        from huggingface_hub import snapshot_download
    except ImportError:
        print("ERROR: huggingface_hub not installed.")
        print("  pip install huggingface_hub")
        sys.exit(1)

    try:
        path = snapshot_download(
            repo_id=MODEL_ID,
            ignore_patterns=["*.md", "*.txt"],
        )
        print(f"\n[OK] Model downloaded to: {path}")
        print("\nQwen3-TTS Vivian voice is now ready in GoVibe!")
        print("Restart the application to activate it.")
    except Exception as exc:
        print(f"\nERROR: {type(exc).__name__}: {exc}")
        print("\nTroubleshooting:")
        print("  1. Ensure you have an internet connection")
        print("  2. Run: pip install huggingface_hub")
        print("  3. If on a slow connection, try: HF_HUB_ENABLE_HF_TRANSFER=1 python download_qwen_tts.py")
        sys.exit(1)


if __name__ == "__main__":
    main()

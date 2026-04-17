import os
import sys
import socket

os.environ["HF_ENDPOINT"] = "https://hf-mirror.com"

# ── DNS patch: cas-bridge.xethub.hf.co can't be resolved by local DNS,
#    but the IP is reachable. Intercept getaddrinfo to return the known IPs.
_XETHUB_IPS = ["3.175.86.81", "3.175.86.100", "3.175.86.94", "3.175.86.80"]
_orig_getaddrinfo = socket.getaddrinfo

def _patched_getaddrinfo(host, port, *args, **kwargs):
    if isinstance(host, str) and "xethub" in host:
        print(f"[DNS patch] {host} -> {_XETHUB_IPS[0]}", flush=True)
        results = []
        for ip in _XETHUB_IPS:
            try:
                r = _orig_getaddrinfo(ip, port, *args, **kwargs)
                results.extend(r)
                break  # first working IP is enough
            except Exception:
                continue
        return results if results else _orig_getaddrinfo(host, port, *args, **kwargs)
    return _orig_getaddrinfo(host, port, *args, **kwargs)

socket.getaddrinfo = _patched_getaddrinfo

from huggingface_hub import hf_hub_download, list_repo_files

repo_id = "Qwen/Qwen3-TTS-12Hz-0.6B-CustomVoice"

print("Listing files in repo...")
try:
    files = list(list_repo_files(repo_id))
    print(f"Files to download: {files}")
except Exception as e:
    print(f"Error listing files: {e}")
    sys.exit(1)

# Clear corrupt incomplete blobs before downloading
blob_dir = os.path.expanduser(f"~/.cache/huggingface/hub/models--Qwen--Qwen3-TTS-12Hz-0.6B-CustomVoice/blobs")
for bf in os.listdir(blob_dir):
    if bf.endswith(".incomplete"):
        p = os.path.join(blob_dir, bf)
        os.remove(p)
        print(f"[cleanup] Removed corrupt incomplete blob: {bf}")

print("\nDownloading each file individually...")
for filename in files:
    print(f"\n--- {filename} ---", flush=True)
    try:
        path = hf_hub_download(
            repo_id=repo_id,
            filename=filename,
            force_download=False,
        )
        size = os.path.getsize(path)
        print(f"OK ({size//1024//1024} MB): {path}", flush=True)
    except Exception as e:
        print(f"ERROR: {e}", flush=True)

print("\nAll done!")

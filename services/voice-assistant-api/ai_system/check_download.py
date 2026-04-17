import os
cache = os.path.expanduser("~/.cache/huggingface/hub/models--Qwen--Qwen3-TTS-12Hz-0.6B-CustomVoice/blobs")
if not os.path.exists(cache):
    print("blobs dir not found")
else:
    files = os.listdir(cache)
    print(f"blobs count: {len(files)}")
    total = 0
    for f in files:
        p = os.path.join(cache, f)
        s = os.path.getsize(p)
        total += s
        status = "(incomplete)" if f.endswith(".incomplete") else "DONE"
        print(f"  {f[:20]}... {s//1024//1024} MB {status}")
    print(f"Total: {total//1024//1024} MB")

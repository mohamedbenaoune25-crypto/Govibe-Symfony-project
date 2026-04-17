import os
os.environ["OPENCV_LOG_LEVEL"] = "SILENT"
import sys
_devnull = open(os.devnull, 'w')
sys.stderr = _devnull

import cv2
import numpy as np
import json
import argparse
import time

# ─── Similarity threshold ────────────────────────────────────────────────────
# 0.87 gives ~13 % tolerance → handles moderate lighting / angle change
# (old 0.92 was too tight: only 8 % tolerance on a 128-pixel descriptor)
THRESHOLD = 0.87

# How many consecutive good frames needed to confirm identity
CONFIRM_FRAMES = 2


def extract_face_descriptor(gray_frame, face_cascade):
    """Identical preprocessing to register_face.py — must stay in sync."""
    eq = cv2.equalizeHist(gray_frame)
    faces = face_cascade.detectMultiScale(eq, scaleFactor=1.1, minNeighbors=5, minSize=(80, 80))
    if len(faces) == 0:
        return None, None
    if len(faces) > 1:
        return None, 'multi'
    x, y, w, h = faces[0]
    roi = gray_frame[y:y+h, x:x+w]
    roi = cv2.resize(roi, (16, 8))
    roi = roi.astype(np.float32) / 255.0
    return roi.flatten(), (x, y, x+w, y+h)


def cosine_similarity(a, b):
    dot = float(np.dot(a, b))
    denom = float(np.linalg.norm(a) * np.linalg.norm(b))
    return dot / denom if denom > 0 else 0.0


def draw_similarity_bar(frame, similarity, x, y, w=200, h=14):
    """Draw a horizontal confidence bar below the face box."""
    pct = min(max(similarity, 0.0), 1.0)
    fill = int(pct * w)
    color = (0, 220, 0) if similarity >= THRESHOLD else (0, 120, 255)
    cv2.rectangle(frame, (x, y), (x + w, y + h), (60, 60, 60), -1)
    cv2.rectangle(frame, (x, y), (x + fill, y + h), color, -1)
    cv2.rectangle(frame, (x, y), (x + w, y + h), (200, 200, 200), 1)
    label = f"{int(pct * 100)}%  (need {int(THRESHOLD * 100)}%)"
    cv2.putText(frame, label, (x, y + h + 16),
                cv2.FONT_HERSHEY_SIMPLEX, 0.45, color, 1)


def login_face(encoding_json):
    saved = np.array(json.loads(encoding_json), dtype=np.float32)

    cascade_path = cv2.data.haarcascades + "haarcascade_frontalface_default.xml"
    face_cascade = cv2.CascadeClassifier(cascade_path)
    if face_cascade.empty():
        print(json.dumps({"status": "error", "message": "Haar cascade introuvable."}))
        sys.exit(1)

    cap = cv2.VideoCapture(0, cv2.CAP_DSHOW)
    if not cap.isOpened():
        cap = cv2.VideoCapture(0)
    if not cap.isOpened():
        print(json.dumps({"status": "error", "message": "Impossible d'ouvrir la camera."}))
        sys.exit(1)

    max_duration   = 25          # seconds before timeout
    start_time     = time.time()
    consec_matches = 0           # consecutive frames above threshold
    best_similarity = 0.0
    result_json    = {"status": "error", "message": "Aucun visage reconnu dans le delai imparti."}

    while True:
        elapsed = time.time() - start_time
        if elapsed > max_duration:
            break

        ret, frame = cap.read()
        if not ret:
            continue

        frame = cv2.flip(frame, 1)
        gray  = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        descriptor, info = extract_face_descriptor(gray, face_cascade)

        remaining = int(max_duration - elapsed)
        # ── header bar ──────────────────────────────────────────────────────
        header = f"GoVibe - Face ID   Temps restant: {remaining}s   ('q' = Quitter)"
        cv2.putText(frame, header, (10, 26),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.55, (255, 255, 255), 1, cv2.LINE_AA)

        if isinstance(info, tuple):
            x1, y1, x2, y2 = info
            similarity = cosine_similarity(descriptor, saved)
            if similarity > best_similarity:
                best_similarity = similarity

            matched = similarity >= THRESHOLD
            color   = (0, 220, 0) if matched else (0, 80, 230)
            label   = "RECONNU" if matched else "Verification..."

            cv2.rectangle(frame, (x1, y1), (x2, y2), color, 2)
            cv2.putText(frame, label, (x1, y1 - 12),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 2, cv2.LINE_AA)
            draw_similarity_bar(frame, similarity, x1, y2 + 6)

            if matched:
                consec_matches += 1
            else:
                # allow 1 bad frame before resetting (reduces flicker)
                if consec_matches > 0:
                    consec_matches -= 1

            if consec_matches >= CONFIRM_FRAMES:
                result_json = {"status": "success",
                               "distance": round(1.0 - best_similarity, 4)}
                cv2.waitKey(600)   # brief pause so user sees green box
                break

        elif info == 'multi':
            consec_matches = 0
            cv2.putText(frame, "Plusieurs visages detectes — restez seul.",
                        (10, 56), cv2.FONT_HERSHEY_SIMPLEX, 0.55, (0, 0, 255), 1, cv2.LINE_AA)
        else:
            consec_matches = 0
            cv2.putText(frame, "Aucun visage detecte — rapprochez-vous.",
                        (10, 56), cv2.FONT_HERSHEY_SIMPLEX, 0.55, (0, 140, 255), 1, cv2.LINE_AA)

        cv2.imshow("GoVibe - Connexion Face ID", frame)
        if cv2.waitKey(1) & 0xFF == ord('q'):
            result_json = {"status": "error", "message": "Annule par l'utilisateur."}
            break

    cap.release()
    cv2.destroyAllWindows()
    print(json.dumps(result_json))


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--encoding', type=str, required=True)
    args = parser.parse_args()
    try:
        login_face(args.encoding)
    except Exception as e:
        import traceback as _tb
        print(json.dumps({"status": "error",
                          "message": str(e),
                          "detail": _tb.format_exc()}))
        sys.exit(1)

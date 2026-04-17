import os
os.environ["OPENCV_LOG_LEVEL"] = "SILENT"
import sys
_devnull = open(os.devnull, 'w')
sys.stderr = _devnull

import cv2
import numpy as np
import json

# Number of sample frames to average when registering (more = more robust)
SAMPLE_COUNT = 5


def extract_face_descriptor(gray_frame, face_cascade):
    """Identical preprocessing to login_face.py — must stay in sync."""
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


def register_face():
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

    samples = []

    while True:
        ret, frame = cap.read()
        if not ret:
            continue
        frame = cv2.flip(frame, 1)
        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        descriptor, info = extract_face_descriptor(gray, face_cascade)

        # ── UI overlay ────────────────────────────────────────────────────────
        collected = len(samples)
        header = (f"GoVibe Face ID — Enregistrement   "
                  f"{collected}/{SAMPLE_COUNT} captures")
        cv2.putText(frame, header, (10, 26),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.55, (255, 255, 255), 1, cv2.LINE_AA)

        if isinstance(info, tuple):
            x1, y1, x2, y2 = info
            cv2.rectangle(frame, (x1, y1), (x2, y2), (0, 220, 0), 2)
            hint = f"'c'=Capturer ({collected}/{SAMPLE_COUNT})  'q'=Quitter"
            cv2.putText(frame, hint, (x1, y1 - 10),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 220, 0), 1, cv2.LINE_AA)
        elif info == 'multi':
            cv2.putText(frame, "Plusieurs visages — restez seul!",
                        (10, 56), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 0, 255), 1, cv2.LINE_AA)
        else:
            cv2.putText(frame, "Aucun visage detecte — approchez-vous.",
                        (10, 56), cv2.FONT_HERSHEY_SIMPLEX, 0.55, (0, 140, 255), 1, cv2.LINE_AA)

        cv2.imshow("GoVibe - Configuration Face ID", frame)

        key = cv2.waitKey(1) & 0xFF
        if key == ord('q'):
            break
        elif key == ord('c') and descriptor is not None and info != 'multi':
            samples.append(descriptor)
            if len(samples) >= SAMPLE_COUNT:
                break   # enough samples collected

    cap.release()
    cv2.destroyAllWindows()

    if samples:
        # Average all captured descriptors → single robust encoding
        avg_encoding = np.mean(np.stack(samples, axis=0), axis=0).tolist()
        print(json.dumps({"status": "success", "encoding": avg_encoding}))
    else:
        print(json.dumps({"status": "error", "message": "Capture annulee."}))


if __name__ == '__main__':
    try:
        register_face()
    except Exception as e:
        import traceback as _tb
        print(json.dumps({"status": "error",
                          "message": str(e),
                          "detail": _tb.format_exc()}))
        sys.exit(1)

"""
GoVibe Face Recognition Microservice
=====================================
FastAPI microservice for face encoding extraction and verification.
Uses MediaPipe for face detection and InsightFace (ArcFace/ONNX) for
128-d embedding extraction. No dlib compilation required.

Endpoints:
    POST /encode-face   — Extract face embedding from base64 image
    POST /verify-face   — Compare a face image against a stored embedding
    GET  /health        — Health check

Port: 5002 (configurable via FACE_API_PORT env var)
"""

import base64
import io
import logging
import os
from typing import List, Optional

import numpy as np
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from PIL import Image
from pydantic import BaseModel

# ─── Logging ───────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("face_api")

# ─── FastAPI App ───────────────────────────────────────────────────────
app = FastAPI(
    title="GoVibe Face Recognition API",
    description="Microservice for face encoding and verification (MediaPipe + ArcFace)",
    version="2.0.0",
)

# CORS — Allow Symfony dev server
app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "http://127.0.0.1:8000",
        "http://localhost:8000",
        "https://127.0.0.1:8000",
    ],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ─── Constants ─────────────────────────────────────────────────────────
COSINE_THRESHOLD = 0.45      # Cosine distance threshold (lower = stricter)
MAX_IMAGE_SIZE = 10_000_000  # 10 MB max image size

# ─── Lazy-loaded models (loaded on first request) ──────────────────────
_mp_face_detection = None
_insightface_model = None


def _get_mediapipe_detector():
    """Lazy-load MediaPipe face detector."""
    global _mp_face_detection
    if _mp_face_detection is None:
        import mediapipe as mp
        _mp_face_detection = mp.solutions.face_detection.FaceDetection(
            model_selection=1,      # 0 = short-range, 1 = full-range
            min_detection_confidence=0.5,
        )
        logger.info("MediaPipe face detector loaded")
    return _mp_face_detection


def _get_insightface_model():
    """Lazy-load InsightFace ArcFace model for face embeddings."""
    global _insightface_model
    if _insightface_model is None:
        try:
            import insightface
            from insightface.app import FaceAnalysis
            _insightface_model = FaceAnalysis(
                name='buffalo_l',
                providers=['CPUExecutionProvider'],
            )
            _insightface_model.prepare(ctx_id=-1, det_size=(640, 640))
            logger.info("InsightFace ArcFace model loaded (buffalo_l)")
        except Exception as e:
            logger.error(f"Failed to load InsightFace model: {e}")
            raise
    return _insightface_model


# ─── Request / Response Models ─────────────────────────────────────────
class EncodeFaceRequest(BaseModel):
    image: str  # Base64-encoded image (with or without data URI prefix)


class EncodeFaceResponse(BaseModel):
    success: bool
    encoding: Optional[List[float]] = None
    face_count: int = 0
    message: str = ""


class VerifyFaceRequest(BaseModel):
    image: str
    stored_encoding: List[float]


class VerifyFaceResponse(BaseModel):
    success: bool
    match: bool = False
    distance: float = 1.0
    confidence: int = 0
    message: str = ""


class HealthResponse(BaseModel):
    status: str
    service: str
    version: str


# ─── Utility Functions ─────────────────────────────────────────────────
def decode_base64_image(base64_str: str) -> np.ndarray:
    """
    Decode a base64 string into a numpy array (RGB image).
    Handles both raw base64 and data URI format.
    """
    if "," in base64_str:
        base64_str = base64_str.split(",", 1)[1]

    try:
        image_bytes = base64.b64decode(base64_str)
    except Exception as e:
        raise ValueError(f"Invalid base64 encoding: {e}")

    if len(image_bytes) > MAX_IMAGE_SIZE:
        raise ValueError(f"Image too large: {len(image_bytes)} bytes (max {MAX_IMAGE_SIZE})")

    try:
        pil_image = Image.open(io.BytesIO(image_bytes))
        pil_image = pil_image.convert("RGB")

        # Resize if very large
        max_dim = 1024
        if max(pil_image.size) > max_dim:
            ratio = max_dim / max(pil_image.size)
            new_size = (int(pil_image.width * ratio), int(pil_image.height * ratio))
            pil_image = pil_image.resize(new_size, Image.LANCZOS)

        return np.array(pil_image)
    except Exception as e:
        raise ValueError(f"Failed to process image: {e}")


def extract_face_encoding(image_array: np.ndarray) -> tuple:
    """
    Detect faces using InsightFace and extract embedding from the largest face.

    Returns:
        tuple: (encoding_list, face_count, error_message)
    """
    model = _get_insightface_model()

    # InsightFace expects BGR, convert from RGB
    import cv2
    bgr_image = cv2.cvtColor(image_array, cv2.COLOR_RGB2BGR)

    faces = model.get(bgr_image)
    face_count = len(faces)

    if face_count == 0:
        return None, 0, "Aucun visage détecté. Positionnez votre visage face à la caméra."

    # Use the face with the largest bounding box
    if face_count > 1:
        faces = sorted(faces, key=lambda f: (f.bbox[2] - f.bbox[0]) * (f.bbox[3] - f.bbox[1]), reverse=True)
        logger.warning(f"Multiple faces detected ({face_count}), using largest face")

    best_face = faces[0]

    if best_face.embedding is None:
        return None, face_count, "Impossible d'extraire les caractéristiques du visage. Essayez avec un meilleur éclairage."

    # Normalize embedding to unit vector for cosine similarity
    embedding = best_face.embedding
    norm = np.linalg.norm(embedding)
    if norm > 0:
        embedding = embedding / norm

    encoding_list = embedding.tolist()

    return encoding_list, face_count, ""


def cosine_distance(vec_a: np.ndarray, vec_b: np.ndarray) -> float:
    """Compute cosine distance between two vectors (0 = identical, 2 = opposite)."""
    dot_product = np.dot(vec_a, vec_b)
    norm_a = np.linalg.norm(vec_a)
    norm_b = np.linalg.norm(vec_b)
    if norm_a == 0 or norm_b == 0:
        return 1.0
    similarity = dot_product / (norm_a * norm_b)
    return float(1.0 - similarity)


# ─── Endpoints ─────────────────────────────────────────────────────────
@app.get("/health", response_model=HealthResponse)
async def health_check():
    """Health check endpoint."""
    return HealthResponse(
        status="ok",
        service="GoVibe Face Recognition API",
        version="2.0.0",
    )


@app.post("/encode-face", response_model=EncodeFaceResponse)
async def encode_face(request: EncodeFaceRequest):
    """
    Extract a face embedding from a base64 image.

    Used during:
        - User registration (mandatory face enrollment)
        - Profile face re-enrollment
    """
    logger.info("Received encode-face request")

    try:
        image_array = decode_base64_image(request.image)
    except ValueError as e:
        logger.error(f"Image decode error: {e}")
        return EncodeFaceResponse(success=False, message=str(e))

    try:
        encoding, face_count, error_msg = extract_face_encoding(image_array)
    except Exception as e:
        logger.error(f"Face extraction error: {e}")
        return EncodeFaceResponse(
            success=False,
            message="Erreur interne du service de reconnaissance faciale.",
        )

    if encoding is None:
        logger.warning(f"Face encoding failed: {error_msg}")
        return EncodeFaceResponse(
            success=False,
            face_count=face_count,
            message=error_msg,
        )

    logger.info(f"Face encoded successfully ({face_count} face(s), {len(encoding)}-d vector)")

    return EncodeFaceResponse(
        success=True,
        encoding=encoding,
        face_count=face_count,
        message="Visage encodé avec succès.",
    )


@app.post("/verify-face", response_model=VerifyFaceResponse)
async def verify_face(request: VerifyFaceRequest):
    """
    Verify a face image against a stored embedding.

    Used during:
        - Face ID login (email + face)
        - MFA verification (alternative to OTP)
    """
    logger.info("Received verify-face request")

    stored_len = len(request.stored_encoding)
    if stored_len < 64:
        logger.error(f"Invalid stored encoding length: {stored_len}")
        raise HTTPException(
            status_code=400,
            detail=f"stored_encoding dimension too small (got {stored_len})",
        )

    try:
        image_array = decode_base64_image(request.image)
    except ValueError as e:
        logger.error(f"Image decode error: {e}")
        return VerifyFaceResponse(success=False, message=str(e))

    try:
        live_encoding, face_count, error_msg = extract_face_encoding(image_array)
    except Exception as e:
        logger.error(f"Face extraction error: {e}")
        return VerifyFaceResponse(
            success=False,
            message="Erreur interne du service de reconnaissance faciale.",
        )

    if live_encoding is None:
        logger.warning(f"Face extraction failed during verify: {error_msg}")
        return VerifyFaceResponse(success=False, message=error_msg)

    # Compute cosine distance
    live_np = np.array(live_encoding)
    stored_np = np.array(request.stored_encoding)
    distance = cosine_distance(live_np, stored_np)

    is_match = distance < COSINE_THRESHOLD

    # Convert to confidence (0 distance = 100%, 1.0 = 0%)
    confidence = max(0, min(100, int((1.0 - distance) * 100)))

    logger.info(
        f"Face verification: match={is_match}, cosine_dist={distance:.4f}, "
        f"confidence={confidence}%, threshold={COSINE_THRESHOLD}"
    )

    return VerifyFaceResponse(
        success=True,
        match=is_match,
        distance=round(distance, 4),
        confidence=confidence,
        message="Visage vérifié avec succès." if is_match else "Visage non reconnu.",
    )


# ─── Entry Point ───────────────────────────────────────────────────────
if __name__ == "__main__":
    import uvicorn

    port = int(os.environ.get("FACE_API_PORT", 5002))
    logger.info(f"Starting GoVibe Face Recognition API on port {port}")
    uvicorn.run(app, host="0.0.0.0", port=port, log_level="info")

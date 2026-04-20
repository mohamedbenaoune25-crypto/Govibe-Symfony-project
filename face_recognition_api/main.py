"""
GoVibe Face Recognition Microservice
FastAPI application providing face encoding and verification endpoints.
Runs on port 5002 and communicates with the Symfony backend.
"""

import base64
import io
import logging
from typing import Optional

import numpy as np
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("face_recognition_api")

app = FastAPI(
    title="GoVibe Face Recognition API",
    description="Microservice for face encoding and verification",
    version="1.0.0",
)

# CORS middleware for local development
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://127.0.0.1:8000", "http://localhost:8000"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Try to import face_recognition, fall back to a simulated mode
try:
    import face_recognition
    FACE_RECOGNITION_AVAILABLE = True
    logger.info("face_recognition library loaded successfully")
except ImportError:
    FACE_RECOGNITION_AVAILABLE = False
    logger.warning(
        "face_recognition library not available. Running in SIMULATED mode. "
        "Install with: pip install face_recognition"
    )

try:
    from PIL import Image
    PIL_AVAILABLE = True
except ImportError:
    PIL_AVAILABLE = False
    logger.warning("Pillow not available. Install with: pip install Pillow")


# ─── Request / Response Models ───────────────────────────────────────

class EncodeRequest(BaseModel):
    image: str  # Base64-encoded image (with or without data URI prefix)


class EncodeResponse(BaseModel):
    success: bool
    encoding: Optional[list] = None
    face_count: int = 0
    message: str = ""


class VerifyRequest(BaseModel):
    image: str  # Base64-encoded image
    stored_encoding: list  # Previously stored face encoding


class VerifyResponse(BaseModel):
    success: bool
    match: bool = False
    distance: float = 1.0
    confidence: int = 0
    message: str = ""


class HealthResponse(BaseModel):
    status: str
    face_recognition_available: bool
    mode: str


# ─── Helper Functions ────────────────────────────────────────────────

def decode_base64_image(base64_string: str) -> np.ndarray:
    """Decode a base64 image string to a numpy array for face_recognition."""
    # Strip data URI prefix if present
    if "," in base64_string:
        base64_string = base64_string.split(",", 1)[1]

    image_bytes = base64.b64decode(base64_string)

    if not PIL_AVAILABLE:
        raise RuntimeError("Pillow is required to process images")

    image = Image.open(io.BytesIO(image_bytes))

    # Convert to RGB if needed (e.g., RGBA or grayscale)
    if image.mode != "RGB":
        image = image.convert("RGB")

    return np.array(image)


# ─── Endpoints ───────────────────────────────────────────────────────

@app.get("/health", response_model=HealthResponse)
async def health_check():
    """Health check endpoint."""
    return HealthResponse(
        status="ok",
        face_recognition_available=FACE_RECOGNITION_AVAILABLE,
        mode="live" if FACE_RECOGNITION_AVAILABLE else "simulated",
    )


@app.post("/encode-face", response_model=EncodeResponse)
async def encode_face(request: EncodeRequest):
    """
    Extract a 128-dimensional face encoding from a base64 image.
    Used during user registration and face enrollment.
    """
    try:
        image_array = decode_base64_image(request.image)
    except Exception as e:
        logger.error(f"Failed to decode image: {e}")
        return EncodeResponse(
            success=False,
            face_count=0,
            message="Image invalide. Veuillez envoyer une image en base64 valide.",
        )

    if FACE_RECOGNITION_AVAILABLE:
        # Real face detection + encoding
        face_locations = face_recognition.face_locations(image_array, model="hog")
        face_count = len(face_locations)

        if face_count == 0:
            return EncodeResponse(
                success=False,
                face_count=0,
                message="Aucun visage détecté. Veuillez réessayer avec une photo claire de votre visage.",
            )

        if face_count > 1:
            return EncodeResponse(
                success=False,
                face_count=face_count,
                message=f"{face_count} visages détectés. Veuillez fournir une photo avec un seul visage.",
            )

        encodings = face_recognition.face_encodings(image_array, face_locations)
        encoding_list = encodings[0].tolist()

        logger.info(f"Face encoded successfully (dimensions: {len(encoding_list)})")

        return EncodeResponse(
            success=True,
            encoding=encoding_list,
            face_count=1,
            message="Visage encodé avec succès.",
        )
    else:
        # Simulated mode — return a deterministic fake encoding based on image hash
        # Use 512 dimensions to match face_recognition_models default
        import hashlib
        ENCODING_DIM = 512
        image_hash = hashlib.md5(request.image[:100].encode()).hexdigest()
        np.random.seed(int(image_hash[:8], 16) % (2**31))
        fake_encoding = np.random.uniform(-0.3, 0.3, ENCODING_DIM).tolist()

        logger.info(f"Face encoded in SIMULATED mode (dimensions: {ENCODING_DIM})")

        return EncodeResponse(
            success=True,
            encoding=fake_encoding,
            face_count=1,
            message="Visage encodé avec succès (mode simulé).",
        )


@app.post("/verify-face", response_model=VerifyResponse)
async def verify_face(request: VerifyRequest):
    """
    Verify a face image against a stored encoding.
    Used during Face ID login and MFA verification.
    """
    try:
        image_array = decode_base64_image(request.image)
    except Exception as e:
        logger.error(f"Failed to decode image: {e}")
        return VerifyResponse(
            success=False,
            message="Image invalide.",
        )

    stored_encoding = np.array(request.stored_encoding)

    if FACE_RECOGNITION_AVAILABLE:
        # Real verification
        face_locations = face_recognition.face_locations(image_array, model="hog")

        if len(face_locations) == 0:
            return VerifyResponse(
                success=True,
                match=False,
                distance=1.0,
                confidence=0,
                message="Aucun visage détecté dans l'image.",
            )

        encodings = face_recognition.face_encodings(image_array, face_locations)
        current_encoding = encodings[0]

        # Calculate Euclidean distance
        distance = float(np.linalg.norm(current_encoding - stored_encoding))
        threshold = 0.6  # Standard face_recognition threshold
        match = distance <= threshold
        confidence = max(0, min(100, int((1 - distance / threshold) * 100))) if match else max(0, int((1 - distance) * 50))

        logger.info(f"Face verification: match={match}, distance={distance:.4f}, confidence={confidence}%")

        return VerifyResponse(
            success=True,
            match=match,
            distance=round(distance, 4),
            confidence=confidence,
            message="Identité confirmée !" if match else "Visage non reconnu.",
        )
    else:
        # Simulated mode — match dimensions to whatever is stored in DB
        import hashlib
        encoding_dim = len(request.stored_encoding)
        image_hash = hashlib.md5(request.image[:100].encode()).hexdigest()
        np.random.seed(int(image_hash[:8], 16) % (2**31))
        fake_encoding = np.random.uniform(-0.3, 0.3, encoding_dim)

        distance = float(np.linalg.norm(fake_encoding - stored_encoding))
        # In simulated mode, be lenient
        match = distance < 1.0
        confidence = max(0, min(100, int((1 - distance) * 80)))

        logger.info(f"Face verification (SIMULATED): match={match}, distance={distance:.4f}")

        return VerifyResponse(
            success=True,
            match=match,
            distance=round(distance, 4),
            confidence=confidence,
            message="Identité confirmée (mode simulé) !" if match else "Visage non reconnu (mode simulé).",
        )


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=5002, reload=True)

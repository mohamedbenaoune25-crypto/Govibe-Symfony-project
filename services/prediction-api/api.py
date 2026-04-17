"""
GoVibe AI Model Inference Service
==================================
FastAPI server exposing REST endpoints for ML model predictions.
Used by Symfony to get booking and weather predictions.

Endpoints:
    POST /api/predict          — Generic prediction
    POST /api/predict/booking  — Booking probability (xgb_model)
    POST /api/predict/weather  — Weather impact (xgb_weather_model)
    POST /api/predict/batch    — Batch predictions
    GET  /api/health           — Health check
    GET  /api/models           — List loaded models
"""

import logging
import os
from typing import Dict, List, Optional, Any
from contextlib import asynccontextmanager

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
import uvicorn

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

from core.loader import model_loader
from core.predictor import predictor


# ═══════════════════════════════════════════════════════════════════
# PYDANTIC MODELS
# ═══════════════════════════════════════════════════════════════════

class PredictRequest(BaseModel):
    model_name: str = Field(..., description="Name of the model to use")
    features: List[float] = Field(..., description="Input features array")
    return_proba: Optional[bool] = Field(False, description="Return probabilities")

class BookingPredictRequest(BaseModel):
    """Booking prediction — matches Vol entity fields."""
    days_before_departure: int = Field(..., ge=0, le=365)
    price: float = Field(..., ge=0)
    seats_left: int = Field(..., ge=0)
    is_weekend: int = Field(0, ge=0, le=1)
    is_holiday: int = Field(0, ge=0, le=1)
    seat_type: int = Field(0, ge=0, le=2, description="0=economy, 1=business, 2=first")

    class Config:
        json_schema_extra = {
            "example": {
                "days_before_departure": 14,
                "price": 250,
                "seats_left": 45,
                "is_weekend": 1,
                "is_holiday": 0,
                "seat_type": 0
            }
        }

class WeatherPredictRequest(BaseModel):
    """Weather impact prediction."""
    features: List[float] = Field(..., description="Weather feature vector")

    class Config:
        json_schema_extra = {
            "example": {
                "features": [72.5, 65.3, 1013.2, 45.0]
            }
        }

class BatchPredictRequest(BaseModel):
    model_name: str
    features_list: List[List[float]]

from typing import Union
class FlightAnalyticsItem(BaseModel):
    id: Union[int, str]
    booking_features: List[float]
    weather_features: List[float]

class FlightAnalyticsBatchRequest(BaseModel):
    flights: List[FlightAnalyticsItem]

class HealthResponse(BaseModel):
    status: str
    models_loaded: int
    available_models: List[str]
    message: str

class PredictResponse(BaseModel):
    success: bool
    model: Optional[str] = None
    prediction: Optional[Any] = None
    type: Optional[str] = None
    confidence: Optional[float] = None
    error: Optional[str] = None


# ═══════════════════════════════════════════════════════════════════
# LIFESPAN
# ═══════════════════════════════════════════════════════════════════

@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("=" * 60)
    logger.info("GoVibe AI Service Starting...")
    logger.info("=" * 60)

    try:
        models_dir = os.path.join(os.path.dirname(__file__), "models")
        loaded_models = model_loader.load_models(models_dir)
        logger.info(f"Service ready with {len(loaded_models)} models")
        for name in loaded_models:
            logger.info(f"  -> {name}")
    except Exception as e:
        logger.error(f"Failed to load models: {str(e)}")
        raise

    yield

    logger.info("GoVibe AI Service Shutting Down...")


# ═══════════════════════════════════════════════════════════════════
# FASTAPI APP
# ═══════════════════════════════════════════════════════════════════

app = FastAPI(
    title="GoVibe AI Inference Service",
    description="ML model predictions for flights and bookings",
    version="2.0.0",
    lifespan=lifespan
)

# CORS — Allow Symfony dev server
app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "http://127.0.0.1:8000",
        "http://localhost:8000",
        "http://127.0.0.1:8001",
        "http://localhost:8001",
        "http://127.0.0.1:8080",
        "http://localhost:8080",
        "http://127.0.0.1:9000",
        "http://localhost:9000",
        "http://localhost",
        "http://127.0.0.1",
        "*", 
    ],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# ═══════════════════════════════════════════════════════════════════
# ROUTES
# ═══════════════════════════════════════════════════════════════════

@app.get("/api/health", response_model=HealthResponse)
async def health_check():
    models = model_loader.get_all_models()
    return HealthResponse(
        status="healthy",
        models_loaded=len(models),
        available_models=list(models.keys()),
        message=f"GoVibe AI Service running with {len(models)} models"
    )


@app.get("/api/models")
async def list_models():
    try:
        models_info = predictor.get_available_models()
        return {"success": True, "models": models_info, "count": len(models_info)}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


# ── Booking prediction ────────────────────────────────────────────
@app.post("/api/predict/booking")
async def predict_booking(request: BookingPredictRequest):
    """
    Predict booking probability for a flight checkout.
    Uses xgb_model trained on: days_before_departure, price, seats_left,
    is_weekend, is_holiday, seat_type → booked (0/1)
    """
    try:
        if not model_loader.model_exists('xgb_model'):
            raise HTTPException(status_code=503, detail="Booking model not loaded")

        result = predictor.predict_booking(
            days_before=request.days_before_departure,
            price=request.price,
            seats_left=request.seats_left,
            is_weekend=request.is_weekend,
            is_holiday=request.is_holiday,
            seat_type=request.seat_type,
        )
        return result

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Booking prediction error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


# ── Weather prediction ────────────────────────────────────────────
@app.post("/api/predict/weather")
async def predict_weather(request: WeatherPredictRequest):
    """
    Predict weather impact on flights.
    Uses xgb_weather_model.
    """
    try:
        if not model_loader.model_exists('xgb_weather_model'):
            raise HTTPException(status_code=503, detail="Weather model not loaded")

        result = predictor.predict_weather(request.features)
        return result

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Weather prediction error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


# ── Generic prediction ────────────────────────────────────────────
@app.post("/api/predict", response_model=PredictResponse)
async def predict(request: PredictRequest):
    try:
        if not model_loader.model_exists(request.model_name):
            available = list(model_loader.get_all_models().keys())
            raise HTTPException(
                status_code=404,
                detail=f"Model '{request.model_name}' not found. Available: {available}"
            )
        result = predictor.predict(request.model_name, request.features, return_proba=request.return_proba)
        return PredictResponse(**result)
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Prediction error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


# ── Batch prediction ──────────────────────────────────────────────
@app.post("/api/predict/batch")
async def batch_predict(request: BatchPredictRequest):
    try:
        if not model_loader.model_exists(request.model_name):
            available = list(model_loader.get_all_models().keys())
            raise HTTPException(
                status_code=404,
                detail=f"Model '{request.model_name}' not found. Available: {available}"
            )
        results = predictor.batch_predict(request.model_name, request.features_list)
        return {"success": True, "model": request.model_name, "total": len(results), "predictions": results}
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Batch prediction error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/api/predict/analytics-batch")
async def predict_analytics_batch(request: FlightAnalyticsBatchRequest):
    try:
        flights_data = [
            {"id": f.id, "booking_features": f.booking_features, "weather_features": f.weather_features}
            for f in request.flights
        ]
        results = predictor.batch_predict_analytics(flights_data)
        return {"success": True, "predictions": results}
    except Exception as e:
        logger.error(f"Analytics batch prediction error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/")
async def root():
    return {
        "service": "GoVibe AI Inference",
        "version": "2.0.0",
        "docs": "/docs",
        "endpoints": {
            "booking": "/api/predict/booking",
            "weather": "/api/predict/weather",
            "generic": "/api/predict",
            "health": "/api/health",
            "models": "/api/models",
        }
    }


# ═══════════════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════════════

if __name__ == "__main__":
    host = os.getenv("AI_HOST", "127.0.0.1")
    port = int(os.getenv("AI_PORT", 8000))
    workers = int(os.getenv("AI_WORKERS", 1))

    logger.info(f"Starting GoVibe AI Service at http://{host}:{port}")
    logger.info(f"API Docs: http://{host}:{port}/docs")

    uvicorn.run(
        "api:app",
        host=host,
        port=port,
        workers=workers,
        reload=os.getenv("AI_RELOAD", "false").lower() == "true",
        log_level="info"
    )

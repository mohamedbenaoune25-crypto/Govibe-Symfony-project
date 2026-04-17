"""
GoVibe AI Risk Scoring Micro-API
Flask server that loads a trained scikit-learn Logistic Regression model
and exposes a POST /predict-risk endpoint for authentication risk scoring.
"""

from flask import Flask, request, jsonify
import joblib
import numpy as np
import os
import sys

app = Flask(__name__)

# Load the trained model at startup
MODEL_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "risk_model.pkl")

try:
    model = joblib.load(MODEL_PATH)
    print(f"✅ [RiskAPI] Model loaded from {MODEL_PATH}")
except Exception as e:
    print(f"❌ [RiskAPI] Failed to load model: {e}")
    sys.exit(1)

FEATURE_ORDER = ["failed_attempts", "new_device", "new_country", "unusual_time", "is_admin"]


@app.route("/health", methods=["GET"])
def health():
    """Health check endpoint."""
    return jsonify({"status": "ok", "model_loaded": model is not None})


@app.route("/predict-risk", methods=["POST"])
def predict_risk():
    """
    Predict authentication risk.
    
    Input JSON:
        {
            "failed_attempts": int,
            "new_device": 0|1,
            "new_country": 0|1,
            "unusual_time": 0|1,
            "is_admin": 0|1
        }
    
    Output JSON:
        {
            "risk_prediction": 0|1,
            "risk_probability": float (probability of class 1 = risky)
        }
    """
    try:
        data = request.get_json()
        if data is None:
            return jsonify({"error": "No JSON body provided"}), 400

        # Validate all features are present
        missing = [f for f in FEATURE_ORDER if f not in data]
        if missing:
            return jsonify({"error": f"Missing features: {missing}"}), 400

        # Build feature array in the correct order
        features = np.array([[
            int(data["failed_attempts"]),
            int(data["new_device"]),
            int(data["new_country"]),
            int(data["unusual_time"]),
            int(data["is_admin"])
        ]])

        # Predict
        prediction = model.predict(features)[0]
        probabilities = model.predict_proba(features)[0]
        risk_probability = float(probabilities[1])  # probability of class 1 (risky)

        result = {
            "risk_prediction": int(prediction),
            "risk_probability": round(risk_probability, 6)
        }

        print(f"📊 [RiskAPI] Features={data} → prediction={prediction}, probability={risk_probability:.4f}")
        return jsonify(result)

    except Exception as e:
        print(f"❌ [RiskAPI] Error: {e}")
        return jsonify({"error": str(e)}), 500


if __name__ == "__main__":
    print("🚀 [RiskAPI] Starting GoVibe Risk Scoring API on http://localhost:5001")
    app.run(host="0.0.0.0", port=5001, debug=False)

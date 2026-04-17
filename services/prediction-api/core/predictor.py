"""
Predictor - Handles ML model inference and predictions.
Provides a clean interface to make predictions using loaded models.
"""

import logging
import numpy as np
from typing import Any, Dict, List, Union
from .loader import model_loader

logger = logging.getLogger(__name__)


class Predictor:
    """Handle model inference and predictions."""

    def __init__(self, loader=None):
        self.loader = loader or model_loader

    # ── Generic predict ─────────────────────────────────────────────
    def predict(self, model_name: str, features: Union[List, np.ndarray],
                return_proba: bool = False) -> Dict[str, Any]:
        try:
            model = self.loader.get_model(model_name)

            if isinstance(features, list):
                features = np.array(features)
            if features.ndim == 1:
                features = features.reshape(1, -1)

            if hasattr(model, 'predict_proba') and return_proba:
                prediction = model.predict(features)
                proba = model.predict_proba(features)
                result = {
                    'success': True,
                    'model': model_name,
                    'type': 'classification',
                    'prediction': prediction.tolist() if isinstance(prediction, np.ndarray) else prediction,
                    'proba': proba.tolist() if isinstance(proba, np.ndarray) else proba,
                    'confidence': float(np.max(proba[0])) if proba.shape[0] > 0 else 0.0,
                    'error': None
                }
            else:
                prediction = model.predict(features)
                model_type = 'classification' if hasattr(model, 'classes_') else 'regression'
                result = {
                    'success': True,
                    'model': model_name,
                    'type': model_type,
                    'prediction': prediction.tolist() if isinstance(prediction, np.ndarray) else prediction,
                    'error': None
                }

            logger.info(f"Prediction successful: {model_name}")
            return result

        except ValueError as e:
            error_msg = f"Input validation error: {str(e)}"
            logger.error(error_msg)
            return {'success': False, 'model': model_name, 'type': None, 'prediction': None, 'error': error_msg}
        except Exception as e:
            error_msg = f"Prediction failed: {str(e)}"
            logger.error(error_msg)
            return {'success': False, 'model': model_name, 'type': None, 'prediction': None, 'error': error_msg}

    # ── Booking prediction (xgb_model) ──────────────────────────────
    # Features: days_before_departure, price, seats_left, is_weekend, is_holiday, seat_type
    # Target: booked (0/1)
    def predict_booking(self, days_before: int, price: float, seats_left: int,
                        is_weekend: int, is_holiday: int, seat_type: int) -> Dict[str, Any]:
        """Predict booking probability using the xgb_model."""
        features = [float(days_before), float(price), float(seats_left),
                     float(is_weekend), float(is_holiday), float(seat_type)]
        result = self.predict('xgb_model', features, return_proba=True)

        if result['success']:
            pred_value = result['prediction'][0] if isinstance(result['prediction'], list) else result['prediction']
            proba_list = result.get('proba', [[0.5, 0.5]])
            booking_proba = float(proba_list[0][1]) if len(proba_list[0]) > 1 else 0.5

            return {
                'success': True,
                'booked': bool(pred_value),
                'booking_probability': round(booking_proba, 4),
                'confidence': round(result.get('confidence', 0.0), 4),
                'risk_level': 'high' if booking_proba > 0.7 else ('medium' if booking_proba > 0.4 else 'low'),
                'features_used': {
                    'days_before_departure': days_before,
                    'price': price,
                    'seats_left': seats_left,
                    'is_weekend': is_weekend,
                    'is_holiday': is_holiday,
                    'seat_type': seat_type,
                }
            }
        return result

    # ── Weather prediction (xgb_weather_model) ──────────────────────
    def predict_weather(self, features: List[float]) -> Dict[str, Any]:
        """Predict weather impact using xgb_weather_model."""
        result = self.predict('xgb_weather_model', features, return_proba=True)

        if result['success']:
            pred_value = result['prediction'][0] if isinstance(result['prediction'], list) else result['prediction']
            proba_list = result.get('proba', [[0.5, 0.5]])

            # For classification: probability of disruption (class 1)
            if isinstance(proba_list[0], list) and len(proba_list[0]) > 1:
                impact_score = float(proba_list[0][1])
            else:
                impact_score = float(pred_value) if isinstance(pred_value, (int, float)) else 0.5

            if impact_score >= 0.7:
                status = 'danger'
                label = 'High Risk'
            elif impact_score >= 0.4:
                status = 'warning'
                label = 'Caution'
            else:
                status = 'safe'
                label = 'Clear'

            return {
                'success': True,
                'weather_impact': round(impact_score, 4),
                'prediction': int(pred_value) if isinstance(pred_value, (int, float, np.integer)) else pred_value,
                'status': status,
                'label': label,
                'confidence': round(result.get('confidence', 0.0), 4),
            }
        return result

    # ── Batch ───────────────────────────────────────────────────────
    def batch_predict(self, model_name: str, features_list: List) -> List[Dict[str, Any]]:
        results = []
        for i, features in enumerate(features_list):
            try:
                result = self.predict(model_name, features)
                results.append(result)
            except Exception as e:
                results.append({'success': False, 'sample_index': i, 'error': str(e)})
        return results

    def batch_predict_analytics(self, flights_data: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Vectorized batch prediction for both booking and weather."""
        if not flights_data:
            return []

        booking_matrix = [f['booking_features'] for f in flights_data]
        weather_matrix = [f['weather_features'] for f in flights_data]

        booking_results = [None] * len(flights_data)
        weather_results = [None] * len(flights_data)

        # 1. Booking Predictions (vectorized)
        try:
            booking_model = self.loader.get_model('xgb_model')
            X_book = np.array(booking_matrix, dtype=float)
            if hasattr(booking_model, 'predict_proba'):
                book_probas = booking_model.predict_proba(X_book)
                for i, proba in enumerate(book_probas):
                    booking_proba = float(proba[1]) if len(proba) > 1 else float(proba[0])
                    booking_results[i] = {
                        'booking_probability': round(booking_proba, 4),
                        'risk_level': 'high' if booking_proba > 0.7 else ('medium' if booking_proba > 0.4 else 'low')
                    }
        except Exception as e:
            logger.error(f"Booking batch error: {e}")

        # 2. Weather Predictions (vectorized)
        try:
            weather_model = self.loader.get_model('xgb_weather_model')
            X_weather = np.array(weather_matrix, dtype=float)
            if hasattr(weather_model, 'predict_proba'):
                weather_probas = weather_model.predict_proba(X_weather)
                for i, proba in enumerate(weather_probas):
                    impact_score = float(proba[1]) if len(proba) > 1 else float(proba[0])
                    weather_results[i] = {
                        'weather_impact': round(impact_score, 4),
                        'status': 'danger' if impact_score >= 0.7 else ('warning' if impact_score >= 0.4 else 'safe'),
                        'label': 'High Risk' if impact_score >= 0.7 else ('Caution' if impact_score >= 0.4 else 'Clear')
                    }
        except Exception as e:
             logger.error(f"Weather batch error: {e}")

        # 3. Combine results
        final_results = []
        for i, f in enumerate(flights_data):
            final_results.append({
                'id': f['id'],
                'booking': booking_results[i] or {'error': True, 'booking_probability': 0.5},
                'weather': weather_results[i] or {'error': True, 'weather_impact': 0.3}
            })
            
        return final_results

    def get_available_models(self) -> Dict[str, str]:
        models = self.loader.get_all_models()
        result = {}
        for name, model in models.items():
            if hasattr(model, 'classes_'):
                model_type = 'classifier'
            else:
                model_type = 'regressor'
            result[name] = model_type
        return result


# Singleton instance
predictor = Predictor()

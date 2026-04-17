"""
Model Loader - Handles loading and caching of trained ML models.
This module loads .pkl files at startup to avoid repeated loading per request.
"""

import pickle
import os
import logging
from typing import Dict, Any
from pathlib import Path

logger = logging.getLogger(__name__)


class ModelLoader:
    """Load and cache ML models in memory."""
    
    _instance = None
    _models: Dict[str, Any] = {}
    
    def __new__(cls):
        """Singleton pattern - ensure only one instance."""
        if cls._instance is None:
            cls._instance = super(ModelLoader, cls).__new__(cls)
        return cls._instance
    
    def __init__(self):
        """Initialize model loader (called only once due to singleton)."""
        if not hasattr(self, '_initialized'):
            self._initialized = False
    
    def load_models(self, models_dir: str = None) -> Dict[str, Any]:
        """
        Load all .pkl files from models directory.
        
        Args:
            models_dir: Path to directory containing .pkl files
            
        Returns:
            Dictionary of loaded models {model_name: model_object}
        """
        if self._initialized:
            logger.info("Models already loaded, returning cached models")
            return self._models
        
        if models_dir is None:
            models_dir = Path(__file__).parent.parent / "models"
        else:
            models_dir = Path(models_dir)
        
        if not models_dir.exists():
            logger.error(f"Models directory not found: {models_dir}")
            raise FileNotFoundError(f"Models directory not found: {models_dir}")
        
        pkl_files = list(models_dir.glob("*.pkl"))
        
        if not pkl_files:
            logger.error(f"No .pkl files found in {models_dir}")
            raise FileNotFoundError(f"No .pkl files found in {models_dir}")
        
        logger.info(f"Found {len(pkl_files)} model files")
        
        for pkl_file in pkl_files:
            try:
                    import joblib
                    model = joblib.load(pkl_file)
                    model_name = pkl_file.stem  # Remove .pkl extension
                    self._models[model_name] = model
                    logger.info(f"✓ Loaded model: {model_name}")
            except Exception as e:
                logger.error(f"✗ Failed to load model {pkl_file.name}: {str(e)}")
                raise
        
        self._initialized = True
        logger.info(f"✓ All {len(self._models)} models loaded successfully")
        return self._models
    
    def get_model(self, model_name: str) -> Any:
        """
        Get a loaded model by name.
        
        Args:
            model_name: Name of the model (without .pkl extension)
            
        Returns:
            Model object
            
        Raises:
            KeyError: If model not found
        """
        if model_name not in self._models:
            available = list(self._models.keys())
            raise KeyError(f"Model '{model_name}' not found. Available: {available}")
        
        return self._models[model_name]
    
    def get_all_models(self) -> Dict[str, Any]:
        """Get all loaded models."""
        return self._models.copy()
    
    def model_exists(self, model_name: str) -> bool:
        """Check if a model is loaded."""
        return model_name in self._models


# Singleton instance
model_loader = ModelLoader()

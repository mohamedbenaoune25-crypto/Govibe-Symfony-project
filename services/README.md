This directory contains Python sidecar services that Symfony consumes over HTTP.

Structure:
- `prediction-api/`: batch prediction and analytics inference service.
- `face-recognition-api/`: biometric face encoding and verification service.
- `voice-assistant-api/`: voice assistant, risk API, and related assistant tooling.

Boundary rules:
- These services are external to the Symfony app boundary.
- Symfony should integrate through HTTP clients and environment-configured base URLs.
- Keep service-specific dependencies, Dockerfiles, and runtime files inside each service folder.

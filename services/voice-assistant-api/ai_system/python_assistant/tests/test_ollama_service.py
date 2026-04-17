from python_assistant.ollama_service import MODEL, OLLAMA_BASE, SYSTEM_PROMPT
from python_assistant.ollama_service import OllamaService


def test_constants():
    assert OLLAMA_BASE == "http://localhost:11434"
    assert MODEL == "llama3.2"


def test_system_prompt_contains_required_actions():
    for action in [
        "BOOK",
        "RECHERCHER",
        "ANNULER",
        "PAYER",
        "AIDE",
        "DECRIRE",
        "DECONNEXION",
        "LOGIN",
        "SIGNUP",
        "FOCUS_EMAIL",
        "FOCUS_PASSWORD",
        "NONE",
        "UNKNOWN",
    ]:
        assert action in SYSTEM_PROMPT


def test_resolve_model_prefers_smaller_installed_model(monkeypatch):
    service = OllamaService()
    monkeypatch.setattr(service, "list_models", lambda: ["llama3.2", "phi3:mini", "qwen2.5:0.5b"])
    assert service.resolve_model() == "phi3:mini"

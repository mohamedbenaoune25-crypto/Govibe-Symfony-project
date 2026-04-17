from python_assistant.models import AgentResponse
from python_assistant.python_voice_agent import parse_response


def test_unknown_factory_defaults():
    ar = AgentResponse.unknown("test")
    assert ar.intent == "UNKNOWN"
    assert ar.action == "UNKNOWN"
    assert ar.confidence == 0.0
    assert ar.destination is None
    assert ar.date is None
    assert ar.engine == "error"


def test_parse_book_with_destination_and_date():
    raw = (
        '{"intent":"BOOK","response":"Opening booking.","action":"BOOK",'
        '"confidence":0.95,"destination":"Paris","date":"2026-03-15","engine":"ollama"}'
    )
    ar = parse_response(raw)
    assert ar.intent == "BOOK"
    assert ar.action == "BOOK"
    assert ar.destination == "Paris"
    assert ar.date == "2026-03-15"
    assert ar.confidence == 0.95


def test_parse_null_literals_to_none():
    raw = (
        '{"intent":"VIEW_BOOKINGS","response":"ok","action":"VIEW_BOOKINGS",'
        '"confidence":0.8,"destination":"null","date":"null","engine":"sentence-transformers"}'
    )
    ar = parse_response(raw)
    assert ar.destination is None
    assert ar.date is None
    assert not ar.has_destination()
    assert not ar.has_date()

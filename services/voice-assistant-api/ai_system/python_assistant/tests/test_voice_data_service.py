import json

from python_assistant.voice_data_service import MAX_PER_CATEGORY, build_db_context_json


def test_build_db_context_json_shape():
    payload = build_db_context_json(
        activities=[{"id": 1, "name": "Kayak"}],
        cars=[{"id": 2, "marque": "BMW"}],
        hotels=[{"id": 3, "nom": "Sahara"}],
    )
    data = json.loads(payload)
    assert data["type"] == "db_context"
    assert len(data["activities"]) == 1
    assert len(data["cars"]) == 1
    assert len(data["hotels"]) == 1


def test_max_cap_per_category():
    items = [{"id": i} for i in range(MAX_PER_CATEGORY + 10)]
    payload = build_db_context_json(items, items, items)
    data = json.loads(payload)
    assert len(data["activities"]) == MAX_PER_CATEGORY
    assert len(data["cars"]) == MAX_PER_CATEGORY
    assert len(data["hotels"]) == MAX_PER_CATEGORY

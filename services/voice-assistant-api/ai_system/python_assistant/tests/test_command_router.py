from python_assistant.command_router import CommandRouter
from python_assistant.noise_orchestrator import NoiseOrchestrator


class DummyVas:
    def __init__(self):
        self.spoken = []
        self.logged_out = False

    def vivian_speak(self, text: str) -> None:
        self.spoken.append(text)

    def notify_user_logged_out(self) -> None:
        self.logged_out = True


class DummyProxy:
    def __init__(self):
        self.opened = False
        self.cancelled = False
        self.bookings = False
        self.search = False
        self.paid = False

    def open_booking(self) -> None:
        self.opened = True

    def cancel_booking(self) -> None:
        self.cancelled = True

    def show_bookings_tab(self) -> None:
        self.bookings = True

    def show_search_tab(self) -> None:
        self.search = True

    def trigger_payment(self) -> None:
        self.paid = True

    def describe_screen(self) -> str:
        return "Ecran de test"


def test_try_keyword_match_case_and_accent_insensitive():
    router = CommandRouter(DummyVas(), DummyProxy())
    assert router.try_keyword_match("aide")
    assert router.try_keyword_match("récalibrer")


def test_on_command_unknown_asks_repeat():
    vas = DummyVas()
    router = CommandRouter(vas, DummyProxy())
    router.on_command("xyz", "xyz")
    assert any("repeat" in text.lower() for text in vas.spoken)


def test_recalibrate_sets_orchestrator_uncalibrated():
    vas = DummyVas()
    router = CommandRouter(vas, DummyProxy())
    orch = NoiseOrchestrator.get_instance()
    orch.calibrated = True
    router.on_command("RECALIBRER", "recalibrer")
    assert not orch.is_calibrated()

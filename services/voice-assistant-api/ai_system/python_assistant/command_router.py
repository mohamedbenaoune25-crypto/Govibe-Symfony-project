from __future__ import annotations

import unicodedata
from collections import OrderedDict
from typing import Callable, Optional, Protocol

from .noise_orchestrator import NoiseOrchestrator


class VoiceAssistantProtocol(Protocol):
    def vivian_speak(self, text: str) -> None: ...

    def notify_user_logged_out(self) -> None: ...


class ControllerProxyProtocol(Protocol):
    def open_booking(self) -> None: ...

    def cancel_booking(self) -> None: ...

    def show_bookings_tab(self) -> None: ...

    def show_search_tab(self) -> None: ...

    def trigger_payment(self) -> None: ...

    def describe_screen(self) -> str: ...


class CommandRouter:
    def __init__(self, vas: VoiceAssistantProtocol, proxy: Optional[ControllerProxyProtocol]) -> None:
        self.vas = vas
        self.proxy = proxy
        self.pending_destination: Optional[str] = None
        self.pending_date: Optional[str] = None
        self.pending_passengers: int = 1
        self.command_table: "OrderedDict[str, Callable[[str], None]]" = OrderedDict()
        self._build_command_table()

    @staticmethod
    def _norm(text: Optional[str]) -> str:
        if not text:
            return ""
        nfd = unicodedata.normalize("NFD", text)
        stripped = "".join(ch for ch in nfd if unicodedata.category(ch) != "Mn")
        return stripped.upper().strip()

    def set_proxy(self, proxy: Optional[ControllerProxyProtocol]) -> None:
        self.proxy = proxy

    def set_voice_context(self, destination: Optional[str], date: Optional[str], passengers: int = 1) -> None:
        self.pending_destination = destination
        self.pending_date = date
        self.pending_passengers = passengers if passengers > 0 else 1

    def on_command(self, command: str, raw_text: str) -> None:
        _ = raw_text
        if self.try_keyword_match(command):
            return
        self._speak("I heard something interesting, but I need you to repeat that clearly.")

    def try_keyword_match(self, command: str) -> bool:
        normalized = self._norm(command)
        for key, handler in self.command_table.items():
            if self._norm(key) in normalized:
                handler(command)
                return True
        return False

    def _build_command_table(self) -> None:
        self.command_table["AIDE"] = lambda _cmd: self._do_help()
        self.command_table["HELP"] = lambda _cmd: self._do_help()
        self.command_table["COMMANDES"] = lambda _cmd: self._do_help()

        self.command_table["QUOI"] = lambda _cmd: self._do_describe()
        self.command_table["WHAT"] = lambda _cmd: self._do_describe()
        self.command_table["DECRIRE"] = lambda _cmd: self._do_describe()

        self.command_table["RECALIBRER"] = lambda _cmd: self._do_recalibrate()
        self.command_table["CALIBRER"] = lambda _cmd: self._do_recalibrate()
        self.command_table["BRUIT"] = lambda _cmd: self._do_recalibrate()

        self.command_table["BOOK"] = lambda _cmd: self._do_book()
        self.command_table["RESERVER"] = lambda _cmd: self._do_book()
        self.command_table["MES RESERVATIONS"] = lambda _cmd: self._do_show_bookings()
        self.command_table["RECHERCHER"] = lambda _cmd: self._do_search()
        self.command_table["ANNULER"] = lambda _cmd: self._do_cancel()
        self.command_table["PAYER"] = lambda _cmd: self._do_pay()
        self.command_table["DECONNEXION"] = lambda _cmd: self._do_logout()

    def _speak(self, text: str) -> None:
        self.vas.vivian_speak(text)

    def _do_help(self) -> None:
        self._speak("Say book, search, my bookings, cancel, pay, describe, or logout.")

    def _do_describe(self) -> None:
        if self.proxy is None:
            self._speak("No active screen is connected.")
            return
        self._speak(self.proxy.describe_screen())

    def _do_recalibrate(self) -> None:
        self._speak("Recalibrating microphone noise profile. Stay quiet for a few seconds.")
        NoiseOrchestrator.get_instance().force_recalibrate()

    def _do_book(self) -> None:
        if self.proxy:
            self.proxy.open_booking()
        self._speak("Opening booking form.")

    def _do_show_bookings(self) -> None:
        if self.proxy:
            self.proxy.show_bookings_tab()
        self._speak("Showing your reservations.")

    def _do_search(self) -> None:
        if self.proxy:
            self.proxy.show_search_tab()
        self._speak("Opening flight search.")

    def _do_cancel(self) -> None:
        if self.proxy:
            self.proxy.cancel_booking()
        self._speak("Cancelled.")

    def _do_pay(self) -> None:
        if self.proxy:
            self.proxy.trigger_payment()
        self._speak("Proceeding to payment.")

    def _do_logout(self) -> None:
        self.vas.notify_user_logged_out()
        self._speak("Logged out. See you soon.")

from dataclasses import dataclass
from typing import Optional


@dataclass(frozen=True)
class AgentResponse:
    intent: str
    response: str
    action: str
    confidence: float
    destination: Optional[str]
    date: Optional[str]
    engine: str
    passengers: int = 1

    @staticmethod
    def unknown(_: str = "") -> "AgentResponse":
        return AgentResponse(
            intent="UNKNOWN",
            response="I did not understand. Say Help for the list of commands.",
            action="UNKNOWN",
            confidence=0.0,
            destination=None,
            date=None,
            engine="error",
            passengers=1,
        )

    def has_destination(self) -> bool:
        return bool(self.destination and self.destination.strip())

    def has_date(self) -> bool:
        return bool(self.date and self.date.strip())

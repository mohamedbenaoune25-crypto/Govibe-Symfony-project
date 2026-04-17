# NLU Voice Assistant Absolute Checklist

## 1. Speech Recognition Layer

- [ ] Test in quiet room, office noise, and outdoor noise.
- [ ] Test with multiple accents and speaking rates.
- [ ] Validate filler words handling (um, uh, like).
- [ ] Validate phonetic-confusion pairs (delete/delegate, pay/bay).

## 2. Intent Recognition and Entity Extraction

- [ ] Validate paraphrase mapping to same intent.
- [ ] Validate partial commands (for example: "open").
- [ ] Validate entities: filename, directory, flags.
- [ ] Validate multi-word entities and special names.
- [ ] Validate ambiguous-intent conflict resolution.

## 3. Command Execution Safety

- [ ] Dry-run log enabled before execution.
- [ ] Destructive intents require explicit confirmation.
- [ ] Argument sanitization blocks injection characters.
- [ ] Privileged commands are gated.
- [ ] Allowlist and denylist policy is active.

## 4. Error Handling and Edge Cases

- [ ] Unknown intent requests clarification.
- [ ] Command execution errors are captured and returned.
- [ ] Empty or silent input times out cleanly.
- [ ] Very long inputs are bounded and reported.
- [ ] Noise-only input does not trigger commands.

## 5. Context and Multi-Turn Handling

- [ ] Pronouns resolve correctly (it, that, again).
- [ ] Corrections are applied safely.
- [ ] Chained commands are handled deterministically.
- [ ] Session reset works on timeout and explicit exit.

## 6. Performance and Latency

- [ ] Measure end-to-end latency: input to feedback.
- [ ] Test rapid command bursts.
- [ ] Report progress for long-running commands.
- [ ] Stress multi-session behavior when applicable.

## 7. Feedback and Response Layer

- [ ] Read-back understood intent for risky actions.
- [ ] Distinct success vs failure TTS responses.
- [ ] Long output is summarized.
- [ ] Technical term pronunciation reviewed.

## 8. Regression and Automation

- [ ] Maintain golden phrase/audio set.
- [ ] Replay golden set in CI after model updates.
- [ ] Log full lifecycle per request.
- [ ] Track STT WER and Intent Accuracy separately.

## Non-Negotiable Rules

- Never execute raw unvalidated voice input.
- Test unhappy paths more than happy paths.
- Keep STT, NLU, and execution logs separate.

"""
behavior_tracker.py — Unsupervised learning from user voice interactions.

How it works
============
Every utterance the agent hears is recorded here with its classified intent and
confidence score.  Utterances that fall below the recognition threshold are called
"misses".  Periodically (triggered from voice_agent.py) the tracker:

  1. Loads all saved misses from disk (user_behavior_misses.json).
  2. Encodes them with the same sentence-transformer / TF-IDF model that the
     main classifier uses.
  3. Runs Agglomerative Clustering with a cosine-distance threshold, grouping
     similar unrecognised phrases into candidate intents.
  4. For each cluster that has ≥ MIN_CLUSTER_SIZE members it finds the nearest
     *known* intent by comparing the cluster centroid against all existing INTENTS
     phrase embeddings.
  5. If cosine similarity to the nearest intent is ≥ ASSOC_THRESHOLD the cluster's
     phrases are added to that intent's phrase list and saved to
     learned_phrases.json.
  6. On next startup (or immediately) inject_learned_phrases() loads
     learned_phrases.json and merges the new phrases into the live INTENTS dict.

This means the agent silently improves over time without any manual intervention.
The developer can inspect learned_phrases.json at any time to review / prune.

Implicit correction pairs
=========================
When a user says X (miss), then Y (hit, different intent), the tracker treats Y as
a paraphrase correction for X and stores the pair in
user_behavior_corrections.json.  Future clustering uses these pairs to seed
cluster-to-intent assignments more accurately.

Files created / read
====================
  <script_dir>/user_behavior_misses.json      — ring buffer, max BUFFER_MAX
  <script_dir>/user_behavior_hits.json        — ring buffer, max BUFFER_MAX
  <script_dir>/user_behavior_corrections.json — (miss_text, hit_text, hit_intent)
  <script_dir>/learned_phrases.json           — {intent_key: [phrase, ...]}
"""

from __future__ import annotations

import json
import math
import os
import sys
import time
from pathlib import Path
from typing import Dict, List, Optional, Tuple

# ---------------------------------------------------------------------------
# Tunables
# ---------------------------------------------------------------------------
BUFFER_MAX        = 10_000   # max entries in each ring-buffer file
MIN_CLUSTER_SIZE  = 4        # min misses in a cluster to be considered actionable
ASSOC_THRESHOLD   = 0.52     # min cosine-sim for cluster→intent assignment
CONSOLIDATE_EVERY = 150      # auto-consolidate after this many total records
MISS_CONF_MAX     = 0.42     # anything below this is a "miss" (matches classify() threshold)
CLUSTER_DIST      = 0.38     # agglomerative distance threshold (1 - cosine_sim)
MAX_LEARNED_PER_INTENT = 40  # cap so the phrase list doesn't bloat indefinitely

_SCRIPT_DIR = Path(__file__).parent
_MISSES_FILE       = _SCRIPT_DIR / "user_behavior_misses.json"
_HITS_FILE         = _SCRIPT_DIR / "user_behavior_hits.json"
_CORRECTIONS_FILE  = _SCRIPT_DIR / "user_behavior_corrections.json"
_LEARNED_FILE      = _SCRIPT_DIR / "learned_phrases.json"


# ---------------------------------------------------------------------------
# Helper: JSON ring-buffer I/O
# ---------------------------------------------------------------------------

def _load_json_list(path: Path) -> list:
    try:
        if path.exists():
            return json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        pass
    return []


def _save_json_list(path: Path, data: list, maxlen: int = BUFFER_MAX) -> None:
    if len(data) > maxlen:
        data = data[-maxlen:]
    try:
        path.write_text(json.dumps(data, ensure_ascii=False, indent=None),
                        encoding="utf-8")
    except Exception as e:
        sys.stderr.write(f"[BehaviorTracker] save error {path}: {e}\n")


def _load_learned() -> Dict[str, List[str]]:
    try:
        if _LEARNED_FILE.exists():
            return json.loads(_LEARNED_FILE.read_text(encoding="utf-8"))
    except Exception:
        pass
    return {}


def _save_learned(data: Dict[str, List[str]]) -> None:
    try:
        _LEARNED_FILE.write_text(
            json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8"
        )
    except Exception as e:
        sys.stderr.write(f"[BehaviorTracker] save learned error: {e}\n")


# ---------------------------------------------------------------------------
# BehaviorTracker
# ---------------------------------------------------------------------------

class BehaviorTracker:
    """
    Drop-in tracker for voice_agent.py.

    Usage in voice_agent.py
    -----------------------
        from behavior_tracker import BehaviorTracker
        _tracker = BehaviorTracker()
        _tracker.inject_learned_phrases(INTENTS)  # at startup

        # after every classify():
        _tracker.record(text, intent_key, conf, state=_agent_state)

        # inside the main loop (every N ticks, or on demand):
        _tracker.maybe_consolidate()
    """

    def __init__(self) -> None:
        self._total_records   = 0
        self._last_miss_text: Optional[str]   = None
        self._last_miss_state: Optional[str]  = None
        # lazy embedder — initialised on first consolidate() call
        self._embed_fn = None          # callable: list[str] -> np.ndarray
        self._intent_vecs: Optional[dict] = None  # {intent_key: centroid_vec}

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def record(self, text: str, intent: str, conf: float, state: str = "") -> None:
        """
        Call this after every classify() result.
        Records hits and misses separately; builds correction pairs automatically.
        """
        text = text.strip()
        if not text:
            return
        self._total_records += 1

        entry = {"t": int(time.time()), "text": text, "intent": intent,
                 "conf": round(conf, 4), "state": state}

        is_miss = intent == "UNKNOWN" or conf < MISS_CONF_MAX
        if is_miss:
            misses = _load_json_list(_MISSES_FILE)
            misses.append(entry)
            _save_json_list(_MISSES_FILE, misses)
            # Remember for incoming correction pair
            self._last_miss_text  = text
            self._last_miss_state = state
        else:
            hits = _load_json_list(_HITS_FILE)
            hits.append(entry)
            _save_json_list(_HITS_FILE, hits)
            # If the previous utterance was a miss, this is a correction
            if self._last_miss_text and intent != "UNKNOWN":
                corr = _load_json_list(_CORRECTIONS_FILE)
                corr.append({
                    "miss":       self._last_miss_text,
                    "hit":        text,
                    "hit_intent": intent,
                    "state":      self._last_miss_state or state,
                    "t":          int(time.time()),
                })
                _save_json_list(_CORRECTIONS_FILE, corr, maxlen=5000)
            self._last_miss_text  = None
            self._last_miss_state = None

    def maybe_consolidate(self) -> None:
        """Call periodically; runs clustering when enough data accumulated."""
        if self._total_records > 0 and self._total_records % CONSOLIDATE_EVERY == 0:
            self.consolidate()

    def consolidate(self, intents_dict: Optional[dict] = None) -> Dict[str, List[str]]:
        """
        Cluster unrecognised phrases and associate them with known intents.
        Returns a dict {intent_key: [new_phrase, ...]} of newly learned phrases
        and persists them to learned_phrases.json.

        If intents_dict is supplied the live dict is also updated in-place so
        changes take effect immediately without a restart.
        """
        misses = _load_json_list(_MISSES_FILE)
        if len(misses) < MIN_CLUSTER_SIZE * 2:
            sys.stderr.write(
                f"[BehaviorTracker] consolidate: only {len(misses)} misses, "
                f"need {MIN_CLUSTER_SIZE * 2}+. Skipping.\n"
            )
            return {}

        texts = [m["text"] for m in misses]
        # De-duplicate while preserving order
        seen: set = set()
        unique_texts: List[str] = []
        for t in texts:
            t_low = t.lower().strip()
            if t_low not in seen:
                seen.add(t_low)
                unique_texts.append(t)

        if len(unique_texts) < MIN_CLUSTER_SIZE:
            return {}

        sys.stderr.write(
            f"[BehaviorTracker] Clustering {len(unique_texts)} unique misses …\n"
        )

        # ------------------------------------------------------------------
        # Step 1: Embed all missed utterances
        # ------------------------------------------------------------------
        embedder = self._get_embedder()
        if embedder is None:
            sys.stderr.write("[BehaviorTracker] No embedder available. Skipping.\n")
            return {}

        try:
            vecs = embedder(unique_texts)   # shape (N, D)
        except Exception as e:
            sys.stderr.write(f"[BehaviorTracker] embed error: {e}\n")
            return {}

        # ------------------------------------------------------------------
        # Step 2: Agglomerative clustering
        # ------------------------------------------------------------------
        try:
            from sklearn.cluster import AgglomerativeClustering
            import numpy as np
            vecs_norm = vecs / (np.linalg.norm(vecs, axis=1, keepdims=True) + 1e-9)
            # cosine distance = 1 - cosine_similarity
            agg = AgglomerativeClustering(
                n_clusters=None,
                metric="cosine",
                linkage="average",
                distance_threshold=CLUSTER_DIST,
            )
            labels = agg.fit_predict(vecs_norm)
        except Exception as e:
            sys.stderr.write(f"[BehaviorTracker] clustering error: {e}\n")
            return {}

        # Collect clusters
        import numpy as np
        from collections import defaultdict
        clusters: Dict[int, List[int]] = defaultdict(list)
        for idx, lbl in enumerate(labels):
            clusters[lbl].append(idx)

        # ------------------------------------------------------------------
        # Step 3: For each large-enough cluster, find nearest known intent
        # ------------------------------------------------------------------
        intent_centroids = self._get_intent_centroids(intents_dict, embedder)
        learned = _load_learned()
        new_phrases: Dict[str, List[str]] = {}

        corrections = _load_json_list(_CORRECTIONS_FILE)
        corr_map: Dict[str, str] = {c["miss"].lower(): c["hit_intent"]
                                     for c in corrections}

        for lbl, member_idxs in clusters.items():
            if len(member_idxs) < MIN_CLUSTER_SIZE:
                continue

            member_texts = [unique_texts[i] for i in member_idxs]
            centroid = np.mean(vecs_norm[member_idxs], axis=0)
            centroid /= (np.linalg.norm(centroid) + 1e-9)

            # Check correction pairs first (explicit user signal)
            intent_votes: Dict[str, int] = defaultdict(int)
            for mt in member_texts:
                voted = corr_map.get(mt.lower())
                if voted:
                    intent_votes[voted] += 2   # weight correction signal x2

            # Compare centroid to known intent phrase centroids
            best_intent: Optional[str] = None
            best_sim = -1.0
            if intent_centroids:
                for ik, ic in intent_centroids.items():
                    sim = float(np.dot(centroid, ic))
                    if sim > best_sim:
                        best_sim = sim
                        best_intent = ik

            # Override with vote winner if available
            if intent_votes:
                voted_winner = max(intent_votes, key=intent_votes.__getitem__)
                # Trust correction over centroid if it's close enough
                if best_intent is None or intent_votes[voted_winner] >= 2:
                    best_intent = voted_winner
                    best_sim = max(best_sim, ASSOC_THRESHOLD)   # mark as confident

            if best_intent is None or best_sim < ASSOC_THRESHOLD:
                sys.stderr.write(
                    f"[BehaviorTracker] Cluster {lbl} ({len(member_idxs)} members) "
                    f"could not be assigned (best_sim={best_sim:.3f}). "
                    f"Texts: {member_texts[:3]}\n"
                )
                continue

            # Merge into learned dict
            existing = set(learned.get(best_intent, []))
            added: List[str] = []
            for mt in member_texts:
                mt_lc = mt.lower().strip()
                if mt_lc not in existing and len(mt_lc) >= 3:
                    existing.add(mt_lc)
                    added.append(mt_lc)

            if added:
                current = learned.get(best_intent, [])
                current = list(dict.fromkeys(current + added))  # de-dup, preserve order
                if len(current) > MAX_LEARNED_PER_INTENT:
                    current = current[-MAX_LEARNED_PER_INTENT:]
                learned[best_intent] = current
                new_phrases[best_intent] = added
                sys.stderr.write(
                    f"[BehaviorTracker] Learned {len(added)} phrase(s) for "
                    f"{best_intent!r} (sim={best_sim:.3f}): {added[:3]}\n"
                )

        if new_phrases:
            _save_learned(learned)
            # Hot-patch live INTENTS dict if supplied
            if intents_dict is not None:
                self.inject_learned_phrases(intents_dict, learned=learned)

        sys.stderr.write(
            f"[BehaviorTracker] consolidate done: "
            f"{sum(len(v) for v in new_phrases.values())} new phrase(s) "
            f"across {len(new_phrases)} intent(s).\n"
        )
        return new_phrases

    def inject_learned_phrases(
        self,
        intents_dict: dict,
        learned: Optional[Dict[str, List[str]]] = None,
    ) -> int:
        """
        Merge learned_phrases.json into the live INTENTS dict.
        Returns the total number of phrases injected.
        Call this once at agent startup after building INTENTS.
        """
        if learned is None:
            learned = _load_learned()
        total = 0
        for intent_key, phrases in learned.items():
            if intent_key not in intents_dict:
                continue
            existing = intents_dict[intent_key].get("phrases", [])
            existing_set = {p.lower().strip() for p in existing}
            added = 0
            for p in phrases:
                p_clean = p.lower().strip()
                if p_clean and p_clean not in existing_set:
                    existing.append(p_clean)
                    existing_set.add(p_clean)
                    added += 1
            intents_dict[intent_key]["phrases"] = existing
            total += added
        if total:
            sys.stderr.write(
                f"[BehaviorTracker] Injected {total} learned phrase(s) into live INTENTS.\n"
            )
        return total

    def stats(self) -> dict:
        """Return a summary dict for logging/debug."""
        misses = _load_json_list(_MISSES_FILE)
        hits   = _load_json_list(_HITS_FILE)
        corr   = _load_json_list(_CORRECTIONS_FILE)
        learned = _load_learned()
        return {
            "total_misses":       len(misses),
            "total_hits":         len(hits),
            "correction_pairs":   len(corr),
            "learned_intents":    len(learned),
            "learned_phrases":    sum(len(v) for v in learned.values()),
            "records_this_session": self._total_records,
        }

    # ------------------------------------------------------------------
    # Private
    # ------------------------------------------------------------------

    def _get_embedder(self):
        """Return a callable(list[str]) -> np.ndarray, using best available model."""
        if self._embed_fn is not None:
            return self._embed_fn

        # Try sentence-transformers first (same as main classifier)
        try:
            from sentence_transformers import SentenceTransformer
            import numpy as np
            # Re-use model from voice_agent globals if already loaded to avoid
            # loading it twice.  We do a best-effort import.
            try:
                import voice_agent as _va
                model = _va._st_model   # type: ignore[attr-defined]
            except Exception:
                model = SentenceTransformer("paraphrase-multilingual-MiniLM-L12-v2")

            def _st_embed(texts: List[str]):
                return np.array(model.encode(texts, convert_to_numpy=True,
                                             show_progress_bar=False))

            self._embed_fn = _st_embed
            sys.stderr.write("[BehaviorTracker] Using sentence-transformers embedder.\n")
            return self._embed_fn
        except Exception:
            pass

        # Fallback: TF-IDF (char n-grams, same as _classify_tfidf)
        try:
            from sklearn.feature_extraction.text import TfidfVectorizer
            import numpy as np
            misses = _load_json_list(_MISSES_FILE)
            hits   = _load_json_list(_HITS_FILE)
            corpus = [e["text"] for e in misses + hits] or ["placeholder"]
            vec = TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 4),
                                  sublinear_tf=True, min_df=1)
            vec.fit(corpus)

            def _tfidf_embed(texts: List[str]):
                return vec.transform(texts).toarray().astype(float)

            self._embed_fn = _tfidf_embed
            sys.stderr.write("[BehaviorTracker] Using TF-IDF fallback embedder.\n")
            return self._embed_fn
        except Exception as e:
            sys.stderr.write(f"[BehaviorTracker] No embedder available: {e}\n")
            return None

    def _get_intent_centroids(
        self,
        intents_dict: Optional[dict],
        embedder,
    ) -> Dict[str, "np.ndarray"]:  # type: ignore[type-arg]
        """Compute (or return cached) per-intent phrase centroids."""
        if self._intent_vecs is not None:
            return self._intent_vecs
        if intents_dict is None:
            try:
                import voice_agent as _va
                intents_dict = _va.INTENTS   # type: ignore[attr-defined]
            except Exception:
                return {}

        import numpy as np
        centroids: Dict[str, "np.ndarray"] = {}  # type: ignore[type-arg]
        for key, info in intents_dict.items():
            phrases = info.get("phrases", [])
            if not phrases:
                continue
            try:
                vecs = embedder(phrases)
                vecs_norm = vecs / (np.linalg.norm(vecs, axis=1, keepdims=True) + 1e-9)
                c = np.mean(vecs_norm, axis=0)
                c /= (np.linalg.norm(c) + 1e-9)
                centroids[key] = c
            except Exception:
                continue
        self._intent_vecs = centroids
        return centroids


# ---------------------------------------------------------------------------
# Standalone CLI — run `python behavior_tracker.py` to see stats & consolidate
# ---------------------------------------------------------------------------
if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="GoVibe Agent Behavior Tracker")
    parser.add_argument("--stats", action="store_true",  help="Print stats")
    parser.add_argument("--consolidate", action="store_true",
                        help="Run clustering and update learned_phrases.json")
    parser.add_argument("--show-learned", action="store_true",
                        help="Print learned_phrases.json contents")
    parser.add_argument("--clear-misses", action="store_true",
                        help="Delete user_behavior_misses.json (fresh start)")
    args = parser.parse_args()

    tracker = BehaviorTracker()

    if args.stats:
        s = tracker.stats()
        for k, v in s.items():
            print(f"  {k}: {v}")

    if args.clear_misses:
        _MISSES_FILE.unlink(missing_ok=True)
        print("Misses cleared.")

    if args.show_learned:
        learned = _load_learned()
        if not learned:
            print("No learned phrases yet.")
        for intent, phrases in learned.items():
            print(f"\n[{intent}] ({len(phrases)} phrases)")
            for p in phrases:
                print(f"  - {p}")

    if args.consolidate:
        new = tracker.consolidate()
        if new:
            for k, v in new.items():
                print(f"[{k}] +{len(v)} phrases: {v[:3]}")
        else:
            print("Nothing new learned (not enough data or all assigned).")

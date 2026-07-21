#!/usr/bin/env python3
"""Replay Write/StrReplace tool operations from a Cursor agent JSONL transcript."""

from __future__ import annotations

import json
import sys
from pathlib import Path

TRANSCRIPT = Path(
    "/Users/hsuhtet/.cursor/projects/Users-hsuhtet-rosewood/agent-transcripts/"
    "d705bcba-e42c-4a19-beda-191e0e38f1af/d705bcba-e42c-4a19-beda-191e0e38f1af.jsonl"
)
BACKEND_PREFIX = "/Users/hsuhtet/rosewood/backend"


def main() -> int:
    if not TRANSCRIPT.is_file():
        print(f"Transcript not found: {TRANSCRIPT}", file=sys.stderr)
        return 1

    writes: list[str] = []
    str_replaces: list[tuple[str, str]] = []
    skipped = 0
    failed: list[str] = []

    with TRANSCRIPT.open(encoding="utf-8") as f:
        for line_no, line in enumerate(f, 1):
            line = line.strip()
            if not line:
                continue
            try:
                obj = json.loads(line)
            except json.JSONDecodeError as e:
                failed.append(f"line {line_no}: invalid JSON: {e}")
                continue

            msg = obj.get("message") or {}
            content = msg.get("content") or []
            if not isinstance(content, list):
                continue

            for item in content:
                if not isinstance(item, dict) or item.get("type") != "tool_use":
                    continue
                name = item.get("name")
                inp = item.get("input") or {}
                path = inp.get("path", "")
                if not path.startswith(BACKEND_PREFIX):
                    continue

                file_path = Path(path)
                if name == "Write":
                    contents = inp.get("contents")
                    if contents is None:
                        skipped += 1
                        continue
                    file_path.parent.mkdir(parents=True, exist_ok=True)
                    file_path.write_text(contents, encoding="utf-8")
                    rel = path[len(BACKEND_PREFIX) + 1 :]
                    writes.append(rel)
                elif name == "StrReplace":
                    old = inp.get("old_string")
                    new = inp.get("new_string")
                    if old is None or new is None:
                        skipped += 1
                        continue
                    if not file_path.is_file():
                        failed.append(
                            f"line {line_no}: StrReplace on missing file {path}"
                        )
                        continue
                    text = file_path.read_text(encoding="utf-8")
                    if old not in text:
                        failed.append(
                            f"line {line_no}: old_string not found in {path}"
                        )
                        continue
                    file_path.write_text(text.replace(old, new, 1), encoding="utf-8")
                    rel = path[len(BACKEND_PREFIX) + 1 :]
                    str_replaces.append((rel, f"line {line_no}"))

    unique_writes = sorted(set(writes))
    unique_patches = sorted(set(r for r, _ in str_replaces))

    print(f"Write operations: {len(writes)} ({len(unique_writes)} unique files)")
    print(f"StrReplace operations: {len(str_replaces)} ({len(unique_patches)} unique files)")
    print(f"Skipped (missing fields): {skipped}")
    print(f"Failures: {len(failed)}")

    if failed:
        print("\n--- Failures (first 40) ---")
        for msg in failed[:40]:
            print(msg)
        if len(failed) > 40:
            print(f"... and {len(failed) - 40} more")

    print("\n--- Files written ---")
    for rel in unique_writes:
        print(rel)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())

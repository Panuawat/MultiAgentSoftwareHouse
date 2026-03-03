"""
กุ้ง PM Agent — STUB
Loads mock response from mock/pm_response.json when APP_AGENT_MODE=mock
"""

import json
import os

MOCK_DIR = os.path.join(os.path.dirname(__file__), "..", "mock")


def run(requirement: str) -> dict:
    mode = os.environ.get("APP_AGENT_MODE", "mock")
    if mode == "mock":
        with open(os.path.join(MOCK_DIR, "pm_response.json")) as f:
            return json.load(f)
    raise NotImplementedError("Live Gemini API mode not implemented yet.")

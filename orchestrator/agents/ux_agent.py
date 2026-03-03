"""
กุ้ง UX/UI Agent — STUB
Loads mock response from mock/ux_response.json when APP_AGENT_MODE=mock
"""

import json
import os

MOCK_DIR = os.path.join(os.path.dirname(__file__), "..", "mock")


def run(requirements: dict) -> dict:
    mode = os.environ.get("APP_AGENT_MODE", "mock")
    if mode == "mock":
        with open(os.path.join(MOCK_DIR, "ux_response.json")) as f:
            return json.load(f)
    raise NotImplementedError("Live Ollama mode not implemented yet.")

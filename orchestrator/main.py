"""
OpenClaw Orchestrator — STUB
Python is not available in this environment.
This file is a stub placeholder only.
"""

import sys
import os

AGENT_MODE = os.environ.get("APP_AGENT_MODE", "mock")


def main():
    print(f"[OpenClaw] Orchestrator stub running in mode: {AGENT_MODE}")
    print("[OpenClaw] Python orchestrator is a stub — agents run via Laravel Queue Jobs.")
    print("[OpenClaw] Use mock/ directory for test responses.")


if __name__ == "__main__":
    main()

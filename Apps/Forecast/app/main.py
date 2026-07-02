"""Forecast sidecar — stateless HTTP wrapper around statsforecast.

Run (Windows dev):
    .venv\\Scripts\\activate
    uvicorn app.main:app --host 127.0.0.1 --port 8100

Bind to 127.0.0.1 only: the service has no auth. If it is ever deployed where
the port is reachable by others, front it with a firewall rule or add a
shared-secret header check first.
"""

from __future__ import annotations

from importlib.metadata import version

from fastapi import FastAPI

from . import forecaster
from .schemas import ForecastRequest, ForecastResponse

app = FastAPI(title="Retail Forecast Sidecar", version="1.0.0")


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "statsforecast_version": version("statsforecast")}


@app.post("/forecast", response_model=ForecastResponse)
def forecast(request: ForecastRequest) -> ForecastResponse:
    return forecaster.run(request)

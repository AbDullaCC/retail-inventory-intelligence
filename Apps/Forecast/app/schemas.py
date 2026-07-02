"""Request/response contract for the forecast sidecar.

Laravel is the only client: it sends zero-filled daily demand series ending
yesterday and stores the response verbatim. Everything is snake_case JSON.
"""

from __future__ import annotations

from datetime import date, datetime

from pydantic import BaseModel, Field


class HistoryPoint(BaseModel):
    date: date
    qty: float = Field(ge=0)


class SeriesIn(BaseModel):
    product_id: int
    history: list[HistoryPoint] = Field(min_length=1)


class ForecastRequest(BaseModel):
    horizon_days: int = Field(default=28, ge=1, le=90)
    lead_time_days: int = Field(default=7, ge=1, le=60)
    levels: list[int] = Field(default=[90])
    series: list[SeriesIn] = Field(min_length=1, max_length=500)


class ForecastPoint(BaseModel):
    date: date
    mean: float
    lo_90: float | None = None
    hi_90: float | None = None


class SeriesResult(BaseModel):
    product_id: int
    model_used: str
    history_days: int
    forecast: list[ForecastPoint]
    expected_daily_demand: float
    demand_over_lead_time: float
    p90_demand_over_lead_time: float | None = None


class ForecastResponse(BaseModel):
    generated_at: datetime
    horizon_days: int
    results: list[SeriesResult]

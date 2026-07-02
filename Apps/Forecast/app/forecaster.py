"""Series preparation and per-model-group StatsForecast runs.

Series arriving from Laravel are re-indexed onto a continuous daily calendar
(zero-filled, negatives clipped) defensively, classified, grouped by model,
and each group is forecast in one vectorized StatsForecast call. Prediction
intervals are requested only from models that support them (Croston/TSB raise
on `level=`); any group that fails numerically falls back to SeasonalNaive so
one pathological series can never sink the whole batch.
"""

from __future__ import annotations

from datetime import datetime, timezone

import numpy as np
import pandas as pd
from statsforecast import StatsForecast
from statsforecast.models import (
    MSTL,
    TSB,
    AutoETS,
    CrostonOptimized,
    Naive,
    SeasonalNaive,
)

from . import classify as cl
from .schemas import (
    ForecastPoint,
    ForecastRequest,
    ForecastResponse,
    SeriesResult,
)

MODEL_FACTORIES = {
    cl.NAIVE: lambda: Naive(),
    cl.SEASONAL_NAIVE: lambda: SeasonalNaive(season_length=7),
    cl.AUTO_ETS: lambda: AutoETS(season_length=7),
    cl.MSTL: lambda: MSTL(season_length=[7, 364]),
    cl.CROSTON: lambda: CrostonOptimized(),
    cl.TSB_MODEL: lambda: TSB(alpha_d=0.2, alpha_p=0.2),
}

INTERVAL_CAPABLE = {cl.NAIVE, cl.SEASONAL_NAIVE, cl.AUTO_ETS, cl.MSTL}

FALLBACK_MODEL = cl.SEASONAL_NAIVE


def run(request: ForecastRequest) -> ForecastResponse:
    level = request.levels[0] if request.levels else 90

    prepared: dict[int, pd.DataFrame] = {}
    groups: dict[str, list[int]] = {}
    for series in request.series:
        frame = _prepare(series.product_id, [(p.date, p.qty) for p in series.history])
        prepared[series.product_id] = frame
        model_key = cl.classify(frame["y"].to_numpy())
        groups.setdefault(model_key, []).append(series.product_id)

    results: list[SeriesResult] = []
    for model_key, product_ids in groups.items():
        frame = pd.concat([prepared[pid] for pid in product_ids], ignore_index=True)
        forecast, used_key = _forecast_group(model_key, frame, request.horizon_days, level)
        with_intervals = used_key in INTERVAL_CAPABLE and f"lo-{level}" in _interval_columns(forecast, used_key, level)

        for pid in product_ids:
            block = forecast[forecast["unique_id"] == str(pid)]
            results.append(
                _build_result(
                    pid,
                    used_key,
                    len(prepared[pid]),
                    block,
                    request.lead_time_days,
                    level if with_intervals else None,
                )
            )

    return ForecastResponse(
        generated_at=datetime.now(timezone.utc),
        horizon_days=request.horizon_days,
        results=results,
    )


def _prepare(product_id: int, points: list[tuple]) -> pd.DataFrame:
    frame = pd.DataFrame(points, columns=["ds", "y"])
    frame["ds"] = pd.to_datetime(frame["ds"])
    frame = frame.groupby("ds", as_index=False)["y"].sum().sort_values("ds")

    # Continuous daily calendar: missing days are real zero-demand days.
    calendar = pd.date_range(frame["ds"].min(), frame["ds"].max(), freq="D")
    frame = frame.set_index("ds").reindex(calendar, fill_value=0.0).rename_axis("ds").reset_index()

    frame["y"] = frame["y"].clip(lower=0.0)
    frame["unique_id"] = str(product_id)
    return frame[["unique_id", "ds", "y"]]


def _forecast_group(
    model_key: str, frame: pd.DataFrame, horizon: int, level: int
) -> tuple[pd.DataFrame, str]:
    for key in (model_key, FALLBACK_MODEL):
        try:
            sf = StatsForecast(models=[MODEL_FACTORIES[key]()], freq="D", n_jobs=1)
            kwargs = {"level": [level]} if key in INTERVAL_CAPABLE else {}
            try:
                out = sf.forecast(df=frame, h=horizon, **kwargs)
            except Exception:
                if not kwargs:
                    raise
                # Some model configs can't produce intervals; points still can.
                out = sf.forecast(df=frame, h=horizon)
            if "unique_id" not in out.columns:
                out = out.reset_index()
            return out, key
        except Exception:
            if key == FALLBACK_MODEL:
                raise
    raise RuntimeError("unreachable")


def _interval_columns(forecast: pd.DataFrame, model_key: str, level: int) -> dict[str, str]:
    alias = _alias(forecast, model_key)
    columns = {}
    if f"{alias}-lo-{level}" in forecast.columns:
        columns[f"lo-{level}"] = f"{alias}-lo-{level}"
    if f"{alias}-hi-{level}" in forecast.columns:
        columns[f"hi-{level}"] = f"{alias}-hi-{level}"
    return columns


def _alias(forecast: pd.DataFrame, model_key: str) -> str:
    if model_key in forecast.columns:
        return model_key
    # statsforecast aliases models by their repr; find the point-forecast column.
    reserved = {"unique_id", "ds"}
    for column in forecast.columns:
        if column not in reserved and "-lo-" not in column and "-hi-" not in column:
            return column
    raise KeyError(f"no forecast column found for {model_key}")


def _build_result(
    product_id: int,
    model_key: str,
    history_days: int,
    block: pd.DataFrame,
    lead_time_days: int,
    level: int | None,
) -> SeriesResult:
    alias = _alias(block, model_key)
    means = np.clip(block[alias].to_numpy(dtype=float), 0.0, None)

    lo = hi = None
    if level is not None:
        interval = _interval_columns(block, model_key, level)
        if len(interval) == 2:
            lo = np.clip(block[interval[f"lo-{level}"]].to_numpy(dtype=float), 0.0, None)
            hi = np.clip(block[interval[f"hi-{level}"]].to_numpy(dtype=float), 0.0, None)

    points = [
        ForecastPoint(
            date=pd.Timestamp(ds).date(),
            mean=round(float(means[i]), 4),
            lo_90=round(float(lo[i]), 4) if lo is not None else None,
            hi_90=round(float(hi[i]), 4) if hi is not None else None,
        )
        for i, ds in enumerate(block["ds"].to_numpy())
    ]

    lead = min(lead_time_days, len(means))
    return SeriesResult(
        product_id=product_id,
        model_used=model_key,
        history_days=history_days,
        forecast=points,
        expected_daily_demand=round(float(means.mean()), 4) if len(means) else 0.0,
        demand_over_lead_time=round(float(means[:lead].sum()), 4),
        # Summing daily p90s overstates the true p90 of the total (assumes
        # perfectly correlated days) — deliberately conservative and simple.
        p90_demand_over_lead_time=round(float(hi[:lead].sum()), 4) if hi is not None else None,
    )

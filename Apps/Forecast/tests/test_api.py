from datetime import date, timedelta

import numpy as np
from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def series(product_id, values, end=date(2026, 7, 1)):
    start = end - timedelta(days=len(values) - 1)
    return {
        "product_id": product_id,
        "history": [
            {"date": (start + timedelta(days=i)).isoformat(), "qty": float(v)}
            for i, v in enumerate(values)
        ],
    }


def test_health():
    response = client.get("/health")
    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    assert "statsforecast_version" in body


def test_forecast_contract():
    steady = np.resize([4, 5, 6, 4, 5, 9, 11], 84).tolist()
    sparse = np.resize([3, 0, 0, 0], 84).tolist()

    response = client.post(
        "/forecast",
        json={
            "horizon_days": 14,
            "lead_time_days": 7,
            "levels": [90],
            "series": [series(1, steady), series(2, sparse)],
        },
    )
    assert response.status_code == 200
    body = response.json()

    assert body["horizon_days"] == 14
    assert len(body["results"]) == 2
    by_id = {r["product_id"]: r for r in body["results"]}

    steady_result = by_id[1]
    assert steady_result["model_used"] == "AutoETS"
    assert len(steady_result["forecast"]) == 14
    assert steady_result["expected_daily_demand"] > 0
    # Interval-capable model: p90 present and at least the mean demand.
    assert steady_result["p90_demand_over_lead_time"] is not None
    assert steady_result["p90_demand_over_lead_time"] >= steady_result["demand_over_lead_time"]
    # Day 1 of the forecast continues the day after the history ends.
    assert steady_result["forecast"][0]["date"] == "2026-07-02"

    sparse_result = by_id[2]
    assert sparse_result["model_used"] == "CrostonOptimized"
    # Croston produces no intervals — nulls, not zeros.
    assert sparse_result["p90_demand_over_lead_time"] is None
    assert sparse_result["forecast"][0]["lo_90"] is None
    assert 0 < sparse_result["expected_daily_demand"] < 3

    # Aggregates must be consistent with the daily points.
    lead_sum = sum(p["mean"] for p in steady_result["forecast"][:7])
    assert abs(lead_sum - steady_result["demand_over_lead_time"]) < 0.01


def test_forecast_never_negative():
    declining = list(np.linspace(30, 0, 84).round())
    response = client.post(
        "/forecast",
        json={"horizon_days": 28, "lead_time_days": 7, "series": [series(9, declining)]},
    )
    assert response.status_code == 200
    for point in response.json()["results"][0]["forecast"]:
        assert point["mean"] >= 0

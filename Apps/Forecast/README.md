# Forecast Sidecar

Stateless FastAPI service that turns daily sales history into demand forecasts
using [Nixtla statsforecast](https://github.com/Nixtla/statsforecast). The
Laravel backend is the only client: it POSTs zero-filled daily series (built
from the `stock_movements` ledger) and stores the response in
`product_forecasts`. This service has no database access and holds no state.

## Model selection

Each series is classified with the Syntetos–Boylan criteria (ADI × CV²) and
routed to the model that fits its demand pattern:

| Pattern | Condition | Model |
|---|---|---|
| Too little history | < 14 days | Naive |
| Sparse history | < 28 days or < 3 sale-days | SeasonalNaive (weekly) |
| Smooth/erratic, long history | ADI < 1.32, ≥ 730 days | MSTL (weekly + annual seasonality) |
| Smooth/erratic | ADI < 1.32 | AutoETS (weekly seasonality) |
| Intermittent | ADI ≥ 1.32, CV² < 0.49 | CrostonOptimized |
| Lumpy | ADI ≥ 1.32, CV² ≥ 0.49 | TSB |

Croston/TSB forecast a demand *rate* and produce no prediction intervals —
`lo_90`/`hi_90`/`p90_demand_over_lead_time` are `null` for them and the
backend falls back to its safety-buffer-days formula. A group that fails
numerically falls back to SeasonalNaive rather than failing the batch.

`p90_demand_over_lead_time` sums the daily p90s, which overstates the true
p90 of the lead-time total (it assumes perfectly correlated days). This is a
deliberate, conservative simplification.

## Run (Windows dev)

```
cd Apps\Forecast
py -3.12 -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 8100
```

Python 3.11/3.12 (statsforecast depends on numba). The first request after
boot is slower — numba JIT warm-up. Tests: `pytest`.

## Security

Bind to `127.0.0.1` only — the service has no auth. If it is ever deployed
where the port is reachable by others, front it with a firewall rule or add a
shared-secret header check first.

## API

- `GET /health` → `{status, statsforecast_version}`
- `POST /forecast` → per product: `model_used`, daily `{date, mean, lo_90, hi_90}`
  for the horizon, `expected_daily_demand`, `demand_over_lead_time`,
  `p90_demand_over_lead_time`. Interactive docs at `/docs` when running.

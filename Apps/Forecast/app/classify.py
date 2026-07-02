"""Demand-pattern classification: routes each product's series to the model
suited to its behaviour.

Uses the Syntetos-Boylan criteria — ADI (average days between demands) and
CV² (variability of demand sizes) — the standard scheme for retail/spares
demand:

- smooth or erratic demand (frequent sales)  -> exponential smoothing
  (MSTL with weekly+annual seasonality when two full years exist, else
  AutoETS with weekly seasonality),
- intermittent (rare, regular-sized sales)   -> Croston,
- lumpy (rare, wildly varying sales)         -> TSB (also decays toward zero
  when a product stops selling, which feeds dead-stock detection),
- too little history                         -> naive baselines.
"""

from __future__ import annotations

import numpy as np

MIN_HISTORY_DAYS = 14
SHORT_HISTORY_DAYS = 28
# 2 * 364 (52 whole weeks — keeps weekday alignment) + margin: the minimum
# history for MSTL to see the annual cycle twice.
MSTL_MIN_DAYS = 730
# The classic Syntetos-Boylan cutoff is ADI >= 1.32, but backtesting on the
# Online Retail II catalogue showed series in the 1.32–2.0 band (selling most
# days, with wholesale-driven size variance) forecast better under exponential
# smoothing. Croston/TSB take over only when a SKU sells less than every
# other day on average.
ADI_THRESHOLD = 2.0
CV2_THRESHOLD = 0.49

NAIVE = "Naive"
SEASONAL_NAIVE = "SeasonalNaive"
AUTO_ETS = "AutoETS"
MSTL = "MSTL"
CROSTON = "CrostonOptimized"
TSB_MODEL = "TSB"


def classify(y: np.ndarray) -> str:
    """Return the model key for a zero-filled daily demand series."""
    n = len(y)
    nonzero = y[y > 0]

    if n < MIN_HISTORY_DAYS:
        return NAIVE
    if len(nonzero) < 3 or n < SHORT_HISTORY_DAYS:
        return SEASONAL_NAIVE

    adi = n / len(nonzero)

    if adi >= ADI_THRESHOLD:
        # A long silent tail means demand is dying, not merely sparse — TSB's
        # decaying demand-probability models that (and powers dead-stock
        # detection). Otherwise CrostonOptimized, whose self-tuned smoothing
        # backtested better than fixed-alpha TSB on steady-but-sparse SKUs.
        return TSB_MODEL if _is_dying(y) else CROSTON

    return MSTL if n >= MSTL_MIN_DAYS else AUTO_ETS


def _is_dying(y: np.ndarray, tail_days: int = 28) -> bool:
    """True when the trailing zero-run dwarfs the series' usual sale gap."""
    trailing_zeros = 0
    for value in y[::-1]:
        if value > 0:
            break
        trailing_zeros += 1

    nonzero_count = int((y > 0).sum())
    usual_gap = len(y) / max(1, nonzero_count)
    return trailing_zeros >= max(tail_days, 3 * usual_gap)

import numpy as np

from app import classify as cl


def repeat(pattern, days):
    return np.resize(np.array(pattern, dtype=float), days)


def test_tiny_history_is_naive():
    assert cl.classify(np.array([3.0] * 10)) == cl.NAIVE


def test_short_history_is_seasonal_naive():
    assert cl.classify(np.array([2.0] * 20)) == cl.SEASONAL_NAIVE


def test_too_few_sale_days_is_seasonal_naive():
    y = np.zeros(60)
    y[10] = 5.0
    y[40] = 3.0
    assert cl.classify(y) == cl.SEASONAL_NAIVE


def test_steady_daily_seller_is_auto_ets():
    # Sells almost every day with a weekly rhythm — smooth demand.
    y = repeat([4, 5, 6, 4, 5, 9, 11], 120)
    assert cl.classify(y) == cl.AUTO_ETS


def test_long_history_steady_seller_is_mstl():
    y = repeat([4, 5, 6, 4, 5, 9, 11], cl.MSTL_MIN_DAYS)
    assert cl.classify(y) == cl.MSTL


def test_intermittent_but_alive_is_croston():
    # Sells every ~4th day and keeps selling: sparse but not dying.
    y = repeat([3, 0, 0, 0], 120)
    assert cl.classify(y) == cl.CROSTON


def test_lumpy_but_alive_is_croston():
    # Rare AND wildly varying sizes, still selling recently.
    y = repeat([1, 0, 0, 0, 40, 0, 0, 0], 160)
    assert cl.classify(y) == cl.CROSTON


def test_dying_demand_is_tsb():
    # Used to sell steadily every few days, then went silent for two months —
    # TSB's decaying probability captures obsolescence (dead-stock detection).
    y = np.concatenate([repeat([4, 0, 3, 0], 120), np.zeros(60)])
    assert cl.classify(y) == cl.TSB_MODEL

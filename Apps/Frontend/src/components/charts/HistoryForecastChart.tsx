import {
  Area,
  CartesianGrid,
  ComposedChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
  Line,
} from 'recharts'
import type { DemandHistoryPoint, ForecastPoint } from '../../types'
import { formatNumber, formatShortDate } from '../../lib/format'
import { axisTick, chartColors, gridProps, tooltipStyle } from './theme'

interface Props {
  history: DemandHistoryPoint[]
  forecast?: ForecastPoint[]
  valueLabel?: string
  height?: number
}

interface Row {
  date: string
  actual?: number
  forecast?: number
  band_lo?: number
  band_delta?: number
}

/**
 * Product-detail demand chart: solid actuals, dashed model forecast, shaded
 * p90 band. Renders history-only when no forecast is available.
 */
export default function HistoryForecastChart({
  history,
  forecast = [],
  valueLabel = 'units sold',
  height = 240,
}: Props) {
  const rows: Row[] = history.map((p) => ({ date: p.date, actual: p.qty }))

  if (forecast.length > 0 && rows.length > 0) {
    const last = rows[rows.length - 1]!
    last.forecast = last.actual
    for (const p of forecast) {
      const lo = p.lo_90 ?? p.mean
      const hi = p.hi_90 ?? p.mean
      rows.push({
        date: p.date,
        forecast: Math.round(p.mean * 10) / 10,
        band_lo: Math.round(lo * 10) / 10,
        band_delta: Math.max(0, Math.round((hi - lo) * 10) / 10),
      })
    }
  }

  const labels: Record<string, string> = {
    actual: valueLabel,
    forecast: 'forecast',
    band_delta: 'p90 range',
  }

  return (
    <ResponsiveContainer width="100%" height={height}>
      <ComposedChart data={rows} margin={{ top: 8, right: 8, bottom: 0, left: -18 }}>
        <defs>
          <linearGradient id="actualFill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor={chartColors.brand} stopOpacity={0.2} />
            <stop offset="100%" stopColor={chartColors.brand} stopOpacity={0.02} />
          </linearGradient>
        </defs>
        <CartesianGrid {...gridProps} />
        <XAxis
          dataKey="date"
          tick={axisTick}
          tickLine={false}
          axisLine={false}
          tickFormatter={formatShortDate}
          interval="preserveStartEnd"
          minTickGap={32}
        />
        <YAxis tick={axisTick} tickLine={false} axisLine={false} width={44} allowDecimals={false} />
        <Tooltip
          contentStyle={tooltipStyle}
          labelFormatter={(label) => formatShortDate(String(label))}
          formatter={(value, name) => [formatNumber(Number(value)), labels[String(name)] ?? name]}
        />
        <Area dataKey="band_lo" stackId="band" stroke="none" fill="none" legendType="none" tooltipType="none" />
        {/* Zero-opacity stroke draws nothing — it only gives the tooltip row a
            readable text color instead of the pale band fill. */}
        <Area
          dataKey="band_delta"
          stackId="band"
          stroke={chartColors.infoDark}
          strokeOpacity={0}
          fill={chartColors.infoSoft}
          fillOpacity={0.75}
        />
        <Area dataKey="actual" stroke={chartColors.brand} strokeWidth={2} fill="url(#actualFill)" dot={false} />
        <Line
          dataKey="forecast"
          stroke={chartColors.info}
          strokeWidth={2}
          strokeDasharray="5 4"
          dot={false}
        />
      </ComposedChart>
    </ResponsiveContainer>
  )
}

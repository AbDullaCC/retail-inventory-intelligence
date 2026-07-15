import {
  Area,
  ComposedChart,
  CartesianGrid,
  Line,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import type { ForecastSummaryDaily, MovementTrendPoint } from '../../types'
import { formatNumber, formatShortDate } from '../../lib/format'
import { axisTick, chartColors, gridProps, tooltipStyle } from './theme'

interface Props {
  series: MovementTrendPoint[]
  /** Store-wide forecast — extends the chart past today with a dashed line + band. */
  projection?: ForecastSummaryDaily[]
  height?: number
}

interface Row {
  date: string
  units_out?: number
  units_in?: number
  projected?: number
  band_lo?: number
  band_delta?: number
}

const seriesLabels: Record<string, string> = {
  units_out: 'Units sold',
  units_in: 'Units received',
  projected: 'Projected sales',
  band_delta: 'Worst case (p90)',
}

export default function MovementsTrendChart({ series, projection = [], height = 260 }: Props) {
  const rows: Row[] = series.map((p) => ({
    date: p.date,
    units_out: p.units_out,
    units_in: p.units_in,
  }))

  if (projection.length > 0 && rows.length > 0) {
    // Duplicate the boundary so the dashed projection continues the solid line.
    const last = rows[rows.length - 1]!
    last.projected = last.units_out
    for (const p of projection) {
      rows.push({
        date: p.date,
        projected: Math.round(p.mean * 10) / 10,
        band_lo: Math.round(p.mean * 10) / 10,
        band_delta: Math.max(0, Math.round((p.hi_90 - p.mean) * 10) / 10),
      })
    }
  }

  return (
    <ResponsiveContainer width="100%" height={height}>
      <ComposedChart data={rows} margin={{ top: 8, right: 8, bottom: 0, left: -12 }}>
        <defs>
          <linearGradient id="unitsOutFill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor={chartColors.brand} stopOpacity={0.22} />
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
        <YAxis tick={axisTick} tickLine={false} axisLine={false} width={48} />
        <Tooltip
          contentStyle={tooltipStyle}
          labelFormatter={(label) => formatShortDate(String(label))}
          formatter={(value, name) => [formatNumber(Number(value)), seriesLabels[String(name)] ?? name]}
        />
        {/* p90 band: invisible baseline + shaded span stacked on top.
            The zero-opacity stroke draws nothing — it only sets the tooltip
            text color (Recharts uses stroke over fill), because the pale
            band fill is unreadable on the white tooltip. */}
        <Area dataKey="band_lo" stackId="band" stroke="none" fill="none" legendType="none" tooltipType="none" />
        <Area
          dataKey="band_delta"
          stackId="band"
          stroke={chartColors.infoDark}
          strokeOpacity={0}
          fill={chartColors.infoSoft}
          fillOpacity={0.7}
        />
        <Area
          dataKey="units_out"
          stroke={chartColors.brand}
          strokeWidth={2}
          fill="url(#unitsOutFill)"
          dot={false}
        />
        <Line dataKey="units_in" stroke={chartColors.info} strokeWidth={1.5} dot={false} />
        <Line
          dataKey="projected"
          stroke={chartColors.brandDark}
          strokeWidth={2}
          strokeDasharray="5 4"
          dot={false}
        />
      </ComposedChart>
    </ResponsiveContainer>
  )
}

import { Bar, BarChart, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { formatShortDate } from '../../lib/format'
import type { StockoutDay } from '../../lib/insights'
import { axisTick, chartColors, tooltipStyle } from './theme'

interface Props {
  days: StockoutDay[]
  leadTimeDays: number
  height?: number
}

/**
 * Products by projected stockout day over the coming weeks. Bars inside the
 * lead time are the ones an order placed today can no longer save.
 */
export default function UpcomingStockoutsChart({ days, leadTimeDays, height = 260 }: Props) {
  return (
    <div>
      <div className="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
        <span className="inline-flex items-center gap-1.5">
          <span className="h-2 w-2 rounded-full" style={{ background: chartColors.danger }} />
          runs out before new stock could arrive ({leadTimeDays}-day lead time)
        </span>
        <span className="inline-flex items-center gap-1.5">
          <span className="h-2 w-2 rounded-full" style={{ background: chartColors.warning }} />
          runs out later — the reorder window is still open
        </span>
      </div>
      <ResponsiveContainer width="100%" height={height}>
        <BarChart data={days} margin={{ top: 8, right: 8, bottom: 0, left: -18 }}>
          <XAxis
            dataKey="date"
            tick={axisTick}
            tickLine={false}
            axisLine={false}
            interval={2}
            tickFormatter={(value) => formatShortDate(String(value))}
          />
          <YAxis allowDecimals={false} tick={axisTick} tickLine={false} axisLine={false} />
          <Tooltip
            cursor={{ fill: 'rgb(241 245 249 / 0.6)' }}
            content={({ active, payload }) => {
              if (!active || !payload || payload.length === 0) return null
              const day = payload[0]!.payload as StockoutDay
              return (
                <div style={tooltipStyle}>
                  <p className="font-medium text-slate-900">{formatShortDate(day.date)}</p>
                  <p className="text-slate-600">
                    {day.count === 1 ? '1 product' : `${day.count} products`} projected to run out
                  </p>
                  {day.names.length > 0 && (
                    <p className="mt-1 max-w-56 text-slate-500">
                      {day.names.join(', ')}
                      {day.count > day.names.length && ` +${day.count - day.names.length} more`}
                    </p>
                  )}
                </div>
              )
            }}
          />
          <Bar dataKey="count" radius={[4, 4, 0, 0]} maxBarSize={22}>
            {days.map((day) => (
              <Cell
                key={day.date}
                fill={day.withinLeadTime ? chartColors.danger : chartColors.warning}
              />
            ))}
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </div>
  )
}

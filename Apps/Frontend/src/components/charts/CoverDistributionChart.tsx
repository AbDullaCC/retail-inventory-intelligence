import { Bar, BarChart, Cell, LabelList, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { formatNumber } from '../../lib/format'
import type { CoverBucket, CoverTone } from '../../lib/insights'
import { axisTick, chartColors, tooltipStyle } from './theme'

const toneFill: Record<CoverTone, string> = {
  danger: chartColors.danger,
  warning: chartColors.warning,
  success: chartColors.success,
  neutral: chartColors.slate,
}

interface Props {
  buckets: CoverBucket[]
  height?: number
}

/** Histogram of days-of-cover across the products currently in the list. */
export default function CoverDistributionChart({ buckets, height = 260 }: Props) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart data={buckets} margin={{ top: 16, right: 8, bottom: 0, left: -18 }}>
        <XAxis
          dataKey="label"
          tick={{ ...axisTick, fontSize: 12 }}
          tickLine={false}
          axisLine={false}
          interval={0}
        />
        <YAxis allowDecimals={false} tick={axisTick} tickLine={false} axisLine={false} />
        <Tooltip
          contentStyle={tooltipStyle}
          cursor={{ fill: 'rgb(241 245 249 / 0.6)' }}
          formatter={(value) => [`${formatNumber(Number(value))} products`, 'Count']}
        />
        <Bar dataKey="count" radius={[4, 4, 0, 0]} maxBarSize={48}>
          <LabelList
            dataKey="count"
            position="top"
            style={{ fill: '#475569', fontSize: 11 }}
            formatter={(value: unknown) => {
              const count = Number(value)
              return count > 0 ? formatNumber(count) : ''
            }}
          />
          {buckets.map((bucket) => (
            <Cell key={bucket.label} fill={toneFill[bucket.tone]} />
          ))}
        </Bar>
      </BarChart>
    </ResponsiveContainer>
  )
}

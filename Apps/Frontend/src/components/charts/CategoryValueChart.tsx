import { Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import type { CategoryValue } from '../../types'
import { formatCompactCurrency, formatCurrency } from '../../lib/format'
import { axisTick, chartColors, tooltipStyle } from './theme'

interface Props {
  data: CategoryValue[]
  height?: number
}

const TOP = 6

export default function CategoryValueChart({ data, height = 260 }: Props) {
  const top = data.slice(0, TOP)
  const rest = data.slice(TOP)
  const rows = [
    ...top.map((c) => ({ name: c.category_name, value: c.stock_value })),
    ...(rest.length > 0
      ? [{ name: 'Other', value: rest.reduce((sum, c) => sum + c.stock_value, 0) }]
      : []),
  ]

  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart data={rows} layout="vertical" margin={{ top: 4, right: 40, bottom: 0, left: 8 }}>
        <XAxis
          type="number"
          tick={axisTick}
          tickLine={false}
          axisLine={false}
          tickFormatter={(v) => formatCompactCurrency(Number(v))}
        />
        <YAxis
          type="category"
          dataKey="name"
          tick={{ ...axisTick, fontSize: 12 }}
          tickLine={false}
          axisLine={false}
          width={130}
        />
        <Tooltip
          contentStyle={tooltipStyle}
          cursor={{ fill: 'rgb(241 245 249 / 0.6)' }}
          formatter={(value) => [formatCurrency(Number(value)), 'Stock value']}
        />
        <Bar dataKey="value" fill={chartColors.brand} radius={[0, 4, 4, 0]} barSize={18} />
      </BarChart>
    </ResponsiveContainer>
  )
}

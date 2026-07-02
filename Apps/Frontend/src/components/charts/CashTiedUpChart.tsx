import { Bar, BarChart, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { formatCompactCurrency, formatCurrency } from '../../lib/format'
import { axisTick, chartColors, tooltipStyle } from './theme'

export interface CashItem {
  productId: number
  name: string
  value: number
}

interface Props {
  items: CashItem[]
  onSelect?: (productId: number) => void
  height?: number
}

export default function CashTiedUpChart({ items, onSelect, height = 260 }: Props) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart data={items} layout="vertical" margin={{ top: 4, right: 40, bottom: 0, left: 8 }}>
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
          width={170}
        />
        <Tooltip
          contentStyle={tooltipStyle}
          cursor={{ fill: 'rgb(241 245 249 / 0.6)' }}
          formatter={(value) => [formatCurrency(Number(value)), 'Cash tied up']}
        />
        <Bar
          dataKey="value"
          fill={chartColors.warning}
          radius={[0, 4, 4, 0]}
          barSize={16}
          onClick={(entry) => {
            const id = (entry as unknown as CashItem).productId
            if (onSelect && id) onSelect(id)
          }}
          cursor={onSelect ? 'pointer' : undefined}
        >
          {items.map((item) => (
            <Cell key={item.productId} />
          ))}
        </Bar>
      </BarChart>
    </ResponsiveContainer>
  )
}

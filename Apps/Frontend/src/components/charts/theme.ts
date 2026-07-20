/**
 * Shared chart styling — token hexes (SVG attributes want concrete colors)
 * and common axis props so every chart reads as one family.
 */
export const chartColors = {
  brand: '#1d8f8a',
  brandDark: '#0f7470',
  info: '#0284c7',
  infoDark: '#0369a1',
  infoSoft: '#e0f2fe',
  warning: '#d97706',
  danger: '#dc2626',
  success: '#16a34a',
  // Neutral status fill (e.g. "no sales") — slate-500 keeps ≥3:1 on white.
  slate: '#64748b',
  axis: '#94a3b8',
  grid: '#e2e8f0',
}

export const axisTick = { fill: chartColors.axis, fontSize: 11 }

export const gridProps = {
  vertical: false,
  stroke: chartColors.grid,
  strokeDasharray: '3 3',
}

export const tooltipStyle = {
  background: '#fff',
  border: '1px solid #e2e8f0',
  borderRadius: 10,
  boxShadow: '0 10px 30px -6px rgb(15 23 42 / 0.15)',
  fontSize: 12,
  padding: '8px 12px',
}

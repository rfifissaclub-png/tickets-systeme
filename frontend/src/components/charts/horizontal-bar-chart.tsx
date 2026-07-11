export interface BarDatum {
  label: string
  value: number
}

interface HorizontalBarChartProps {
  title: string
  data: BarDatum[]
  colors: string[]
}

/**
 * Direct labels sit outside the bar (not inside) regardless of bar length —
 * simpler and safer than measuring text-fits-inside-bar for every render,
 * and per the dataviz skill's own guidance, a label that might not fit
 * belongs outside the mark rather than being conditionally placed. Since
 * every value is already visible as text, there's nothing gated behind
 * hover, so this chart doesn't need its own tooltip layer.
 */
export function HorizontalBarChart({ title, data, colors }: HorizontalBarChartProps) {
  const max = Math.max(...data.map((datum) => datum.value), 1)

  return (
    <div className="viz-root space-y-3">
      <h3 className="text-sm font-medium">{title}</h3>
      <div className="space-y-2">
        {data.map((datum, index) => {
          const widthPercent = Math.max((datum.value / max) * 100, datum.value > 0 ? 2 : 0)

          return (
            <div
              key={datum.label}
              className="flex items-center gap-3"
              role="img"
              aria-label={`${datum.label}: ${datum.value}`}
            >
              <span className="text-muted-foreground w-28 shrink-0 truncate text-xs">{datum.label}</span>
              <div className="bg-muted/40 relative h-5 flex-1 overflow-hidden rounded-sm" aria-hidden="true">
                <div
                  className="absolute inset-y-0 left-0 h-5 rounded-r-[4px]"
                  style={{ width: `${widthPercent}%`, backgroundColor: colors[index % colors.length] }}
                />
              </div>
              <span className="text-foreground w-10 shrink-0 text-right text-xs font-medium tabular-nums">
                {datum.value}
              </span>
            </div>
          )
        })}
      </div>
    </div>
  )
}

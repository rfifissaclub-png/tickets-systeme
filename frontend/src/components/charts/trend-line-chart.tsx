import { useMemo, useState } from 'react'

export interface TrendPoint {
  date: string
  created: number
  resolved: number
}

interface TrendLineChartProps {
  title: string
  data: TrendPoint[]
}

const WIDTH = 720
const HEIGHT = 260
const PADDING = { top: 16, right: 16, bottom: 28, left: 36 }
const PLOT_WIDTH = WIDTH - PADDING.left - PADDING.right
const PLOT_HEIGHT = HEIGHT - PADDING.top - PADDING.bottom

function niceMax(value: number): number {
  if (value <= 0) return 4
  const magnitude = 10 ** Math.floor(Math.log10(value))
  const normalized = value / magnitude
  const step = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10
  return step * magnitude
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

/**
 * Two series, same unit (ticket counts) — a single shared Y axis, per the
 * dataviz skill's "one axis" rule (never dual-axis). Crosshair snaps to the
 * nearest date and shows both series in one tooltip, so the pointer never
 * has to land precisely on a line.
 */
export function TrendLineChart({ title, data }: TrendLineChartProps) {
  const [hoverIndex, setHoverIndex] = useState<number | null>(null)

  const maxValue = useMemo(
    () => niceMax(Math.max(...data.map((point) => Math.max(point.created, point.resolved)), 0)),
    [data],
  )

  const xFor = (index: number) =>
    data.length <= 1 ? PADDING.left : PADDING.left + (index / (data.length - 1)) * PLOT_WIDTH
  const yFor = (value: number) => PADDING.top + PLOT_HEIGHT - (value / maxValue) * PLOT_HEIGHT

  const createdPath = data.map((point, index) => `${xFor(index)},${yFor(point.created)}`).join(' ')
  const resolvedPath = data.map((point, index) => `${xFor(index)},${yFor(point.resolved)}`).join(' ')

  const gridSteps = 4
  const gridValues = Array.from({ length: gridSteps + 1 }, (_, index) => (maxValue / gridSteps) * index)

  const onPointerMove = (event: React.PointerEvent<SVGRectElement>) => {
    if (data.length === 0) return
    const rect = event.currentTarget.getBoundingClientRect()
    const relativeX = ((event.clientX - rect.left) / rect.width) * WIDTH
    const ratio = Math.min(Math.max((relativeX - PADDING.left) / PLOT_WIDTH, 0), 1)
    const index = Math.round(ratio * (data.length - 1))
    setHoverIndex(index)
  }

  const hovered = hoverIndex !== null ? data[hoverIndex] : null

  return (
    <div className="viz-root space-y-3">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-medium">{title}</h3>
        {/* Legend: line-key swatches, always present for 2+ series */}
        <div className="flex items-center gap-4 text-xs">
          <span className="flex items-center gap-1.5">
            <svg width="16" height="8" aria-hidden="true">
              <line x1="0" y1="4" x2="16" y2="4" stroke="var(--viz-series-1)" strokeWidth="2" strokeLinecap="round" />
            </svg>
            <span className="text-muted-foreground">Created</span>
          </span>
          <span className="flex items-center gap-1.5">
            <svg width="16" height="8" aria-hidden="true">
              <line x1="0" y1="4" x2="16" y2="4" stroke="var(--viz-series-2)" strokeWidth="2" strokeLinecap="round" />
            </svg>
            <span className="text-muted-foreground">Resolved</span>
          </span>
        </div>
      </div>

      <div className="relative">
        <svg viewBox={`0 0 ${WIDTH} ${HEIGHT}`} className="w-full" role="img" aria-label={`${title} line chart`}>
          {gridValues.map((value) => (
            <g key={value}>
              <line
                x1={PADDING.left}
                x2={WIDTH - PADDING.right}
                y1={yFor(value)}
                y2={yFor(value)}
                stroke="var(--viz-grid)"
                strokeWidth="1"
              />
              <text x={PADDING.left - 8} y={yFor(value)} textAnchor="end" dominantBaseline="middle" fontSize="10" fill="var(--viz-axis)">
                {Math.round(value)}
              </text>
            </g>
          ))}

          {data.length > 0 && (
            <>
              <text x={xFor(0)} y={HEIGHT - 8} textAnchor="start" fontSize="10" fill="var(--viz-axis)">
                {formatDate(data[0].date)}
              </text>
              <text x={xFor(data.length - 1)} y={HEIGHT - 8} textAnchor="end" fontSize="10" fill="var(--viz-axis)">
                {formatDate(data[data.length - 1].date)}
              </text>
            </>
          )}

          <polyline points={createdPath} fill="none" stroke="var(--viz-series-1)" strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
          <polyline points={resolvedPath} fill="none" stroke="var(--viz-series-2)" strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />

          {data.length > 0 && (
            <>
              <circle cx={xFor(data.length - 1)} cy={yFor(data[data.length - 1].created)} r="4" fill="var(--viz-series-1)" stroke="var(--card)" strokeWidth="2" />
              <circle cx={xFor(data.length - 1)} cy={yFor(data[data.length - 1].resolved)} r="4" fill="var(--viz-series-2)" stroke="var(--card)" strokeWidth="2" />
            </>
          )}

          {hoverIndex !== null && (
            <line
              x1={xFor(hoverIndex)}
              x2={xFor(hoverIndex)}
              y1={PADDING.top}
              y2={HEIGHT - PADDING.bottom}
              stroke="var(--viz-axis)"
              strokeWidth="1"
              strokeDasharray="2 2"
            />
          )}

          <rect
            x={PADDING.left}
            y={PADDING.top}
            width={PLOT_WIDTH}
            height={PLOT_HEIGHT}
            fill="transparent"
            onPointerMove={onPointerMove}
            onPointerLeave={() => setHoverIndex(null)}
          />
        </svg>

        {hovered && (
          <div
            className="bg-popover text-popover-foreground pointer-events-none absolute top-2 z-10 rounded-md border px-2.5 py-1.5 text-xs shadow-md"
            style={{
              left: `${(xFor(hoverIndex ?? 0) / WIDTH) * 100}%`,
              transform: hoverIndex !== null && hoverIndex > data.length / 2 ? 'translateX(-100%)' : undefined,
            }}
          >
            <p className="text-muted-foreground mb-1 font-medium">{formatDate(hovered.date)}</p>
            <p>
              <span className="font-semibold tabular-nums">{hovered.created}</span> created
            </p>
            <p>
              <span className="font-semibold tabular-nums">{hovered.resolved}</span> resolved
            </p>
          </div>
        )}
      </div>
    </div>
  )
}

import { Card, CardContent } from '@/components/ui/card'

interface StatTileProps {
  label: string
  value: string
}

/** Figure contract (dataviz skill): label sentence-case no colon, value in proportional figures, semibold. */
export function StatTile({ label, value }: StatTileProps) {
  return (
    <Card className="viz-root">
      <CardContent className="space-y-1">
        <p className="text-muted-foreground text-sm">{label}</p>
        <p className="text-2xl font-semibold">{value}</p>
      </CardContent>
    </Card>
  )
}

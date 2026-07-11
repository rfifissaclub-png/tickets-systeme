import { Component, type ErrorInfo, type ReactNode } from 'react'
import { AlertTriangleIcon } from 'lucide-react'

import { Button } from '@/components/ui/button'

interface Props {
  children: ReactNode
  /** Optional custom fallback; defaults to a generic full-panel error state. */
  fallback?: ReactNode
}

interface State {
  error: Error | null
}

/**
 * Class component is required here — React has no hook-based equivalent of
 * componentDidCatch/getDerivedStateFromError yet, so this is the one place
 * in the app that can't be a function component.
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null }

  static getDerivedStateFromError(error: Error): State {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // eslint-disable-next-line no-console
    console.error('Unhandled UI error:', error, info.componentStack)
  }

  private reset = () => this.setState({ error: null })

  render() {
    if (this.state.error) {
      if (this.props.fallback) return this.props.fallback

      return (
        <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 p-8 text-center">
          <AlertTriangleIcon className="text-destructive size-10" />
          <div className="space-y-1">
            <h2 className="text-lg font-semibold">Something went wrong</h2>
            <p className="text-muted-foreground text-sm">
              An unexpected error occurred while rendering this page.
            </p>
          </div>
          <Button onClick={this.reset} variant="outline">
            Try again
          </Button>
        </div>
      )
    }

    return this.props.children
  }
}

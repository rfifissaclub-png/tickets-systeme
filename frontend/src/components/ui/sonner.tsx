import { Toaster as SonnerToaster, type ToasterProps } from 'sonner'

import { useThemeStore } from '@/stores/theme-store'

function Toaster(props: ToasterProps) {
  const theme = useThemeStore((state) => state.theme)

  return (
    <SonnerToaster
      theme={theme}
      className="toaster group"
      style={
        {
          '--normal-bg': 'var(--popover)',
          '--normal-text': 'var(--popover-foreground)',
          '--normal-border': 'var(--border)',
        } as React.CSSProperties
      }
      {...props}
    />
  )
}

export { Toaster }

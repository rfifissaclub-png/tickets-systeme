import { useEffect } from 'react'

import { useThemeStore } from '@/stores/theme-store'

export function ThemeProvider({ children }: { children: React.ReactNode }) {
  const theme = useThemeStore((state) => state.theme)

  useEffect(() => {
    const root = window.document.documentElement

    const apply = (resolved: 'light' | 'dark') => {
      root.classList.remove('light', 'dark')
      root.classList.add(resolved)
    }

    if (theme === 'system') {
      const media = window.matchMedia('(prefers-color-scheme: dark)')
      apply(media.matches ? 'dark' : 'light')

      const listener = (event: MediaQueryListEvent) => apply(event.matches ? 'dark' : 'light')
      media.addEventListener('change', listener)
      return () => media.removeEventListener('change', listener)
    }

    apply(theme)
  }, [theme])

  return <>{children}</>
}

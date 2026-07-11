import { useTranslation } from 'react-i18next'
import { MonitorIcon, MoonIcon, SunIcon } from 'lucide-react'

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Button } from '@/components/ui/button'
import { useThemeStore, type Theme } from '@/stores/theme-store'

const OPTIONS: { value: Theme; labelKey: string; icon: typeof SunIcon }[] = [
  { value: 'light', labelKey: 'theme.light', icon: SunIcon },
  { value: 'dark', labelKey: 'theme.dark', icon: MoonIcon },
  { value: 'system', labelKey: 'theme.system', icon: MonitorIcon },
]

export function ThemeToggle() {
  const { t } = useTranslation()
  const theme = useThemeStore((state) => state.theme)
  const setTheme = useThemeStore((state) => state.setTheme)
  const Active = OPTIONS.find((option) => option.value === theme)?.icon ?? SunIcon

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" aria-label={t('theme.toggle')}>
          <Active className="size-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {OPTIONS.map((option) => (
          <DropdownMenuItem key={option.value} onClick={() => setTheme(option.value)}>
            <option.icon className="size-4" />
            {t(option.labelKey)}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

export type ThemeMode = 'light' | 'dark' | 'system'

const STORAGE_KEY = 'theme-mode'

export function useTheme() {
  const mode = useState<ThemeMode>('theme-mode', () => 'system')

  function apply(value: ThemeMode) {
    if (value === 'system') {
      document.documentElement.removeAttribute('data-theme')
    } else {
      document.documentElement.dataset.theme = value
    }
  }

  function setMode(value: ThemeMode) {
    mode.value = value
    localStorage.setItem(STORAGE_KEY, value)
    apply(value)
  }

  function init() {
    const stored = localStorage.getItem(STORAGE_KEY) as ThemeMode | null
    mode.value = stored ?? 'system'
    apply(mode.value)
  }

  return { mode, setMode, init }
}

import type { Config } from 'tailwindcss'

export default <Partial<Config>>{
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: 'var(--primary)',
          strong: 'var(--primary-strong)',
          soft: 'var(--primary-soft)',
        },
        surface: {
          DEFAULT: 'var(--surface)',
          sunken: 'var(--surface-sunken)',
          dark: 'var(--surface-dark)',
          'dark-raised': 'var(--surface-dark-raised)',
          'dark-hover': 'var(--surface-dark-hover)',
        },
        canvas: 'var(--background)',
        hairline: 'var(--border)',
        'hairline-dark': 'var(--border-dark)',
        ink: {
          DEFAULT: 'var(--text-primary)',
          soft: 'var(--text-secondary)',
          faint: 'var(--text-tertiary)',
        },
        'ink-dark': {
          DEFAULT: 'var(--text-dark-primary)',
          soft: 'var(--text-dark-secondary)',
        },
        state: {
          success: 'var(--success)',
          'success-soft': 'var(--success-soft)',
          warning: 'var(--warning)',
          'warning-soft': 'var(--warning-soft)',
          danger: 'var(--danger)',
          'danger-soft': 'var(--danger-soft)',
          neutral: 'var(--neutral)',
          'neutral-soft': 'var(--neutral-soft)',
        },
      },
      borderRadius: {
        sm: 'var(--radius-sm)',
        md: 'var(--radius-md)',
        lg: 'var(--radius-lg)',
        pill: 'var(--radius-pill)',
      },
      boxShadow: {
        ambient: 'var(--shadow-ambient)',
        key: 'var(--shadow-key)',
        raised: 'var(--shadow-raised)',
        'dark-key': 'var(--shadow-dark-key)',
      },
      transitionTimingFunction: {
        soft: 'cubic-bezier(0.16, 1, 0.3, 1)',
      },
      transitionDuration: {
        fast: '120ms',
        base: '180ms',
        slow: '260ms',
      },
      spacing: {
        control: 'var(--control-h)',
        row: 'var(--row-h)',
      },
    },
  },
}

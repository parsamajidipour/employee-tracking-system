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
          muted: 'var(--surface-muted)',
        },
        canvas: 'var(--background)',
        hairline: 'var(--border)',
        ink: {
          DEFAULT: 'var(--text-primary)',
          soft: 'var(--text-secondary)',
          faint: 'var(--text-tertiary)',
        },
        state: {
          success: 'var(--success)',
          warning: 'var(--warning)',
          danger: 'var(--danger)',
          neutral: 'var(--neutral)',
        },
      },
      borderRadius: {
        card: 'var(--radius-card)',
        control: 'var(--radius-control)',
        small: 'var(--radius-small)',
      },
      boxShadow: {
        card: 'var(--shadow-card)',
        raised: 'var(--shadow-raised)',
      },
      transitionTimingFunction: {
        soft: 'cubic-bezier(0.33, 1, 0.68, 1)',
      },
    },
  },
}

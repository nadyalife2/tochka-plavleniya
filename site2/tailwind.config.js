/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    './*.{html,php}',
    './includes/**/*.php',
    './assets/js/**/*.js'
  ],
  theme: {
    extend: {
      colors: {
        paper: 'var(--color-paper)',
        'paper-subtle': 'var(--color-paper-subtle)',
        'paper-border': 'var(--color-paper-border)',
        'paper-border-dark': 'var(--color-paper-border-dark)',
        ink: 'var(--color-ink)',
        'ink-muted': 'var(--color-ink-muted)',
        'ink-faint': 'var(--color-ink-faint)',
        callout: 'var(--color-callout)',
        card: 'var(--color-card)',
        highlight: '#fef9c3',
        'highlight-strong': '#fef08a',
        brand: {
          orange: '#eb5211',
          dark: '#0f0f0f',
          yellowNote: '#fcf282'
        },
        sketch: {
          orange: '#FC6C2B',
          yellow: '#FFF1A8',
          mint: '#BDEEE2',
          peach: '#FFDCCB',
          ink: '#1A1A1A',
          paper: '#FCFAF7'
        }
      },
      fontFamily: {
        sans: ['"IBM Plex Sans"', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
        serif: ['Newsreader', 'Georgia', 'serif'],
        mono: ['"JetBrains Mono"', 'monospace'],
        hand: ['Caveat', 'cursive'],
        logo: ['"IBM Plex Sans"', 'sans-serif']
      }
    }
  },
  plugins: []
};

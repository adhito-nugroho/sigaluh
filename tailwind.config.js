/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./pages/**/*.php",
    "./includes/**/*.php",
    "./*.php",
    "./assets/**/*.js"
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Roboto', 'Roboto Flex', 'system-ui', 'sans-serif'],
        display: ['Roboto Flex', 'Roboto', 'system-ui', 'sans-serif'],
        mono: ['Roboto Mono', 'monospace'],
      }
    },
  },
  plugins: [],
}

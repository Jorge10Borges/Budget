/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './src/**/*.{astro,html,js,jsx,ts,tsx,svelte}',
    './public/**/*.html'
  ],
  theme: {
    extend: {
      colors: {
        primary: '#003ca3'
      }
    },
  },
  plugins: [],
}

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./assets/**/*.js",
    "./templates/**/*.html.twig",
  ],
  theme: {
    extend: {
      colors: {
        'paynes-gray': '#555b6e',
        'cambridge-blue': '#89b0ae',
        'mint-green': '#bee3db',
        'seasalt': '#faf9f9',
        'apricot': '#ffd6ba',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms')
  ],
}


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

        'magnolia': '#f4effaff',
      'russian-violet': '#2f184bff',
      'tekhelet': '#532b88ff',
      'amethyst': '#9b72cfff',
      'wisteria': '#c8b1e4ff',

      'g-violet': '#d292ff',
      'g-dark-blue': '#00002e',
      'g-light-blue': '#3c46ff',
      'g-light-blue-hovered': '#0000ff',
      'g-dark-red':  '#170005',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms')
  ],
}


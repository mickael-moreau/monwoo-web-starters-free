/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./assets/**/*.{js,json,svelte,ts,scss}",

    "./templates/**/*.html.twig",
    "./src/**/*.php",

    // TODO : inject with bundle recipe ? or webpack bundle local build ?
    // Symfony Bundle
    "../../../packages/mws-moon-manager/assets/**/*.scss",
    "../../../packages/mws-moon-manager/assets/**/*.js",
    "../../../packages/mws-moon-manager/assets/**/*.svelte",
    "../../../packages/mws-moon-manager/assets/**/*.ts",
    "../../../packages/mws-moon-manager/templates/**/*.html.twig",
    "../../../packages/mws-moon-manager/src/**/*.php",
    // Svelte UX package
    "../../../packages/mws-moon-manager-ux/components/**/*.js",
    "../../../packages/mws-moon-manager-ux/components/**/*.svelte",
    "../../../packages/mws-moon-manager-ux/components/**/*.ts",
    // https://flowbite.com/docs/getting-started/quickstart/
    "./node_modules/flowbite/**/*.js",
    // https://flowbite-svelte.com/docs/pages/introduction
    './node_modules/flowbite-svelte/**/*.{html,js,svelte,ts}',
  ],
  theme: {
    extend: {
      colors: {
        // flowbite-svelte
        primary: {
          50: '#FFF5F2',
          100: '#FFF1EE',
          200: '#FFE4DE',
          300: '#FFD5CC',
          400: '#FFBCAD',
          500: '#FE795D',
          600: '#EF562F',
          700: '#EB4F27',
          800: '#CC4522',
          900: '#A5371B'
        }
      }
    }
  },
  darkMode: 'class',
  plugins: [
    // https://github.com/tailwindlabs/tailwindcss-forms
    require('@tailwindcss/forms'),
    require('flowbite/plugin'),
  ],
}
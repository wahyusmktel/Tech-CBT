/** @type {import('tailwindcss').Config} */
export default {
    content: [
      "./index.html",
      "./src/**/*.{js,ts,jsx,tsx}",
    ],
    theme: {
      extend: {
        colors: {
          'brand-red': '#E53E3E', // Merah utama
          'brand-gray': '#F3F4F6', // Abu-abu terang
        },
        fontFamily: {
          // Menggunakan Inter yang sangat identik dengan San Francisco (MacOS)
          sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
        },
      },
    },
    plugins: [],
  }
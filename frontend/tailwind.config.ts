import type { Config } from "tailwindcss";

const config: Config = {
  content: [
    "./src/pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/components/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/app/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        background: "var(--background)",
        foreground: "var(--foreground)",
        clay:  { DEFAULT: '#C4A882', dark: '#8B6914' },
        cream: { DEFAULT: '#F5F0E8', muted: '#E8DDD0' },
        bark:  { DEFAULT: '#3D2B1F', light: '#5C3D2E' },
        moss:  '#4A5240',
        amber: '#D4A853',
      },
    },
  },
  plugins: [],
};
export default config;

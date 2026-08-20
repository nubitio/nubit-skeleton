import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// The icon and text font stylesheets reference several copies of each font.
// Modern browsers only need woff2; retaining the fallbacks makes Vite emit
// more than 4 MB of redundant assets in every deployment.
const modernFontsWoff2Only = {
  name: 'modern-fonts-woff2-only',
  enforce: 'pre' as const,
  transform(source: string, id: string) {
    if (id.includes('@phosphor-icons') && id.endsWith('/regular/style.css')) {
      return source.replace(
        /src:\s*url\("\.\/Phosphor\.woff2"\)[\s\S]*?url\("\.\/Phosphor\.svg#Phosphor"\) format\("svg"\);/,
        'src: url("./Phosphor.woff2") format("woff2");',
      );
    }
    if (id.includes('@fontsource') && id.endsWith('.css')) {
      return source.replace(/,\s*url\([^)]*\.woff\) format\('woff'\)/g, '');
    }
    return null;
  },
};

// In docker compose the API container is reachable as `app`; when running
// vite directly on the host, the API is published on localhost:8000.
const apiTarget = process.env.API_PROXY_TARGET ?? 'http://localhost:8000';
const mercureTarget = process.env.MERCURE_PROXY_TARGET ?? 'http://localhost:3000';

export default defineConfig({
  plugins: [modernFontsWoff2Only, react()],
  build: {
    // The stack includes the rich-text editor in the CRUD runtime. Track the
    // compressed regression separately instead of treating Rollup's 500 kB
    // uncompressed default as the product budget.
    chunkSizeWarningLimit: 1100,
  },
  server: {
    proxy: {
      '/api': { target: apiTarget, changeOrigin: false },
      '/.well-known/mercure': { target: mercureTarget, changeOrigin: true },
    },
  },
  preview: {
    proxy: {
      '/api': { target: apiTarget, changeOrigin: false },
      '/.well-known/mercure': { target: mercureTarget, changeOrigin: true },
    },
  },
});

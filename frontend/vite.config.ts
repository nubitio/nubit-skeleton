import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// In docker compose the API container is reachable as `app`; when running
// vite directly on the host, the API is published on localhost:8000.
const apiTarget = process.env.API_PROXY_TARGET ?? 'http://localhost:8000';

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': { target: apiTarget, changeOrigin: false },
    },
  },
});

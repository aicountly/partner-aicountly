import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  base: '/',
  server: {
    port: 5173,
    // Local dev: the SPA calls /api on the Vite origin so the session cookie
    // stays first-party, exactly as it is in production.
    proxy: {
      '/api': {
        target: process.env.VITE_DEV_API_ORIGIN || 'http://127.0.0.1:8081',
        changeOrigin: false,
        rewrite: (p) => p.replace(/^\/api/, ''),
      },
    },
  },
})

/// <reference types="vitest" />
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  // When the SPA is served from Laravel's public/spa/ in production, the
  // assets need to be prefixed with /spa/. Override with VITE_BASE_URL='/'
  // when hosting the SPA on a separate domain (e.g. Vercel).
  base: '/spa/',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
  server: {
    port: 5173,
    strictPort: true,
  },
  test: {
    environment: 'jsdom',
    globals: true,
  },
})

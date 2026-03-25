import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [vue()],
  root: 'frontend',
  base: '/dist/',
  build: {
    outDir: '../public/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'frontend/index.html')
    }
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'frontend/src')
    }
  },
  server: {
    proxy: {
      '/api': 'http://localhost:8080',
      '/track': 'http://localhost:8080',
      '/phish': 'http://localhost:8080',
      '/awareness': 'http://localhost:8080'
    }
  }
})

import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// En Docker la API vive en pedidos_nginx; fuera de Docker, en localhost:8120.
const api = process.env.VITE_API_PROXY ?? 'http://localhost:8120'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    proxy: { '/api': { target: api, changeOrigin: true } },
  },
})

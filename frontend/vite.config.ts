import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // Слушаем все интерфейсы: иначе Vite доступен только внутри контейнера
    // и nginx не может до него достучаться.
    host: '0.0.0.0',
    port: 5173,

    // Браузер обращается к приложению по localhost:8080 (nginx), а не к 5173.
    // Без этого блока WebSocket для HMR стучится не туда, и горячая
    // перезагрузка молча перестаёт работать.
    hmr: {
      clientPort: 8080,
    },

    watch: {
      // На Windows + Docker inotify не видит изменения файлов через
      // смонтированный том, поэтому включаем опрос.
      usePolling: true,
      interval: 300,
    },
  },
})

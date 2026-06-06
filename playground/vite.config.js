import { defineConfig } from 'vite';

export default defineConfig({
  server: {
    port: 5173,
    proxy: {
      '/wp-json': {
        target: 'http://127.0.0.1:9400',
        changeOrigin: true,
      },
    },
  },
});

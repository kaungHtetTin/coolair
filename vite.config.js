import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
export default defineConfig({
  base: './', publicDir: false, plugins: [react()],
  build: { outDir: 'public/build', emptyOutDir: true, manifest: true, rollupOptions: { input: 'resources/js/app.jsx' } },
});

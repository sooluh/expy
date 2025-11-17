import { minify } from 'terser'
import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import laravel, { refreshPaths } from 'laravel-vite-plugin'

const minifier = () => {
  return {
    name: 'app-minifier',
    enforce: 'post',
    generateBundle(_outputOptions, bundle) {
      Object.keys(bundle).forEach(async (fileName) => {
        const chunk = bundle[fileName]

        if (chunk.type === 'chunk' && chunk.code) {
          const terser = await minify(chunk.code, {
            compress: true,
            mangle: true,
            ie8: true,
            safari10: true,
            ecma: 2015,
          })

          let code = terser.code || chunk.code

          code = code.replace(/\/\*\*[\s\S]*?\*\//g, '')
          code = code.replace(/\/\*[\s\S]*?\*\//g, '')
          code = code.replace(/\\n(\s+)?/g, '')

          chunk.code = code
        }
      })
    },
  }
}

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/scss/studio.scss',
        'resources/js/studio.js',
        'resources/css/filament/studio/theme.css',
      ],
      refresh: [...refreshPaths, ' app/Http/Livewire/**'],
    }),
    tailwindcss(),
    minifier(),
  ],
  css: {
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler',
      },
    },
  },
  build: {
    target: 'es2015',
    rollupOptions: {
      output: {
        manualChunks(id) {
          const chunks = []
          const result = chunks.find((str) => id.toLowerCase().includes(str))

          if (result) return result
        },
      },
    },
  },
  optimizeDeps: {
    esbuildOptions: {
      target: 'es2015',
    },
  },
  server: {
    cors: true,
  },
})

import { defineConfig } from 'tsdown'

// Each entry is a self-contained IIFE that references wp.* globals at runtime.
// No @wordpress/* packages are bundled — they are declared as ambient globals
// in src/js/globals.d.ts and loaded by WordPress core as script dependencies.

const shared = {
  outDir: 'assets/js',
  format: 'iife' as const,
  platform: 'browser' as const,
  dts: false,
  sourcemap: false,
  treeshake: true,
} as const

export default defineConfig([
  { ...shared, entry: { sidebar: 'src/js/sidebar.ts' } },
  { ...shared, entry: { 'quick-edit': 'src/js/quick-edit.ts' } },
  { ...shared, entry: { 'classic-editor': 'src/js/classic-editor.ts' } },
])

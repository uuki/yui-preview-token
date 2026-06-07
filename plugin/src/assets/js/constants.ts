/**
 * Plugin-wide TypeScript constants.
 *
 * Values tightly coupled to a single module (e.g. component style objects,
 * REST namespace used only by PHP) remain in their respective files.
 */

// ── Token expiry presets ──────────────────────────────────────────────────────

export const PRESET_SECONDS = {
  '1h':  3_600,
  '24h': 86_400,
  '30d': 30 * 86_400,
} as const satisfies Record<string, number>

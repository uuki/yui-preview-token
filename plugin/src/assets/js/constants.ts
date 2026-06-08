/**
 * Plugin-wide TypeScript constants.
 *
 * Values tightly coupled to a single module (e.g. component style objects,
 * REST namespace used only by PHP) remain in their respective files.
 *
 * Element IDs and data attributes marked "sync: Constants.php" must be kept
 * in sync with the corresponding PHP constant in src/WordPress/Constants.php.
 */

// ── Token expiry presets ──────────────────────────────────────────────────────

export const PRESET_SECONDS = {
  '1h':  3_600,
  '24h': 86_400,
  '30d': 30 * 86_400,
} as const satisfies Record<string, number>

// ── Element IDs ─── sync: Constants::ELEMENT_* ───────────────────────────────

export const ELEMENT_CLASSIC_ROOT     = 'pvt-classic-meta-box-root'
export const ELEMENT_ORIGINS_LIST     = 'pvt-origins-list'
export const ELEMENT_ADD_ORIGIN       = 'pvt-add-origin'
export const ELEMENT_WILDCARD_WARNING = 'pvt-wildcard-warning'

// ── CSS classes ───────────────────────────────────────────────────────────────

export const CLASS_QUICK_EDIT_ROOT = 'pvt-quick-edit-root'
export const CLASS_ORIGIN_ROW      = 'pvt-origin-row'
export const CLASS_REMOVE_ORIGIN   = 'pvt-remove-origin'

// ── data attributes ─── sync: Constants::ATTR_* ───────────────────────────────

export const ATTR_PANEL  = 'data-pvt-panel'
export const ATTR_ACTION = 'data-pvt-action'

// ── Gutenberg plugin ID ───────────────────────────────────────────────────────

export const PLUGIN_ID_SIDEBAR = 'pvt-preview'

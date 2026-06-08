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

export const ELEMENT_CLASSIC_ROOT     = 'yuipt-classic-meta-box-root'
export const ELEMENT_ORIGINS_LIST     = 'yuipt-origins-list'
export const ELEMENT_ADD_ORIGIN       = 'yuipt-add-origin'
export const ELEMENT_WILDCARD_WARNING = 'yuipt-wildcard-warning'

// ── CSS classes ───────────────────────────────────────────────────────────────

export const CLASS_QUICK_EDIT_ROOT = 'yuipt-quick-edit-root'
export const CLASS_ORIGIN_ROW      = 'yuipt-origin-row'
export const CLASS_REMOVE_ORIGIN   = 'yuipt-remove-origin'

// ── data attributes ─── sync: Constants::ATTR_* ───────────────────────────────

export const ATTR_PANEL  = 'data-yuipt-panel'
export const ATTR_ACTION = 'data-yuipt-action'

// ── Gutenberg plugin ID ───────────────────────────────────────────────────────

export const PLUGIN_ID_SIDEBAR = 'yuipt-preview'

// ── Log prefix ────────────────────────────────────────────────────────────────

export const LOG_PREFIX = '[YUIPT]'

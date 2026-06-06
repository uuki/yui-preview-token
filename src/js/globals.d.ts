/**
 * Ambient declarations for WordPress globals.
 *
 * These are NOT imported at runtime. WordPress core loads the wp.* packages as
 * script dependencies before our IIFE runs. Types are pulled from devDependencies
 * solely for TypeScript's benefit.
 */

import type * as WPElement    from '@wordpress/element'
import type * as WPComponents from '@wordpress/components'
import type * as WPPlugins    from '@wordpress/plugins'
import type * as WPData       from '@wordpress/data'
import type * as WPEditor     from '@wordpress/editor'

declare global {
  const wp: {
    element:    typeof WPElement
    components: typeof WPComponents
    plugins:    typeof WPPlugins
    data:       typeof WPData
    /** wp.editor is the canonical home of PluginDocumentSettingPanel in WP 6.6+. */
    editor:     typeof WPEditor
    /** wp.editPost is kept for older WP versions. */
    editPost:   typeof WPEditor
  }

  interface WptI18n {
    // Preset labels
    preset1h:       string
    preset24h:      string
    preset30d:      string
    presetCustom:   string
    presetNoExpiry: string
    // Panel UI
    loading:           string
    expiry:            string
    update:            string
    cancel:            string
    openPreview:       string
    copyPreviewUrl:    string
    changeExpiry:      string
    deleteToken:       string
    deleteConfirm:     string
    yes:               string
    generateToken:     string
    regenerateToken:   string
    // Status / error
    tokenExpired:   string   // 'Token expired: %s'
    expiresRelative: string  // 'Expires: %s (%s remaining)'
    lessThan1min:   string
    errorOccurred:  string
  }

  interface WptPreviewData {
    tokenBase:     string
    nonce:         string
    allowNoExpiry: boolean
    i18n:          WptI18n
  }

  /** Injected by wp_localize_script before each entry script runs. */
  const wptPreviewData: WptPreviewData | undefined

  interface WptSettingsData {
    /** name attribute for origin inputs, e.g. "wpt_allowed_origins[]" */
    field:        string
    /** aria-label for the × remove button */
    removeLabel:  string
    /** "Security Warning" title */
    warningTitle: string
    /** Full wildcard (*) warning message */
    warningText:  string
  }

  /** Injected on the plugin settings page only. */
  const wptSettingsData: WptSettingsData
}

export {}

/**
 * Gutenberg sidebar entry.
 * Registers a PluginDocumentSettingPanel with WptTokenPanel.
 * WordPress deps: wp-element, wp-components, wp-plugins, wp-data, wp-editor, wp-edit-post
 */

import { WptTokenPanel } from './token-panel'

if (typeof wptPreviewData === 'undefined') {
  // wptPreviewData is injected by wp_localize_script; if missing, skip silently
  // (avoids breaking Gutenberg if the plugin's enqueue hook didn't fire)
  console.warn('[WPT] wptPreviewData is not defined')
}

const { createElement: el }      = wp.element
const { Button, SelectControl }  = wp.components
const { registerPlugin }         = wp.plugins
const { useSelect }              = wp.data

const PluginDocumentSettingPanel =
  wp.editor?.PluginDocumentSettingPanel ??
  wp.editPost?.PluginDocumentSettingPanel

if (!PluginDocumentSettingPanel) {
  // Silently skip on pages where Gutenberg is not loaded
} else {
  registerPlugin('wpt-preview', {
    render() {
      const postId = useSelect(
        (select: ReturnType<typeof wp.data.select>) =>
          (select('core/editor') as { getCurrentPostId: () => number | null }).getCurrentPostId(),
        [],
      )

      if (!postId) return null

      return el(
        PluginDocumentSettingPanel,
        { name: 'wpt-preview', title: 'External Preview', icon: 'site', initialOpen: true },
        el(WptTokenPanel, { postId, Btn: Button, SelectInput: SelectControl }),
      )
    },
  })
}

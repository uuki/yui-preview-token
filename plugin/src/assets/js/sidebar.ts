/**
 * Gutenberg sidebar entry.
 * Registers a PluginDocumentSettingPanel with PvtTokenPanel.
 * WordPress deps: wp-element, wp-components, wp-plugins, wp-data, wp-editor, wp-edit-post
 */

import { PvtTokenPanel } from './token-panel'

if (typeof pvtPreviewData === 'undefined') {
  // pvtPreviewData is injected by wp_localize_script; if missing, skip silently
  // (avoids breaking Gutenberg if the plugin's enqueue hook didn't fire)
  console.warn('[PVT] pvtPreviewData is not defined')
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
  registerPlugin('pvt-preview', {
    render() {
      const postId = useSelect(
        (select: ReturnType<typeof wp.data.select>) =>
          (select('core/editor') as { getCurrentPostId: () => number | null }).getCurrentPostId(),
        [],
      )

      if (!postId) return null

      // Save unsaved Gutenberg changes before opening the external preview,
      // so the token endpoint returns the latest edited content.
      const onBeforeOpenPreview = async (): Promise<void> => {
        const editor   = wp.data.select('core/editor') as { isEditedPostDirty: () => boolean }
        const dispatch = wp.data.dispatch('core/editor') as { savePost: () => Promise<void> }
        if (editor.isEditedPostDirty()) await dispatch.savePost()
      }

      return el(
        PluginDocumentSettingPanel,
        { name: 'pvt-preview', title: 'External Preview', icon: 'site', initialOpen: true },
        el(PvtTokenPanel, { postId, Btn: Button, SelectInput: SelectControl, onBeforeOpenPreview }),
      )
    },
  })
}

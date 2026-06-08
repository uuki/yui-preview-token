/**
 * Gutenberg sidebar entry.
 * Registers a PluginDocumentSettingPanel with YuiptTokenPanel.
 * WordPress deps: wp-element, wp-components, wp-plugins, wp-data, wp-editor, wp-edit-post
 */

import { YuiptTokenPanel } from './token-panel'
import { PLUGIN_ID_SIDEBAR, LOG_PREFIX } from './constants'
import type { BtnComponent } from './types'

if (typeof yuiptPreviewData === 'undefined') {
  // yuiptPreviewData is injected by wp_localize_script; if missing, skip silently
  // (avoids breaking Gutenberg if the plugin's enqueue hook didn't fire)
  console.warn(`${LOG_PREFIX} yuiptPreviewData is not defined`)
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
  registerPlugin(PLUGIN_ID_SIDEBAR, {
    render() {
      const postId = (useSelect as Function)(
        (select: (store: string) => Record<string, Function>) =>
          (select('core/editor') as { getCurrentPostId: () => number | null }).getCurrentPostId(),
        [],
      ) as number | null

      if (!postId) return null

      // Save unsaved Gutenberg changes before opening the external preview,
      // so the token endpoint returns the latest edited content.
      const onBeforeOpenPreview = async (): Promise<void> => {
        const editor   = (wp.data.select as Function)('core/editor') as { isEditedPostDirty: () => boolean }
        const dispatch = wp.data.dispatch('core/editor') as unknown as { savePost: () => Promise<void> }
        if (editor.isEditedPostDirty()) await dispatch.savePost()
      }

      const panel = el(YuiptTokenPanel, { postId, Btn: Button as unknown as BtnComponent, SelectInput: SelectControl, onBeforeOpenPreview })
      // PluginDocumentSettingPanel props (name, initialOpen) extend beyond @wordpress/editor types
      return (el as Function)(
        PluginDocumentSettingPanel,
        { name: PLUGIN_ID_SIDEBAR, title: 'External Preview', icon: 'site', initialOpen: true },
        panel,
      ) as ReturnType<typeof el>
    },
  })
}

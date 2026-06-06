/**
 * Classic Editor meta box entry.
 * Mounts WptTokenPanel into the container injected by Settings::render_classic_meta_box().
 * WordPress deps: wp-element
 */

import { WptTokenPanel } from './token-panel'
import { NativeBtn, NativeSelect } from './native-components'

if (typeof wptPreviewData === 'undefined') {
  throw new Error('[WPT] wptPreviewData is not defined')
}

const { createElement: el } = wp.element

interface WptContainer extends HTMLElement {
  _wptRoot?: { render: (node: unknown) => void; unmount: () => void }
}

const initClassicMetaBox = (): void => {
  const root = document.getElementById('wpt-classic-meta-box-root') as WptContainer | null
  if (!root) return

  const postId = parseInt(root.dataset['postId'] ?? '0', 10)
  if (!postId) return

  const panel = el(WptTokenPanel, { postId, Btn: NativeBtn, SelectInput: NativeSelect })

  if (wp.element.createRoot) {
    if (!root._wptRoot) root._wptRoot = wp.element.createRoot(root)
    root._wptRoot.render(panel)
  } else {
    // @ts-expect-error — legacy React 17 render API
    wp.element.render(panel, root)
  }
}

if (document.readyState !== 'loading') {
  initClassicMetaBox()
} else {
  document.addEventListener('DOMContentLoaded', initClassicMetaBox)
}

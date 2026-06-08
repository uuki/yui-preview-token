/**
 * Classic Editor meta box entry.
 * Mounts PvtTokenPanel into the container injected by Settings::render_classic_meta_box().
 * WordPress deps: wp-element
 */

import { PvtTokenPanel } from './token-panel'
import { NativeBtn, NativeSelect } from './native-components'
import { ELEMENT_CLASSIC_ROOT } from './constants'

if (typeof pvtPreviewData === 'undefined') {
  throw new Error('[PVT] pvtPreviewData is not defined')
}

const { createElement: el } = wp.element

interface PvtContainer extends HTMLElement {
  _pvtRoot?: { render: (node: unknown) => void; unmount: () => void }
}

const initClassicMetaBox = (): void => {
  const root = document.getElementById(ELEMENT_CLASSIC_ROOT) as PvtContainer | null
  if (!root) return

  const postId = parseInt(root.dataset['postId'] ?? '0', 10)
  if (!postId) return

  const panel = el(PvtTokenPanel, { postId, Btn: NativeBtn, SelectInput: NativeSelect })

  if (wp.element.createRoot) {
    if (!root._pvtRoot) root._pvtRoot = wp.element.createRoot(root)
    root._pvtRoot.render(panel)
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

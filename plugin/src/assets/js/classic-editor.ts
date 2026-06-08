/**
 * Classic Editor meta box entry.
 * Mounts YuiptTokenPanel into the container injected by Settings::render_classic_meta_box().
 * WordPress deps: wp-element
 */

import { YuiptTokenPanel } from './token-panel'
import { NativeBtn, NativeSelect } from './native-components'
import { ELEMENT_CLASSIC_ROOT, LOG_PREFIX } from './constants'

if (typeof yuiptPreviewData === 'undefined') {
  throw new Error(`${LOG_PREFIX} yuiptPreviewData is not defined`)
}

const { createElement: el } = wp.element

interface YuiptContainer extends HTMLElement {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  _yuiptRoot?: { render: (children: any) => void; unmount: () => void }
}

const initClassicMetaBox = (): void => {
  const root = document.getElementById(ELEMENT_CLASSIC_ROOT) as YuiptContainer | null
  if (!root) return

  const postId = parseInt(root.dataset['postId'] ?? '0', 10)
  if (!postId) return

  const panel = el(YuiptTokenPanel, { postId, Btn: NativeBtn, SelectInput: NativeSelect })

  if (wp.element.createRoot) {
    if (!root._yuiptRoot) root._yuiptRoot = wp.element.createRoot(root)
    root._yuiptRoot!.render(panel)
  } else {
    wp.element.render(panel, root)
  }
}

if (document.readyState !== 'loading') {
  initClassicMetaBox()
} else {
  document.addEventListener('DOMContentLoaded', initClassicMetaBox)
}

/**
 * Quick Edit entry.
 * Mounts YuiptTokenPanel inside the #edit-{postId} rows via MutationObserver.
 * WordPress deps: wp-element, inline-edit-post
 */

import { YuiptTokenPanel } from './token-panel'
import { NativeBtn, NativeSelect } from './native-components'
import { CLASS_QUICK_EDIT_ROOT, LOG_PREFIX } from './constants'

if (typeof yuiptPreviewData === 'undefined') {
  throw new Error(`${LOG_PREFIX} yuiptPreviewData is not defined`)
}

const { createElement: el } = wp.element

// ── renderToContainer ─────────────────────────────────────────────────────────

interface YuiptContainer extends HTMLElement {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  _yuiptRoot?: { render: (children: any) => void; unmount: () => void }
}

const renderToContainer = (container: YuiptContainer, postId: number): void => {
  const panel = el(YuiptTokenPanel, { postId, Btn: NativeBtn, SelectInput: NativeSelect })
  if (wp.element.createRoot) {
    if (!container._yuiptRoot) container._yuiptRoot = wp.element.createRoot(container)
    container._yuiptRoot!.render(panel)
  } else {
    wp.element.render(panel, container)
  }
}

// ── Mount / unmount ───────────────────────────────────────────────────────────

const getQuickEditCol = (row: HTMLElement): HTMLElement | null =>
  row.querySelector('.inline-edit-col-left .inline-edit-col') ??
  row.querySelector('.inline-edit-col')

const mountPanel = (row: HTMLElement, postId: number): void => {
  const col = getQuickEditCol(row)
  if (!col || !postId) return

  let container = col.querySelector<YuiptContainer>(`.${CLASS_QUICK_EDIT_ROOT}`)
  if (!container) {
    container = document.createElement('div') as YuiptContainer
    container.className = CLASS_QUICK_EDIT_ROOT
    container.style.cssText = 'border-top:1px solid #ddd;margin-top:8px;padding-top:8px'
    col.appendChild(container)
  }
  renderToContainer(container, postId)
}

const unmountRow = (row: HTMLElement): void => {
  const container = row.querySelector<YuiptContainer>(`.${CLASS_QUICK_EDIT_ROOT}`)
  if (!container) return
  container._yuiptRoot?.unmount()
  container._yuiptRoot = undefined
  container.remove()
}

// ── MutationObserver ──────────────────────────────────────────────────────────
// WordPress 6.x creates a new <tr id="edit-{postId}"> for each Quick Edit open
// instead of toggling #inline-edit. Observe #the-list for childList changes.

const observeQuickEdit = (): void => {
  const list = document.getElementById('the-list')
  if (!list) return

  new MutationObserver(mutations => {
    for (const mutation of mutations) {
      for (const node of Array.from(mutation.addedNodes)) {
        if (!(node instanceof HTMLElement)) continue
        const match = /^edit-(\d+)$/.exec(node.id ?? '')
        if (match) mountPanel(node, parseInt(match[1]!, 10))
      }
      for (const node of Array.from(mutation.removedNodes)) {
        if (!(node instanceof HTMLElement)) continue
        if (/^edit-\d+$/.test(node.id ?? '')) unmountRow(node)
      }
    }
  }).observe(list, { childList: true })
}

if (document.readyState !== 'loading') {
  observeQuickEdit()
} else {
  document.addEventListener('DOMContentLoaded', observeQuickEdit)
}

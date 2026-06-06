/**
 * Quick Edit entry.
 * Mounts PvtTokenPanel inside the #edit-{postId} rows via MutationObserver.
 * WordPress deps: wp-element, inline-edit-post
 */

import { PvtTokenPanel } from './token-panel'
import { NativeBtn, NativeSelect } from './native-components'

if (typeof pvtPreviewData === 'undefined') {
  throw new Error('[PVT] pvtPreviewData is not defined')
}

const { createElement: el } = wp.element

// ── renderToContainer ─────────────────────────────────────────────────────────

interface PvtContainer extends HTMLElement {
  _pvtRoot?: { render: (node: unknown) => void; unmount: () => void }
}

const renderToContainer = (container: PvtContainer, postId: number): void => {
  const panel = el(PvtTokenPanel, { postId, Btn: NativeBtn, SelectInput: NativeSelect })
  if (wp.element.createRoot) {
    if (!container._pvtRoot) container._pvtRoot = wp.element.createRoot(container)
    container._pvtRoot.render(panel)
  } else {
    // @ts-expect-error — legacy React 17 render API
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

  let container = col.querySelector<PvtContainer>('.pvt-quick-edit-root')
  if (!container) {
    container = document.createElement('div') as PvtContainer
    container.className = 'pvt-quick-edit-root'
    container.style.cssText = 'border-top:1px solid #ddd;margin-top:8px;padding-top:8px'
    col.appendChild(container)
  }
  renderToContainer(container, postId)
}

const unmountRow = (row: HTMLElement): void => {
  const container = row.querySelector<PvtContainer>('.pvt-quick-edit-root')
  if (!container) return
  container._pvtRoot?.unmount()
  container._pvtRoot = undefined
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
      for (const node of mutation.addedNodes) {
        if (!(node instanceof HTMLElement)) continue
        const match = /^edit-(\d+)$/.exec(node.id ?? '')
        if (match) mountPanel(node, parseInt(match[1]!, 10))
      }
      for (const node of mutation.removedNodes) {
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

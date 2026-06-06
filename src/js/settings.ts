/**
 * Settings page entry — CORS origin list management + wildcard security warning.
 * WordPress deps: none (plain DOM, no wp.* globals needed)
 * Data injected via wp_localize_script as window.wptSettingsData.
 */

const { field, removeLabel, warningTitle, warningText } = wptSettingsData

const list   = document.getElementById('wpt-origins-list') as HTMLElement | null
const addBtn = document.getElementById('wpt-add-origin')   as HTMLElement | null

if (!list || !addBtn) {
  // Guard: script may be enqueued on a page where the markup isn't present.
} else {
  // ── Row factory ────────────────────────────────────────────────────────────

  const makeRow = (value: string): HTMLDivElement => {
    const row   = document.createElement('div')
    row.className = 'wpt-origin-row'
    row.style.cssText = 'display:flex;gap:6px;align-items:center'

    const input = document.createElement('input')
    input.type        = 'text'
    input.name        = field
    input.value       = value
    input.className   = 'regular-text code'
    input.placeholder = 'https://example.com  or  https://*.example.com'
    input.style.cssText = 'flex:1;font-family:monospace'
    input.addEventListener('input', updateWildcardWarning)

    const btn = document.createElement('button')
    btn.type      = 'button'
    btn.className = 'button wpt-remove-origin'
    btn.setAttribute('aria-label', removeLabel)
    btn.innerHTML = '&#x2715;'
    btn.addEventListener('click', () => removeRow(row))

    row.appendChild(input)
    row.appendChild(btn)
    return row
  }

  // ── Remove row ─────────────────────────────────────────────────────────────

  const removeRow = (row: HTMLDivElement): void => {
    const rows = list.querySelectorAll('.wpt-origin-row')
    if (rows.length <= 1) {
      // Keep one empty row instead of collapsing the list entirely
      const inp = row.querySelector<HTMLInputElement>('input')
      if (inp) inp.value = ''
      updateWildcardWarning()
      return
    }
    list.removeChild(row)
    updateWildcardWarning()
  }

  // ── Wildcard warning ───────────────────────────────────────────────────────

  const WARNING_ID = 'wpt-wildcard-warning'

  const updateWildcardWarning = (): void => {
    const hasBareWildcard = Array.from(
      list.querySelectorAll<HTMLInputElement>('input')
    ).some(inp => inp.value.trim() === '*')

    const existing = document.getElementById(WARNING_ID)

    if (hasBareWildcard && !existing) {
      const div = document.createElement('div')
      div.id        = WARNING_ID
      div.className = 'notice notice-warning inline'
      div.style.cssText = 'margin-top:6px;padding:8px 12px'
      div.innerHTML = `<strong>${warningTitle}:</strong> ${warningText}`
      list.parentNode?.insertBefore(div, list.nextSibling)
    } else if (!hasBareWildcard && existing) {
      existing.remove()
    }
  }

  // ── Wire up existing rows ──────────────────────────────────────────────────

  list.querySelectorAll<HTMLButtonElement>('.wpt-remove-origin').forEach(btn => {
    btn.addEventListener('click', () => {
      const row = btn.closest<HTMLDivElement>('.wpt-origin-row')
      if (row) removeRow(row)
    })
  })

  list.querySelectorAll<HTMLInputElement>('input').forEach(inp => {
    inp.addEventListener('input', updateWildcardWarning)
  })

  // ── Add origin button ──────────────────────────────────────────────────────

  addBtn.addEventListener('click', () => {
    const row = makeRow('')
    list.appendChild(row)
    row.querySelector<HTMLInputElement>('input')?.focus()
  })
}

import type { BtnComponent, SelectComponent, SelectOption, TokenData } from './types'
import {
  apiFetch,
  computeExpiresAt,
  defaultCustomIso,
  fmt,
  formatExpiry,
  getPresetOptions,
} from './utils'

const { createElement: el, useState, useEffect } = wp.element

// ── Style constants ───────────────────────────────────────────────────────────

const S_ERROR:   React.CSSProperties = { margin: '4px 0 0', fontSize: '12px', color: '#cc1818' }
const S_META:    React.CSSProperties = { margin: '0 0 8px', fontSize: '12px', color: '#757575' }
const S_DIVIDER: React.CSSProperties = { color: '#ddd', margin: '0 4px' }

// ── i18n helper ───────────────────────────────────────────────────────────────

const t = (): WptI18n => wptPreviewData?.i18n ?? {
  preset1h: '1 hour', preset24h: '24 hours', preset30d: '30 days',
  presetCustom: 'Custom', presetNoExpiry: 'No expiry',
  loading: 'Loading…', expiry: 'Expiry',
  update: 'Update', cancel: 'Cancel',
  openPreview: 'Open external preview',
  copyPreviewUrl: 'Copy external preview URL',
  changeExpiry: 'Change expiry', deleteToken: 'Delete',
  deleteConfirm: 'Delete this token?', yes: 'Yes',
  generateToken: 'Generate token', regenerateToken: 'Regenerate token',
  tokenExpired: 'Token expired: %s', expiresRelative: 'Expires: %1$s (%2$s remaining)',
  lessThan1min: '< 1 min', errorOccurred: 'An error occurred',
}

// ── Sub-components ────────────────────────────────────────────────────────────

const textLink = (label: string, onClick: () => void, style?: React.CSSProperties) =>
  el('a', {
    href: '#',
    onClick: (e: MouseEvent) => { e.preventDefault(); onClick() },
    style: { fontSize: '12px', ...style },
  }, label)

// ── Main component ────────────────────────────────────────────────────────────

export interface WptTokenPanelProps {
  postId:      number | null
  Btn:         BtnComponent
  SelectInput: SelectComponent
}

export const WptTokenPanel = ({ postId, Btn, SelectInput }: WptTokenPanelProps) => {
  const allowNoExpiry = wptPreviewData?.allowNoExpiry ?? false
  const PRESET_OPTIONS: SelectOption[] = getPresetOptions(allowNoExpiry)

  const [token,     setToken]     = useState<TokenData | null>(null)
  const [loaded,    setLoaded]    = useState(false)
  const [preset,    setPreset]    = useState('1h')
  const [customIso, setCustomIso] = useState('')
  const [mode,      setMode]      = useState<'view' | 'editing' | 'confirm_delete'>('view')
  const [busy,      setBusy]      = useState(false)
  const [error,     setError]     = useState('')

  useEffect(() => {
    if (!postId) return
    setLoaded(false); setToken(null); setMode('view')

    fetch(`${wptPreviewData?.tokenBase ?? ''}?post_id=${postId}`, {
      headers: { 'X-WP-Nonce': wptPreviewData?.nonce ?? '' },
    })
      .then(r => r.ok ? r.json() as Promise<TokenData> : null)
      .then(d => { setToken(d); setLoaded(true) })
      .catch(() => setLoaded(true))
  }, [postId])

  if (!postId) return null

  const expiry    = token ? formatExpiry(token.expires_at) : null
  const isActive  = !!(token && expiry && !expiry.expired)
  const isExpired = !!(token && expiry && expiry.expired)

  const handlePresetChange = (v: string) => {
    if (v === 'custom') setCustomIso(defaultCustomIso())
    else setCustomIso('')
    setPreset(v)
  }

  const withBusy = async (fn: () => Promise<void>) => {
    setBusy(true); setError('')
    try { await fn() }
    catch (e) { setError((e as Error).message || t().errorOccurred) }
    finally { setBusy(false) }
  }

  const doGenerate     = () => withBusy(async () => {
    const d = await apiFetch<TokenData>('POST', { post_id: postId, expires_at: computeExpiresAt(preset, customIso) })
    setToken(d); setMode('view')
  })
  const doUpdateExpiry = () => withBusy(async () => {
    const d = await apiFetch<TokenData>('PATCH', { post_id: postId, expires_at: computeExpiresAt(preset, customIso) })
    setToken(d); setMode('view')
  })
  const doDelete = () => withBusy(async () => {
    await apiFetch('DELETE', null, { post_id: String(postId) })
    setToken(null); setMode('view')
  })
  const doCopy = () => {
    if (token?.preview_url) navigator.clipboard?.writeText(token.preview_url)
  }

  const expirySelector = () => el('div', null,
    el(SelectInput, {
      label: t().expiry,
      value: preset,
      options: PRESET_OPTIONS,
      onChange: handlePresetChange,
    }),
    preset === 'custom'
      ? el('input', {
          key: 'custom-dt',
          type: 'datetime-local',
          value: customIso,
          min: new Date().toISOString().slice(0, 16),
          onChange: (e: Event) => setCustomIso((e.target as HTMLInputElement).value),
          style: { width: '100%', marginTop: '6px', boxSizing: 'border-box' as const },
        })
      : null,
  )

  if (!loaded) return el('div', { 'data-wpt-panel': 'loading' }, el('p', { style: S_META }, t().loading))

  if (isActive && mode === 'editing') {
    return el('div', { 'data-wpt-panel': 'editing' },
      expirySelector(),
      error ? el('p', { style: S_ERROR }, error) : null,
      el('div', { style: { display: 'flex', gap: '8px', alignItems: 'center', marginTop: '8px' } },
        el(Btn, { variant: 'primary', onClick: doUpdateExpiry, isBusy: busy, isSmall: true }, t().update),
        textLink(t().cancel, () => { setMode('view'); setError('') }),
      ),
    )
  }

  if (isActive) {
    const expiresLabel = expiry?.rel
      ? fmt(t().expiresRelative, expiry.abs, expiry.rel)
      : expiry?.abs ?? ''

    return el('div', { 'data-wpt-panel': 'active' },
      el('p', { style: S_META }, expiresLabel),
      el('div', { style: { display: 'flex', gap: '4px', alignItems: 'center', marginBottom: '8px' } },
        el('span', { 'data-wpt-action': 'preview', style: { flex: '1' } },
          el(Btn, {
            variant: 'secondary',
            href: token?.preview_url,
            target: '_blank',
            style: { width: '100%', justifyContent: 'center' },
          }, t().openPreview),
        ),
        el(Btn, {
          variant: 'tertiary',
          isSmall: true,
          onClick: doCopy,
          title: t().copyPreviewUrl,
          style: { padding: '0 6px' },
        },
          el('span', {
            className: 'dashicons dashicons-admin-page',
            style: { fontSize: '18px', width: '18px', height: '18px', textDecoration: 'none' },
          }),
        ),
      ),
      mode === 'confirm_delete'
        ? el('span', { style: { fontSize: '12px' } },
            t().deleteConfirm + ' ',
            el(Btn, { variant: 'link', isDestructive: true, isSmall: true, onClick: doDelete, isBusy: busy }, t().yes),
            el('span', { style: S_DIVIDER }, '|'),
            textLink(t().cancel, () => setMode('view')),
          )
        : el('p', { style: { margin: 0 } },
            textLink(t().changeExpiry, () => setMode('editing')),
            el('span', { style: S_DIVIDER }, '·'),
            textLink(t().deleteToken, () => setMode('confirm_delete'), { color: '#cc1818' }),
          ),
      error ? el('p', { style: S_ERROR }, error) : null,
    )
  }

  // Treat expired tokens the same as no token — show the "Generate" view
  // without an expiry-error message. Conceptually, an expired token is gone.
  return el('div', { 'data-wpt-panel': 'empty' },
    expirySelector(),
    error ? el('p', { style: S_ERROR }, error) : null,
    el('span', { 'data-wpt-action': 'generate' },
      el(Btn, {
        variant: 'secondary',
        onClick: doGenerate,
        isBusy: busy,
        style: { width: '100%', justifyContent: 'center', marginTop: '8px' },
      }, t().generateToken),
    ),
  )
}

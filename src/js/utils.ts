import type { ExpiryInfo, SelectOption } from './types'
import { PRESET_SECONDS } from './constants'

export { PRESET_SECONDS }

/**
 * Minimal sprintf supporting both %s (sequential) and %1$s/%2$s (ordered)
 * placeholders — avoids the @wordpress/i18n sprintf dependency.
 */
export const fmt = (template: string, ...args: (string | number)[]): string => {
  // Handle ordered placeholders (%1$s, %2$s, …)
  let result = template.replace(/%(\d+)\$s/g, (_, i) => String(args[parseInt(i, 10) - 1] ?? ''))
  // Handle remaining sequential %s placeholders
  return args.reduce<string>((s, a) => s.replace('%s', String(a)), result)
}

const i18n = (): WptI18n | undefined => wptPreviewData?.i18n

export const getPresetOptions = (allowNoExpiry: boolean): SelectOption[] => {
  const t = i18n()
  return [
    { label: t?.preset1h       ?? '1 hour',    value: '1h'       },
    { label: t?.preset24h      ?? '24 hours',  value: '24h'      },
    { label: t?.preset30d      ?? '30 days',   value: '30d'      },
    { label: t?.presetCustom   ?? 'Custom',    value: 'custom'   },
    ...(allowNoExpiry ? [{ label: t?.presetNoExpiry ?? 'No expiry', value: 'noexpiry' }] : []),
  ]
}

// ── Pure functions ────────────────────────────────────────────────────────────

export const defaultCustomIso = (): string =>
  new Date(Date.now() + 86_400_000).toISOString().slice(0, 16)

export const computeExpiresAt = (preset: string, customIso: string): number => {
  const now = Math.floor(Date.now() / 1000)
  if (preset === 'noexpiry') return 0
  if (preset === 'custom')   return Math.floor(new Date(customIso).getTime() / 1000)
  return now + (PRESET_SECONDS[preset] ?? 3_600)
}

export const formatExpiry = (expiresAt: number): ExpiryInfo => {
  const now  = Math.floor(Date.now() / 1000)
  const diff = expiresAt - now
  const abs  = new Date(expiresAt * 1000).toLocaleString()
  const t    = i18n()

  if (diff > 90 * 365 * 86_400) {
    return { abs: t?.presetNoExpiry ?? 'No expiry', rel: '', expired: false }
  }
  if (diff <= 0)    return { abs, rel: '', expired: true }
  if (diff < 60)    return { abs, rel: t?.lessThan1min ?? '< 1 min', expired: false }
  if (diff < 3_600) return { abs, rel: `${Math.floor(diff / 60)}m`, expired: false }
  if (diff < 86_400) {
    const h = Math.floor(diff / 3_600)
    const m = Math.floor((diff % 3_600) / 60)
    return { abs, rel: `${h}h ${m}m`, expired: false }
  }
  return { abs, rel: `${Math.floor(diff / 86_400)}d`, expired: false }
}

// ── API fetch ─────────────────────────────────────────────────────────────────

type JsonBody = Record<string, unknown>

export const apiFetch = async <T = unknown>(
  method: string,
  body?: JsonBody | null,
  queryParams?: Record<string, string>,
): Promise<T | null> => {
  if (typeof wptPreviewData === 'undefined') {
    throw new Error('wptPreviewData is not defined')
  }

  const { tokenBase, nonce } = wptPreviewData
  const url = queryParams
    ? `${tokenBase}?${new URLSearchParams(queryParams).toString()}`
    : tokenBase

  const headers: Record<string, string> = { 'X-WP-Nonce': nonce }
  let bodyStr: string | undefined

  if (body != null) {
    headers['Content-Type'] = 'application/json'
    bodyStr = JSON.stringify(body)
  }

  const res = await fetch(url, { method, headers, body: bodyStr })

  if (res.status === 204) return null
  if (!res.ok) {
    const e = await res.json().catch(() => ({})) as { message?: string }
    throw new Error(e.message ?? `HTTP ${res.status}`)
  }
  return res.json() as Promise<T>
}

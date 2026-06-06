import type { ComponentType, CSSProperties, ReactNode } from '@wordpress/element'

export interface TokenData {
  preview_url: string
  expires_at:  number
  issued_at:   number
  issued_by:   number
  raw?:        string
}

export interface ExpiryInfo {
  abs:     string
  rel:     string
  expired: boolean
}

export type ExpiryPreset = '1h' | '24h' | '30d' | 'custom' | 'noexpiry'

export interface SelectOption {
  label: string
  value: string
}

// ── Injected component interfaces ────────────────────────────────────────────
// Minimal props required by PvtTokenPanel; both wp.components.Button and
// NativeBtn must satisfy BtnProps, and both SelectControl/NativeSelect must
// satisfy SelectInputProps.

export interface BtnProps {
  variant?:          'primary' | 'secondary' | 'tertiary' | 'link'
  href?:             string
  target?:           string
  onClick?:          () => void
  style?:            CSSProperties
  isBusy?:           boolean
  isSmall?:          boolean
  isDestructive?:    boolean
  title?:            string
  disabled?:         boolean
  children?:         ReactNode
  className?:        string
  rel?:              string
  type?:             'button' | 'submit'
  'aria-label'?:     string
  /** Stable selector for E2E tests — locale-independent. */
  'data-pvt-action'?: string
  [key: string]:     unknown
}

export interface SelectInputProps {
  label?:    string
  value:     string
  options:   SelectOption[]
  onChange:  (value: string) => void
}

export type BtnComponent       = ComponentType<BtnProps>
export type SelectComponent    = ComponentType<SelectInputProps>

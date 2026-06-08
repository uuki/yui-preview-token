/**
 * Native HTML wrappers that satisfy BtnComponent / SelectComponent.
 * Used in Quick Edit and Classic Editor where wp.components is not loaded.
 */

import type { BtnProps, SelectInputProps } from './types'

const { createElement: el } = wp.element

// ── NativeBtn ─────────────────────────────────────────────────────────────────

export const NativeBtn = ({
  variant,
  href,
  target,
  onClick,
  style,
  isBusy,
  isSmall,
  isDestructive,
  title,
  children,
  disabled,
  // Forward remaining props (e.g. ATTR_ACTION) to the DOM element
  ...rest
}: BtnProps) => {
  const cls: string[] = []

  if      (variant === 'primary')                          cls.push('button', 'button-primary')
  else if (variant === 'secondary')                        cls.push('button')
  else if (variant === 'link' && isDestructive)            cls.push('button-link', 'button-link-delete')
  else if (variant === 'link' || variant === 'tertiary')   cls.push('button-link')

  if (isSmall) cls.push('button-small')

  const mergedStyle = { opacity: isBusy ? '0.7' : '1', ...style }

  if (href) {
    return el('a', {
      ...rest,
      href,
      target: target ?? '_blank',
      rel: 'noopener noreferrer',
      className: cls.join(' '),
      style: mergedStyle,
    }, children)
  }

  return el('button', {
    ...rest,
    type: 'button' as const,
    onClick,
    className: cls.join(' '),
    style: mergedStyle,
    title,
    disabled: !!isBusy || !!disabled,
  }, children)
}

// ── NativeSelect ──────────────────────────────────────────────────────────────

export const NativeSelect = ({ label, value, options, onChange }: SelectInputProps) =>
  el('div', null,
    label
      ? el('label', {
          style: {
            display: 'block',
            fontWeight: '600',
            marginBottom: '4px',
            fontSize: '11px',
            textTransform: 'uppercase',
            color: '#646970',
          },
        }, label)
      : null,
    el('select', {
      value,
      onChange: (e: Event) => onChange((e.target as HTMLSelectElement).value),
      style: { width: '100%' },
    },
      options.map(opt => el('option', { key: opt.value, value: opt.value }, opt.label)),
    ),
  )

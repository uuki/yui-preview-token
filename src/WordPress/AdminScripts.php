<?php

declare(strict_types=1);

namespace WPT\WordPress;

class AdminScripts
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor']);
        add_action('admin_enqueue_scripts',       [$this, 'enqueue_admin']);
        add_action('add_meta_boxes',              [$this, 'register_classic_meta_box']);
    }

    // ── Block editor (Gutenberg) ─────────────────────────────────────────

    public function enqueue_block_editor(): void
    {
        if ($this->settings->get_frontend_url() === '') {
            return;
        }

        wp_localize_script('wp-edit-post', 'wptPreviewData', $this->preview_data());
        wp_add_inline_script(
            'wp-edit-post',
            $this->make_script($this->shared_js() . $this->sidebar_js()),
            'after'
        );
    }

    // ── Post list (Quick Edit) ───────────────────────────────────────────

    public function enqueue_admin(string $hook): void
    {
        if ($this->settings->get_frontend_url() === '') {
            return;
        }

        if ($hook === 'edit.php') {
            // Virtual script: no src, depends on wp-element + inline-edit-post.
            wp_register_script('wpt-quick-edit', false, ['wp-element', 'inline-edit-post'], null, true);
            wp_enqueue_script('wpt-quick-edit');
            wp_localize_script('wpt-quick-edit', 'wptPreviewData', $this->preview_data());
            wp_add_inline_script(
                'wpt-quick-edit',
                $this->make_script($this->shared_js() . $this->quick_edit_js()),
                'after'
            );
        } elseif (in_array($hook, ['post.php', 'post-new.php'], true)) {
            // Classic Editor meta box: only when the block editor is NOT active.
            $screen = get_current_screen();
            if (!$screen || $screen->is_block_editor()) {
                return; // Gutenberg pages are handled by enqueue_block_editor_assets.
            }

            wp_enqueue_script('wp-element');
            wp_localize_script('wp-element', 'wptPreviewData', $this->preview_data());
            wp_add_inline_script(
                'wp-element',
                $this->make_script($this->shared_js() . $this->classic_editor_js()),
                'after'
            );
        }
    }

    // ── Classic Editor meta box ──────────────────────────────────────────

    public function register_classic_meta_box(): void
    {
        if ($this->settings->get_frontend_url() === '') {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->is_block_editor()) {
            return; // PluginDocumentSettingPanel handles Gutenberg context.
        }

        add_meta_box(
            'wpt-preview',
            'External Preview',
            [$this, 'render_classic_meta_box'],
            null,   // all registered post types
            'side',
            'high'
        );
    }

    public function render_classic_meta_box(\WP_Post $post): void
    {
        printf(
            '<div id="wpt-classic-meta-box-root" data-post-id="%d"></div>',
            esc_attr($post->ID)
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function preview_data(): array
    {
        return [
            'tokenBase'     => rest_url('wp-preview-token/v1/token'),
            'nonce'         => wp_create_nonce('wp_rest'),
            'allowNoExpiry' => $this->settings->get_allow_no_expiry(),
        ];
    }

    private function make_script(string $body): string
    {
        return "(() => {\n'use strict';\n" . $body . "\n})();";
    }

    // ── Shared JS ────────────────────────────────────────────────────────
    // Shared between Gutenberg sidebar, Quick Edit, and Classic Editor.
    // Defines WptTokenPanel (with Btn/SelectInput DI), utilities,
    // and NativeBtn/NativeSelect + renderToContainer for non-Gutenberg contexts.

    private function shared_js(): string
    {
        return <<<'JS'
const { createElement: el, useState, useEffect } = wp.element;

if (typeof wptPreviewData === 'undefined') return;
const { tokenBase, nonce, allowNoExpiry } = wptPreviewData;

const PRESET_SECONDS = { '1h': 3600, '24h': 86400, '30d': 30 * 86400 };
const PRESET_OPTIONS = [
    { label: '1 hour',   value: '1h'  },
    { label: '24 hours', value: '24h' },
    { label: '30 days',  value: '30d' },
    { label: 'Custom',   value: 'custom' },
].concat(allowNoExpiry ? [{ label: 'No expiry', value: 'noexpiry' }] : []);

const defaultCustomIso = () => new Date(Date.now() + 86400000).toISOString().slice(0, 16);

const computeExpiresAt = (preset, customIso) => {
    const now = Math.floor(Date.now() / 1000);
    if (preset === 'noexpiry') return 0;
    if (preset === 'custom')   return Math.floor(new Date(customIso).getTime() / 1000);
    return now + (PRESET_SECONDS[preset] || 3600);
};

const formatExpiry = (expiresAt) => {
    const now  = Math.floor(Date.now() / 1000);
    const diff = expiresAt - now;
    const abs  = new Date(expiresAt * 1000).toLocaleString();
    if (diff > 90 * 365 * 86400) return { abs: 'No expiry', rel: '', expired: false };
    if (diff <= 0)    return { abs, rel: '', expired: true };
    if (diff < 60)    return { abs, rel: '< 1 min', expired: false };
    if (diff < 3600)  return { abs, rel: `${Math.floor(diff / 60)}m`, expired: false };
    if (diff < 86400) return { abs, rel: `${Math.floor(diff / 3600)}h ${Math.floor((diff % 3600) / 60)}m`, expired: false };
    return { abs, rel: `${Math.floor(diff / 86400)}d`, expired: false };
};

const apiFetch = async (method, body, queryParams) => {
    const url  = queryParams ? `${tokenBase}?${new URLSearchParams(queryParams)}` : tokenBase;
    const opts = { method, headers: { 'X-WP-Nonce': nonce } };
    if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    const res = await fetch(url, opts);
    if (res.status === 204) return null;
    if (!res.ok) { const e = await res.json().catch(() => ({})); throw new Error(e.message || `HTTP ${res.status}`); }
    return res.json();
};

const ERR_STYLE  = { margin: '4px 0 0', fontSize: '12px', color: '#cc1818' };
const META_STYLE = { margin: '0 0 8px', fontSize: '12px', color: '#757575' };

// WptTokenPanel – token management UI.
// Btn and SelectInput are injected to support different UI primitives:
//   Gutenberg sidebar → wp.components.Button / SelectControl
//   Quick Edit / Classic Editor → NativeBtn / NativeSelect (below)
function WptTokenPanel({ postId, Btn, SelectInput }) {
    const [token,     setToken]     = useState(null);
    const [loaded,    setLoaded]    = useState(false);
    const [preset,    setPreset]    = useState('1h');
    const [customIso, setCustomIso] = useState('');
    const [mode,      setMode]      = useState('view');
    const [busy,      setBusy]      = useState(false);
    const [error,     setError]     = useState('');

    useEffect(() => {
        if (!postId) return;
        setLoaded(false); setToken(null); setMode('view');
        fetch(`${tokenBase}?post_id=${postId}`, { headers: { 'X-WP-Nonce': nonce } })
            .then(r => r.ok ? r.json() : null)
            .then(d => { setToken(d); setLoaded(true); })
            .catch(() => setLoaded(true));
    }, [postId]);

    if (!postId) return null;

    const expiry    = token ? formatExpiry(token.expires_at) : null;
    const isActive  = !!(token && expiry && !expiry.expired);
    const isExpired = !!(token && expiry && expiry.expired);

    const handlePresetChange = (v) => {
        if (v === 'custom') setCustomIso(defaultCustomIso());
        else setCustomIso('');
        setPreset(v);
    };

    const withBusy = async (fn) => {
        setBusy(true); setError('');
        try { await fn(); } catch (e) { setError(e.message || 'An error occurred'); }
        finally { setBusy(false); }
    };

    const doGenerate     = () => withBusy(async () => { const d = await apiFetch('POST',  { post_id: postId, expires_at: computeExpiresAt(preset, customIso) }); setToken(d); setMode('view'); });
    const doUpdateExpiry = () => withBusy(async () => { const d = await apiFetch('PATCH', { post_id: postId, expires_at: computeExpiresAt(preset, customIso) }); setToken(d); setMode('view'); });
    const doDelete       = () => withBusy(async () => { await apiFetch('DELETE', null, { post_id: postId }); setToken(null); setMode('view'); });
    const doCopy         = () => token && token.preview_url && navigator.clipboard && navigator.clipboard.writeText(token.preview_url);

    const textLink = (label, onClick, style) =>
        el('a', { href: '#', onClick: e => { e.preventDefault(); onClick(); }, style: Object.assign({ fontSize: '12px' }, style || {}) }, label);

    const expirySelector = () => el('div', null,
        el(SelectInput, { label: 'Expiry', value: preset, options: PRESET_OPTIONS, onChange: handlePresetChange }),
        preset === 'custom'
            ? el('input', { key: 'custom-dt', type: 'datetime-local', value: customIso, min: new Date().toISOString().slice(0, 16), onChange: e => setCustomIso(e.target.value), style: { width: '100%', marginTop: '6px', boxSizing: 'border-box' } })
            : null
    );

    if (!loaded) return el('p', { style: META_STYLE }, 'Loading…');

    if (isActive && mode === 'editing') return el('div', null,
        expirySelector(),
        error ? el('p', { style: ERR_STYLE }, error) : null,
        el('div', { style: { display: 'flex', gap: '8px', alignItems: 'center', marginTop: '8px' } },
            el(Btn, { variant: 'primary', onClick: doUpdateExpiry, isBusy: busy, isSmall: true }, 'Update'),
            textLink('Cancel', () => { setMode('view'); setError(''); })
        )
    );

    if (isActive) return el('div', null,
        el('p', { style: META_STYLE }, expiry.rel ? `Expires: ${expiry.abs} (${expiry.rel} remaining)` : expiry.abs),
        el('div', { style: { display: 'flex', gap: '4px', alignItems: 'center', marginBottom: '8px' } },
            el(Btn, { variant: 'secondary', href: token.preview_url, target: '_blank', style: { flex: '1', justifyContent: 'center' } }, 'Open external preview'),
            el(Btn, { variant: 'tertiary', isSmall: true, onClick: doCopy, title: 'Copy external preview URL', style: { padding: '0 6px' } },
                el('span', { className: 'dashicons dashicons-admin-page', style: { fontSize: '18px', width: '18px', height: '18px', textDecoration: 'none' } })
            )
        ),
        mode === 'confirm_delete'
            ? el('span', { style: { fontSize: '12px' } }, 'Delete this token? ',
                el(Btn, { variant: 'link', isDestructive: true, isSmall: true, onClick: doDelete, isBusy: busy }, 'Yes'),
                el('span', { style: { color: '#ddd', margin: '0 4px' } }, '|'),
                textLink('Cancel', () => setMode('view'))
              )
            : el('p', { style: { margin: 0 } },
                textLink('Change expiry', () => setMode('editing')),
                el('span', { style: { color: '#ddd', margin: '0 4px' } }, '·'),
                textLink('Delete', () => setMode('confirm_delete'), { color: '#cc1818' })
              ),
        error ? el('p', { style: ERR_STYLE }, error) : null
    );

    return el('div', null,
        isExpired ? el('p', { style: Object.assign({}, ERR_STYLE, { marginBottom: '8px' }) }, `Token expired: ${expiry.abs}`) : null,
        expirySelector(),
        error ? el('p', { style: ERR_STYLE }, error) : null,
        el(Btn, { variant: 'secondary', onClick: doGenerate, isBusy: busy, style: { width: '100%', justifyContent: 'center', marginTop: '8px' } }, isExpired ? 'Regenerate token' : 'Generate token')
    );
}

// ── NativeBtn / NativeSelect / renderToContainer ─────────────────────────
// Used by Quick Edit and Classic Editor (no wp-components available there).

const NativeBtn = ({ variant, href, target, onClick, style, isBusy, isSmall, isDestructive, title, children }) => {
    const cls = [];
    if      (variant === 'primary')                     cls.push('button', 'button-primary');
    else if (variant === 'secondary')                   cls.push('button');
    else if (variant === 'link' && isDestructive)       cls.push('button-link', 'button-link-delete');
    else if (variant === 'link' || variant === 'tertiary') cls.push('button-link');
    if (isSmall) cls.push('button-small');
    const merged = Object.assign({ opacity: isBusy ? '0.7' : '1' }, style || {});
    if (href) return el('a', { href, target: target || '_blank', rel: 'noopener noreferrer', className: cls.join(' '), style: merged }, children);
    return el('button', { type: 'button', onClick, className: cls.join(' '), style: merged, title, disabled: !!isBusy }, children);
};

const NativeSelect = ({ label, value, options, onChange }) =>
    el('div', null,
        label ? el('label', { style: { display: 'block', fontWeight: '600', marginBottom: '4px', fontSize: '11px', textTransform: 'uppercase', color: '#646970' } }, label) : null,
        el('select', { value, onChange: e => onChange(e.target.value), style: { width: '100%' } },
            options.map(opt => el('option', { key: opt.value, value: opt.value }, opt.label))
        )
    );

const renderToContainer = (container, postId) => {
    const panel = el(WptTokenPanel, { postId, Btn: NativeBtn, SelectInput: NativeSelect });
    if (wp.element.createRoot) {
        if (!container._wptRoot) container._wptRoot = wp.element.createRoot(container);
        container._wptRoot.render(panel);
    } else {
        wp.element.render(panel, container);
    }
};
JS;
    }

    // ── Sidebar JS (Gutenberg only) ──────────────────────────────────────

    private function sidebar_js(): string
    {
        // PluginDocumentSettingPanel moved from wp.editPost to wp.editor in WP 6.6.
        return <<<'JS'
const { useSelect }      = wp.data;
const { registerPlugin } = wp.plugins;
const { Button, SelectControl } = wp.components;

const PluginDocumentSettingPanel =
    wp.editor?.PluginDocumentSettingPanel ??
    wp.editPost?.PluginDocumentSettingPanel;

if (!PluginDocumentSettingPanel) return;

registerPlugin('wpt-preview', {
    render() {
        const postId = useSelect(s => s('core/editor').getCurrentPostId());
        if (!postId) return null;
        return el(PluginDocumentSettingPanel,
            { name: 'wpt-preview', title: 'External Preview', icon: 'site', initialOpen: true },
            el(WptTokenPanel, { postId, Btn: Button, SelectInput: SelectControl })
        );
    },
});
JS;
    }

    // ── Quick Edit JS (post list only) ───────────────────────────────────

    private function quick_edit_js(): string
    {
        // NativeBtn, NativeSelect, renderToContainer are defined in shared_js().
        return <<<'JS'
// WordPress ≥ 6.x creates a new <tr id="edit-{postId}"> for each Quick Edit open
// instead of toggling #inline-edit. Observe #the-list for childList changes.

const getQuickEditCol = (row) =>
    row.querySelector('.inline-edit-col-left .inline-edit-col')
    || row.querySelector('.inline-edit-col');

const mountPanel = (row, postId) => {
    const col = getQuickEditCol(row);
    if (!col || !postId) return;

    let container = col.querySelector('.wpt-quick-edit-root');
    if (!container) {
        container = document.createElement('div');
        container.className = 'wpt-quick-edit-root';
        container.style.cssText = 'border-top:1px solid #ddd;margin-top:8px;padding-top:8px';
        col.appendChild(container);
    }
    renderToContainer(container, postId);
};

const unmountRow = (row) => {
    const container = row.querySelector('.wpt-quick-edit-root');
    if (!container) return;
    if (container._wptRoot) { container._wptRoot.unmount(); container._wptRoot = null; }
    container.remove();
};

const observeQuickEdit = () => {
    const list = document.getElementById('the-list');
    if (!list) return;

    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType !== 1) return;
                const match = (node.id || '').match(/^edit-(\d+)$/);
                if (match) mountPanel(node, parseInt(match[1], 10));
            });
            mutation.removedNodes.forEach((node) => {
                if (node.nodeType !== 1) return;
                if (/^edit-\d+$/.test(node.id || '')) unmountRow(node);
            });
        });
    }).observe(list, { childList: true });
};

if (document.readyState !== 'loading') {
    observeQuickEdit();
} else {
    document.addEventListener('DOMContentLoaded', observeQuickEdit);
}
JS;
    }

    // ── Classic Editor JS (post.php / post-new.php only) ─────────────────

    private function classic_editor_js(): string
    {
        // NativeBtn, NativeSelect, renderToContainer are defined in shared_js().
        return <<<'JS'
// Mount WptTokenPanel into the meta box container injected by render_classic_meta_box().
const initClassicMetaBox = () => {
    const root = document.getElementById('wpt-classic-meta-box-root');
    if (!root) return;
    const postId = parseInt(root.dataset.postId, 10) || 0;
    if (!postId) return;
    renderToContainer(root, postId);
};

if (document.readyState !== 'loading') {
    initClassicMetaBox();
} else {
    document.addEventListener('DOMContentLoaded', initClassicMetaBox);
}
JS;
    }
}

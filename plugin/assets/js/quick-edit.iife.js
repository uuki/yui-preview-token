(function() {
	//#region src/assets/js/constants.ts
	/**
	* Plugin-wide TypeScript constants.
	*
	* Values tightly coupled to a single module (e.g. component style objects,
	* REST namespace used only by PHP) remain in their respective files.
	*
	* Element IDs and data attributes marked "sync: Constants.php" must be kept
	* in sync with the corresponding PHP constant in src/WordPress/Constants.php.
	*/
	const PRESET_SECONDS = {
		"1h": 3600,
		"24h": 86400,
		"30d": 30 * 86400
	};
	const CLASS_QUICK_EDIT_ROOT = "pvt-quick-edit-root";
	const ATTR_PANEL = "data-pvt-panel";
	const ATTR_ACTION = "data-pvt-action";
	//#endregion
	//#region src/assets/js/utils.ts
	/**
	* Minimal sprintf supporting both %s (sequential) and %1$s/%2$s (ordered)
	* placeholders — avoids the @wordpress/i18n sprintf dependency.
	*/
	const fmt = (template, ...args) => {
		let result = template.replace(/%(\d+)\$s/g, (_, i) => String(args[parseInt(i, 10) - 1] ?? ""));
		return args.reduce((s, a) => s.replace("%s", String(a)), result);
	};
	const i18n = () => pvtPreviewData?.i18n;
	const getPresetOptions = (allowNoExpiry) => {
		const t = i18n();
		return [
			{
				label: t?.preset1h ?? "1 hour",
				value: "1h"
			},
			{
				label: t?.preset24h ?? "24 hours",
				value: "24h"
			},
			{
				label: t?.preset30d ?? "30 days",
				value: "30d"
			},
			{
				label: t?.presetCustom ?? "Custom",
				value: "custom"
			},
			...allowNoExpiry ? [{
				label: t?.presetNoExpiry ?? "No expiry",
				value: "noexpiry"
			}] : []
		];
	};
	/**
	* Formats a Date as "YYYY-MM-DDTHH:MM" in the browser's LOCAL timezone.
	* toISOString() returns UTC, which would be offset by the browser's UTC
	* offset when set as a datetime-local value — causing the wrong time to
	* appear in the picker for users outside UTC.
	*/
	const toLocalDatetimeString = (date) => {
		const pad = (n) => String(n).padStart(2, "0");
		return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
	};
	const defaultCustomIso = () => toLocalDatetimeString(new Date(Date.now() + 864e5));
	const computeExpiresAt = (preset, customIso) => {
		const now = Math.floor(Date.now() / 1e3);
		if (preset === "noexpiry") return 0;
		if (preset === "custom") return Math.floor(new Date(customIso).getTime() / 1e3);
		return now + (PRESET_SECONDS[preset] ?? 3600);
	};
	const formatExpiry = (expiresAt) => {
		const diff = expiresAt - Math.floor(Date.now() / 1e3);
		const abs = (/* @__PURE__ */ new Date(expiresAt * 1e3)).toLocaleString();
		const t = i18n();
		if (diff > 90 * 365 * 86400) return {
			abs: t?.presetNoExpiry ?? "No expiry",
			rel: "",
			expired: false
		};
		if (diff <= 0) return {
			abs,
			rel: "",
			expired: true
		};
		if (diff < 60) return {
			abs,
			rel: t?.lessThan1min ?? "< 1 min",
			expired: false
		};
		if (diff < 3600) return {
			abs,
			rel: `${Math.floor(diff / 60)}m`,
			expired: false
		};
		if (diff < 86400) return {
			abs,
			rel: `${Math.floor(diff / 3600)}h ${Math.floor(diff % 3600 / 60)}m`,
			expired: false
		};
		return {
			abs,
			rel: `${Math.floor(diff / 86400)}d`,
			expired: false
		};
	};
	const apiFetch = async (method, body, queryParams) => {
		if (typeof pvtPreviewData === "undefined") throw new Error("pvtPreviewData is not defined");
		const { tokenBase, nonce } = pvtPreviewData;
		const url = queryParams ? `${tokenBase}?${new URLSearchParams(queryParams).toString()}` : tokenBase;
		const headers = { "X-WP-Nonce": nonce };
		let bodyStr;
		if (body != null) {
			headers["Content-Type"] = "application/json";
			bodyStr = JSON.stringify(body);
		}
		const res = await fetch(url, {
			method,
			headers,
			body: bodyStr
		});
		if (res.status === 204) return null;
		if (!res.ok) {
			const e = await res.json().catch(() => ({}));
			throw new Error(e.message ?? `HTTP ${res.status}`);
		}
		return res.json();
	};
	//#endregion
	//#region src/assets/js/token-panel.ts
	const { createElement: el$2, useState, useEffect } = wp.element;
	const S_ERROR = {
		margin: "4px 0 0",
		fontSize: "12px",
		color: "#cc1818"
	};
	const S_META = {
		margin: "0 0 8px",
		fontSize: "12px",
		color: "#757575"
	};
	const S_DIVIDER = {
		color: "#ddd",
		margin: "0 4px"
	};
	const t = () => pvtPreviewData?.i18n ?? {
		preset1h: "1 hour",
		preset24h: "24 hours",
		preset30d: "30 days",
		presetCustom: "Custom",
		presetNoExpiry: "No expiry",
		loading: "Loading…",
		expiry: "Expiry",
		update: "Update",
		cancel: "Cancel",
		openPreview: "Open external preview",
		copyPreviewUrl: "Copy external preview URL",
		changeExpiry: "Change expiry",
		deleteToken: "Delete",
		deleteConfirm: "Delete this token?",
		yes: "Yes",
		generateToken: "Generate token",
		regenerateToken: "Regenerate token",
		tokenExpired: "Token expired: %s",
		expiresRelative: "Expires: %1$s (%2$s remaining)",
		lessThan1min: "< 1 min",
		errorOccurred: "An error occurred"
	};
	const textLink = (label, onClick, style) => el$2("a", {
		href: "#",
		onClick: (e) => {
			e.preventDefault();
			onClick();
		},
		style: {
			fontSize: "12px",
			...style
		}
	}, label);
	const PvtTokenPanel = ({ postId, Btn, SelectInput, onBeforeOpenPreview }) => {
		const PRESET_OPTIONS = getPresetOptions(pvtPreviewData?.allowNoExpiry ?? false);
		const [token, setToken] = useState(null);
		const [loaded, setLoaded] = useState(false);
		const [preset, setPreset] = useState("1h");
		const [customIso, setCustomIso] = useState("");
		const [mode, setMode] = useState("view");
		const [busy, setBusy] = useState(false);
		const [error, setError] = useState("");
		useEffect(() => {
			if (!postId) return;
			setLoaded(false);
			setToken(null);
			setMode("view");
			fetch(`${pvtPreviewData?.tokenBase ?? ""}?post_id=${postId}`, { headers: { "X-WP-Nonce": pvtPreviewData?.nonce ?? "" } }).then((r) => r.ok ? r.json() : null).then((d) => {
				setToken(d);
				setLoaded(true);
			}).catch(() => setLoaded(true));
		}, [postId]);
		if (!postId) return null;
		const expiry = token ? formatExpiry(token.expires_at) : null;
		const isActive = !!(token && expiry && !expiry.expired);
		token && expiry && expiry.expired;
		const handlePresetChange = (v) => {
			if (v === "custom") setCustomIso(defaultCustomIso());
			else setCustomIso("");
			setPreset(v);
		};
		const withBusy = async (fn) => {
			setBusy(true);
			setError("");
			try {
				await fn();
			} catch (e) {
				setError(e.message || t().errorOccurred);
			} finally {
				setBusy(false);
			}
		};
		const doGenerate = () => withBusy(async () => {
			setToken(await apiFetch("POST", {
				post_id: postId,
				expires_at: computeExpiresAt(preset, customIso)
			}));
			setMode("view");
		});
		const doUpdateExpiry = () => withBusy(async () => {
			setToken(await apiFetch("PATCH", {
				post_id: postId,
				expires_at: computeExpiresAt(preset, customIso)
			}));
			setMode("view");
		});
		const doDelete = () => withBusy(async () => {
			await apiFetch("DELETE", null, { post_id: String(postId) });
			setToken(null);
			setMode("view");
		});
		const doCopy = () => {
			if (token?.preview_url) navigator.clipboard?.writeText(token.preview_url);
		};
		const doOpenPreview = () => withBusy(async () => {
			if (onBeforeOpenPreview) await onBeforeOpenPreview();
			if (token?.preview_url) window.open(token.preview_url, "_blank");
		});
		const expirySelector = () => el$2("div", null, el$2(SelectInput, {
			label: t().expiry,
			value: preset,
			options: PRESET_OPTIONS,
			onChange: handlePresetChange
		}), preset === "custom" ? el$2("input", {
			key: "custom-dt",
			type: "datetime-local",
			value: customIso,
			min: toLocalDatetimeString(/* @__PURE__ */ new Date()),
			onChange: (e) => setCustomIso(e.target.value),
			style: {
				width: "100%",
				marginTop: "6px",
				boxSizing: "border-box"
			}
		}) : null);
		if (!loaded) return el$2("div", { [ATTR_PANEL]: "loading" }, el$2("p", { style: S_META }, t().loading));
		if (isActive && mode === "editing") return el$2("div", { [ATTR_PANEL]: "editing" }, expirySelector(), error ? el$2("p", { style: S_ERROR }, error) : null, el$2("div", { style: {
			display: "flex",
			gap: "8px",
			alignItems: "center",
			marginTop: "8px"
		} }, el$2(Btn, {
			variant: "primary",
			onClick: doUpdateExpiry,
			isBusy: busy,
			isSmall: true
		}, t().update), textLink(t().cancel, () => {
			setMode("view");
			setError("");
		})));
		if (isActive) {
			const expiresLabel = expiry?.rel ? fmt(t().expiresRelative, expiry.abs, expiry.rel) : expiry?.abs ?? "";
			return el$2("div", { [ATTR_PANEL]: "active" }, el$2("p", { style: S_META }, expiresLabel), el$2("div", { style: {
				display: "flex",
				gap: "4px",
				alignItems: "center",
				marginBottom: "8px"
			} }, el$2("span", {
				[ATTR_ACTION]: "preview",
				style: { flex: "1" }
			}, el$2(Btn, {
				variant: "secondary",
				onClick: doOpenPreview,
				isBusy: busy,
				style: {
					width: "100%",
					justifyContent: "center"
				}
			}, t().openPreview)), el$2(Btn, {
				variant: "tertiary",
				isSmall: true,
				onClick: doCopy,
				title: t().copyPreviewUrl,
				style: { padding: "0 6px" }
			}, el$2("span", {
				className: "dashicons dashicons-admin-page",
				style: {
					fontSize: "18px",
					width: "18px",
					height: "18px",
					textDecoration: "none"
				}
			}))), mode === "confirm_delete" ? el$2("span", { style: { fontSize: "12px" } }, t().deleteConfirm + " ", el$2(Btn, {
				variant: "link",
				isDestructive: true,
				isSmall: true,
				onClick: doDelete,
				isBusy: busy
			}, t().yes), el$2("span", { style: S_DIVIDER }, "|"), textLink(t().cancel, () => setMode("view"))) : el$2("p", { style: { margin: 0 } }, textLink(t().changeExpiry, () => setMode("editing")), el$2("span", { style: S_DIVIDER }, "·"), textLink(t().deleteToken, () => setMode("confirm_delete"), { color: "#cc1818" })), error ? el$2("p", { style: S_ERROR }, error) : null);
		}
		return el$2("div", { [ATTR_PANEL]: "empty" }, expirySelector(), error ? el$2("p", { style: S_ERROR }, error) : null, el$2("span", { [ATTR_ACTION]: "generate" }, el$2(Btn, {
			variant: "secondary",
			onClick: doGenerate,
			isBusy: busy,
			style: {
				width: "100%",
				justifyContent: "center",
				marginTop: "8px"
			}
		}, t().generateToken)));
	};
	//#endregion
	//#region src/assets/js/native-components.ts
	const { createElement: el$1 } = wp.element;
	const NativeBtn = ({ variant, href, target, onClick, style, isBusy, isSmall, isDestructive, title, children, disabled, ...rest }) => {
		const cls = [];
		if (variant === "primary") cls.push("button", "button-primary");
		else if (variant === "secondary") cls.push("button");
		else if (variant === "link" && isDestructive) cls.push("button-link", "button-link-delete");
		else if (variant === "link" || variant === "tertiary") cls.push("button-link");
		if (isSmall) cls.push("button-small");
		const mergedStyle = {
			opacity: isBusy ? "0.7" : "1",
			...style
		};
		if (href) return el$1("a", {
			...rest,
			href,
			target: target ?? "_blank",
			rel: "noopener noreferrer",
			className: cls.join(" "),
			style: mergedStyle
		}, children);
		return el$1("button", {
			...rest,
			type: "button",
			onClick,
			className: cls.join(" "),
			style: mergedStyle,
			title,
			disabled: !!isBusy || !!disabled
		}, children);
	};
	const NativeSelect = ({ label, value, options, onChange }) => el$1("div", null, label ? el$1("label", { style: {
		display: "block",
		fontWeight: "600",
		marginBottom: "4px",
		fontSize: "11px",
		textTransform: "uppercase",
		color: "#646970"
	} }, label) : null, el$1("select", {
		value,
		onChange: (e) => onChange(e.target.value),
		style: { width: "100%" }
	}, options.map((opt) => el$1("option", {
		key: opt.value,
		value: opt.value
	}, opt.label))));
	//#endregion
	//#region src/assets/js/quick-edit.ts
	/**
	* Quick Edit entry.
	* Mounts PvtTokenPanel inside the #edit-{postId} rows via MutationObserver.
	* WordPress deps: wp-element, inline-edit-post
	*/
	if (typeof pvtPreviewData === "undefined") throw new Error("[PVT] pvtPreviewData is not defined");
	const { createElement: el } = wp.element;
	const renderToContainer = (container, postId) => {
		const panel = el(PvtTokenPanel, {
			postId,
			Btn: NativeBtn,
			SelectInput: NativeSelect
		});
		if (wp.element.createRoot) {
			if (!container._pvtRoot) container._pvtRoot = wp.element.createRoot(container);
			container._pvtRoot.render(panel);
		} else wp.element.render(panel, container);
	};
	const getQuickEditCol = (row) => row.querySelector(".inline-edit-col-left .inline-edit-col") ?? row.querySelector(".inline-edit-col");
	const mountPanel = (row, postId) => {
		const col = getQuickEditCol(row);
		if (!col || !postId) return;
		let container = col.querySelector(`.${CLASS_QUICK_EDIT_ROOT}`);
		if (!container) {
			container = document.createElement("div");
			container.className = CLASS_QUICK_EDIT_ROOT;
			container.style.cssText = "border-top:1px solid #ddd;margin-top:8px;padding-top:8px";
			col.appendChild(container);
		}
		renderToContainer(container, postId);
	};
	const unmountRow = (row) => {
		const container = row.querySelector(`.${CLASS_QUICK_EDIT_ROOT}`);
		if (!container) return;
		container._pvtRoot?.unmount();
		container._pvtRoot = void 0;
		container.remove();
	};
	const observeQuickEdit = () => {
		const list = document.getElementById("the-list");
		if (!list) return;
		new MutationObserver((mutations) => {
			for (const mutation of mutations) {
				for (const node of mutation.addedNodes) {
					if (!(node instanceof HTMLElement)) continue;
					const match = /^edit-(\d+)$/.exec(node.id ?? "");
					if (match) mountPanel(node, parseInt(match[1], 10));
				}
				for (const node of mutation.removedNodes) {
					if (!(node instanceof HTMLElement)) continue;
					if (/^edit-\d+$/.test(node.id ?? "")) unmountRow(node);
				}
			}
		}).observe(list, { childList: true });
	};
	if (document.readyState !== "loading") observeQuickEdit();
	else document.addEventListener("DOMContentLoaded", observeQuickEdit);
	//#endregion
})();

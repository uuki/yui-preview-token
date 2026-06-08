(function() {
	//#region src/assets/js/constants.ts
	const ELEMENT_ORIGINS_LIST = "yuipt-origins-list";
	const ELEMENT_ADD_ORIGIN = "yuipt-add-origin";
	const ELEMENT_WILDCARD_WARNING = "yuipt-wildcard-warning";
	const CLASS_ORIGIN_ROW = "yuipt-origin-row";
	const CLASS_REMOVE_ORIGIN = "yuipt-remove-origin";
	//#endregion
	//#region src/assets/js/settings.ts
	/**
	* Settings page entry — CORS origin list management + wildcard security warning.
	* WordPress deps: none (plain DOM, no wp.* globals needed)
	* Data injected via wp_localize_script as window.yuiptSettingsData.
	*/
	const { field, removeLabel, warningTitle, warningText } = yuiptSettingsData;
	const list = document.getElementById(ELEMENT_ORIGINS_LIST);
	const addBtn = document.getElementById(ELEMENT_ADD_ORIGIN);
	if (!list || !addBtn) {} else {
		const makeRow = (value) => {
			const row = document.createElement("div");
			row.className = CLASS_ORIGIN_ROW;
			row.style.cssText = "display:flex;gap:6px;align-items:center";
			const input = document.createElement("input");
			input.type = "text";
			input.name = field;
			input.value = value;
			input.className = "regular-text";
			input.placeholder = "https://example.com  or  https://*.example.com";
			input.style.cssText = "flex:1;font-family:-apple-system,\"system-ui\",\"Segoe UI\",Roboto,Oxygen-Sans,Ubuntu,Cantarell,\"Helvetica Neue\",sans-serif";
			input.addEventListener("input", updateWildcardWarning);
			const btn = document.createElement("button");
			btn.type = "button";
			btn.className = `button ${CLASS_REMOVE_ORIGIN}`;
			btn.setAttribute("aria-label", removeLabel);
			btn.innerHTML = "&#x2715;";
			btn.addEventListener("click", () => removeRow(row));
			row.appendChild(input);
			row.appendChild(btn);
			return row;
		};
		const removeRow = (row) => {
			if (list.querySelectorAll(`.yuipt-origin-row`).length <= 1) {
				const inp = row.querySelector("input");
				if (inp) inp.value = "";
				updateWildcardWarning();
				return;
			}
			list.removeChild(row);
			updateWildcardWarning();
		};
		const WARNING_ID = ELEMENT_WILDCARD_WARNING;
		const updateWildcardWarning = () => {
			const hasBareWildcard = Array.from(list.querySelectorAll("input")).some((inp) => inp.value.trim() === "*");
			const existing = document.getElementById(WARNING_ID);
			if (hasBareWildcard && !existing) {
				const div = document.createElement("div");
				div.id = WARNING_ID;
				div.className = "notice notice-warning inline";
				div.style.cssText = "margin-top:6px;padding:8px 12px";
				div.innerHTML = `<strong>${warningTitle}:</strong> ${warningText}`;
				list.parentNode?.insertBefore(div, list.nextSibling);
			} else if (!hasBareWildcard && existing) existing.remove();
		};
		list.querySelectorAll(`.${CLASS_REMOVE_ORIGIN}`).forEach((btn) => {
			btn.addEventListener("click", () => {
				const row = btn.closest(`.${CLASS_ORIGIN_ROW}`);
				if (row) removeRow(row);
			});
		});
		list.querySelectorAll("input").forEach((inp) => {
			inp.addEventListener("input", updateWildcardWarning);
		});
		addBtn.addEventListener("click", () => {
			const row = makeRow("");
			list.appendChild(row);
			row.querySelector("input")?.focus();
		});
	}
	//#endregion
})();

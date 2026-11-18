(function () {
	'use strict';

	var STORAGE_KEY = 'cws_dark_mode';
	var STORAGE_TTL = 30 * 24 * 60 * 60 * 1000;
	var config = window.cwsDarkMode || {};
	var defaultMode = config.defaultMode === 'dark' || config.defaultMode === 'light' ? config.defaultMode : 'system';
	var root = document.documentElement;

	function storedMode() {
		try {
			var raw = window.localStorage.getItem(STORAGE_KEY);
			if (!raw) {
				return null;
			}
			var data = JSON.parse(raw);
			if (!data || (data.value !== 'dark' && data.value !== 'light')) {
				return null;
			}
			if (data.expires && Date.now() > data.expires) {
				window.localStorage.removeItem(STORAGE_KEY);
				return null;
			}
			return data.value;
		} catch (e) {
			return null;
		}
	}

	function saveMode(mode) {
		try {
			window.localStorage.setItem(STORAGE_KEY, JSON.stringify({
				value: mode,
				expires: Date.now() + STORAGE_TTL
			}));
		} catch (e) {
			// Storage unavailable (private mode) — keep the in-memory state.
		}
	}

	function systemPrefersDark() {
		return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
	}

	function initialMode() {
		var stored = storedMode();
		if (stored) {
			return stored;
		}
		if (defaultMode === 'dark') {
			return 'dark';
		}
		if (defaultMode === 'light') {
			return 'light';
		}
		return systemPrefersDark() ? 'dark' : 'light';
	}

	var mode = initialMode();

	function updateToggles() {
		var isDark = mode === 'dark';
		var toggles = document.querySelectorAll('.cws-dark-mode-toggle');

		Array.prototype.forEach.call(toggles, function (toggle) {
			toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
			toggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');

			var darkIcon = toggle.querySelector('.cws-mode-icon-dark');
			var lightIcon = toggle.querySelector('.cws-mode-icon-default');

			if (darkIcon) {
				darkIcon.classList.toggle('activated', isDark);
			}
			if (lightIcon) {
				lightIcon.classList.toggle('activated', !isDark);
			}
		});
	}

	function applyMode() {
		root.classList.toggle('cws-dark-mode', mode === 'dark');
		root.classList.toggle('cws-light-mode', mode !== 'dark');
		updateToggles();
	}

	function toggleMode() {
		mode = mode === 'dark' ? 'light' : 'dark';
		saveMode(mode);
		applyMode();
	}

	function bindToggles() {
		var toggles = document.querySelectorAll('.cws-dark-mode-toggle');

		Array.prototype.forEach.call(toggles, function (toggle) {
			if (toggle.dataset.cwsDarkBound) {
				return;
			}
			toggle.dataset.cwsDarkBound = '1';

			toggle.addEventListener('click', toggleMode);
			toggle.addEventListener('keydown', function (event) {
				if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
					event.preventDefault();
					toggleMode();
				}
			});
		});

		updateToggles();
	}

	// Apply before the body paints to avoid a flash of the wrong theme.
	applyMode();

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bindToggles);
	} else {
		bindToggles();
	}

	if (window.matchMedia) {
		var media = window.matchMedia('(prefers-color-scheme: dark)');
		var onSystemChange = function () {
			if (storedMode() || defaultMode !== 'system') {
				return;
			}
			mode = systemPrefersDark() ? 'dark' : 'light';
			applyMode();
		};
		if (media.addEventListener) {
			media.addEventListener('change', onSystemChange);
		} else if (media.addListener) {
			media.addListener(onSystemChange);
		}
	}
})();

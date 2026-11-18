(function () {
	'use strict';

	var STORAGE_KEY = 'cws_font_scale';
	var STEPS = [ 1, 1.1, 1.2, 1.3 ];
	var config = window.cwsFontResizer || {};
	var target = config.target || '.entry-content';

	function readScale() {
		var raw;
		try {
			raw = window.localStorage.getItem(STORAGE_KEY);
		} catch (e) {
			return 1;
		}
		var value = parseFloat(raw);
		return isNaN(value) || STEPS.indexOf(value) === -1 ? 1 : value;
	}

	function saveScale(scale) {
		try {
			window.localStorage.setItem(STORAGE_KEY, String(scale));
		} catch (e) {
			// Storage unavailable — the size still applies for this page view.
		}
	}

	function updateTriggers(scale) {
		var label = 'Font size ' + Math.round(scale * 100) + '%';
		Array.prototype.forEach.call(document.querySelectorAll('.font-resizer-trigger'), function (trigger) {
			trigger.setAttribute('aria-label', label);
			trigger.setAttribute('data-title', label);
		});
	}

	function applyScale(scale) {
		Array.prototype.forEach.call(document.querySelectorAll(target), function (el) {
			if (scale === 1) {
				el.style.removeProperty('font-size');
			} else {
				el.style.fontSize = Math.round(scale * 100) + '%';
			}
		});
		updateTriggers(scale);
	}

	var scale = readScale();

	function cycleScale(event) {
		if (event) {
			event.preventDefault();
		}
		var index = STEPS.indexOf(scale);
		scale = STEPS[ ( index + 1 ) % STEPS.length ];
		saveScale(scale);
		applyScale(scale);
	}

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('.font-resizer-trigger'), function (trigger) {
			if (trigger.dataset.cwsFontBound) {
				return;
			}
			trigger.dataset.cwsFontBound = '1';
			trigger.addEventListener('click', cycleScale);
		});
		applyScale(scale);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

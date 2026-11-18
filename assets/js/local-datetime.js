(function () {
	'use strict';

	var DATE_STYLES = [ 'full', 'long', 'medium', 'short' ];
	var REFRESH_MS = 15000;

	function buildFormatter(el) {
		var dateStyle = el.getAttribute('data-date-format') || 'long';
		var timeFormat = el.getAttribute('data-time-format') || '12';
		var display = el.getAttribute('data-display') || 'both';
		var options = {};

		if (DATE_STYLES.indexOf(dateStyle) === -1) {
			dateStyle = 'long';
		}

		if (display === 'date' || display === 'both') {
			options.dateStyle = dateStyle;
		}

		if (display === 'time' || display === 'both') {
			options.timeStyle = 'short';
			options.hour12 = timeFormat !== '24';
		}

		try {
			return new Intl.DateTimeFormat(undefined, options);
		} catch (e) {
			delete options.hour12;
			try {
				return new Intl.DateTimeFormat(undefined, options);
			} catch (e2) {
				return null;
			}
		}
	}

	function init() {
		var nodes = document.querySelectorAll('.cotlas-local-datetime');
		if (!nodes.length) {
			return;
		}

		var entries = Array.prototype.map.call(nodes, function (el) {
			var formatter = buildFormatter(el);
			if (formatter) {
				el.textContent = formatter.format(new Date());
			}
			return { el: el, formatter: formatter };
		});

		window.setInterval(function () {
			var now = new Date();
			entries.forEach(function (entry) {
				if (entry.formatter) {
					entry.el.textContent = entry.formatter.format(now);
				}
			});
		}, REFRESH_MS);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

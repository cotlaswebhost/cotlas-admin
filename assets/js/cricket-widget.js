/**
 * Cricket Widget — tab switching + carousel dots.
 */
(function () {
	'use strict';

	function initWidget(root) {
		var tabs = root.querySelectorAll('.ccw-tab');
		var panels = root.querySelectorAll('.ccw-panel');

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				tabs.forEach(function (t) {
					t.classList.remove('ccw-tab--active');
					t.setAttribute('aria-selected', 'false');
				});
				panels.forEach(function (p) { p.classList.add('ccw-panel--hidden'); });

				tab.classList.add('ccw-tab--active');
				tab.setAttribute('aria-selected', 'true');
				var target = document.getElementById(tab.getAttribute('data-target'));
				if (target) { target.classList.remove('ccw-panel--hidden'); }
			});
		});

		// Simple carousel via dot clicks.
		panels.forEach(function (panel) {
			var track = panel.querySelector('.ccw-carousel__track');
			var dots = panel.querySelectorAll('.ccw-dot');
			if (!track || dots.length === 0) { return; }

			dots.forEach(function (dot, idx) {
				dot.addEventListener('click', function () {
					track.style.transform = 'translateX(-' + (idx * 100) + '%)';
					dots.forEach(function (d) { d.classList.remove('ccw-dot--active'); });
					dot.classList.add('ccw-dot--active');
				});
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.cotlas-cricket-widget').forEach(initWidget);
	});
})();
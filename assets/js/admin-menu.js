document.addEventListener('DOMContentLoaded', function () {
	var adminMenu = document.getElementById('adminmenu');
	var adminBody = document.body;
	var flyoutPanel = document.createElement('div');
	var flyoutCloseTimer = null;
	var activeFlyoutItem = null;

	if (!adminMenu) {
		return;
	}

	flyoutPanel.className = 'cotlas-folded-flyout';
	flyoutPanel.hidden = true;
	document.body.appendChild(flyoutPanel);

	function isFoldedMenu() {
		return adminBody.classList.contains('folded');
	}

	function isDesktopMenu() {
		return window.innerWidth > 960;
	}

	function isTabletCompactMenu() {
		return window.innerWidth <= 960 && window.innerWidth > 782;
	}

	function isFlyoutMenuMode() {
		return isFoldedMenu() || isTabletCompactMenu();
	}

	function normalizeDesktopBodyClasses() {
		if (isDesktopMenu() && adminBody.classList.contains('auto-fold')) {
			adminBody.classList.remove('auto-fold');
		}

		adminBody.classList.toggle('cotlas-tablet-compact', isTabletCompactMenu());
	}

	function clearFlyoutCloseTimer() {
		if (flyoutCloseTimer) {
			window.clearTimeout(flyoutCloseTimer);
			flyoutCloseTimer = null;
		}
	}

	function hideFlyout() {
		clearFlyoutCloseTimer();
		flyoutPanel.hidden = true;
		flyoutPanel.innerHTML = '';
		if (activeFlyoutItem) {
			activeFlyoutItem.classList.remove('cotlas-flyout-visible');
		}
		activeFlyoutItem = null;
	}

	function scheduleFlyoutClose() {
		clearFlyoutCloseTimer();
		flyoutCloseTimer = window.setTimeout(hideFlyout, 140);
	}

	function positionFlyout(menuItem) {
		var itemRect = menuItem.getBoundingClientRect();
		var top = Math.max(16, itemRect.top - 2);
		var left = itemRect.right + 12;
		var maxTop = window.innerHeight - flyoutPanel.offsetHeight - 16;

		if (top > maxTop) {
			top = Math.max(16, maxTop);
		}

		flyoutPanel.style.top = top + 'px';
		flyoutPanel.style.left = left + 'px';
	}

	function showFlyout(menuItem, submenu) {
		if (!isFlyoutMenuMode()) {
			return;
		}

		clearFlyoutCloseTimer();

		if (activeFlyoutItem && activeFlyoutItem !== menuItem) {
			activeFlyoutItem.classList.remove('cotlas-flyout-visible');
		}

		activeFlyoutItem = menuItem;
		menuItem.classList.add('cotlas-flyout-visible');
		flyoutPanel.innerHTML = submenu.innerHTML;
		flyoutPanel.hidden = false;
		positionFlyout(menuItem);
	}

	function closeOtherMenus(activeItem, menuItems) {
		menuItems.forEach(function (item) {
			if (item !== activeItem) {
				item.classList.remove('cotlas-menu-open');
				var itemToggle = item.querySelector(':scope > a.menu-top');
				if (itemToggle) {
					itemToggle.setAttribute('aria-expanded', 'false');
				}
			}
		});
	}

	function syncMenuState(menuItems) {
		normalizeDesktopBodyClasses();

		var flyoutMode = isFlyoutMenuMode();

		menuItems.forEach(function (menuItem) {
			var toggle = menuItem.querySelector(':scope > a.menu-top');
			if (!toggle) {
				return;
			}

			var shouldBeOpen = !flyoutMode && (
				menuItem.classList.contains('current') ||
				menuItem.classList.contains('wp-has-current-submenu')
			);

			menuItem.classList.toggle('cotlas-menu-open', shouldBeOpen);
			toggle.setAttribute('aria-expanded', shouldBeOpen ? 'true' : 'false');
		});

		if (!flyoutMode) {
			hideFlyout();
		}
	}

	var menuItems = adminMenu.querySelectorAll('li.menu-top');

	menuItems.forEach(function (menuItem) {
		var submenu = menuItem.querySelector(':scope > .wp-submenu');
		var toggle = menuItem.querySelector(':scope > a.menu-top');

		if (!submenu || !toggle) {
			return;
		}

		menuItem.classList.add('wp-has-submenu');

		menuItem.addEventListener('mouseenter', function () {
			showFlyout(menuItem, submenu);
		});

		menuItem.addEventListener('mouseleave', function () {
			if (isFlyoutMenuMode()) {
				scheduleFlyoutClose();
			}
		});

		toggle.addEventListener('click', function (event) {
			var isFlyoutMode = isFlyoutMenuMode();
			var isCurrentSection = menuItem.classList.contains('current') || menuItem.classList.contains('wp-has-current-submenu');
			var willOpen = !menuItem.classList.contains('cotlas-menu-open');

			if (isFlyoutMode || !isCurrentSection) {
				event.preventDefault();
			}

			closeOtherMenus(menuItem, menuItems);

			menuItem.classList.toggle('cotlas-menu-open', willOpen);
			toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

			if (isFlyoutMode) {
				if (willOpen) {
					showFlyout(menuItem, submenu);
				} else {
					hideFlyout();
				}
			}
		});
	});

	syncMenuState(menuItems);

	var bodyClassObserver = new MutationObserver(function () {
		normalizeDesktopBodyClasses();
		syncMenuState(menuItems);
	});

	bodyClassObserver.observe(adminBody, {
		attributes: true,
		attributeFilter: ['class']
	});

	window.addEventListener('resize', function () {
		normalizeDesktopBodyClasses();
		syncMenuState(menuItems);

		if (activeFlyoutItem && !flyoutPanel.hidden) {
			positionFlyout(activeFlyoutItem);
		}
	});

	adminMenu.addEventListener('scroll', function () {
		if (activeFlyoutItem && !flyoutPanel.hidden) {
			positionFlyout(activeFlyoutItem);
		}
	});

	flyoutPanel.addEventListener('mouseenter', function () {
		clearFlyoutCloseTimer();
	});

	flyoutPanel.addEventListener('mouseleave', function () {
		scheduleFlyoutClose();
	});

	document.addEventListener('click', function (event) {
		if (!isFlyoutMenuMode()) {
			return;
		}

		if (adminMenu.contains(event.target) || flyoutPanel.contains(event.target)) {
			return;
		}

		closeOtherMenus(null, menuItems);
		hideFlyout();
	});
});
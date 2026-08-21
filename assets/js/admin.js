/**
 * CZ Cookie Consent – admin helpers.
 */
(function () {
	'use strict';

	// Confirmation for destructive actions.
	document.addEventListener('click', function (event) {
		var target = event.target.closest ? event.target.closest('.czcc-confirm') : null;
		if (target && !window.confirm(target.getAttribute('data-confirm') || 'Are you sure?')) {
			event.preventDefault();
		}
	});
})();

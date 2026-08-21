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

	// Highlight the selected network-mode card.
	document.addEventListener('change', function (event) {
		if (!event.target.name || event.target.name !== 'czcc_network_mode') {
			return;
		}
		document.querySelectorAll('.czcc-mode-option').forEach(function (option) {
			var input = option.querySelector('input[name="czcc_network_mode"]');
			option.classList.toggle('is-active', !!(input && input.checked));
		});
	});
})();

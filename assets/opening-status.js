(() => {
	'use strict';

	const config = window.MOSOpeningStatus || {};
	if (!config.endpoint) return;

	const updateElement = (element, data) => {
		if (!data || !data.state) return;

		element.classList.remove('mos-state-open', 'mos-state-closed');
		element.classList.add(`mos-state-${data.state}`);

		const prefix = element.querySelector('.mos-opening-status__prefix');
		const link = element.querySelector('.mos-opening-status__link');

		if (prefix) prefix.textContent = data.prefix || '';
		if (link) {
			link.textContent = data.cta || '';
			link.setAttribute('href', data.url || '#');
		}
	};

	const refresh = async (element) => {
		try {
			const separator = config.endpoint.includes('?') ? '&' : '?';
			const response = await fetch(`${config.endpoint}${separator}_=${Date.now()}`, {
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { 'Accept': 'application/json' }
			});

			if (!response.ok) return;
			updateElement(element, await response.json());
		} catch (error) {
			// Keep the server-rendered message if the live request fails.
		}
	};

	document.querySelectorAll('[data-mos-opening-status]').forEach((element) => {
		const seconds = Math.max(30, parseInt(element.dataset.refresh || '60', 10));
		refresh(element);
		window.setInterval(() => refresh(element), seconds * 1000);
	});
})();

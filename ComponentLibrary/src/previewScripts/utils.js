const { default: axios } = require('axios');

export const getHTMLElementByID = (id, cache = true) => {
	let ele = null;

	ele = window.document.querySelector(`[data-droip=${id}]`);
	if (!ele) {
		ele = window.document.querySelector(`.${id}`);
	}

	return ele;
};

export const alertMessage = (container, message = '', type = 'success', time = 3000) => {
	const successSVG = `<svg width="26" height="26" viewBox="0 0 26 26" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M13 26C20.1118 26 26 20.099 26 13C26 5.88824 20.099 0 12.9873 0C5.88824 0 0 5.88824 0 13C0 20.099 5.90098 26 13 26ZM11.5598 19.2324C11.1265 19.2324 10.7696 19.0539 10.4382 18.6078L7.23922 14.6824C7.04804 14.4275 6.93333 14.1471 6.93333 13.8539C6.93333 13.2804 7.37941 12.8088 7.95294 12.8088C8.32255 12.8088 8.60294 12.9235 8.92157 13.3441L11.5088 16.6833L16.951 7.9402C17.1931 7.55784 17.5245 7.35392 17.8559 7.35392C18.4167 7.35392 18.9392 7.73628 18.9392 8.33529C18.9392 8.61569 18.7735 8.90882 18.6206 9.17647L12.6304 18.6078C12.3627 19.0284 11.9931 19.2324 11.5598 19.2324Z" fill="#3B0AFF" />
		</svg>`;
	const errorSVG = `<svg width="26" height="24" viewBox="0 0 26 24" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path fillRule="evenodd" clipRule="evenodd" d="M12.3424 0.661601C12.6291 0.151863 13.363 0.151862 13.6497 0.6616L25.8673 22.3818C26.1486 22.8818 25.7873 23.4995 25.2137 23.4995H0.778435C0.204815 23.4995 -0.156469 22.8818 0.124754 22.3818L12.3424 0.661601ZM12.996 8.02235C13.4931 8.02235 13.896 8.42529 13.896 8.92235V13.5541C13.896 14.0511 13.4931 14.4541 12.996 14.4541C12.499 14.4541 12.0961 14.0511 12.0961 13.5541V8.92235C12.0961 8.42529 12.499 8.02235 12.996 8.02235ZM14.0961 18.9995C14.0961 18.392 13.6036 17.8995 12.996 17.8995C12.3885 17.8995 11.896 18.392 11.896 18.9995V19.1023C11.896 19.7099 12.3885 20.2023 12.996 20.2023C13.6036 20.2023 14.0961 19.7099 14.0961 19.1023V18.9995Z" fill="#fff" />
		</svg>`;

	let alert = document.createElement('div');
	alert.classList.add(`${CLASS_PREFIX}-alert`);
	alert.classList.add(`${CLASS_PREFIX}-alert-core-${type}`);
	alert.innerHTML = `
		${type === 'success' ? successSVG : ''}
		${type === 'error' ? errorSVG : ''}
		<span>${message}</span>
	`;
	// just only one alert message show at a time
	container.appendChild(alert);

	setTimeout(() => {
		alert.remove();
	}, time);
};

export const debounce = (func, delay) => {
	let timeoutId;
	return function (...args) {
		if (timeoutId) {
			clearTimeout(timeoutId);
		}
		timeoutId = setTimeout(() => {
			func.apply(this, args);
			timeoutId = null;
		}, delay);
	};
};

export const bytesToMB = (bytes) => Math.floor(bytes / 1000000);

export const showState = (ele) => {
	ele.dataset.element_hide = false;
};

export const hideState = (ele) => {
	ele.dataset.element_hide = true;
};

export const maybeInitiateCollectionPagination = (id) => {
	const config = window.droipCollections[id];

	if (config?.pagination) {
		const collectionElement = getHTMLElementByID(id, false);

		if (collectionElement) {
			const paginationElm = collectionElement.querySelector('[data-droip-pagination="pagination"]');

			if (paginationElm && paginationElm.getAttribute('data-pagination-type') === 'infinite_scroll') {
				const totalPages = parseInt(paginationElm.getAttribute('data-total-pages'), 10);
				let currentPage = parseInt(paginationElm.getAttribute('data-current-page'), 10);

				setupInfinitePagination(paginationElm, config, id, collectionElement, totalPages, currentPage);
			}

			if (paginationElm && paginationElm.children?.length) {
				attachEventListenerToPagination(paginationElm, config, id, collectionElement);
			}
		}
	}
};

export const fetchCollectionData = async ({ collectionId, endpoint = '', formData, droipData, context }) => {
	try {
		formData.append('collection_id', collectionId);
		formData.append('post_id', wp_droip.postId);
		formData.append('context', JSON.stringify(context || wp_droip.context));
		formData.append('droip_data', droipData);

		const response = await axios({
			method: 'post',
			url: `${wp_droip.restUrl}droip/${wp_droip.apiVersion}/frontend/${endpoint}`,
			data: formData,
			headers: {
				'X-WP-Nonce': wp_droip.nonce,
			},
		});

		return response.data;
	} catch (error) {
		console.log(error);
	}
};

export const initComponentLibraryPreviewScripts = () => {
	const configs = window.document.body.querySelectorAll('[data="DroipComponentLibrary-elements-property-vars"]');
	configs.forEach((config) => {
		const config1 = config?.textContent;
		if (config1) {
			const newScript = document.createElement('script');
			newScript.textContent = config1;
			window.document.body.appendChild(newScript);
		}
	});
};

import { alertMessage as showAlert, hideState, showState, initComponentLibraryPreviewScripts } from '../utils';
const { default: axios } = require('axios');
const { getHTMLElementByID, debounce, fetchCollectionData, maybeInitiateCollectionPagination } = require('../utils');

// reset form and file input container after successfull submission
const formResetHandler = (formEl) => {
	if (formEl instanceof HTMLFormElement) {
		// reset form after successfull submission
		formEl.reset();
	}
};

const updateComment = (elId, formData) => {
	const el = getHTMLElementByID(elId);
	const droipData = el.querySelector(`[data-type=droip_data]`).value;
	const comment_parent = formData.get('comment_parent');
	const collectionId = formData.get('collection_id');
	const postId = formData.get('post_id');
	const collectionEl = getHTMLElementByID(collectionId);
	fetchCollectionData({
		collectionId,
		endpoint: 'collection',
		formData: new FormData(),
		droipData: droipData,
		context: { comment: { comment_ID: comment_parent }, type: 'comment', post_id: postId },
	})
		.then((data) => {
			if (data && typeof data === 'string') {
				collectionEl.outerHTML = data;
				initComponentLibraryPreviewScripts();
				window.initCommentChildScripts.forEach((fn) => fn());
				maybeInitiateCollectionPagination(collectionId);
			}
		})
		.catch((error) => {
			console.log({ error });
		});
};

const alertMessage = (container, message, state, errState) => {
	if (state === 'error') {
		showState(errState);
		showAlert(container, message, state);
		const errEl = errState.querySelector('.droip-errormessage');
		errEl.innerHTML = message;
	} else {
		hideState(errState);
		showAlert(container, message, state);
	}
};

const submitHandler = debounce((formEl, properties, id, errState) => {
	const submitButton = formEl.querySelector('[type="submit"]');
	if (submitButton) {
		submitButton.setAttribute('disabled', 'true');
	}
	let formData = new FormData(formEl);

	if (properties.name === 'droip-change-password') {
		const params = new URLSearchParams(window.location.search);
		const username = params.get('login');
		const resetKey = params.get('key');
		formData.append('username', username);
		formData.append('reset_key', resetKey);
	}

	if (properties.name === 'droip-retrieve-username') {
		formData.append('emailSubject', properties.emailSubject);
		formData.append('emailBody', JSON.stringify(properties.emailBody));
	}

	if (properties.name === 'droip-forgot-password') {
		formData.append('emailSubject', properties.emailSubject);
		formData.append('emailBody', JSON.stringify(properties.emailBody));
	}

	const currentUrl = new URL(window.location.href);
	const redirectTo = currentUrl.searchParams.get('redirect_to');
	if (redirectTo) {
		properties.successURL = redirectTo;
	}

	axios
		.post(`${wp_droip.restUrl}DroipComponentLibrary/v1/${properties.name}`, formData, {
			headers: {
				'X-WP-Nonce': wp_droip.nonce,
				'X-WP-ELEMENT-NONCE': properties.nonce || wp_droip.nonce,
			},
		})
		.then((res) => {
			if (res.status === 200) {
				if (properties.successURL && typeof properties.successURL === 'string') {
					if (properties.successURL === '#') window.location.reload();
					else window.location.href = properties.successURL;
				} else {
					const messageContainer = document.querySelector('body');
					alertMessage(messageContainer, res.data.message, 'success', errState);
					if (properties.name === 'droip-comment') {
						updateComment(id, formData);
					}
				}
				formResetHandler(formEl);
			} else {
				const errorMessageContainer = document.querySelector('body');
				alertMessage(errorMessageContainer, res.data.message, 'error', errState);
			}
			if (submitButton) {
				submitButton.removeAttribute('disabled');
			}
		})
		.catch((error) => {
			const errorMessageContainer = document.querySelector('body');
			const errorMessage = error?.response?.data?.message || window.wp.i18n.__('Failed! Please try again', 'droip');
			alertMessage(errorMessageContainer, errorMessage, 'error', errState);
			if (submitButton) {
				submitButton.removeAttribute('disabled');
			}
		});
}, 300);

function loadCompLibFormActionsUsingElementID({ properties, id }) {
	/**
	 * @type {HTMLFormElement}
	 */

	let formEl = getHTMLElementByID(id);
	if (!formEl) return;

	const errState = formEl.querySelector(`[data-ele_name="${properties.name}-error"]`);

	formEl.addEventListener('submit', (e) => {
		e.preventDefault();
		submitHandler(formEl, properties, id, errState);
		return false;
	});
}

function initCompLibForm() {
	const { form } = DroipComponentLibrary || {};
	if (typeof form !== 'undefined' && Object.keys(form).length > 0) {
		Object.keys(form).map((id) => {
			loadCompLibFormActionsUsingElementID({ properties: form[id], id });
		});
	}
}

try {
	initCompLibForm();
} catch (error) {}

window.initCommentChildScripts = [...(window.initCommentChildScripts || []), initCompLibForm];

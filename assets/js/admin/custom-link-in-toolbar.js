// custom-link-in-toolbar.js
// wrapped into IIFE - to leave global space clean.
(function (window, wp) {
	let timeID = null;
	const editWithBtnHTML = `<a class="droip-edit-with-btn">
	<style>
			.droip-edit-with-btn{
				display: inline-flex;
				background: #9353FF;
				box-shadow: 0px 0px 1.5px rgba(0, 0, 0, 0.4), 0px 0.5px 1.5px rgba(0, 0, 0, 0.2);
				border-radius: 7.5px;
				padding: 10px 12px;
				font-weight: 500;
				font-size: 12px;
				line-height: 16px;
				color: #ffffff !important;
				cursor: pointer;
				align-items: center;
				justify-content: space-between;
				gap: 8px;
				text-decoration: none;
				white-space: nowrap;
			}
			.droip-edit-with-btn:hover{
				background: #7338D6;
				box-shadow: 0px 0px 1.5px rgba(0, 0, 0, 0.4), 0px 0.5px 1.5px rgba(0, 0, 0, 0.2), inset 0px 0.5px 0px rgba(255, 255, 255, 0.25), inset 0px 1px 0px rgba(255, 255, 255, 0.06);
				color: #fff;
			}
		 .droip-edit-svg{
				width: 16px;
				height: 16px;
			}
		</style>
			<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" class="droip-edit-svg">
				<path fillRule="evenodd" clipRule="evenodd" d="M4.71738 10.0489L4.01068 10.7556L4.01027 10.756L4.01089 10.7566L4.00936 10.7569L2.83237 10.9923L2.016 11.1556C1.31628 11.2955 0.69936 10.6786 0.839305 9.97889L1.23808 7.98502L1.23706 7.98401L1.94417 7.2769L7.70647 1.5146C8.4721 0.748965 9.71344 0.748965 10.4791 1.5146C11.2447 2.28023 11.2447 3.52157 10.4791 4.2872L4.71738 10.0489ZM8.7258 4.62627L3.51635 9.83572L1.81988 10.175L2.15979 8.47549L7.36741 3.26787L8.7258 4.62627ZM9.43291 3.91916L9.77197 3.58009C10.1471 3.20499 10.1471 2.59681 9.77197 2.2217C9.39686 1.8466 8.78869 1.8466 8.41358 2.2217L8.07451 2.56077L9.43291 3.91916Z" fill="white"/>
			</svg>
			<span>Edit with Droip</span>
		</a>`;
	const backToWordpressEditBtnHTML = `
		<a class="droip-back-dash-btn">
		<style>
		.droip-back-dash-btn{
			padding: 10px 12px;
			background: rgba(0, 0, 0, 0.1);
			border: 1px solid rgba(0, 0, 0, 0.3);
			border-radius: 7.5px;
			color: #000;
			font-weight: 500;
			font-size: 12px;
			line-height: 16px;
			cursor: pointer;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 8px;
			text-decoration: none;
		}
		.droip-back-dash-btn:hover{
			border: 1px solid rgba(0, 0, 0, 0.6);
			color: #000;
		}
		.droip-back-dash-svg{
			width: 16px;
			height: 13px;
		}
		</style>
			<svg width="10" height="9" viewBox="0 0 10 9" fill="none" xmlns="http://www.w3.org/2000/svg" class="droip-back-dash-svg">
				<path d="M0 4.07129C0 3.93701 0.0537109 3.81348 0.161133 3.71143L3.72217 0.161133C3.84033 0.0429688 3.95312 0 4.08203 0C4.34521 0 4.54932 0.193359 4.54932 0.461914C4.54932 0.59082 4.50635 0.714355 4.42041 0.800293L3.21729 2.0249L1.4126 3.67383L2.70703 3.59326H9.50146C9.78076 3.59326 9.97412 3.79199 9.97412 4.07129C9.97412 4.35059 9.78076 4.54932 9.50146 4.54932H2.70703L1.40723 4.46875L3.21729 6.11768L4.42041 7.34229C4.50635 7.42285 4.54932 7.55176 4.54932 7.68066C4.54932 7.94922 4.34521 8.14258 4.08203 8.14258C3.95312 8.14258 3.84033 8.09424 3.73291 7.99219L0.161133 4.43115C0.0537109 4.3291 0 4.20557 0 4.07129Z" fill="black" fillOpacity="0.7"/>
			</svg>
			<span>Back to WordPress Editor</span>
		</a>
	`;

	const backtoWPandEditDroipBtn = `<div class="droip-editor-button-wrapper" style="min-height: 300px; background: #f6f7f7; display: flex; justify-content: center; align-items: center;margin-top: 10px;"><div style="display: flex; gap:10px;">${backToWordpressEditBtnHTML}${editWithBtnHTML}</div></div>`;

	const createTextToHtmlElement = (text) => {
		const div = document.createElement('div');
		div.innerHTML = text;

		return div.firstChild;
	};

	const handleClickEditWithBtn = (e) => {
		e.preventDefault();
		let title = document.querySelector('[name="post_title"]');
		if (title) {
			title = title.value;
		} else {
			// for gutenberg editor
			title = document.getElementsByClassName('wp-block-post-title')?.[0]?.innerText || '';
		}
		const formData = new FormData();
		formData.append('action', 'droip_post_apis');
		formData.append('endpoint', 'back-to-droip-editor');
		formData.append('postId', droip_admin.postId);
		formData.append('title', title);

		fetch(droip_admin.ajaxUrl, {
			method: 'POST', // or 'PUT'
			body: formData,
			headers: {
				'X-WP-Nonce': droip_admin.nonce,
			},
		})
			.then((response) => response.json())
			.then((data) => {
				window.location.href = droip_admin.postEditURL;
			})
			.catch((error) => {
				console.error('Error:', error);
			});
	};
	const handleClickBackToWordpressBtn = () => {
		const formData = new FormData();
		formData.append('action', 'droip_post_apis');
		formData.append('endpoint', 'back-to-wordpress-editor');
		formData.append('postId', droip_admin.postId);

		fetch(droip_admin.ajaxUrl, {
			method: 'POST', // or 'PUT'
			body: formData,
			headers: {
				'X-WP-Nonce': droip_admin.nonce, // Add nonce as a custom header
			},
		})
			.then((response) => response.json())
			.then((data) => {
				if (data) {
				}
			})
			.catch((error) => {
				console.error('Error:', error);
			});
	};

	const classicAddEditWithSingleButton = () => {
		const editButtonWrapper = document.querySelector('#wp-content-media-buttons');
		if (!editButtonWrapper) return;
		editButtonWrapper.append(createTextToHtmlElement(editWithBtnHTML));
		const droipBtns = document.querySelectorAll(`.droip-edit-with-btn`);
		droipBtns.forEach((droipBtn) => {
			droipBtn.addEventListener('click', handleClickEditWithBtn);
		});
	};
	const gutenbergAddEditWithSingleButton = () => {
		const editButtonWrapper = document.querySelector('.edit-post-header-toolbar');
		if (!editButtonWrapper) return;
		editButtonWrapper.append(createTextToHtmlElement(editWithBtnHTML));
		const droipBtns = document.querySelectorAll(`.droip-edit-with-btn`);
		droipBtns.forEach((droipBtn) => {
			droipBtn.addEventListener('click', handleClickEditWithBtn);
		});
		clearInterval(timeID);
	};
	const classicAddDroipButtonsInsideContentWrapper = () => {
		const contentDiv = document.querySelector('#postdivrich');
		if (!contentDiv) return;
		contentDiv.style.display = 'none';
		contentDiv.insertAdjacentHTML('afterend', backtoWPandEditDroipBtn);

		const backToWPbtns = document.querySelectorAll(`.droip-back-dash-btn`);
		backToWPbtns.forEach((backToWPbtn) => {
			backToWPbtn.addEventListener('click', () => {
				handleClickBackToWordpressBtn();
				removeButtonsFromContent(contentDiv);
			});
		});

		const droipBtns = document.querySelectorAll(`.droip-edit-with-btn`);
		droipBtns.forEach((droipBtn) => {
			droipBtn.addEventListener('click', handleClickEditWithBtn);
		});
	};
	const gutenbergAddDroipButtonsInsideContentWrapper = () => {
		let document = window.document;
		let contentDiv = document.querySelector('.is-root-container');
		if (!contentDiv) {
			const editorCanvasIframe = document.querySelector('[name="editor-canvas"]');
			if (!editorCanvasIframe) return;
			// Access the iframe's content window
			let iframeWindow = editorCanvasIframe.contentWindow;
			// Access the document inside the iframe
			let iframeDocument = iframeWindow.document;
			// Find the element inside the iframe using its class name
			contentDiv = iframeDocument.querySelector('.is-root-container');
			if (!contentDiv) {
				return;
			}
			document = iframeDocument;
		}
		contentDiv.style.display = 'none';
		contentDiv.insertAdjacentHTML('afterend', backtoWPandEditDroipBtn);

		const backToWPbtns = document.querySelectorAll(`.droip-back-dash-btn`);
		backToWPbtns.forEach((backToWPbtn) => {
			backToWPbtn.addEventListener('click', () => {
				handleClickBackToWordpressBtn();
				removeButtonsFromContent(contentDiv, document);
			});
		});

		const droipBtns = document.querySelectorAll(`.droip-edit-with-btn`);
		droipBtns.forEach((droipBtn) => {
			droipBtn.addEventListener('click', handleClickEditWithBtn);
		});

		clearInterval(timeID);
	};
	const removeButtonsFromContent = (contentDiv, document = window.document) => {
		const buttons = document.querySelector('.droip-editor-button-wrapper');
		buttons?.remove();
		contentDiv.style.display = 'block';
		classicAddEditWithSingleButton();
		gutenbergAddEditWithSingleButton();
	};

	const enableForClassicEditor = () => {
		if (droip_admin.isEditorModeIsDroip == 1) {
			classicAddDroipButtonsInsideContentWrapper();
		} else {
			classicAddEditWithSingleButton();
		}
	};

	const enableForGutenbergEditor = () => {
		timeID = setInterval(() => {
			if (droip_admin.isEditorModeIsDroip == 1) {
				gutenbergAddDroipButtonsInsideContentWrapper();
			} else {
				gutenbergAddEditWithSingleButton();
			}
		}, 1000);
	};

	enableForClassicEditor();
	enableForGutenbergEditor();
})(window, wp);

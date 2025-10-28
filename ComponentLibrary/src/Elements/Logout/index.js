import React from 'react';

const { droip } = window;

const LogoutButton = (props) => {
	const { elementId, renderChildren, className } = props;

	const { getCanvasElement, getAllAttributes } = droip;
	const element = getCanvasElement(elementId);

	const templates = () => [];

	return (
		<a {...getAllAttributes(element)} className={className}>
			{renderChildren({ template: templates() })}
		</a>
	);
};

export default {
	name: 'droip-logout',
	title: 'Logout',
	svg: (
		<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 32 32">
			<path
				stroke="currentColor"
				strokeWidth="2"
				d="M3 8a3 3 0 0 1 3-3h16a3 3 0 0 1 3 3v2a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3z"
			/>
			<path
				fill="currentColor"
				fillRule="evenodd"
				d="M2 20a4 4 0 0 1 4-4h12a1 1 0 1 1 0 2H6a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h7a1 1 0 1 1 0 2H6a4 4 0 0 1-4-4z"
				clipRule="evenodd"
			/>
			<path fill="currentColor" d="m17 22.5 9.586-9.586a2 2 0 0 1 2.828 0l.172.172a2 2 0 0 1 0 2.828L20 25.5l-3.5.5z" />
		</svg>
	),
	icon: `${droip?.iconPrefix}-form`,
	hoverIcon: `${droip?.iconPrefix}-form-fill`,
	className: 'droiplogoutbtn',
	category: 'Auth Library',
	visibility: false,
	children: [],
	properties: {
		tag: 'a',
		type: 'href',
		attributes: {
			href: '/wp-login.php?action=logout',
		},
		settings: { redirect_url: '' },
	},
	Component: LogoutButton,
	constraints: {
		childrens: [
			{
				element: '*',
				condition: 'ALLOW',
			},
		],
	},
	source: 'DroipComponentLibrary',
	defaultStyle: `
	text-decoration: none;
	`,
	controls: { margin: true, padding: true, height: true, width: true },
	settings: [
		{
			key: 'redirect_url',
			label: 'Redirect URL',
			setting: {
				...droip?.elementSettings?.INPUT,
				placeholder: 'Redirect URL',
			},
		},
	],
};

import React from 'react';
const { droip } = window;

export const getCurrentActiveState = (parentId) => {
	const parentElement = droip.getCanvasElement(parentId);

	if (parentElement.name === 'body') return 'normal';
	if (parentElement.name !== 'droip-register') return getCurrentActiveState(parentElement.parentId);

	const {
		properties: { settings },
	} = parentElement;
	let state = settings?.state;
	return state || 'normal';
};

const RegisterElement = (props) => {
	const { elementId, renderChildren, className } = props;

	const { getCanvasElement, getAllAttributes } = droip;
	const element = getCanvasElement(elementId);

	const templates = () => [['droip-register-error', { manualDelete: false, properties: {} }]];

	return (
		<form {...getAllAttributes(element)} className={className}>
			{renderChildren({ template: templates() })}
		</form>
	);
};

export default {
	name: 'droip-register',
	title: 'Register',
	description: 'HTML form element',
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
	className: '',
	category: 'component library',
	visibility: false,
	children: [],
	properties: {
		tag: 'form',
		settings: {
			successURL: '#',
			state: 'normal',
		},
		attributes: {},
	},
	Component: RegisterElement,
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
		padding: 40px;
		border-radius: 10px;
		box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.2);
		width: 60%;
		margin-left: auto;
		margin-right: auto;
		box-sizing: border-box;
		`,
	controls: { margin: true, padding: true, height: true, width: true },
	settings: [
		{
			key: 'successURL',
			label: 'Success URL',
			setting: {
				...(droip?.elementSettings || {}).INPUT,
				placeholder: 'Register success URL',
			},
		},
		{
			setting: { ...(droip?.elementSettings || {}).DIVIDER_TRANSPARENT },
		},
		{
			key: 'state',
			label: 'State',
			setting: {
				...(droip?.elementSettings || {}).TAB,
				tabs: [
					{
						value: 'normal',
						label: 'Normal',
					},
					{
						value: 'error',
						label: 'Error',
					},
				],
			},
		},
	],
};

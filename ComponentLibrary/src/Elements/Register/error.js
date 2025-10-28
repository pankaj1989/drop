import React from 'react';
import { getCurrentActiveState } from '.';
const { droip } = window;

const RegisterError = (props) => {
	const { elementId, renderChildren, className } = props;

	const { getCanvasElement, getAllAttributes } = droip;
	const element = getCanvasElement(elementId);

	const templates = () => [];

	return (
		<div
			{...getAllAttributes(element)}
			className={className}
			data-element_hide={(getCurrentActiveState(element.parentId) !== 'error').toString()}
		>
			{renderChildren({ template: templates() })}
		</div>
	);
};

export default {
	name: 'droip-register-error',
	title: 'Register Error',
	description: 'HTML form element',
	svg: (
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path
				d="M16.0002 7.99994L12.0002 11.9999M12.0002 11.9999L8.00023 15.9999M12.0002 11.9999L8.00023 7.99994M12.0002 11.9999L16.0002 15.9999M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12Z"
				stroke="currentColor"
				strokeWidth="1.5"
				strokeLinecap="round"
				strokeLinejoin="round"
			></path>
		</svg>
	),
	className: '',
	category: 'component library',
	visibility: false,
	children: [],
	properties: {
		tag: 'div',
		settings: {},
	},
	Component: RegisterError,
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
		width: 100%;
		`,
	controls: { margin: true, padding: true, height: true, width: true },
	settings: [],
};

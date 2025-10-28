import {
	Login,
	LoginError,
	Register,
	RegisterError,
	ForgotPassword,
	ForgotPasswordError,
	Logout,
	ChangePassword,
	RetrieveUsername,
	ChangePasswordError,
	RetrieveUsernameError,
	Comment,
	CommentError,
} from './Elements';
const { droip } = window;
import { SERVER_ASSETS_BASE } from '../../builder/conf';

const AllElements = [
	Login,
	LoginError,
	Register,
	RegisterError,
	ForgotPassword,
	ForgotPasswordError,
	Logout,
	ChangePassword,
	RetrieveUsername,
	ChangePasswordError,
	RetrieveUsernameError,
	Comment,
	CommentError,
];

const registerEachElement = (element) => {
	if (!element) {
		return;
	}
	droip.RegisterElement(element.default);
};

export const registerComponentLibrary = () => {
	AllElements.forEach(registerEachElement);
};

export const addComponentCategory = (componentCategory) => droip.addComponentCategory(componentCategory);

export const replaceAssetPlaceholderWithUrl = (content) => {
	let contentStr = JSON.stringify(content);
	let regex = /\[\[SERVER_BASE_URL\]\]/gim;
	contentStr = contentStr.replace(regex, () => SERVER_ASSETS_BASE);
	return JSON.parse(contentStr);
};

export const conf = {
	ICON_PREFIX: 'droip',
	APP_SRC: 'DroipComponentLibrary',
};

const DroipComponentLibrary = {};
AllElements.forEach((a) => {
	if (!a.default) {
		return;
	}
	DroipComponentLibrary[a.default.name] = a.default;
});

export default DroipComponentLibrary;

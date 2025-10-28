import { addComponentCategory, registerComponentLibrary, replaceAssetPlaceholderWithUrl } from '.';
import axios from 'axios';
import { SERVER_ASSETS_BASE } from '../../builder/conf';

const getCategories = async () => {
	try {
		const response = await axios.get(SERVER_ASSETS_BASE + '/droip-apps/ComponentLibrary/index.json');
		const { data } = response;
		if (!data) {
			console.log('No data received from the server.');
			return;
		}

		const category = replaceAssetPlaceholderWithUrl(data);
		if (!category) {
			console.log('Failed to replace placeholders in the data.');
			return;
		}
		addComponentCategory(category);
	} catch (error) {
		console.error('An error occurred while processing:', error.message);
	}
};

getCategories();
registerComponentLibrary();

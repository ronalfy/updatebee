declare const ajaxurl: string;

interface UpdateBeeDashboard {
	retrieveNonce: string;
	saveNonce: string;
	resetNonce: string;
}

declare const updatebeeDashboard: UpdateBeeDashboard;

interface UpdateBeePlugins {
	retrieveNonce: string;
	bulkNonce: string;
	isNetworkAdmin: boolean;
}

declare const updatebeePlugins: UpdateBeePlugins;

interface UpdateBeeThemes {
	retrieveNonce: string;
	bulkNonce: string;
	isNetworkAdmin: boolean;
}

declare const updatebeeThemes: UpdateBeeThemes;

interface UpdateBeeTranslations {
	retrieveNonce: string;
	bulkNonce: string;
}

declare const updatebeeTranslations: UpdateBeeTranslations;

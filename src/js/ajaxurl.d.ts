declare const ajaxurl: string;

interface UpdateBeeDashboard {
	retrieveNonce: string;
	saveNonce: string;
	resetNonce: string;
}

declare const updatebeeDashboard: UpdateBeeDashboard;

interface UpdateBeePlugins {
	retrieveNonce: string;
}

declare const updatebeePlugins: UpdateBeePlugins;

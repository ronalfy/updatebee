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

interface UpdateBeeLogs {
	retrieveNonce: string;
	detailNonce: string;
	exportNonce: string;
	clearNonce: string;
	saveNonce: string;
	clearLogsDays: number;
	slackWebhookUrl: string;
}

declare const updatebeeLogs: UpdateBeeLogs;

interface UpdateBeeTools {
	forceUpdatesNonce: string;
	triggerCronNonce: string;
	refreshUpdatesNonce: string;
}

declare const updatebeeTools: UpdateBeeTools;

interface UpdateBeeEmails {
	enableCoreEmails: boolean;
	coreEmailRecipients: string[];
	wordpressSaveNonce: string;
	successRetrieveNonce: string;
	successSaveNonce: string;
	successResetNonce: string;
	failureRetrieveNonce: string;
	failureSaveNonce: string;
	failureResetNonce: string;
}

declare const updatebeeEmails: UpdateBeeEmails;

interface UpdateBeeDisableWpCronState {
	enabled: boolean;
	constantDefined: boolean;
	constantValue: boolean | null;
}

interface UpdateBeeCron {
	nonce: string;
	eventsNonce: string;
	initialSubtab: string;
	settingsUrl: string;
	disableWpCron: UpdateBeeDisableWpCronState;
}

declare const updatebeeCron: UpdateBeeCron;

interface UpdateBeeAdvanced {
	saveNonce: string;
	resetNonce: string;
	delayUpdates: number;
	blockUntestedUpdates: boolean;
}

declare const updatebeeAdvanced: UpdateBeeAdvanced;

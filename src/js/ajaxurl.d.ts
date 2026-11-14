declare const ajaxurl: string;

interface UpdateBeeDashboard {
	retrieveNonce: string;
	saveNonce: string;
	resetNonce: string;
	retrieveUpcomingNonce: string;
	refreshNonce: string;
	initialSubtab: string;
	settingsUrl: string;
	schedulesUrl: string;
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
	slackEnabled: boolean;
	slackWebhookConfigured: boolean;
}

declare const updatebeeLogs: UpdateBeeLogs;

interface UpdateBeeTools {
	forceUpdatesNonce: string;
	delayEnabled: boolean;
	triggerCronNonce: string;
	refreshUpdatesNonce: string;
	eumDetected: boolean;
	migrateEumNonce: string;
	hideEumNonce: string;
	cauDetected: boolean;
	migrateCauNonce: string;
	hideCauNonce: string;
	exportSettingsNonce: string;
	importSettingsNonce: string;
	pluginName: string;
}

declare const updatebeeTools: UpdateBeeTools;

interface UpdateBeeCommands {
	forceUpdatesNonce: string;
	refreshUpdatesNonce: string;
	delayEnabled: boolean;
}

declare const updatebeeCommands: UpdateBeeCommands;

interface UpdateBeeEmails {
	pluginName: string;
	enableCoreEmails: boolean;
	coreEmailRecipients: string[];
	wordpressSaveNonce: string;
	fatalErrorDetection: boolean;
	fatalEmailRecipients: string[];
	fatalNotifySlack: boolean;
	fatalSaveNonce: string;
	successRetrieveNonce: string;
	successSaveNonce: string;
	successResetNonce: string;
	failureRetrieveNonce: string;
	failureSaveNonce: string;
	failureResetNonce: string;
	updatesAvailableRetrieveNonce: string;
	updatesAvailableSaveNonce: string;
	updatesAvailableResetNonce: string;
	updatesAvailableSendNowNonce: string;
	slackWebhookConfigured: boolean;
}

declare const updatebeeEmails: UpdateBeeEmails;

interface UpdateBeeWpCronStatus {
	constantDefined: boolean;
	constantValue: boolean | null;
}

interface UpdateBeeCronCommand {
	label: string;
	command: string;
}

interface UpdateBeeCron {
	nonce: string;
	eventsNonce: string;
	initialSubtab: string;
	settingsUrl: string;
	wpCronStatus: UpdateBeeWpCronStatus;
	systemCronCommands: UpdateBeeCronCommand[];
}

declare const updatebeeCron: UpdateBeeCron;

interface UpdateBeeAdvanced {
	saveNonce: string;
	resetNonce: string;
	delayUpdates: number;
	blockUntestedUpdates: boolean;
	upgradeNoticeKeywords: string;
	upgradeNoticeKeywordsEnabled: boolean;
	slackWebhookUrl: string;
	adminBarEnabled: boolean;
	adminBarShowAdmin: boolean;
	adminBarShowFrontend: boolean;
	pluginName: string;
	brandingSaveNonce: string;
	brandingName: string;
	brandingDescription: string;
	brandingAuthor: string;
	brandingUrl: string;
	brandingDefaults: UpdateBeeBrandingDefaults;
}

interface UpdateBeeBrandingDefaults {
	name: string;
	description: string;
	author: string;
	url: string;
}

declare const updatebeeAdvanced: UpdateBeeAdvanced;

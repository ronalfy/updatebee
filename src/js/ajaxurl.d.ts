declare const ajaxurl: string;

interface UpdateBeeDashboard {
	retrieveNonce: string;
	saveNonce: string;
	resetNonce: string;
	showTranslations: boolean;
}

declare const updatebeeDashboard: UpdateBeeDashboard;

interface UpdateBeeUpcoming {
	retrieveUpcomingNonce: string;
	saveDelayNonce: string;
	refreshNonce: string;
	schedulesUrl: string;
	showSchedules: boolean;
}

declare const updatebeeUpcoming: UpdateBeeUpcoming;

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
	slackSkipDelayed: boolean;
	slackWebhookConfigured: boolean;
	discordEnabled: boolean;
	discordSkipDelayed: boolean;
	discordWebhookConfigured: boolean;
	showWebhooks: boolean;
}

declare const updatebeeLogs: UpdateBeeLogs;

interface UpdateBeeTools {
	forceUpdatesNonce: string;
	delayEnabled: boolean;
	ignoreAutomaticUpdaterDisabled: boolean;
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
	ignoreAutomaticUpdaterDisabled: boolean;
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
	fatalNotifyDiscord: boolean;
	fatalSaveNonce: string;
	fatalPreviewUrl: string;
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
	discordWebhookConfigured: boolean;
	showWebhooks: boolean;
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
	canDeactivate: boolean;
	deactivateNonce: string;
	delayUpdates: number;
	blockUntestedUpdates: boolean;
	upgradeNoticeKeywords: string;
	upgradeNoticeKeywordsEnabled: boolean;
	slackWebhookUrl: string;
	discordWebhookUrl: string;
	adminBarEnabled: boolean;
	adminBarShowAdmin: boolean;
	adminBarShowFrontend: boolean;
	hiddenFeatures: string[];
	pluginName: string;
	brandingLocked?: boolean;
	brandingSaveNonce?: string;
	brandingName?: string;
	brandingDescription?: string;
	brandingAuthor?: string;
	brandingUrl?: string;
	brandingHideLicenseTab?: boolean;
	brandingHideHeaderLinks?: boolean;
	canHideLicenseTab?: boolean;
	brandingDefaults?: UpdateBeeBrandingDefaults;
}

interface UpdateBeeBrandingDefaults {
	name: string;
	description: string;
	author: string;
	url: string;
}

declare const updatebeeAdvanced: UpdateBeeAdvanced;

interface UpdateBeeLicenseView {
	has_license: boolean;
	item_name: string;
	variation_name: string;
	status: string;
	status_label: string;
	status_tone: string;
	is_lifetime: boolean;
	is_active: boolean;
	activations: string;
	expires_on: string;
	days_left: string;
	show_upsell: boolean;
	upsell_url: string;
	renewal_url: string;
}

interface UpdateBeeLicense {
	configured: boolean;
	keyDefined: boolean;
	activateNonce: string;
	checkNonce: string;
	revokeNonce: string;
	license: UpdateBeeLicenseView;
	upsellUrl: string;
}

declare const updatebeeLicense: UpdateBeeLicense;

interface UpdateBeeWebhookDelivery {
	time: string;
	status: number;
	error: string;
}

interface UpdateBeeWebhooks {
	screen: 'list' | 'edit';
	webhookId: string;
	listUrl: string;
	newUrl: string;
	listNonce: string;
	retrieveNonce: string;
	saveNonce: string;
	testNonce: string;
}

declare const updatebeeWebhooks: UpdateBeeWebhooks;

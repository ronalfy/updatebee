/**
 * Ajax action and nonce mapping per report type.
 */
import type { EmailReportKind } from './email-types';

export type ReportAjaxConfig = {
	retrieveAction: string;
	saveAction: string;
	resetAction: string;
	retrieveNonce: string;
	saveNonce: string;
	resetNonce: string;
};

/**
 * Resolve ajax config for a report card.
 *
 * @param kind Success or failure report.
 * @return Action names and nonces from localized script data.
 */
export const getReportAjaxConfig = (
	kind: EmailReportKind
): ReportAjaxConfig => {
	if ( 'success' === kind ) {
		return {
			retrieveAction: 'updatebee_retrieve_email_success_options',
			saveAction: 'updatebee_save_email_success_options',
			resetAction: 'updatebee_reset_email_success_options',
			retrieveNonce: updatebeeEmails.successRetrieveNonce,
			saveNonce: updatebeeEmails.successSaveNonce,
			resetNonce: updatebeeEmails.successResetNonce,
		};
	}

	if ( 'updates-available' === kind ) {
		return {
			retrieveAction: 'updatebee_retrieve_email_updates_available_options',
			saveAction: 'updatebee_save_email_updates_available_options',
			resetAction: 'updatebee_reset_email_updates_available_options',
			retrieveNonce: updatebeeEmails.updatesAvailableRetrieveNonce,
			saveNonce: updatebeeEmails.updatesAvailableSaveNonce,
			resetNonce: updatebeeEmails.updatesAvailableResetNonce,
		};
	}

	return {
		retrieveAction: 'updatebee_retrieve_email_failure_options',
		saveAction: 'updatebee_save_email_failure_options',
		resetAction: 'updatebee_reset_email_failure_options',
		retrieveNonce: updatebeeEmails.failureRetrieveNonce,
		saveNonce: updatebeeEmails.failureSaveNonce,
		resetNonce: updatebeeEmails.failureResetNonce,
	};
};

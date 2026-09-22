/**
 * Ajax action mapping per update cron context.
 */
import type { CronContextSlug } from './cron-types';

export type CronAjaxConfig = {
	retrieveAction: string;
	saveAction: string;
	resetAction: string;
};

/**
 * Resolve ajax actions for a cron card slug.
 *
 * @param slug plugin_updates|theme_updates|core_updates.
 */
export const getCronAjaxConfig = ( slug: CronContextSlug ): CronAjaxConfig => ( {
	retrieveAction: `updatebee_get_cron_schedule_${ slug }`,
	saveAction: `updatebee_save_cron_schedule_${ slug }`,
	resetAction: `updatebee_reset_cron_schedule_${ slug }`,
} );

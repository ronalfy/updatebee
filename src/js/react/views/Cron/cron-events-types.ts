/**
 * Types for the Cron Events DataViews table.
 */

export type CronEventRow = {
	id: string;
	hook: string;
	sig: string;
	timestamp: number;
	nextRunUtc: string;
	nextRunLocal: string;
	schedule: string;
	interval: number;
	scheduleLabel: string;
	argsDisplay: string;
	isPersistentCore: boolean;
	canDelete: boolean;
	canRun: boolean;
	hasCallback: boolean;
};

export type CronEventsListPayload = {
	events: CronEventRow[];
};

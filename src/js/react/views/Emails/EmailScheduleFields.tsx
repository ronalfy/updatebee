/**
 * Schedule picker for digest email reports.
 */
import { __ } from '@wordpress/i18n';
import type { ScheduleOptions } from './email-types';

type EmailScheduleFieldsProps = {
	value: ScheduleOptions;
	onChange: ( next: ScheduleOptions ) => void;
};

/**
 * Render schedule frequency and time controls.
 *
 * @param props Current schedule and change handler.
 */
const EmailScheduleFields = ( props: EmailScheduleFieldsProps ) => {
	const { value, onChange } = props;

	const showDaySelect =
		'weekly' === value.schedule || 'two-weeks' === value.schedule;
	const showWeekSelect = 'two-weeks' === value.schedule;
	const showDayNum = 'monthly' === value.schedule;

	const dayNumbers = Array.from( { length: 28 }, ( _, index ) => index + 1 );

	return (
		<div className="updatebee-emails-schedule-fields">
			<div className="updatebee-emails-schedule-row">
				<label htmlFor="updatebee-email-schedule-frequency">
					{ __( 'Frequency', 'updatebee' ) }
				</label>
				<select
					id="updatebee-email-schedule-frequency"
					value={ value.schedule }
					onChange={ ( event ) => {
						onChange( {
							...value,
							schedule: event.target.value,
						} );
					} }
				>
					<option value="hourly">
						{ __( 'Hourly', 'updatebee' ) }
					</option>
					<option value="two-hours">
						{ __( 'Every two hours', 'updatebee' ) }
					</option>
					<option value="three-hours">
						{ __( 'Every three hours', 'updatebee' ) }
					</option>
					<option value="six-hours">
						{ __( 'Every six hours', 'updatebee' ) }
					</option>
					<option value="twelve-hours">
						{ __( 'Every twelve hours', 'updatebee' ) }
					</option>
					<option value="daily">
						{ __( 'Once daily', 'updatebee' ) }
					</option>
					<option value="weekly">
						{ __( 'Once weekly', 'updatebee' ) }
					</option>
					<option value="two-weeks">
						{ __( 'Every two weeks', 'updatebee' ) }
					</option>
					<option value="monthly">
						{ __( 'Once a month', 'updatebee' ) }
					</option>
				</select>
			</div>
			<div className="updatebee-emails-schedule-row">
				<label htmlFor="updatebee-email-schedule-time">
					{ __( 'Time', 'updatebee' ) }
				</label>
				<input
					id="updatebee-email-schedule-time"
					type="time"
					value={ value.startTime }
					onChange={ ( event ) => {
						onChange( {
							...value,
							startTime: event.target.value,
						} );
					} }
				/>
			</div>
			{ showDaySelect && (
				<div className="updatebee-emails-schedule-row">
					<label htmlFor="updatebee-email-schedule-day">
						{ __( 'Day of week', 'updatebee' ) }
					</label>
					<select
						id="updatebee-email-schedule-day"
						value={ value.day }
						onChange={ ( event ) => {
							onChange( {
								...value,
								day: event.target.value,
							} );
						} }
					>
						<option value="sunday">
							{ __( 'Sunday', 'updatebee' ) }
						</option>
						<option value="monday">
							{ __( 'Monday', 'updatebee' ) }
						</option>
						<option value="tuesday">
							{ __( 'Tuesday', 'updatebee' ) }
						</option>
						<option value="wednesday">
							{ __( 'Wednesday', 'updatebee' ) }
						</option>
						<option value="thursday">
							{ __( 'Thursday', 'updatebee' ) }
						</option>
						<option value="friday">
							{ __( 'Friday', 'updatebee' ) }
						</option>
						<option value="saturday">
							{ __( 'Saturday', 'updatebee' ) }
						</option>
					</select>
				</div>
			) }
			{ showWeekSelect && (
				<div className="updatebee-emails-schedule-row">
					<label htmlFor="updatebee-email-schedule-week">
						{ __( 'Week', 'updatebee' ) }
					</label>
					<select
						id="updatebee-email-schedule-week"
						value={ value.week }
						onChange={ ( event ) => {
							onChange( {
								...value,
								week: event.target.value,
							} );
						} }
					>
						<option value="week-one">
							{ __( 'Week one', 'updatebee' ) }
						</option>
						<option value="week-two">
							{ __( 'Week two', 'updatebee' ) }
						</option>
					</select>
				</div>
			) }
			{ showDayNum && (
				<div className="updatebee-emails-schedule-row">
					<label htmlFor="updatebee-email-schedule-day-num">
						{ __( 'Day of month', 'updatebee' ) }
					</label>
					<select
						id="updatebee-email-schedule-day-num"
						value={ String( value.dayNum ) }
						onChange={ ( event ) => {
							onChange( {
								...value,
								dayNum: event.target.value,
							} );
						} }
					>
						{ dayNumbers.map( ( day ) => (
							<option key={ day } value={ String( day ) }>
								{ day }
							</option>
						) ) }
					</select>
				</div>
			) }
		</div>
	);
};

export default EmailScheduleFields;

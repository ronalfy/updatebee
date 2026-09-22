/**
 * Frequency and time controls for update cron cards.
 */
import { __ } from '@wordpress/i18n';
import type { CronFrequency, CronScheduleFormValues } from './cron-types';
import {
	defaultDaysListForSchedule,
	scheduleUsesWeekdays,
} from './cron-form-utils';
import CronWeekdayField from './CronWeekdayField';

type CronScheduleFieldsProps = {
	value: CronScheduleFormValues;
	onChange: ( next: CronScheduleFormValues ) => void;
	fieldIdPrefix: string;
};

/**
 * Render schedule field rows.
 *
 * @param props Values and change handler.
 */
const CronScheduleFields = ( props: CronScheduleFieldsProps ) => {
	const { value, onChange, fieldIdPrefix } = props;

	const showDaySelect =
		'weekly' === value.schedule || 'two-weeks' === value.schedule;
	const showWeekSelect = 'two-weeks' === value.schedule;
	const showDayNum = 'monthly' === value.schedule;
	const showWeekdays = scheduleUsesWeekdays( value.schedule );

	const dayNumbers = Array.from( { length: 28 }, ( _, index ) => index + 1 );

	const updateSchedule = ( schedule: CronFrequency ) => {
		onChange( {
			...value,
			schedule,
			daysList: defaultDaysListForSchedule( schedule ),
		} );
	};

	return (
		<div className="updatebee-cron-schedule-fields">
			<div className="updatebee-cron-schedule-row">
				<label htmlFor={ `${ fieldIdPrefix }-frequency` }>
					{ __( 'Frequency', 'updatebee' ) }
				</label>
				<select
					id={ `${ fieldIdPrefix }-frequency` }
					value={ value.schedule }
					onChange={ ( event ) => {
						updateSchedule( event.target.value as CronFrequency );
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
			<div className="updatebee-cron-schedule-row">
				<label htmlFor={ `${ fieldIdPrefix }-time` }>
					{ __( 'Time', 'updatebee' ) }
				</label>
				<input
					id={ `${ fieldIdPrefix }-time` }
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
			{ showWeekdays && (
				<CronWeekdayField
					schedule={ value.schedule }
					value={ value.daysList }
					onChange={ ( daysList ) => {
						onChange( {
							...value,
							daysList,
						} );
					} }
				/>
			) }
			{ showDaySelect && (
				<div className="updatebee-cron-schedule-row">
					<label htmlFor={ `${ fieldIdPrefix }-day` }>
						{ __( 'Day of week', 'updatebee' ) }
					</label>
					<select
						id={ `${ fieldIdPrefix }-day` }
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
				<div className="updatebee-cron-schedule-row">
					<label htmlFor={ `${ fieldIdPrefix }-week` }>
						{ __( 'Week', 'updatebee' ) }
					</label>
					<select
						id={ `${ fieldIdPrefix }-week` }
						value={ value.week }
						onChange={ ( event ) => {
							onChange( {
								...value,
								week: event.target.value,
							} );
						} }
					>
						<option value="1">
							{ __( 'Week one', 'updatebee' ) }
						</option>
						<option value="2">
							{ __( 'Week two', 'updatebee' ) }
						</option>
					</select>
				</div>
			) }
			{ showDayNum && (
				<div className="updatebee-cron-schedule-row">
					<label htmlFor={ `${ fieldIdPrefix }-day-num` }>
						{ __( 'Day of month', 'updatebee' ) }
					</label>
					<select
						id={ `${ fieldIdPrefix }-day-num` }
						value={ value.dayNum }
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

export default CronScheduleFields;

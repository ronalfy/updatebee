/**
 * Weekday multi-select for sub-daily (allow) and daily (except) schedules.
 */
import { __ } from '@wordpress/i18n';
import type { CronFrequency } from './cron-types';

type CronWeekdayFieldProps = {
	schedule: CronFrequency;
	value: number[];
	onChange: ( next: number[] ) => void;
};

const WEEKDAYS: { value: number; labelKey: string }[] = [
	{ value: 1, labelKey: __( 'Sunday', 'updatebee' ) },
	{ value: 2, labelKey: __( 'Monday', 'updatebee' ) },
	{ value: 3, labelKey: __( 'Tuesday', 'updatebee' ) },
	{ value: 4, labelKey: __( 'Wednesday', 'updatebee' ) },
	{ value: 5, labelKey: __( 'Thursday', 'updatebee' ) },
	{ value: 6, labelKey: __( 'Friday', 'updatebee' ) },
	{ value: 7, labelKey: __( 'Saturday', 'updatebee' ) },
];

const weekdayLabel = ( labelKey: string ): string => {
	switch ( labelKey ) {
		case 'Sunday':
			return __( 'Sunday', 'updatebee' );
		case 'Monday':
			return __( 'Monday', 'updatebee' );
		case 'Tuesday':
			return __( 'Tuesday', 'updatebee' );
		case 'Wednesday':
			return __( 'Wednesday', 'updatebee' );
		case 'Thursday':
			return __( 'Thursday', 'updatebee' );
		case 'Friday':
			return __( 'Friday', 'updatebee' );
		case 'Saturday':
			return __( 'Saturday', 'updatebee' );
		default:
			return labelKey;
	}
};

/**
 * Render weekday checkboxes.
 *
 * @param props Schedule mode, selected days, and handler.
 */
const CronWeekdayField = ( props: CronWeekdayFieldProps ) => {
	const { schedule, value, onChange } = props;

	const label =
		'daily' === schedule
			? __( 'Except on these days', 'updatebee' )
			: __( 'On the following days', 'updatebee' );

	const toggleDay = ( day: number, checked: boolean ) => {
		if ( checked ) {
			onChange( [ ...value, day ].sort( ( a, b ) => a - b ) );
			return;
		}
		onChange( value.filter( ( item ) => item !== day ) );
	};

	return (
		<fieldset className="updatebee-cron-weekdays">
			<legend>{ label }</legend>
			<div className="updatebee-cron-weekdays-grid">
				{ WEEKDAYS.map( ( day ) => (
					<label
						key={ day.value }
						className="updatebee-cron-weekday-option"
					>
						<input
							type="checkbox"
							checked={ value.includes( day.value ) }
							onChange={ ( event ) => {
								toggleDay( day.value, event.target.checked );
							} }
						/>
						{ weekdayLabel( day.labelKey ) }
					</label>
				) ) }
			</div>
		</fieldset>
	);
};

export default CronWeekdayField;

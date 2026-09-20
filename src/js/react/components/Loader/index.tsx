import { ReactSpinner1 } from '@mediaron/react-spinners';
import { __ } from '@wordpress/i18n';

const Loader = () => {
	return (
		<div className="updatebee-loader">
			<ReactSpinner1
				size={64}
				speedMultiplier={1.3}
				color="var(--wpds-color-background-interactive-neutral-strong)"
			/>
			<p>{__('Loading…', 'updatebee')}</p>
		</div>
	);
};

export default Loader;
/**
 * Admin command palette actions for refreshing and forcing updates.
 */
import '../../scss/commands.scss';
import { ReactSpinner1 } from '@mediaron/react-spinners';
import { useCommand } from '@wordpress/commands';
import { Modal } from '@wordpress/components';
import {
	createRoot,
	StrictMode,
	useCallback,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Snackbar from '../react/components/Snackbar';
import sendCommand from '../utils/SendCommand';

const UpdateIcon = () => {
	return (
		<svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="24px" height="24px" viewBox="0 0 26 26">
			<path d="M 12 0 L 12 2.25 C 6.503906 2.761719 2.1875 7.371094 2.1875 13 C 2.1875 15.933594 3.351563 18.582031 5.25 20.53125 L 7.96875 18.09375 C 6.660156 16.792969 5.84375 14.988281 5.84375 13 C 5.84375 9.390625 8.523438 6.398438 12 5.90625 L 12 8 L 18.40625 4 Z M 20.75 5.46875 L 18.0625 7.90625 C 19.371094 9.207031 20.15625 11.015625 20.15625 13 C 20.15625 16.613281 17.480469 19.574219 14 20.0625 L 14 18 L 7.59375 22 L 14 26 L 14 23.75 C 19.496094 23.238281 23.8125 18.628906 23.8125 13 C 23.8125 10.066406 22.648438 7.417969 20.75 5.46875 Z"></path>
		</svg>
	);
}

const COMMAND_KEYWORDS = [ 'updatebee', 'plugins', 'themes', 'core' ];

type MessagePayload = {
	message?: string;
};

type NoticeState = {
	isVisible: boolean;
	message: string;
	type: 'success' | 'error';
};

type PaletteAction = {
	action: string;
	nonce: string;
	progressLabel: string;
	fallbackMessage: string;
	errorMessage: string;
	close: () => void;
};

const INITIAL_NOTICE: NoticeState = {
	isVisible: false,
	message: '',
	type: 'success',
};

/**
 * Register update commands, show progress, then a snackbar.
 *
 * @return Progress modal and snackbar notice.
 */
const UpdateCommands = () => {
	const [ notice, setNotice ] = useState< NoticeState >( INITIAL_NOTICE );
	const [ progressLabel, setProgressLabel ] = useState< string | null >(
		null
	);
	const isRunning = useRef( false );

	const showNotice = useCallback(
		( message: string, type: NoticeState[ 'type' ] ) => {
			setNotice( {
				isVisible: true,
				message,
				type,
			} );
		},
		[]
	);

	const handleClose = useCallback( () => {
		setNotice( ( current ) => ( {
			...current,
			isVisible: false,
		} ) );
	}, [] );

	/**
	 * Ignore dismiss attempts while a request is still running.
	 */
	const ignoreDismiss = useCallback( () => {}, [] );

	const finishAction = useCallback(
		( message: string, type: NoticeState[ 'type' ] ) => {
			isRunning.current = false;
			setProgressLabel( null );
			showNotice( message, type );
		},
		[ showNotice ]
	);

	const runAction = useCallback(
		( paletteAction: PaletteAction ) => {
			paletteAction.close();

			if ( isRunning.current ) {
				return;
			}

			isRunning.current = true;
			setProgressLabel( paletteAction.progressLabel );

			void sendCommand< MessagePayload >( paletteAction.action, {
				nonce: paletteAction.nonce,
			} )
				.then( ( response ) => {
					finishAction(
						response.data.data?.message ||
							paletteAction.fallbackMessage,
						'success'
					);
				} )
				.catch( () => {
					finishAction( paletteAction.errorMessage, 'error' );
				} );
		},
		[ finishAction ]
	);

	useCommand( {
		name: 'updatebee/refresh-updates',
		label: __( 'Refresh updates', 'updatebee' ),
		searchLabel: __(
			'Refresh plugin, theme, and core updates',
			'updatebee'
		),
		icon: <UpdateIcon />,
		keywords: COMMAND_KEYWORDS,
		callback: ( { close } ) => {
			runAction( {
				action: 'updatebee_refresh_updates',
				nonce: updatebeeCommands.refreshUpdatesNonce,
				progressLabel: __( 'Refreshing updates…', 'updatebee' ),
				fallbackMessage: __(
					'Updates have been refreshed.',
					'updatebee'
				),
				errorMessage: __(
					'Updates could not be refreshed.',
					'updatebee'
				),
				close,
			} );
		},
	} );

	useCommand( {
		name: 'updatebee/force-updates',
		label: __( 'Force updates', 'updatebee' ),
		searchLabel: __( 'Force plugin, theme, and core updates', 'updatebee' ),
		icon: <UpdateIcon />,
		keywords: COMMAND_KEYWORDS,
		callback: ( { close } ) => {
			runAction( {
				action: 'updatebee_force_updates',
				nonce: updatebeeCommands.forceUpdatesNonce,
				progressLabel: __( 'Forcing updates…', 'updatebee' ),
				fallbackMessage: __( 'Force updates completed.', 'updatebee' ),
				errorMessage: __(
					'Force updates could not be completed.',
					'updatebee'
				),
				close,
			} );
		},
	} );

	return (
		<>
			{ progressLabel && (
				<Modal
					className="updatebee-command-progress-modal"
					overlayClassName="updatebee-command-progress-overlay"
					title={ progressLabel }
					size="small"
					isDismissible={ false }
					shouldCloseOnClickOutside={ false }
					shouldCloseOnEsc={ false }
					onRequestClose={ ignoreDismiss }
				>
					<div className="updatebee-command-progress">
						<ReactSpinner1
							size={ 36 }
							speedMultiplier={ 1.3 }
							color="currentColor"
							aria-hidden
						/>
					</div>
				</Modal>
			) }
			<Snackbar
				isVisible={ notice.isVisible }
				message={ notice.message }
				type={ notice.type }
				onClose={ handleClose }
			/>
		</>
	);
};

/**
 * Mount the command registration once the admin document is available.
 */
const mountUpdateCommands = (): void => {
	if ( document.getElementById( 'updatebee-command-notices' ) ) {
		return;
	}

	const rootElement = document.createElement( 'div' );
	rootElement.id = 'updatebee-command-notices';
	document.body.appendChild( rootElement );

	createRoot( rootElement ).render(
		<StrictMode>
			<UpdateCommands />
		</StrictMode>
	);
};

if ( document.body ) {
	mountUpdateCommands();
} else {
	window.addEventListener( 'DOMContentLoaded', mountUpdateCommands );
}

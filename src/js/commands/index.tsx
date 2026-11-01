/**
 * Admin command palette actions for refreshing and forcing updates.
 */
import '../../scss/commands.scss';
import { useCommand } from '@wordpress/commands';
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

const COMMAND_KEYWORDS = [ 'updatebee', 'plugins', 'themes', 'core' ];

type MessagePayload = {
	message?: string;
};

type NoticeState = {
	isVisible: boolean;
	message: string;
	type: 'success' | 'error';
};

const INITIAL_NOTICE: NoticeState = {
	isVisible: false,
	message: '',
	type: 'success',
};

/**
 * Register update commands and show their results in a snackbar.
 *
 * @return Snackbar notice, when one is visible.
 */
const UpdateCommands = () => {
	const [ notice, setNotice ] = useState< NoticeState >( INITIAL_NOTICE );
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

	const runAction = useCallback(
		(
			action: string,
			nonce: string,
			fallbackMessage: string,
			errorMessage: string,
			close: () => void
		) => {
			close();

			if ( isRunning.current ) {
				return;
			}

			isRunning.current = true;

			void sendCommand< MessagePayload >( action, { nonce } )
				.then( ( response ) => {
					showNotice(
						response.data.data?.message || fallbackMessage,
						'success'
					);
				} )
				.catch( () => {
					showNotice( errorMessage, 'error' );
				} )
				.finally( () => {
					isRunning.current = false;
				} );
		},
		[ showNotice ]
	);

	useCommand( {
		name: 'updatebee/refresh-updates',
		label: __( 'Refresh updates', 'updatebee' ),
		searchLabel: __(
			'Refresh plugin, theme, and core updates',
			'updatebee'
		),
		keywords: COMMAND_KEYWORDS,
		callback: ( { close } ) => {
			runAction(
				'updatebee_refresh_updates',
				updatebeeCommands.refreshUpdatesNonce,
				__( 'Updates have been refreshed.', 'updatebee' ),
				__( 'Updates could not be refreshed.', 'updatebee' ),
				close
			);
		},
	} );

	useCommand( {
		name: 'updatebee/force-updates',
		label: __( 'Force updates', 'updatebee' ),
		searchLabel: __( 'Force plugin, theme, and core updates', 'updatebee' ),
		keywords: COMMAND_KEYWORDS,
		callback: ( { close } ) => {
			runAction(
				'updatebee_force_updates',
				updatebeeCommands.forceUpdatesNonce,
				__( 'Force updates completed.', 'updatebee' ),
				__( 'Force updates could not be completed.', 'updatebee' ),
				close
			);
		},
	} );

	return (
		<Snackbar
			isVisible={ notice.isVisible }
			message={ notice.message }
			type={ notice.type }
			onClose={ handleClose }
		/>
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

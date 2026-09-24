/**
 * Copyable full crontab line block for Advanced schedule hints.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { copy as copyIcon } from '@wordpress/icons';
import { IconButton } from '@wordpress/ui';
import type { CronCrontabCommand } from './cron-types';

type CronCrontabCommandBlockProps = CronCrontabCommand & {
	id: string;
};

/**
 * Copy text with clipboard API or a textarea fallback.
 *
 * @param text Value to copy.
 */
const copyText = ( text: string ): boolean => {
	if ( '' === text ) {
		return false;
	}

	if ( navigator.clipboard?.writeText ) {
		void navigator.clipboard.writeText( text );
		return true;
	}

	const textarea = document.createElement( 'textarea' );
	textarea.value = text;
	textarea.setAttribute( 'readonly', '' );
	textarea.style.position = 'fixed';
	textarea.style.left = '-9999px';
	document.body.appendChild( textarea );
	textarea.select();

	let didCopy = false;
	try {
		didCopy = document.execCommand( 'copy' );
	} catch {
		didCopy = false;
	}

	document.body.removeChild( textarea );
	return didCopy;
};

/**
 * Label + monospace crontab line + copy control.
 *
 * @param props Command label, line, and element id.
 */
const CronCrontabCommandBlock = ( props: CronCrontabCommandBlockProps ) => {
	const { id, label, command } = props;
	const [ copied, setCopied ] = useState( false );

	const handleCopy = () => {
		if ( ! copyText( command ) ) {
			return;
		}

		setCopied( true );
		window.setTimeout( () => {
			setCopied( false );
		}, 2000 );
	};

	return (
		<div className="updatebee-cron-crontab-block">
			<div
				id={ `${ id }-label` }
				className="updatebee-cron-crontab-block-label"
			>
				{ label }
			</div>
			<div
				className="updatebee-cron-crontab-code"
				aria-labelledby={ `${ id }-label` }
			>
				<pre className="updatebee-cron-crontab-code-line">
					<code>{ command }</code>
				</pre>
				<div className="updatebee-cron-crontab-code-footer">
					<span
						className="updatebee-cron-crontab-copy-status"
						aria-live="polite"
					>
						{ copied ? __( 'Copied', 'updatebee' ) : '' }
					</span>
					<IconButton
						className="updatebee-cron-crontab-copy"
						variant="minimal"
						size="small"
						icon={ copyIcon }
						label={
							copied
								? __( 'Copied', 'updatebee' )
								: __( 'Copy crontab line', 'updatebee' )
						}
						onClick={ handleCopy }
					/>
				</div>
			</div>
		</div>
	);
};

export default CronCrontabCommandBlock;

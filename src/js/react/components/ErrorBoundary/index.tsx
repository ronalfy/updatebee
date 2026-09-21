/**
 * Catch render errors so a crashed subtree does not blank the admin view.
 */
import { Button } from '@wordpress/components';
import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';

export type ErrorFallbackProps = {
	heading?: string;
	message?: string;
	onRetry?: () => void;
};

type ErrorBoundaryProps = ErrorFallbackProps & {
	children: ReactNode;
	fallback?: ReactNode;
};

type ErrorBoundaryState = {
	hasError: boolean;
};

/**
 * Panel-styled fallback used by the error boundary and async load failures.
 *
 * @param props Fallback copy and retry handler.
 * @return Fallback markup.
 */
export const ErrorFallback = ( props: ErrorFallbackProps ) => {
	const {
		heading = __( 'This view could not be displayed.', 'updatebee' ),
		message = __(
			'An unexpected error occurred. Try again or reload the page.',
			'updatebee'
		),
		onRetry,
	} = props;

	return (
		<div className="updatebee-admin-panel-area updatebee-error-fallback">
			<h3 className="updatebee-panel-heading">{ heading }</h3>
			<div className="updatebee-panel-row">
				<p className="description">{ message }</p>
			</div>
			{ onRetry && (
				<div className="updatebee-error-fallback__actions">
					<Button variant="secondary" onClick={ onRetry }>
						{ __( 'Try again', 'updatebee' ) }
					</Button>
				</div>
			) }
		</div>
	);
};

/**
 * Class error boundary. Function components cannot catch render errors.
 */
class ErrorBoundary extends Component<
	ErrorBoundaryProps,
	ErrorBoundaryState
> {
	state: ErrorBoundaryState = {
		hasError: false,
	};

	/**
	 * Mark the boundary as failed when a child throws during render.
	 *
	 * @return Updated state.
	 */
	static getDerivedStateFromError(): ErrorBoundaryState {
		return {
			hasError: true,
		};
	}

	/**
	 * Clear the error so children remount.
	 */
	handleRetry = (): void => {
		this.setState( {
			hasError: false,
		} );
	};

	render() {
		if ( this.state.hasError ) {
			if ( this.props.fallback ) {
				return this.props.fallback;
			}

			return (
				<ErrorFallback
					heading={ this.props.heading }
					message={ this.props.message }
					onRetry={ this.handleRetry }
				/>
			);
		}

		return this.props.children;
	}
}

export default ErrorBoundary;

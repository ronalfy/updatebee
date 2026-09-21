/**
 * Shared React mount helpers for admin SlotFill views.
 */
import { Slot, SlotFillProvider } from '@wordpress/components';
import { createPortal, createRoot, StrictMode } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import ErrorBoundary from '../components/ErrorBoundary';

/**
 * Wrap a view so render errors stay inside the visible admin container.
 *
 * The React root can be a hidden node with portaled children. The boundary
 * must live inside the portal or the fallback would render off-screen.
 *
 * @param children View to protect.
 * @return Protected view.
 */
const withViewBoundary = ( children: ReactNode ): ReactNode => {
	return (
		<ErrorBoundary
			heading={ __( 'This view could not be displayed.', 'updatebee' ) }
			message={ __(
				'An unexpected error occurred. Try again or reload the page.',
				'updatebee'
			) }
		>
			{ children }
		</ErrorBoundary>
	);
};

/**
 * Mount children into a DOM node and optionally portal a footer Slot.
 *
 * @param domId    Target container element ID.
 * @param children React nodes to render in the container.
 */
export const commonFill = ( domId: string, children: ReactNode ): void => {
	const container = document.getElementById( domId );
	const slotContainer = document.getElementById(
		'updatebee-admin-container-slot'
	);
	const view = withViewBoundary( children );

	if ( container ) {
		container.innerHTML = '';

		if (
			slotContainer &&
			slotContainer.parentElement?.contains( container )
		) {
			const commonParent = slotContainer.parentElement;
			const rootContainer = document.createElement( 'div' );
			rootContainer.style.display = 'none';
			commonParent.appendChild( rootContainer );

			const root = createRoot( rootContainer );
			root.render(
				<StrictMode>
					<SlotFillProvider>
						{ createPortal( view, container ) }
						{ createPortal(
							<Slot name="updatebeeFooter" />,
							slotContainer
						) }
					</SlotFillProvider>
				</StrictMode>
			);
		} else {
			const root = createRoot( container );
			root.render(
				<StrictMode>
					<SlotFillProvider>{ view }</SlotFillProvider>
				</StrictMode>
			);
		}
	}
};

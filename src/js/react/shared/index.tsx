/**
 * Shared React mount helpers for admin SlotFill views.
 */
import { Slot, SlotFillProvider } from '@wordpress/components';
import { createPortal, createRoot, StrictMode } from '@wordpress/element';
import type { ReactNode } from 'react';

/**
 * Mount children into a DOM node and optionally portal a footer Slot.
 *
 * @param domId    Target container element ID.
 * @param children React nodes to render in the container.
 */
export const commonFill = ( domId: string, children: ReactNode ): void => {
	const container = document.getElementById( domId );
	const slotContainer = document.getElementById( 'updatebee-admin-container-slot' );

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
						{ createPortal( children, container ) }
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
					<SlotFillProvider>{ children }</SlotFillProvider>
				</StrictMode>
			);
		}
	}
};

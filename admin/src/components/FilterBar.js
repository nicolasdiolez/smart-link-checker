/**
 * FilterBar — Quick-access filter buttons above the links table.
 *
 * @package
 * @since   1.0.0
 */

import { Button, ButtonGroup } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const QUICK_FILTERS = [
	{ key: '', label: __( 'All', 'smart-link-checker' ) },
	{ key: 'broken', label: __( 'Broken', 'smart-link-checker' ) },
	{ key: 'redirect', label: __( 'Redirects', 'smart-link-checker' ) },
	{ key: 'ok', label: __( 'OK', 'smart-link-checker' ) },
	{ key: 'pending', label: __( 'Pending', 'smart-link-checker' ) },
];

const FilterBar = ( { currentStatus, onStatusChange } ) => {
	return (
		<div className="flc-filter-bar">
			<ButtonGroup>
				{ QUICK_FILTERS.map( ( filter ) => (
					<Button
						key={ filter.key }
						variant={
							currentStatus === filter.key
								? 'primary'
								: 'secondary'
						}
						onClick={ () => onStatusChange( filter.key ) }
					>
						{ filter.label }
					</Button>
				) ) }
			</ButtonGroup>
		</div>
	);
};

export default FilterBar;

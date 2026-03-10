/**
 * Dashboard — Summary cards and scan controls.
 *
 * @package
 * @since   1.0.0
 */

import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store';
import ScanPanel from './ScanPanel';

const Dashboard = () => {
	const scanStatus = useSelect(
		( select ) => select( STORE_NAME ).getScanStatus(),
		[]
	);

	const stats = useSelect(
		( select ) => select( STORE_NAME ).getStats(),
		[]
	);

	const overviewCards = [
		{
			key: 'total',
			label: __( 'Total Links', 'flavor-link-checker' ),
			value: scanStatus?.total_links ?? '—',
			className: '',
		},
		{
			key: 'broken',
			label: __( 'Broken', 'flavor-link-checker' ),
			value: scanStatus?.broken_count ?? '—',
			className: 'flc-summary-card--broken',
		},
		{
			key: 'redirects',
			label: __( 'Redirects', 'flavor-link-checker' ),
			value: scanStatus?.redirect_count ?? '—',
			className: 'flc-summary-card--redirect',
		},
		{
			key: 'checked',
			label: __( 'Checked', 'flavor-link-checker' ),
			value: scanStatus?.checked_links ?? '—',
			className: '',
		},
	];

	const byCategory = stats?.byCategory;
	const typeCards = byCategory
		? [
				{
					key: 'internal',
					label: __( 'Internal', 'flavor-link-checker' ),
					value: byCategory.internal_count ?? 0,
					className: 'flc-summary-card--internal',
				},
				{
					key: 'external',
					label: __( 'External', 'flavor-link-checker' ),
					value: byCategory.external_count ?? 0,
					className: 'flc-summary-card--external',
				},
				{
					key: 'affiliate',
					label: __( 'Affiliate', 'flavor-link-checker' ),
					value: byCategory.affiliate_count ?? 0,
					className: 'flc-summary-card--affiliate',
				},
				{
					key: 'cloaked',
					label: __( 'Cloaked', 'flavor-link-checker' ),
					value: byCategory.cloaked_count ?? 0,
					className: 'flc-summary-card--cloaked',
				},
		  ]
		: null;

	const byNetwork = stats?.byNetwork;
	const networkCards = byNetwork
		? Object.entries( byNetwork ).map( ( [ network, count ] ) => ( {
				key: network,
				label: network.charAt( 0 ).toUpperCase() + network.slice( 1 ),
				value: count,
				className: 'flc-summary-card--affiliate',
		  } ) )
		: null;

	const redirectCards = byCategory
		? [
				{
					key: 'single-redirect',
					label: __( 'Single (1 hop)', 'flavor-link-checker' ),
					value: byCategory.single_redirect_count ?? 0,
					className: 'flc-summary-card--redirect',
				},
				{
					key: 'chain-redirect',
					label: __( 'Chains (2+)', 'flavor-link-checker' ),
					value: byCategory.chain_redirect_count ?? 0,
					className: 'flc-summary-card--redirect',
				},
				{
					key: 'loop-redirect',
					label: __( 'Loops', 'flavor-link-checker' ),
					value: byCategory.loop_count ?? 0,
					className: 'flc-summary-card--broken',
				},
		  ]
		: null;

	return (
		<div className="flc-dashboard">
			<ScanPanel />

			<div className="flc-summary-cards">
				{ overviewCards.map( ( card ) => (
					<div
						key={ card.key }
						className={ `flc-summary-card ${ card.className }` }
					>
						<span className="flc-summary-card__value">
							{ card.value }
						</span>
						<span className="flc-summary-card__label">
							{ card.label }
						</span>
					</div>
				) ) }
			</div>

			{ typeCards && (
				<div className="flc-dashboard__section">
					<h3>{ __( 'Link Types', 'flavor-link-checker' ) }</h3>
					<div className="flc-summary-cards">
						{ typeCards.map( ( card ) => (
							<div
								key={ card.key }
								className={ `flc-summary-card ${ card.className }` }
							>
								<span className="flc-summary-card__value">
									{ card.value }
								</span>
								<span className="flc-summary-card__label">
									{ card.label }
								</span>
							</div>
						) ) }
					</div>
				</div>
			) }

			{ networkCards && networkCards.length > 0 && (
				<div className="flc-dashboard__section">
					<h3>
						{ __( 'Affiliate Networks', 'flavor-link-checker' ) }
					</h3>
					<div className="flc-summary-cards">
						{ networkCards.map( ( card ) => (
							<div
								key={ card.key }
								className={ `flc-summary-card ${ card.className }` }
							>
								<span className="flc-summary-card__value">
									{ card.value }
								</span>
								<span className="flc-summary-card__label">
									{ card.label }
								</span>
							</div>
						) ) }
					</div>
				</div>
			) }

			{ redirectCards && (
				<div className="flc-dashboard__section">
					<h3>{ __( 'Redirections', 'flavor-link-checker' ) }</h3>
					<div className="flc-summary-cards">
						{ redirectCards.map( ( card ) => (
							<div
								key={ card.key }
								className={ `flc-summary-card ${ card.className }` }
							>
								<span className="flc-summary-card__value">
									{ card.value }
								</span>
								<span className="flc-summary-card__label">
									{ card.label }
								</span>
							</div>
						) ) }
					</div>
				</div>
			) }
		</div>
	);
};

export default Dashboard;

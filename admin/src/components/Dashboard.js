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
			label: __( 'Total Links', 'sentinel-link-checker' ),
			value: scanStatus?.total_links ?? stats?.byCategory?.total ?? '—',
			className: '',
		},
		{
			key: 'ok',
			label: __( 'OK Links', 'sentinel-link-checker' ),
			value: scanStatus?.ok_count ?? stats?.byCategory?.ok_count ?? '—',
			className: 'flc-summary-card--ok',
		},
		{
			key: 'redirects',
			label: __( 'Redirects', 'sentinel-link-checker' ),
			value: scanStatus?.redirect_count ?? stats?.byCategory?.redirect_count ?? '—',
			className: 'flc-summary-card--redirect',
		},
		{
			key: 'broken',
			label: __( 'Broken', 'sentinel-link-checker' ),
			value: scanStatus?.broken_count ?? stats?.byCategory?.broken_count ?? '—',
			className: 'flc-summary-card--broken',
		},
		{
			key: 'errors',
			label: __( 'Errors', 'sentinel-link-checker' ),
			value:
				( scanStatus?.error_count ?? 0 ) +
					( scanStatus?.timeout_count ?? 0 ) +
					( scanStatus?.skipped_count ?? 0 ) ||
				( stats?.byCategory?.error_count ?? 0 ) +
					( stats?.byCategory?.timeout_count ?? 0 ) +
					( stats?.byCategory?.skipped_count ?? 0 ) ||
				0,
			className: 'flc-summary-card--broken',
		},
		{
			key: 'checked',
			label: __( 'Checked', 'sentinel-link-checker' ),
			value: scanStatus?.checked_links ?? (stats?.byCategory ? (stats.byCategory.total - stats.byCategory.pending_count) : '—'),
			className: '',
		},
	];

	const byCategory = stats?.byCategory;
	const typeCards = byCategory
		? [
				{
					key: 'internal',
					label: __( 'Internal', 'sentinel-link-checker' ),
					value: byCategory.internal_count ?? 0,
					className: 'flc-summary-card--internal',
				},
				{
					key: 'external',
					label: __( 'External', 'sentinel-link-checker' ),
					value: byCategory.external_count ?? 0,
					className: 'flc-summary-card--external',
				},
				{
					key: 'affiliate',
					label: __( 'Affiliate', 'sentinel-link-checker' ),
					value: byCategory.affiliate_count ?? 0,
					className: 'flc-summary-card--affiliate',
				},
				{
					key: 'cloaked',
					label: __( 'Cloaked', 'sentinel-link-checker' ),
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
					label: __( 'Single (1 hop)', 'sentinel-link-checker' ),
					value: byCategory.single_redirect_count ?? 0,
					className: 'flc-summary-card--redirect',
				},
				{
					key: 'chain-redirect',
					label: __( 'Chains (2+)', 'sentinel-link-checker' ),
					value: byCategory.chain_redirect_count ?? 0,
					className: 'flc-summary-card--redirect',
				},
				{
					key: 'loop-redirect',
					label: __( 'Loops', 'sentinel-link-checker' ),
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
						aria-label={ `${ card.label }: ${ card.value }` }
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
					<h3>{ __( 'Link Types', 'sentinel-link-checker' ) }</h3>
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
						{ __( 'Affiliate Networks', 'sentinel-link-checker' ) }
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
					<h3>{ __( 'Redirections', 'sentinel-link-checker' ) }</h3>
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

			<div className="flc-dashboard__section flc-dashboard__support">
				<div className="flc-support-card">
					<div className="flc-support-card__content">
						<h3>{ __( 'Support & Feedback', 'sentinel-link-checker' ) }</h3>
						<p>{ __( 'Are we missing a feature? Found a bug? Or just want to say thanks?', 'sentinel-link-checker' ) }</p>
					</div>
					<div className="flc-support-card__actions">
						<a 
							href="https://wordpress.org/support/plugin/sentinel-link-checker/" 
							target="_blank" 
							rel="noopener noreferrer"
							className="button"
						>
							{ __( 'Get Support', 'sentinel-link-checker' ) }
						</a>
						<a 
							href="https://wordpress.org/support/plugin/sentinel-link-checker/reviews/#new-post" 
							target="_blank" 
							rel="noopener noreferrer"
							className="button button-primary"
						>
							{ __( 'Leave a Review', 'sentinel-link-checker' ) }
						</a>
					</div>
				</div>
			</div>
		</div>
	);
};

export default Dashboard;

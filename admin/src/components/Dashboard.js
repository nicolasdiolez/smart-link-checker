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
			label: __( 'Total Links', 'muri-link-tracker' ),
			value: scanStatus?.total_links ?? stats?.byCategory?.total ?? '—',
			className: '',
		},
		{
			key: 'ok',
			label: __( 'OK Links', 'muri-link-tracker' ),
			value: scanStatus?.ok_count ?? stats?.byCategory?.ok_count ?? '—',
			className: 'mltr-summary-card--ok',
		},
		{
			key: 'redirects',
			label: __( 'Redirects', 'muri-link-tracker' ),
			value: scanStatus?.redirect_count ?? stats?.byCategory?.redirect_count ?? '—',
			className: 'mltr-summary-card--redirect',
		},
		{
			key: 'broken',
			label: __( 'Broken', 'muri-link-tracker' ),
			value: scanStatus?.broken_count ?? stats?.byCategory?.broken_count ?? '—',
			className: 'mltr-summary-card--broken',
		},
		{
			key: 'errors',
			label: __( 'Errors', 'muri-link-tracker' ),
			value:
				( scanStatus?.error_count ?? 0 ) +
					( scanStatus?.timeout_count ?? 0 ) +
					( scanStatus?.skipped_count ?? 0 ) ||
				( stats?.byCategory?.error_count ?? 0 ) +
					( stats?.byCategory?.timeout_count ?? 0 ) +
					( stats?.byCategory?.skipped_count ?? 0 ) ||
				0,
			className: 'mltr-summary-card--broken',
		},
		{
			key: 'checked',
			label: __( 'Checked', 'muri-link-tracker' ),
			value: scanStatus?.checked_links ?? (stats?.byCategory ? (stats.byCategory.total - stats.byCategory.pending_count) : '—'),
			className: '',
		},
	];

	const byCategory = stats?.byCategory;
	const typeCards = byCategory
		? [
				{
					key: 'internal',
					label: __( 'Internal', 'muri-link-tracker' ),
					value: byCategory.internal_count ?? 0,
					className: 'mltr-summary-card--internal',
				},
				{
					key: 'external',
					label: __( 'External', 'muri-link-tracker' ),
					value: byCategory.external_count ?? 0,
					className: 'mltr-summary-card--external',
				},
				{
					key: 'affiliate',
					label: __( 'Affiliate', 'muri-link-tracker' ),
					value: byCategory.affiliate_count ?? 0,
					className: 'mltr-summary-card--affiliate',
				},
				{
					key: 'cloaked',
					label: __( 'Cloaked', 'muri-link-tracker' ),
					value: byCategory.cloaked_count ?? 0,
					className: 'mltr-summary-card--cloaked',
				},
		  ]
		: null;

	const byNetwork = stats?.byNetwork;
	const networkCards = byNetwork
		? Object.entries( byNetwork ).map( ( [ network, count ] ) => ( {
				key: network,
				label: network.charAt( 0 ).toUpperCase() + network.slice( 1 ),
				value: count,
				className: 'mltr-summary-card--affiliate',
		  } ) )
		: null;

	const redirectCards = byCategory
		? [
				{
					key: 'single-redirect',
					label: __( 'Single (1 hop)', 'muri-link-tracker' ),
					value: byCategory.single_redirect_count ?? 0,
					className: 'mltr-summary-card--redirect',
				},
				{
					key: 'chain-redirect',
					label: __( 'Chains (2+)', 'muri-link-tracker' ),
					value: byCategory.chain_redirect_count ?? 0,
					className: 'mltr-summary-card--redirect',
				},
				{
					key: 'loop-redirect',
					label: __( 'Loops', 'muri-link-tracker' ),
					value: byCategory.loop_count ?? 0,
					className: 'mltr-summary-card--broken',
				},
		  ]
		: null;

	return (
		<div className="mltr-dashboard">
			<ScanPanel />

			<div className="mltr-summary-cards">
				{ overviewCards.map( ( card ) => (
					<div
						key={ card.key }
						className={ `mltr-summary-card ${ card.className }` }
						aria-label={ `${ card.label }: ${ card.value }` }
					>
						<span className="mltr-summary-card__value">
							{ card.value }
						</span>
						<span className="mltr-summary-card__label">
							{ card.label }
						</span>
					</div>
				) ) }
			</div>

			{ typeCards && (
				<div className="mltr-dashboard__section">
					<h3>{ __( 'Link Types', 'muri-link-tracker' ) }</h3>
					<div className="mltr-summary-cards">
						{ typeCards.map( ( card ) => (
							<div
								key={ card.key }
								className={ `mltr-summary-card ${ card.className }` }
							>
								<span className="mltr-summary-card__value">
									{ card.value }
								</span>
								<span className="mltr-summary-card__label">
									{ card.label }
								</span>
							</div>
						) ) }
					</div>
				</div>
			) }

			{ networkCards && networkCards.length > 0 && (
				<div className="mltr-dashboard__section">
					<h3>
						{ __( 'Affiliate Networks', 'muri-link-tracker' ) }
					</h3>
					<div className="mltr-summary-cards">
						{ networkCards.map( ( card ) => (
							<div
								key={ card.key }
								className={ `mltr-summary-card ${ card.className }` }
							>
								<span className="mltr-summary-card__value">
									{ card.value }
								</span>
								<span className="mltr-summary-card__label">
									{ card.label }
								</span>
							</div>
						) ) }
					</div>
				</div>
			) }

			{ redirectCards && (
				<div className="mltr-dashboard__section">
					<h3>{ __( 'Redirections', 'muri-link-tracker' ) }</h3>
					<div className="mltr-summary-cards">
						{ redirectCards.map( ( card ) => (
							<div
								key={ card.key }
								className={ `mltr-summary-card ${ card.className }` }
							>
								<span className="mltr-summary-card__value">
									{ card.value }
								</span>
								<span className="mltr-summary-card__label">
									{ card.label }
								</span>
							</div>
						) ) }
					</div>
				</div>
			) }

			<div className="mltr-dashboard__section mltr-dashboard__support">
				<div className="mltr-support-card">
					<div className="mltr-support-card__content">
						<h3>{ __( 'Support & Feedback', 'muri-link-tracker' ) }</h3>
						<p>{ __( 'Are we missing a feature? Found a bug? Or just want to say thanks?', 'muri-link-tracker' ) }</p>
					</div>
					<div className="mltr-support-card__actions">
						<a 
							href="https://wordpress.org/support/plugin/muri-link-tracker/" 
							target="_blank" 
							rel="noopener noreferrer"
							className="button"
						>
							{ __( 'Get Support', 'muri-link-tracker' ) }
						</a>
						<a 
							href="https://wordpress.org/support/plugin/muri-link-tracker/reviews/#new-post" 
							target="_blank" 
							rel="noopener noreferrer"
							className="button button-primary"
						>
							{ __( 'Leave a Review', 'muri-link-tracker' ) }
						</a>
					</div>
				</div>
			</div>
		</div>
	);
};

export default Dashboard;

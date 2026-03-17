/**
 * ScanPanel — Scan controls and progress indicator.
 *
 * @package
 * @since   1.0.0
 */

import { useEffect, useCallback, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Button, __experimentalConfirmDialog as ConfirmDialog } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { STORE_NAME } from '../store';

const POLL_INTERVAL = 5000;

const ScanPanel = () => {
	const scanStatus = useSelect(
		(select) => select(STORE_NAME).getScanStatus(),
		[]
	);
	const scanLoading = useSelect(
		(select) => select(STORE_NAME).isLoading('scan'),
		[]
	);
	const { startScan, cancelScan, resumeScan, resetScan, fetchScanStatus, refreshData } =
		useDispatch(STORE_NAME);

	const isRunning = scanStatus?.status === 'running';

	// Poll scan status while running.
	useEffect(() => {
		if (!isRunning) {
			return;
		}
		const id = setTimeout(() => fetchScanStatus(), POLL_INTERVAL);
		return () => clearTimeout(id);
	}, [isRunning, scanStatus, fetchScanStatus]);
 
	// Refresh everything when scan completes.
	useEffect(() => {
		if (scanStatus?.status === 'complete') {
			refreshData();
		}
	}, [scanStatus?.status, refreshData]);

	const handleStart = useCallback(
		(type) => () => startScan(type),
		[startScan]
	);

	const [ isResetConfirmOpen, setResetConfirmOpen ] = useState( false );

	const handleReset = () => {
		setResetConfirmOpen( true );
	};

	const handleResetConfirm = () => {
		setResetConfirmOpen( false );
		resetScan();
	};

	const handleResetCancel = () => {
		setResetConfirmOpen( false );
	};

	const phase = scanStatus?.phase ?? 'scanning';

	let progress = 0;
	if (isRunning) {
		if (phase === 'checking' && scanStatus.total_links > 0) {
			progress = Math.round(
				(scanStatus.checked_links / scanStatus.total_links) * 100
			);
		} else if (scanStatus.total_posts > 0) {
			progress = Math.round(
				(scanStatus.scanned_posts / scanStatus.total_posts) * 100
			);
		}
	}

	return (
		<div className="flc-scan-panel">
			<div className="flc-scan-panel__controls">
				{!isRunning ? (
					<>
						<Button
							variant="primary"
							onClick={handleStart('full')}
							isBusy={scanLoading}
							disabled={scanLoading}
						>
							{__('Full Scan', 'smart-link-checker')}
						</Button>
						<Button
							variant="secondary"
							onClick={handleStart('delta')}
							isBusy={scanLoading}
							disabled={scanLoading}
							className="flc-scan-panel__delta-btn"
						>
							{__('Delta Scan', 'smart-link-checker')}
						</Button>
						{scanStatus?.status === 'cancelled' && (
							<Button
								variant="primary"
								onClick={resumeScan}
								isBusy={scanLoading}
								disabled={scanLoading}
							>
								{__('Resume Scan', 'smart-link-checker')}
							</Button>
						)}
						<Button
							variant="secondary"
							isDestructive
							onClick={handleReset}
							isBusy={scanLoading}
							disabled={scanLoading}
						>
							{__('Reset Data', 'smart-link-checker')}
						</Button>
					</>
				) : (
					<Button
						variant="secondary"
						isDestructive
						onClick={cancelScan}
					>
						{__('Cancel Scan', 'smart-link-checker')}
					</Button>
				)}
			</div>

			{isRunning && (
				<div className="flc-scan-panel__progress" aria-label={ __( 'Scan progress details', 'smart-link-checker' ) }>
					<div
						className="flc-progress-bar"
						role="progressbar"
						aria-valuenow={ progress }
						aria-valuemin={ 0 }
						aria-valuemax={ 100 }
						aria-label={ __( 'Scan progress', 'smart-link-checker' ) }
					>
						<div
							className="flc-progress-bar__fill"
							style={{ width: `${progress}%` }}
						/>
					</div>
					<span className="flc-scan-panel__status">
						{phase === 'checking'
							? sprintf(
								/* translators: 1: checked links, 2: total links, 3: progress percentage. */
								__(
									'Checking links: %1$d / %2$d (%3$d%%)',
									'smart-link-checker'
								),
								scanStatus.checked_links,
								scanStatus.total_links,
								progress
							)
							: sprintf(
								/* translators: 1: scanned posts, 2: total posts, 3: progress percentage. */
								__(
									'Scanning posts: %1$d / %2$d (%3$d%%)',
									'smart-link-checker'
								),
								scanStatus.scanned_posts,
								scanStatus.total_posts,
								progress
							)}
					</span>
				</div>
			)}

			{scanStatus?.status === 'complete' && (
				<p className="flc-scan-panel__complete">
					{sprintf(
						/* translators: 1: total links, 2: ok count, 3: broken count, 4: redirects, 5: errors. */
						__(
							'Scan complete — %1$d links checked: %2$d OK, %3$d redirects, %4$d broken, %5$d errors.',
							'smart-link-checker'
						),
						scanStatus.total_links || 0,
						scanStatus.ok_count || 0,
						scanStatus.redirect_count || 0,
						scanStatus.broken_count || 0,
						( scanStatus.error_count || 0 ) +
							( scanStatus.timeout_count || 0 ) +
							( scanStatus.skipped_count || 0 )
					)}
					<a
						href="https://wordpress.org/support/plugin/smart-link-checker/reviews/#new-post"
						target="_blank"
						rel="noopener noreferrer"
						className="flc-scan-panel__review-link"
					>
						{__( 'Enjoying the plugin? Please leave a 5-star review!', 'smart-link-checker' )}
					</a>
				</p>
			)}

			{!isRunning && (
				<p className="flc-scan-panel__help">
					{__('Delta Scan only checks posts modified since the last successful scan.', 'smart-link-checker')}
				</p>
			)}
		{ isResetConfirmOpen && (
				<ConfirmDialog
					onConfirm={ handleResetConfirm }
					onCancel={ handleResetCancel }
				>
					{ __( 'Are you sure you want to reset all scan data? This will clear all links found so far.', 'smart-link-checker' ) }
				</ConfirmDialog>
			) }
		</div>
	);
};

export default ScanPanel;

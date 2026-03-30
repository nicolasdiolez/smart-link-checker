/**
 * SettingsPanel — Plugin settings form.
 *
 * @package
 * @since   1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	Button,
	TextControl,
	TextareaControl,
	ToggleControl,
	Spinner,
	Panel,
	PanelBody,
	PanelRow,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store';

const SettingsPanel = () => {
	const settings = useSelect(
		(select) => select(STORE_NAME).getSettings(),
		[]
	);
	const loading = useSelect(
		(select) => select(STORE_NAME).isLoading('settings'),
		[]
	);
	const { updateSettings } = useDispatch(STORE_NAME);

	const [form, setForm] = useState(null);

	// Populate form once settings are loaded.
	useEffect(() => {
		if (settings && !form) {
			setForm({ ...settings });
		}
	}, [settings, form]);

	const updateField = (key, value) => {
		setForm((prev) => ({ ...prev, [key]: value }));
	};

	const handleSave = () => {
		updateSettings(form);
	};

	if (!form) {
		return <Spinner />;
	}

	return (
		<div className="slkc-settings">
			<Panel>
				<PanelBody
					title={__('Scanning', 'sentinel-link-checker')}
					initialOpen
				>
					<PanelRow>
						<TextControl
							label={__(
								'Post types to scan',
								'sentinel-link-checker'
							)}
							value={(form.scan_post_types || []).join(
								', '
							)}
							onChange={(val) =>
								updateField(
									'scan_post_types',
									val
										.split(',')
										.map((s) => s.trim())
										.filter(Boolean)
								)
							}
							help={__(
								'Comma-separated list: post, page, product…',
								'sentinel-link-checker'
							)}
							__nextHasNoMarginBottom
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label={__('Batch size', 'sentinel-link-checker')}
							type="number"
							min={10}
							max={200}
							value={form.batch_size}
							onChange={(val) =>
								updateField(
									'batch_size',
									parseInt(val, 10) || 50
								)
							}
							__nextHasNoMarginBottom
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label={__(
								'Scan custom fields',
								'sentinel-link-checker'
							)}
							checked={!!form.scan_custom_fields}
							onChange={(val) =>
								updateField('scan_custom_fields', val)
							}
							__nextHasNoMarginBottom
						/>
					</PanelRow>
				</PanelBody>

				<PanelBody
					title={__('HTTP Checks', 'sentinel-link-checker')}
					initialOpen
				>
					<PanelRow>
						<TextControl
							label={__(
								'Timeout (seconds)',
								'sentinel-link-checker'
							)}
							type="number"
							min={5}
							max={60}
							value={form.check_timeout}
							onChange={(val) =>
								updateField(
									'check_timeout',
									parseInt(val, 10) || 15
								)
							}
							__nextHasNoMarginBottom
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label={__(
								'Delay between requests (ms)',
								'sentinel-link-checker'
							)}
							type="number"
							min={0}
							max={5000}
							value={form.http_request_delay}
							onChange={(val) =>
								updateField(
									'http_request_delay',
									parseInt(val, 10) || 0
								)
							}
							__nextHasNoMarginBottom
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label={__(
								'Recheck interval (days)',
								'sentinel-link-checker'
							)}
							type="number"
							min={1}
							max={30}
							value={form.recheck_interval}
							onChange={(val) =>
								updateField(
									'recheck_interval',
									parseInt(val, 10) || 7
								)
							}
							__nextHasNoMarginBottom
						/>
					</PanelRow>
				</PanelBody>

				<PanelBody
					title={__('Exclusions', 'sentinel-link-checker')}
					initialOpen={false}
				>
					<PanelRow>
						<ToggleControl
							label={__(
								'Exclude media files',
								'sentinel-link-checker'
							)}
							checked={!!form.exclude_media}
							onChange={(val) =>
								updateField('exclude_media', val)
							}
							help={__(
								'Skip images, videos, and document files (PDF, etc.) during scanning.',
								'sentinel-link-checker'
							)}
							__nextHasNoMarginBottom
						/>
					</PanelRow>
					<PanelRow>
						<TextareaControl
							label={__(
								'Excluded URLs',
								'sentinel-link-checker'
							)}
							value={(form.excluded_urls || []).join('\n')}
							onChange={(val) =>
								updateField(
									'excluded_urls',
									val
										.split('\n')
										.map((s) => s.trim())
										.filter(Boolean)
								)
							}
							help={__(
								'One URL pattern per line.',
								'sentinel-link-checker'
							)}
							rows={4}
							__nextHasNoMarginBottom
						/>
					</PanelRow>
				</PanelBody>
			</Panel>

			<div className="slkc-settings__actions">
				<Button
					variant="primary"
					onClick={handleSave}
					isBusy={loading}
					disabled={loading}
				>
					{__('Save Settings', 'sentinel-link-checker')}
				</Button>
			</div>
		</div>
	);
};

export default SettingsPanel;

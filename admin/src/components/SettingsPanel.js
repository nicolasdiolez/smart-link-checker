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
		<div className="flc-settings">
			<Panel>
				<PanelBody
					title={__('Scanning', 'flavor-link-checker')}
					initialOpen
				>
					<PanelRow>
						<TextControl
							label={__(
								'Post types to scan',
								'flavor-link-checker'
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
								'flavor-link-checker'
							)}
							__nextHasNoMarginBottom
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label={__('Batch size', 'flavor-link-checker')}
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
								'flavor-link-checker'
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
					title={__('HTTP Checks', 'flavor-link-checker')}
					initialOpen
				>
					<PanelRow>
						<TextControl
							label={__(
								'Timeout (seconds)',
								'flavor-link-checker'
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
								'flavor-link-checker'
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
								'flavor-link-checker'
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
					title={__('Exclusions', 'flavor-link-checker')}
					initialOpen={false}
				>
					<PanelRow>
						<ToggleControl
							label={__(
								'Exclude media files',
								'flavor-link-checker'
							)}
							checked={!!form.exclude_media}
							onChange={(val) =>
								updateField('exclude_media', val)
							}
							help={__(
								'Skip images, videos, and document files (PDF, etc.) during scanning.',
								'flavor-link-checker'
							)}
							__nextHasNoMarginBottom
						/>
					</PanelRow>
					<PanelRow>
						<TextareaControl
							label={__(
								'Excluded URLs',
								'flavor-link-checker'
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
								'flavor-link-checker'
							)}
							rows={4}
							__nextHasNoMarginBottom
						/>
					</PanelRow>
				</PanelBody>
			</Panel>

			<div className="flc-settings__actions">
				<Button
					variant="primary"
					onClick={handleSave}
					isBusy={loading}
					disabled={loading}
				>
					{__('Save Settings', 'flavor-link-checker')}
				</Button>
			</div>
		</div>
	);
};

export default SettingsPanel;

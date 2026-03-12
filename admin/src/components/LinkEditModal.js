/**
 * LinkEditModal — Edit a link's URL and rel attributes.
 *
 * @package
 * @since   1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Modal, Button, TextControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store';

const LinkEditModal = ( { linkId, onClose } ) => {
	const currentLink = useSelect(
		( select ) => select( STORE_NAME ).getCurrentLink(),
		[]
	);
	const loading = useSelect(
		( select ) => select( STORE_NAME ).isLoading( 'currentLink' ),
		[]
	);
	const { fetchLink, updateLink, setCurrentLink } = useDispatch( STORE_NAME );

	const [ url, setUrl ] = useState( '' );
	const [ rel, setRel ] = useState( '' );
	const [ saving, setSaving ] = useState( false );

	// Fetch full link details on mount.
	useEffect( () => {
		fetchLink( linkId );
		return () => setCurrentLink( null );
	}, [ linkId, fetchLink, setCurrentLink ] );

	// Populate form when link data arrives.
	useEffect( () => {
		if ( currentLink ) {
			setUrl( currentLink.url || '' );
			// Build rel string from first instance if available.
			if ( currentLink.instances?.length > 0 ) {
				const inst = currentLink.instances[ 0 ];
				const parts = [];
				if ( inst.relNofollow ) {
					parts.push( 'nofollow' );
				}
				if ( inst.relSponsored ) {
					parts.push( 'sponsored' );
				}
				if ( inst.relUgc ) {
					parts.push( 'ugc' );
				}
				setRel( parts.join( ' ' ) );
			}
		}
	}, [ currentLink ] );

	const handleSave = async () => {
		const data = {};
		if ( url !== currentLink.url ) {
			data.url = url;
		}
		if ( rel !== undefined ) {
			data.rel = rel;
		}

		if ( Object.keys( data ).length === 0 ) {
			onClose( false );
			return;
		}

		setSaving( true );
		const result = await updateLink( linkId, data );
		setSaving( false );

		if ( result ) {
			onClose( true );
		}
	};

	return (
		<Modal
			title={ __( 'Edit Link', 'smart-link-checker' ) }
			onRequestClose={ () => onClose( false ) }
			className="flc-edit-modal"
		>
			{ loading && <Spinner /> }
			{ ! loading && currentLink && (
				<>
					<TextControl
						label={ __( 'URL', 'smart-link-checker' ) }
						value={ url }
						onChange={ setUrl }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Rel attribute', 'smart-link-checker' ) }
						value={ rel }
						onChange={ setRel }
						help={ __(
							'e.g. nofollow sponsored',
							'smart-link-checker'
						) }
						__nextHasNoMarginBottom
					/>

					{ currentLink.instances?.length > 0 && (
						<div className="flc-edit-modal__instances">
							<h3>
								{ __( 'Found in:', 'smart-link-checker' ) }
							</h3>
							<ul>
								{ currentLink.instances.map( ( inst ) => (
									<li key={ inst.id }>
										<a
											href={ inst.postEditUrl }
											target="_blank"
											rel="noopener noreferrer"
										>
											{ inst.postTitle }
										</a>
										{ inst.anchorText && (
											<span className="flc-edit-modal__anchor">
												{ ' — "' }
												{ inst.anchorText }
												{ '"' }
											</span>
										) }
									</li>
								) ) }
							</ul>
						</div>
					) }

					<div className="flc-edit-modal__actions">
						<Button
							variant="primary"
							onClick={ handleSave }
							isBusy={ saving }
							disabled={ saving }
						>
							{ __( 'Save', 'smart-link-checker' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => onClose( false ) }
						>
							{ __( 'Cancel', 'smart-link-checker' ) }
						</Button>
					</div>
				</>
			) }
		</Modal>
	);
};

export default LinkEditModal;

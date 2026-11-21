/**
 * Translate the Meta Box field arrays returned by `GET /{servers|sites}/{id}/tabs`
 * into renderable descriptors.
 *
 * This is the parity mechanism for the hard cutover. Every one of the 22 server tabs
 * and 29 site tabs already declares its interface as an array of Meta Box fields; the
 * legacy renderer walked that array and the legacy jQuery client posted a subset of it
 * back. This module performs the same two translations so `TabRenderer` can stay generic:
 *
 *   fields  ->  an ordered list of renderable elements (cards, headings, controls)
 *   button  ->  the `action` plus the `params` the tab handler reads from `$_POST`.
 *
 * The param mapping follows the legacy client exactly
 * (`includes/core/apps/wordpress-app/assets/js/wpcd-wpapp-admin-common.js`):
 * a button declares `data-wpcd-action` (the action slug) and `data-wpcd-fields`
 * (a JSON array of `#element-id` selectors). For each selector the legacy code posted
 * `element.attr('data-wpcd-name') = element.val()`. We resolve the same selector against
 * the field list to recover both the request key and the current value.
 */

/** Meta Box container field that opens or closes a card group. */
const CARD_CONTAINER = 'wpcd_card_container';

/** Field types that produce a label rather than a control. */
const LABEL_TYPES = new Set( [ 'heading', 'divider' ] );

/** Field types whose markup is server-produced HTML. */
const RAW_HTML_TYPES = new Set( [ 'custom_html', 'custom-html', 'html' ] );

/** Field types the browser cannot edit usefully in this client. */
const READONLY_TYPES = new Set( [
	'post',
	'taxonomy',
	'taxonomy_advanced',
	'image',
	'image_advanced',
	'single_image',
] );

/** Field types that produce a file picker; their value is a `File`, never a string. */
const FILE_TYPES = new Set( [ 'file', 'file_upload', 'file_input' ] );

/**
 * Read a field's declared attributes.
 *
 * @param {Object} field Field definition.
 * @return {Object} Attribute map, never null.
 */
export function attributesOf( field ) {
	return field && typeof field.attributes === 'object' && field.attributes !== null
		? field.attributes
		: {};
}

/**
 * A field triggers an action when it carries `data-wpcd-action`, or when its id is the
 * `wpcd_app_action_{slug}` shape that `get_fields_for_tab()` generates.
 *
 * @param {Object} field Field definition.
 * @return {boolean} True for action buttons and action switches.
 */
export function isActionField( field ) {
	return actionSlug( field ) !== '';
}

/**
 * Resolve the action slug a field triggers.
 *
 * @param {Object} field Field definition.
 * @return {string} Action slug, or an empty string when the field is not an action.
 */
export function actionSlug( field ) {
	const attributes = attributesOf( field );

	if ( attributes[ 'data-wpcd-action' ] ) {
		return String( attributes[ 'data-wpcd-action' ] );
	}

	const id = String( field && field.id ? field.id : '' );

	if ( id.startsWith( 'wpcd_app_action_' ) ) {
		return id.slice( 'wpcd_app_action_'.length );
	}

	return '';
}

/**
 * The request key a field's value is posted under.
 *
 * @param {Object} field Field definition.
 * @return {string} Parameter name.
 */
export function paramNameOf( field ) {
	const attributes = attributesOf( field );

	if ( attributes[ 'data-wpcd-name' ] ) {
		return String( attributes[ 'data-wpcd-name' ] );
	}

	/* Fall back to the element id, which is what the legacy renderer used as `name`. */
	const id = String( field && field.id ? field.id : '' );
	return id.startsWith( 'wpcd_app_action_' ) ? id.slice( 'wpcd_app_action_'.length ) : id;
}

/**
 * Index every field by its DOM id so `data-wpcd-fields` selectors can be resolved.
 *
 * @param {Array} fields Flat field list.
 * @return {Map<string, Object>} id -> field.
 */
export function indexById( fields ) {
	const index = new Map();

	( fields || [] ).forEach( ( field ) => {
		if ( field && field.id ) {
			index.set( String( field.id ), field );
		}
	} );

	return index;
}

/**
 * Parse a button's `data-wpcd-fields` selector list.
 *
 * @param {Object} field Action field.
 * @return {string[]} Element ids without the leading `#`.
 */
export function dependencyIds( field ) {
	const raw = attributesOf( field )[ 'data-wpcd-fields' ];

	if ( ! raw ) {
		return [];
	}

	let parsed;

	try {
		parsed = JSON.parse( raw );
	} catch ( cause ) {
		return [];
	}

	if ( ! Array.isArray( parsed ) ) {
		return [];
	}

	return parsed
		.map( ( selector ) => String( selector ).trim().replace( /^#/, '' ) )
		.filter( ( id ) => id !== '' );
}

/**
 * Build the `params` object a tab handler expects from `$_POST['params']`.
 *
 * @param {Object} actionField The button pressed.
 * @param {Map}    index       Result of indexById().
 * @param {Object} values      Current control values, keyed by element id.
 * @return {Object} Parameter map.
 */
export function collectActionParams( actionField, index, values ) {
	const params = {};

	dependencyIds( actionField ).forEach( ( id ) => {
		const dependency = index.get( id );

		if ( ! dependency ) {
			return;
		}

		const key = paramNameOf( dependency );

		if ( ! key ) {
			return;
		}

		params[ key ] = encodeValue( dependency, values[ id ] );
	} );

	return params;
}

/**
 * Normalise a value into what the legacy `.val()` call would have produced.
 *
 * The tab handlers compare against `'on'` / `'off'` for switches
 * (for example `backup.php` checks `'on' === $auto_backup_delete_remotes`), so booleans
 * must not travel as PHP truthy strings.
 *
 * @param {Object} field Field definition.
 * @param {*}      value Current value.
 * @return {string} Wire value.
 */
export function encodeValue( field, value ) {
	const type = String( ( field && field.type ) || 'text' );

	if ( type === 'switch' || type === 'checkbox' ) {
		return value ? 'on' : 'off';
	}

	if ( value === null || value === undefined ) {
		return '';
	}

	if ( typeof value === 'boolean' ) {
		return value ? 'on' : 'off';
	}

	return String( value );
}

/**
 * Seed the control values for a field list.
 *
 * @param {Array} fields Flat field list.
 * @return {Object} Element id -> initial value.
 */
export function initialValues( fields ) {
	const values = {};

	( fields || [] ).forEach( ( field ) => {
		if ( ! field || ! field.id || isActionField( field ) ) {
			return;
		}

		values[ field.id ] = declaredValue( field );
	} );

	return values;
}

/**
 * The value a field arrives with, preferring the live value over the stored default.
 *
 * @param {Object} field Field definition.
 * @return {*} Current value.
 */
export function declaredValue( field ) {
	const attributes = attributesOf( field );
	const candidates = [ attributes.value, field.value, field.std, field.default ];

	for ( const candidate of candidates ) {
		if ( candidate !== undefined && candidate !== null && candidate !== '' ) {
			return candidate;
		}
	}

	return '';
}

/**
 * Describe one field for the renderer.
 *
 * @param {Object} field Field definition.
 * @return {Object} Control descriptor.
 */
export function describeField( field ) {
	const attributes = attributesOf( field );
	const type = String( field.type || 'text' );

	let kind = 'text';

	if ( isActionField( field ) ) {
		kind = 'action';
	} else if ( LABEL_TYPES.has( type ) ) {
		kind = 'label';
	} else if ( RAW_HTML_TYPES.has( type ) ) {
		kind = 'html';
	} else if ( READONLY_TYPES.has( type ) ) {
		kind = 'readonly';
	} else if ( type === 'hidden' ) {
		kind = 'hidden';
	} else if ( type === 'select' || type === 'select_advanced' ) {
		kind = 'select';
	} else if ( type === 'textarea' ) {
		kind = 'textarea';
	} else if ( type === 'switch' || type === 'checkbox' ) {
		kind = 'toggle';
	} else if ( type === 'number' || type === 'range' ) {
		kind = 'number';
	} else if ( type === 'password' ) {
		kind = 'password';
	} else if ( FILE_TYPES.has( type ) ) {
		kind = 'file';
	} else if ( type === 'time' || type === 'date' || type === 'datetime' ) {
		kind = 'temporal';
	}

	return {
		kind,
		type,
		id: String( field.id || '' ),
		label: String( field.name || '' ),
		description: String( field.desc || '' ),
		placeholder: String( field.placeholder || attributes.placeholder || '' ),
		tooltip: String( field.tooltip || '' ),
		maxlength: attributes.maxlength ? Number( attributes.maxlength ) : undefined,
		options: normaliseOptions( field.options ),
		value: declaredValue( field ),
		action: isActionField( field ) ? actionSlug( field ) : '',
		param: paramNameOf( field ),
		confirm: String( attributes[ 'data-wpcd-confirmation-prompt' ] || '' ),
		showLog: Boolean( attributes[ 'data-show-log-console' ] ),
		logMessage: String( attributes[ 'data-initial-console-message' ] || '' ),
		raw: String( field.std || field.value || '' ),
		onLabel: String( field.on_label || 'Enabled' ),
		offLabel: String( field.off_label || 'Disabled' ),
	};
}

/**
 * Normalise Meta Box `options` (either a value=>label map or a list) into pairs.
 *
 * @param {*} options Raw options.
 * @return {Array<{value: string, label: string}>} Options.
 */
function normaliseOptions( options ) {
	if ( ! options || typeof options !== 'object' ) {
		return [];
	}

	if ( Array.isArray( options ) ) {
		return options.map( ( option ) => {
			if ( option && typeof option === 'object' ) {
				return {
					value: String( option.value ?? option.key ?? '' ),
					label: String( option.label ?? option.name ?? option.value ?? '' ),
				};
			}

			return { value: String( option ), label: String( option ) };
		} );
	}

	return Object.entries( options ).map( ( [ value, label ] ) => ( {
		value: String( value ),
		label: typeof label === 'string' ? label : String( value ),
	} ) );
}

const CARD_WIDTH_PATTERN = /wpcd-card-(half|third|two-thirds|full)\b/;

/**
 * Convert a flat field list for one tab into an ordered element list.
 *
 * Card containers become `card-start` / `card-end` markers so the renderer can lay
 * sibling fields out in the same columns the Meta Box layout used.
 *
 * @param {Array} fields All fields, any tab.
 * @param {string} tab   Tab slug.
 * @return {Array<Object>} Renderable elements.
 */
export function elementsForTab( fields, tab ) {
	const elements = [];
	const scoped = ( fields || [] ).filter( ( field ) => field && String( field.tab || '' ) === String( tab ) );

	scoped.forEach( ( field ) => {
		const type = String( field.type || '' );

		if ( type === CARD_CONTAINER ) {
			const markup = String( field.column_row_before || '' );

			if ( markup.trim().startsWith( '</' ) ) {
				elements.push( { kind: 'card-end', id: field.id || '' } );
				return;
			}

			const width = CARD_WIDTH_PATTERN.exec( markup );

			elements.push( {
				kind: 'card-start',
				id: field.id || '',
				width: width ? width[ 1 ] : 'full',
				borderless: markup.includes( 'no-border' ),
			} );
			return;
		}

		elements.push( { kind: 'field', field: describeField( field ) } );
	} );

	return elements;
}

/**
 * Split an ordered element list into rows of cards.
 *
 * Anything before the first `card-start` becomes a row of its own so headings and
 * free-standing controls still render.
 *
 * @param {Array<Object>} elements Renderable elements.
 * @return {Array<{type: string, width: string, items: Array<Object>}>} Rows.
 */
export function groupIntoRows( elements ) {
	const rows = [];
	let current = { type: 'cards', width: 'full', items: [] };

	( elements || [] ).forEach( ( element ) => {
		if ( element.kind === 'card-start' ) {
			rows.push( current );
			current = {
				type: 'cards',
				width: element.width,
				borderless: element.borderless,
				items: [],
			};
			return;
		}

		if ( element.kind === 'card-end' ) {
			rows.push( current );
			current = { type: 'cards', width: 'full', items: [] };
			return;
		}

		current.items.push( element.field );
	} );

	rows.push( current );

	return rows.filter( ( row ) => row.items.length > 0 );
}

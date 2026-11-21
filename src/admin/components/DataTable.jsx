/**
 * Sortable, keyboard reachable table.
 *
 * Columns are declared as `{ key, header, render, sortValue, align }`. Nothing here
 * knows about servers or sites, so the same component serves every list route.
 */

import { useMemo, useState } from 'react';

import { Skeleton, EmptyState } from './Card.jsx';

/**
 * @param {Object}   props
 * @param {Array}    props.columns   Column descriptors.
 * @param {Array}    props.rows      Row objects.
 * @param {Function} props.rowKey    Row -> stable key.
 * @param {Function} props.onRowClick Optional row activation.
 * @param {boolean}  props.loading   Show placeholders.
 * @param {string}   props.emptyTitle Empty state headline.
 * @param {string}   props.emptyMessage Empty state body.
 * @return {JSX.Element} Table element.
 */
export default function DataTable( {
	columns,
	rows,
	rowKey,
	onRowClick,
	loading = false,
	emptyTitle = 'Nothing here yet',
	emptyMessage = '',
} ) {
	const [ sort, setSort ] = useState( { key: '', direction: 'asc' } );

	const sorted = useMemo( () => {
		if ( ! sort.key ) {
			return rows;
		}

		const column = columns.find( ( candidate ) => candidate.key === sort.key );

		if ( ! column ) {
			return rows;
		}

		const read = ( row ) =>
			column.sortValue ? column.sortValue( row ) : row[ column.key ];

		return [ ...rows ].sort( ( left, right ) => {
			const a = read( left );
			const b = read( right );

			if ( a === b ) {
				return 0;
			}

			const result = a > b ? 1 : -1;
			return sort.direction === 'asc' ? result : -result;
		} );
	}, [ rows, columns, sort ] );

	if ( loading ) {
		return (
			<div className="rounded-card border border-surface-border bg-surface-base p-5">
				<Skeleton rows={ 5 } />
			</div>
		);
	}

	if ( ! rows || rows.length === 0 ) {
		return <EmptyState title={ emptyTitle } message={ emptyMessage } />;
	}

	const toggleSort = ( key ) => {
		setSort( ( previous ) =>
			previous.key === key
				? { key, direction: previous.direction === 'asc' ? 'desc' : 'asc' }
				: { key, direction: 'asc' }
		);
	};

	return (
		<div className="overflow-hidden rounded-card border border-surface-border bg-surface-base">
			<table className="w-full border-collapse text-sm">
				<thead>
					<tr className="border-b border-surface-border bg-surface-muted/60 text-left">
						{ columns.map( ( column ) => {
							const active = sort.key === column.key;

							return (
								<th
									key={ column.key }
									scope="col"
									className={ `px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-ink-500 ${
										column.align === 'right' ? 'text-right' : ''
									}` }
								>
									{ column.sortable === false ? (
										column.header
									) : (
										<button
											type="button"
											onClick={ () => toggleSort( column.key ) }
											className="inline-flex items-center gap-1 uppercase tracking-wide hover:text-ink-800"
										>
											{ column.header }
											<span aria-hidden="true" className={ active ? 'text-brand-600' : 'text-ink-300' }>
												{ active && sort.direction === 'desc' ? '▾' : '▴' }
											</span>
										</button>
									) }
								</th>
							);
						} ) }
					</tr>
				</thead>
				<tbody>
					{ sorted.map( ( row ) => (
						<tr
							key={ rowKey( row ) }
							className={ `border-b border-surface-border last:border-b-0 ${
								onRowClick ? 'cursor-pointer hover:bg-surface-muted/60' : ''
							}` }
							onClick={ onRowClick ? () => onRowClick( row ) : undefined }
							onKeyDown={
								onRowClick
									? ( event ) => {
											if ( event.key === 'Enter' || event.key === ' ' ) {
												event.preventDefault();
												onRowClick( row );
											}
									  }
									: undefined
							}
							tabIndex={ onRowClick ? 0 : undefined }
						>
							{ columns.map( ( column ) => (
								<td
									key={ column.key }
									className={ `px-4 py-3 align-middle ${
										column.align === 'right' ? 'text-right' : ''
									}` }
								>
									{ column.render ? column.render( row ) : row[ column.key ] }
								</td>
							) ) }
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

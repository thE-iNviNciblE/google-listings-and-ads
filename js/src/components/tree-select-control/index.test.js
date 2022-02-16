/**
 * External dependencies
 */
import { fireEvent, render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import TreeSelectControl from '.~/components/tree-select-control/index';

describe( 'TreeSelectControl Component', () => {
	it( "Doesn't render without options", () => {
		const { queryByRole } = render( <TreeSelectControl /> );
		expect( queryByRole( 'combobox' ) ).toBeFalsy();
	} );

	it( 'Renders the provided options and selected values', () => {
		const selectedValues = [ 'ES' ];

		const options = [
			{
				id: 'EU',
				name: 'Europe',
				children: [
					{ id: 'ES', name: 'Spain' },
					{ id: 'FR', name: 'France' },
					{ id: 'IT', name: 'Italy' },
				],
			},
		];

		const { queryByLabelText, queryByRole } = render(
			<TreeSelectControl options={ options } value={ selectedValues } />
		);

		const control = queryByRole( 'combobox' );
		expect( queryByRole( 'listbox' ) ).toBeFalsy();
		options.forEach( ( { children } ) => {
			children.forEach( ( item ) => {
				expect( queryByLabelText( item.name ) ).toBeFalsy();
			} );
		} );

		fireEvent.click( control );
		expect( queryByRole( 'listbox' ) ).toBeTruthy();

		options.forEach( ( { children } ) => {
			children.forEach( ( item ) => {
				const checkbox = queryByLabelText( item.name );
				expect( checkbox ).toBeTruthy();
				expect( checkbox.checked ).toEqual(
					selectedValues.includes( item.id )
				);
			} );
		} );
	} );

	it( 'Calls onChange property with the selected values', () => {
		const onChange = jest.fn().mockName( 'onChange' );
		const options = [
			{
				id: 'EU',
				name: 'Europe',
				children: [
					{ id: 'ES', name: 'Spain' },
					{ id: 'FR', name: 'France' },
					{ id: 'IT', name: 'Italy' },
				],
			},
			{
				id: 'AS',
				name: 'Asia',
				children: [
					{ id: 'JP', name: 'Japan' },
					{ id: 'CH', name: 'China' },
				],
			},
		];

		const { rerender, queryByLabelText, queryByRole } = render(
			<TreeSelectControl
				options={ options }
				value={ [] }
				onChange={ onChange }
			/>
		);

		const control = queryByRole( 'combobox' );
		fireEvent.click( control );
		let checkbox = queryByLabelText( 'Spain' );

		fireEvent.click( checkbox );
		expect( onChange ).toHaveBeenCalledTimes( 1 );
		expect( onChange ).toHaveBeenCalledWith( [ 'ES' ] );

		rerender(
			<TreeSelectControl
				options={ options }
				value={ [ 'ES' ] }
				onChange={ onChange }
			/>
		);

		fireEvent.click( checkbox );
		expect( onChange ).toHaveBeenCalledTimes( 2 );
		expect( onChange ).toHaveBeenCalledWith( [] );

		rerender(
			<TreeSelectControl
				options={ options }
				value={ [ 'JP' ] }
				onChange={ onChange }
			/>
		);

		checkbox = queryByLabelText( 'Asia' );
		expect( checkbox.checked ).toBeFalsy();
		fireEvent.click( checkbox );
		expect( onChange ).toHaveBeenCalledTimes( 3 );
		expect( onChange ).toHaveBeenCalledWith( [ 'JP', 'CH' ] );

		rerender(
			<TreeSelectControl
				options={ options }
				value={ [ 'JP', 'CH' ] }
				onChange={ onChange }
			/>
		);

		expect( checkbox.checked ).toBeTruthy();
		fireEvent.click( checkbox );
		expect( onChange ).toHaveBeenCalledTimes( 4 );
		expect( onChange ).toHaveBeenCalledWith( [] );
	} );

	it( 'Renders the label', () => {
		const options = [
			{
				id: 'EU',
				name: 'Europe',
				children: [
					{ id: 'ES', name: 'Spain' },
					{ id: 'FR', name: 'France' },
					{ id: 'IT', name: 'Italy' },
				],
			},
		];

		const { queryByLabelText } = render(
			<TreeSelectControl options={ options } label="Select" />
		);

		expect( queryByLabelText( 'Select' ) ).toBeTruthy();
	} );

	it( 'Shows tags with the selected values', async () => {
		const onChange = jest.fn().mockName( 'on Change' );
		const options = [
			{
				id: 'EU',
				name: 'Europe',
				children: [
					{ id: 'ES', name: 'Spain' },
					{ id: 'FR', name: 'France' },
					{ id: 'IT', name: 'Italy' },
				],
			},
		];

		const { queryByText, queryByLabelText } = render(
			<TreeSelectControl
				onChange={ onChange }
				options={ options }
				value={ [ 'ES', 'IT' ] }
			/>
		);

		expect( queryByText( 'Spain (1 of 2)' ) ).toBeTruthy();
		expect( queryByText( 'Italy (2 of 2)' ) ).toBeTruthy();

		const removeButton = queryByLabelText( 'Remove Italy' );
		expect( removeButton ).toBeTruthy();
		fireEvent.click( removeButton );
		expect( onChange ).toHaveBeenCalledWith( [ 'ES' ] );
	} );

	it( 'Disabled state show tags but does not allow to change', () => {
		const options = [
			{
				id: 'EU',
				name: 'Europe',
				children: [
					{ id: 'ES', name: 'Spain' },
					{ id: 'FR', name: 'France' },
					{ id: 'IT', name: 'Italy' },
				],
			},
		];

		const { queryByText, queryByLabelText, queryByRole } = render(
			<TreeSelectControl
				disabled={ true }
				options={ options }
				value={ [ 'ES', 'IT' ] }
			/>
		);
		const control = queryByRole( 'combobox' );
		expect( queryByText( 'Spain (1 of 2)' ) ).toBeTruthy();
		expect( queryByText( 'Italy (2 of 2)' ) ).toBeTruthy();
		expect( queryByLabelText( 'Remove Italy' ) ).toBeFalsy();

		expect( queryByRole( 'listbox' ) ).toBeFalsy();
		fireEvent.click( control );
		expect( queryByRole( 'listbox' ) ).toBeFalsy();
	} );
} );

/**
 * External dependencies
 */
import { compose } from '@wordpress/compose';
import { withDispatch, withSelect } from '@wordpress/data';
import { OPTIONS_STORE_NAME } from '@woocommerce/data';
import { __experimentalInputControl as InputControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './index.scss';
import Faqs from './faqs';
import GetStartedCard from './get-started-card';

const GetStartedPage = ( { exampleOption, updateOptions } ) => {

	const doUpdate = ( newValue ) => {

		console.log( newValue );

		updateOptions( {
			gla_example_option: newValue,
		} );
	};

	return (
		<div className="woocommerce-marketing-google-get-started-page">
			<InputControl
				value={ exampleOption }
				onChange={ ( newValue ) => doUpdate( newValue ) }
			/>
			<GetStartedCard />
			<Faqs></Faqs>
		</div>
	);
};

// default export
export default compose(
	withSelect( ( select ) => {
		const { getOption } = select( OPTIONS_STORE_NAME );
		const exampleFromDB = getOption( 'gla_example_option' ) || '';
		return {
			exampleOption: exampleFromDB,
		};
	} ),
	withDispatch( ( dispatch ) => {
		const { updateOptions } = dispatch( OPTIONS_STORE_NAME );
		return {
			updateOptions,
		};
	} )
)( GetStartedPage );

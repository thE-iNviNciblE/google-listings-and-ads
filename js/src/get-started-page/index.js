/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { compose } from '@wordpress/compose';
import { withDispatch, withSelect } from '@wordpress/data';
import { OPTIONS_STORE_NAME } from '@woocommerce/data';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './index.scss';

const GetStartedPage = ( { query, optionView, updateOptions } ) => {

	const updateTest = () => {

		let temp = optionView;

		if (temp == 'yes') {
			temp = 'no';
		} else {
			temp = 'yes';
		}

		console.log(temp);

		updateOptions( {
			gla_started: temp,
		} );
	};

	return (
		<div>
			Hello World!
			<div>
				<Button
					label={ __( 'Test Option Update', 'gla' ) }
					onClick={ updateTest }
				>
					Click me
				</Button>
			</div>
		</div>
	);
};

export { GetStartedPage };

// default export
export default compose(
	withSelect( ( select ) => {
		const { getOption, isOptionsUpdating } = select( OPTIONS_STORE_NAME );
		const isUpdateRequesting = isOptionsUpdating();

		return {
			optionView: getOption( 'gla_started' ),
		};
	} ),
	withDispatch( ( dispatch ) => {
		const { updateOptions } = dispatch( OPTIONS_STORE_NAME );
		return {
			updateOptions,
		};
	} )
)( GetStartedPage );

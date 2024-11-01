/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * All files containing `style` keyword are bundled together. The code used
 * gets applied both to the front of your site and to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './style.scss';
import { useState, useEffect } from "react"

import { decodeEntities } from '@wordpress/html-entities';
const { registerPaymentMethod } = window.wc.wcBlocksRegistry
const { getSetting } = window.wc.wcSettings

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
const { TextInput } = window.wc.blocksComponents
const settings = getSetting('webcompro_data', {})

const label = decodeEntities(settings.title)

const Content = (props) => {
	const [name, setName] = useState("")
	const [pan, setPan] = useState("")
	const [expiry, setExpiry] = useState("")
	const [cvv, setCvv] = useState("")
	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup } = eventRegistration;

	useEffect(() => {
		const unsubscribe = onPaymentSetup(async () => {
			// Here we can do any processing we need, and then emit a response.
			// For example, we might validate a custom field, or perform an AJAX request, and then emit a response indicating it is valid or not.
			const dataIsNotValid = pan.length < 1 || expiry.length < 1 || cvv.length < 1;

			if (dataIsNotValid) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: 'Dati carta non corretti',
				};
			}

			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						pan, 
						cvv, 
						expiry, 
						name,
						nonce: settings.nonce
					},
				}
			};
		});
		// Unsubscribes when this component is unmounted.
		return () => {
			unsubscribe();
		};
	}, [
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS,
		onPaymentSetup,
		pan,
		cvv,
		expiry,
		name

	]);

	return <>
		{decodeEntities(settings.description || '')}
		<TextInput value={name} onChange={(e) => setName(e)} label={"Nome"}></TextInput>
		<TextInput value={pan} onChange={(e) => setPan(e)} label={"Numero carta di credito"}></TextInput>
		<TextInput value={expiry} onChange={(e) => setExpiry(e)} label={"Scadenza (mm/yy)"}></TextInput>
		<TextInput value={cvv} onChange={(e) => setCvv(e)} label={"Cvv"}></TextInput>
	</>
}

const LabelTitle = (props) => {
	const { PaymentMethodLabel } = props.components
	return <PaymentMethodLabel text={label} />
}

registerPaymentMethod({
	name: "webcompro",
	label: <LabelTitle />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	}
})
import {useMemo} from 'react';
import amountFormatter from '@givewp/blocks/form/app/utilities/amountFormatter';

/**
 * @unreleased
 */
export default function useCurrencyFormatter<AmountFormatter>(currency, options) {
    return useMemo(() => amountFormatter(currency, options), [currency, navigator.language]);
}

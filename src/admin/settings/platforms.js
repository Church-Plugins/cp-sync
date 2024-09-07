import ccb from "./chms/ccb";
import pco from "./chms/pco";
import mp from "./chms/mp";

const platforms = {
	'pco': pco,
	'ccb': ccb,
	'mp':  mp,
};

export default platforms;
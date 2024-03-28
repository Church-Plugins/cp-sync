import ccb from "./ccb";
import ministryPlatform from "./ministry-platform";
import pco from "./pco";

const platforms = {
	'mp': ministryPlatform,
	'pco': pco,
	'ccb': ccb,
}

export default platforms;
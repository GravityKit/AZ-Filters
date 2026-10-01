const path = require('path');
const { createPlaywrightConfig } = require('@gravitykit/e2e-bootstrap');

const config = createPlaywrightConfig({
	setupDir: __dirname,
	testDir: path.resolve(__dirname, '..'),
	use: {
		trace: 'on-first-retry'
	}
});

// One shared WordPress + MySQL backend: serialize under CI so front-end and
// admin specs don't saturate it. Override with E2E_WORKERS (a positive integer).
const workersOverride = Number.parseInt(process.env.E2E_WORKERS, 10);

if (Number.isInteger(workersOverride) && workersOverride > 0) {
	config.workers = workersOverride;
} else if (process.env.CI) {
	config.workers = 1;
}

module.exports = config;

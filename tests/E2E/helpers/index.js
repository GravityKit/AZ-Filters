/**
 * E2E test infrastructure entry point for AZ-Filters.
 *
 * Initializes the shared @gravitykit/e2e-bootstrap + e2e-fixtures helpers
 * (API client base URL, fixtures create/createView, cleanup) against this
 * harness's wp-env instance. Mirrors GravityView's tests/E2E/helpers/index.js.
 */

const path = require('path');
const { createHelpers } = require('@gravitykit/e2e-bootstrap');

module.exports = createHelpers({
	setupDir: path.resolve(__dirname, '../setup')
});

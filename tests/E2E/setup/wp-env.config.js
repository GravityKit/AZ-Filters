const fs = require('fs');
const path = require('path');
const { generateWpEnvConfig } = require('@gravitykit/e2e-bootstrap');

// The A-Z Entry Filter widget only exists when GravityView (and Gravity Forms)
// are active, so the harness needs all three mounted.
//
// Locally: mount GravityView + Gravity Forms from their sibling repos in
// gravitykit-qa/ when present, so a checkout needs no .env to run.
// In CI: the siblings do not exist (single-repo checkout), so they are provided
// via WP_ENV_PLUGINS in .env instead (GravityView is git-cloned so its tests/
// dir exists for the PHPUnit suite). See .circleci/config.yml.
const siblings = path.resolve(__dirname, '../../..', '..');
const additionalPlugins = [
	path.join(siblings, 'GravityView'),
	path.join(siblings, 'gravityforms')
].filter((p) => fs.existsSync(p));

generateWpEnvConfig({
	outputDir: __dirname,
	pluginPath: '../../..',
	additionalPlugins,
	additionalMappings: {
		// Generic View-meta writer (fields + widgets) used to seed the A-Z widget
		// without the editor UI. Shared verbatim with GravityView's E2E harness.
		'wp-content/mu-plugins/e2e-view-fields.php': '../mu-plugins/e2e-view-fields.php'
	}
}).catch((err) => {
	console.error(err);
	process.exit(1);
});

const path = require('path');
const { generateWpEnvConfig } = require('@gravitykit/e2e-bootstrap');

// The A-Z Entry Filter widget only exists when GravityView (and Gravity Forms)
// are active, so the harness mounts all three from their sibling repos:
//   pluginPath      -> AZ-Filters (plugin under test)
//   additionalPlugins -> GravityView + Gravity Forms
// Paths are resolved relative to this setup dir (tests/E2E/setup); `../../..`
// is the AZ-Filters root, `../../../../<repo>` is a sibling in gravitykit-qa/.
const siblings = path.resolve(__dirname, '../../..', '..');

generateWpEnvConfig({
	outputDir: __dirname,
	pluginPath: '../../..',
	additionalPlugins: [
		path.join(siblings, 'GravityView'),
		path.join(siblings, 'gravityforms')
	],
	additionalMappings: {
		// Generic View-meta writer (fields + widgets) used to seed the A-Z widget
		// without the editor UI. Shared verbatim with GravityView's E2E harness.
		'wp-content/mu-plugins/e2e-view-fields.php': '../mu-plugins/e2e-view-fields.php'
	}
}).catch((err) => {
	console.error(err);
	process.exit(1);
});

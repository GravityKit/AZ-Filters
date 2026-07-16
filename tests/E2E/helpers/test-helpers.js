const fixtures = require('./index.js');

/**
 * Cities dataset used by the A-Z specs. The City field (id 2) deliberately
 * spans several first letters, with three starting with "B" so a single letter
 * reveals a subset larger than one:
 *   B -> Boston, Buffalo, Boise   A -> Austin (1)   C -> Chicago (1)   Z -> none
 */
const CITY_FORM = {
	title: 'AZ Cities',
	fields: [
		{ id: 1, type: 'text', label: 'Name' },
		{ id: 2, type: 'text', label: 'City' }
	]
};

const CITY_ENTRIES = [
	{ 1: 'Alice', 2: 'Boston' },
	{ 1: 'Bob', 2: 'Buffalo' },
	{ 1: 'Carol', 2: 'Boise' },
	{ 1: 'Dave', 2: 'Austin' },
	{ 1: 'Erin', 2: 'Chicago' },
	{ 1: 'Frank', 2: 'Denver' },
	{ 1: 'Grace', 2: 'Miami' },
	{ 1: 'Heidi', 2: 'Seattle' },
	{ 1: 'Ivan', 2: 'Portland' },
	{ 1: 'Judy', 2: 'Nashville' }
];

const CITY_FIELD_ID = '2';

const B_CITIES = ['Boston', 'Buffalo', 'Boise'];

/**
 * Seeds a List View on the Cities form with the A-Z Entry Filter widget
 * configured to filter by the City field, entirely through the fixtures REST
 * API (no editor UI).
 *
 * Steps:
 *   1. fixtures.create() seeds the form, entries, and a bare List View (with the
 *      hide-until-searched setting).
 *   2. configureView() fills the directory fields with PresetFieldGenerator
 *      defaults so entries actually render.
 *   3. The generic /view-field route writes the az_filter widget into the
 *      View's `_gravityview_directory_widgets` header zone. The widget config
 *      shape (a `header_top` zone keyed by unique id, each value carrying an
 *      `id`) matches the fields shape the route already handles, so no
 *      widget-specific seeding code is needed.
 *
 * @param {Object}  params
 * @param {boolean} params.hideUntilSearched Whether to enable "Hide entries until search".
 * @param {string}  [params.title]           View title.
 * @returns {Promise<object>} Fixture data: { view_id, view_url, form_id, entries, ... }.
 */
async function createAZCitiesView({ hideUntilSearched, title }) {
	const hide = hideUntilSearched ? 1 : 0;
	const viewTitle = title || (hideUntilSearched ? 'AZ Hide Until Searched' : 'AZ No Hide');

	const data = await fixtures.create({
		form: CITY_FORM,
		entries: CITY_ENTRIES,
		view: {
			title: viewTitle,
			template: 'default_list',
			settings: { hide_until_searched: hide }
		}
	});

	// Seed the directory fields (PresetFieldGenerator) so the List renders.
	const configured = await fixtures.api.configureView(data.view_id, {
		template: 'default_list',
		settings: { hide_until_searched: hide }
	});

	if (!configured || true !== configured.success) {
		throw new Error(
			`createAZCitiesView: configureView failed for View ${data.view_id}: ${JSON.stringify(configured)}`
		);
	}

	// Add the A-Z Entry Filter widget to the View header, filtering by City.
	const widget = await fixtures.api.post('/view-field', {
		view_id: data.view_id,
		op: 'set',
		zone: 'header_top',
		fields: [
			{
				id: 'az_filter',
				filter_field: CITY_FIELD_ID,
				header: 1,
				footer: 1,
				localization: 'en_US',
				uppercase: 1
			}
		],
		meta_key: '_gravityview_directory_widgets'
	});

	if (!widget || true !== widget.success) {
		throw new Error(
			`createAZCitiesView: seeding az_filter widget failed for View ${data.view_id}: ${JSON.stringify(widget)}`
		);
	}

	return data;
}

module.exports = {
	CITY_FORM,
	CITY_ENTRIES,
	CITY_FIELD_ID,
	B_CITIES,
	createAZCitiesView
};

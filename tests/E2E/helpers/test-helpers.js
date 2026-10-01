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
const NAME_FIELD_ID = '1';

const B_CITIES = ['Boston', 'Buffalo', 'Boise'];

/**
 * The full English alphabet the widget renders for the `en_US` localization.
 */
const EN_ALPHABET = 'abcdefghijklmnopqrstuvwxyz'.split('');

/**
 * Localized datasets. Each pairs a locale with cities in that script, a letter
 * that matches exactly two of them, and a city that must stay out of the result.
 * Russian and Greek are non-Latin; Swedish exercises the letters that sort after
 * z rather than folding into a.
 */
const LOCALIZED_ALPHABETS = [
	{
		locale: 'ru_RU',
		label: 'Russian',
		cities: ['Москва', 'Минск', 'Казань'],
		letter: 'м',
		matching: ['Москва', 'Минск'],
		excluded: 'Казань',
		sampleLetters: ['а', 'б', 'я']
	},
	{
		locale: 'el_GR',
		label: 'Greek',
		cities: ['Αθήνα', 'Αλεξανδρούπολη', 'Κόρινθος'],
		letter: 'α',
		matching: ['Αθήνα', 'Αλεξανδρούπολη'],
		excluded: 'Κόρινθος',
		sampleLetters: ['α', 'β', 'ω']
	},
	{
		locale: 'sv_SE',
		label: 'Swedish',
		cities: ['Örebro', 'Östersund', 'Stockholm'],
		letter: 'ö',
		matching: ['Örebro', 'Östersund'],
		excluded: 'Stockholm',
		sampleLetters: ['å', 'ä', 'ö']
	}
];

/**
 * Enough entries under one letter to spill past a small page size, so a test can
 * page within an active filter: five B-cities plus two that must never appear.
 */
const PAGING_ENTRIES = [
	{ 1: 'P1', 2: 'Boston' },
	{ 1: 'P2', 2: 'Buffalo' },
	{ 1: 'P3', 2: 'Boise' },
	{ 1: 'P4', 2: 'Baltimore' },
	{ 1: 'P5', 2: 'Bakersfield' },
	{ 1: 'P6', 2: 'Austin' },
	{ 1: 'P7', 2: 'Chicago' }
];

const PAGING_B_CITIES = ['Boston', 'Buffalo', 'Boise', 'Baltimore', 'Bakersfield'];

/**
 * Seeds a List View with the A-Z Entry Filter widget, entirely through the
 * fixtures REST API (no editor UI).
 *
 * Steps:
 *   1. fixtures.create() seeds the form, entries, and a bare List View.
 *   2. configureView() fills the directory fields with PresetFieldGenerator
 *      defaults so entries actually render.
 *   3. The generic /view-field route writes the widgets into the View's
 *      `_gravityview_directory_widgets` header zone. The widget config shape (a
 *      `header_top` zone keyed by unique id, each value carrying an `id`)
 *      matches the fields shape the route already handles, so no
 *      widget-specific seeding code is needed.
 *
 * @param {Object}   params
 * @param {boolean}  [params.hideUntilSearched] Whether to enable "Hide entries until search".
 * @param {string}   [params.title]             View title.
 * @param {string}   [params.localization]      Alphabet locale for the widget.
 * @param {string}   [params.filterField]       Field the widget filters on.
 * @param {Object}   [params.form]              Form definition.
 * @param {Array}    [params.entries]           Entries to seed.
 * @param {Object}   [params.settings]          Extra View settings.
 * @param {boolean}  [params.searchBar]         Add a Search Bar searching the Name field.
 * @returns {Promise<object>} Fixture data: { view_id, view_url, form_id, entries, ... }.
 */
async function createAZView({
	hideUntilSearched = false,
	title,
	localization = 'en_US',
	filterField = CITY_FIELD_ID,
	form = CITY_FORM,
	entries = CITY_ENTRIES,
	settings = {},
	searchBar = false,
	pageLinks = false
} = {}) {
	const hide = hideUntilSearched ? 1 : 0;
	const viewTitle = title || (hideUntilSearched ? 'AZ Hide Until Searched' : 'AZ No Hide');
	const viewSettings = Object.assign({ hide_until_searched: hide }, settings);

	const data = await fixtures.create({
		form,
		entries,
		view: {
			title: viewTitle,
			template: 'default_list',
			settings: viewSettings
		}
	});

	// Seed the directory fields (PresetFieldGenerator) so the List renders.
	const configured = await fixtures.api.configureView(data.view_id, {
		template: 'default_list',
		settings: viewSettings
	});

	if (!configured || true !== configured.success) {
		throw new Error(
			`createAZView: configureView failed for View ${data.view_id}: ${JSON.stringify(configured)}`
		);
	}

	const widgets = [
		{
			id: 'az_filter',
			filter_field: filterField,
			header: 1,
			footer: 1,
			localization,
			uppercase: 1
		}
	];

	if (pageLinks) {
		widgets.push({ id: 'page_links', show_all: 0 });
	}

	if (searchBar) {
		widgets.push({
			id: 'search_bar',
			search_layout: 'horizontal',
			search_clear: 0,
			search_mode: 'any',
			search_fields: JSON.stringify([{ field: NAME_FIELD_ID, input: 'input_text' }])
		});
	}

	const widget = await fixtures.api.post('/view-field', {
		view_id: data.view_id,
		op: 'set',
		zone: 'header_top',
		fields: widgets,
		meta_key: '_gravityview_directory_widgets'
	});

	if (!widget || true !== widget.success) {
		throw new Error(
			`createAZView: seeding widgets failed for View ${data.view_id}: ${JSON.stringify(widget)}`
		);
	}

	return data;
}

/**
 * Seeds a List View on the Cities form filtering by City.
 *
 * @param {Object}  params
 * @param {boolean} params.hideUntilSearched Whether to enable "Hide entries until search".
 * @param {string}  [params.title]           View title.
 * @returns {Promise<object>} Fixture data.
 */
async function createAZCitiesView({ hideUntilSearched, title }) {
	return createAZView({ hideUntilSearched, title });
}

/**
 * Seeds a View with no A-Z widget at all, used as the control on a page that
 * also renders an A-Z View: its rows must be untouched by an active letter.
 *
 * @param {Object} params
 * @param {string} [params.title]   View title.
 * @param {Array}  [params.entries] Entries to seed.
 * @returns {Promise<object>} Fixture data.
 */
async function createPlainView({ title = 'AZ Control View', entries = CITY_ENTRIES, searchBar = false } = {}) {
	const data = await fixtures.create({
		form: CITY_FORM,
		entries,
		view: { title, template: 'default_list', settings: { hide_until_searched: 0 } }
	});

	const configured = await fixtures.api.configureView(data.view_id, {
		template: 'default_list',
		settings: { hide_until_searched: 0 }
	});

	if (!configured || true !== configured.success) {
		throw new Error(
			`createPlainView: configureView failed for View ${data.view_id}: ${JSON.stringify(configured)}`
		);
	}

	if (searchBar) {
		const widget = await fixtures.api.post('/view-field', {
			view_id: data.view_id,
			op: 'set',
			zone: 'header_top',
			fields: [
				{
					id: 'search_bar',
					search_layout: 'horizontal',
					search_clear: 0,
					search_mode: 'any',
					search_fields: JSON.stringify([{ field: NAME_FIELD_ID, input: 'input_text' }])
				}
			],
			meta_key: '_gravityview_directory_widgets'
		});

		if (!widget || true !== widget.success) {
			throw new Error(
				`createPlainView: seeding search bar failed for View ${data.view_id}: ${JSON.stringify(widget)}`
			);
		}
	}

	return data;
}

/**
 * Publishes a page rendering the given Views as shortcodes, so more than one
 * View builds its query in a single request.
 *
 * @param {number[]} viewIds  View IDs, rendered in order.
 * @param {string}   [title]  Page title.
 * @returns {Promise<string>} The page URL.
 */
async function createViewsPage(viewIds, title = 'AZ Multi View Page') {
	const content = viewIds.map((id) => `[gravityview id="${id}"]`).join('\n\n');

	const page = await fixtures.api.post('/page', { title, content });

	if (!page || true !== page.success) {
		throw new Error(`createViewsPage: creating page failed: ${JSON.stringify(page)}`);
	}

	return page.page_url;
}

/**
 * Creates a user with a known display name and assigns entries to them, so
 * Created By filtering has authors it can bucket by first character.
 *
 * @param {string}   displayName The author's display name.
 * @param {number[]} entryIds    Entries to attribute to the new author.
 * @returns {Promise<number>} The new user ID.
 */
async function createAuthorFor(displayName, entryIds) {
	const result = await fixtures.api.post('/entry-author', {
		display_name: displayName,
		entry_ids: entryIds
	});

	if (!result || true !== result.success) {
		throw new Error(`createAuthorFor: assigning author failed: ${JSON.stringify(result)}`);
	}

	return result.user_id;
}

/**
 * Locates a letter link in an A-Z bar. Letters are uppercased with CSS
 * (gv-uppercase), so the link's accessible name is the lowercase character.
 *
 * @param {import('@playwright/test').Locator|import('@playwright/test').Page} scope
 * @param {string} letter
 */
function letterLink(scope, letter) {
	return scope.getByRole('link', { name: letter, exact: true });
}

/**
 * Locates the "Show All" reset link inside an A-Z bar. Scoped to the widget markup on
 * purpose: an accessible-name lookup matches substrings, so a View whose title contains
 * the same words would match its own title link instead.
 *
 * @param {import('@playwright/test').Locator|import('@playwright/test').Page} scope
 */
function showAllLink(scope) {
	return scope.locator('ul.gravityview-az-filter li .show-all a');
}

module.exports = {
	CITY_FORM,
	CITY_ENTRIES,
	CITY_FIELD_ID,
	NAME_FIELD_ID,
	B_CITIES,
	EN_ALPHABET,
	LOCALIZED_ALPHABETS,
	PAGING_ENTRIES,
	PAGING_B_CITIES,
	createAZView,
	createAZCitiesView,
	createPlainView,
	createViewsPage,
	createAuthorFor,
	letterLink,
	showAllLink
};

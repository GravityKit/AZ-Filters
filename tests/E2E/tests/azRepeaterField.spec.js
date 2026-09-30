import { test, expect } from '@playwright/test';
import fixtures from '../helpers/index.js';
import { createAZView, showAllLink } from '../helpers/test-helpers';

/**
 * Gravity Forms Repeater fields and the A-Z filter. A text field inside a repeater can
 * be the A-Z field: an entry matches when any of its rows starts with the letter. The
 * repeater itself stores no value, so it is kept out of the widget's field dropdown, and
 * a widget saved with it does not hide every entry.
 *
 * @see https://linear.app/gravitykit/issue/GVAZ-14
 */
test.describe('A-Z filter with a Repeater field', () => {
	const REPEATER_ID = '2';
	const COMPANY_ID = '3';
	const FIRST_NAME_ID = '4.3';

	const FORM = {
		title: 'AZ Repeater',
		fields: [
			{ id: 1, type: 'text', label: 'Team' },
			{ id: 2, type: 'repeater', label: 'Companies' }
		]
	};

	const SUB_FIELDS = [
		{ id: 3, type: 'text', label: 'Company' },
		{
			id: 4,
			type: 'name',
			label: 'Owner',
			inputs: [
				{ id: '4.3', label: 'First' },
				{ id: '4.6', label: 'Last' }
			]
		}
	];

	// Team names are what the list shows; each is unique so a match is unambiguous.
	const TEAMS = ['Orchard Team', 'Grove Team', 'Vacant Team'];

	const ROWS = [
		[
			{ 3: 'Apple', '4.3': 'Mary', '4.6': 'Jones' },
			{ 3: 'Banana', '4.3': 'Ned', '4.6': 'Kim' }
		],
		[{ 3: 'Cherry', '4.3': 'Olga', '4.6': 'Park' }],
		null
	];

	// The fixture /create route drops a field's `fields` and array entry values, so the
	// sub-fields and rows are written through GFAPI afterwards.
	async function addRepeaterRows(data) {
		await fixtures.api.updateFormField(data.form_id, 2, { fields: SUB_FIELDS });

		for (const [index, rows] of ROWS.entries()) {
			if (rows) {
				await fixtures.api.updateEntry(data.entries[index].id, { 2: rows });
			}
		}
	}

	const created = [];

	async function repeaterView(title, filterField) {
		const data = await createAZView({
			title,
			form: FORM,
			entries: TEAMS.map((team) => ({ 1: team })),
			filterField,
			prepare: addRepeaterRows
		});

		created.push(data);

		return data;
	}

	async function expectTeams(page, visible) {
		for (const team of TEAMS) {
			await expect(page.getByText(team, { exact: true })).toHaveCount(visible.includes(team) ? 1 : 0);
		}
	}

	test.afterAll(async () => {
		for (const data of created) {
			if (data?.test_id) {
				await fixtures.api.cleanup(data.test_id);
			}
		}
	});

	test('a text sub-field filters by any row, and Show All restores every entry', async ({ page }) => {
		const { view_url } = await repeaterView('AZ Repeater Company', COMPANY_ID);

		await page.goto(`${view_url}?letter=a`);
		await expectTeams(page, ['Orchard Team']);

		// "Banana" is the second row of the same entry.
		await page.goto(`${view_url}?letter=b`);
		await expectTeams(page, ['Orchard Team']);

		await page.goto(`${view_url}?letter=c`);
		await expectTeams(page, ['Grove Team']);

		await page.goto(`${view_url}?letter=z`);
		await expectTeams(page, []);

		await page.goto(`${view_url}?letter=c`);
		await showAllLink(page).first().click();
		await expectTeams(page, TEAMS);
	});

	test('an input of a multi-input sub-field filters by any row', async ({ page }) => {
		const { view_url } = await repeaterView('AZ Repeater First Name', FIRST_NAME_ID);

		await page.goto(`${view_url}?letter=n`);
		await expectTeams(page, ['Orchard Team']);

		await page.goto(`${view_url}?letter=o`);
		await expectTeams(page, ['Grove Team']);
	});

	test('a widget saved with the repeater itself does not hide every entry', async ({ page }) => {
		const { view_url } = await repeaterView('AZ Repeater Saved', REPEATER_ID);

		await page.goto(`${view_url}?letter=a`);
		await expectTeams(page, TEAMS);
	});

	test('the widget field dropdown offers sub-fields but not the repeater', async ({ page }) => {
		const { view_id } = await repeaterView('AZ Repeater Picker', COMPANY_ID);

		await page.goto(`/wp-admin/post.php?post=${view_id}&action=edit`);

		const widget = page.locator('.gv-fields[data-fieldid="az_filter"]').first();
		await widget.waitFor({ state: 'visible' });
		await widget.hover();
		await widget.locator('.gv-field-settings').first().click();

		const dialog = page.locator('.ui-dialog:visible').last();
		const select = dialog.locator('select[name*="filter_field"]');

		// The options are replaced by an AJAX response once the dialog opens.
		await expect(select.locator(`option[value="${COMPANY_ID}"]`)).toHaveCount(1, { timeout: 15000 });
		await expect(select).toBeEnabled();

		await expect(select.locator(`option[value="${FIRST_NAME_ID}"]`)).toHaveCount(1);
		await expect(select.locator(`option[value="${REPEATER_ID}"]`)).toHaveCount(0);
	});
});

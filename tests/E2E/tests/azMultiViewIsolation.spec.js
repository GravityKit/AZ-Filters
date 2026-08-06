import { test, expect } from '@playwright/test';
import { createAZView, createPlainView, createViewsPage, B_CITIES } from '../helpers/test-helpers';

/**
 * The letter rewrite is registered once and spent by the query it conditioned, so a
 * page rendering more than one View must not let one View's letter filter reach
 * another View's query, and a View rendered twice must return the same rows both times.
 *
 * @see https://linear.app/gravitykit/issue/GVAZ-11
 */
const SEARCH_ENTRIES = [
	{ 1: 'Smith', 2: 'Boston' },
	{ 1: 'Smith', 2: 'Austin' },
	{ 1: 'Jones', 2: 'Boise' }
];

test.describe('A-Z filtering across multiple Views on one page', () => {
	test('an active letter leaves a View without the A-Z widget untouched', async ({ page }) => {
		// The control View searches its own Name field. That search is the condition a
		// leaked rewrite would corrupt: under the forced binary collation, a lowercased
		// search comparison stops matching its own term and the View empties out.
		const azView = await createAZView({
			title: 'AZ Filtered View',
			entries: SEARCH_ENTRIES,
			searchBar: true
		});
		const plainView = await createPlainView({
			title: 'AZ Untouched View',
			entries: SEARCH_ENTRIES,
			searchBar: true
		});
		const pageUrl = await createViewsPage([azView.view_id, plainView.view_id]);

		const filtered = page.locator(`.gv-container-${azView.view_id}`);
		const untouched = page.locator(`.gv-container-${plainView.view_id}`);

		// Both Views answer the search the same way before any letter is applied.
		await page.goto(`${pageUrl}?filter_1=Smith`);

		for (const scope of [filtered, untouched]) {
			await expect(scope.getByText('Boston', { exact: false })).toBeVisible();
			await expect(scope.getByText('Austin', { exact: false })).toBeVisible();
			await expect(scope.getByText('Boise', { exact: false })).toHaveCount(0);
		}

		// Adding the letter narrows only the View that carries the A-Z widget.
		await page.goto(`${pageUrl}?filter_1=Smith&letter=b&e2e_collation=bin`);

		await expect(filtered.getByText('Boston', { exact: false })).toBeVisible();
		await expect(filtered.getByText('Austin', { exact: false })).toHaveCount(0);

		// The View with no A-Z widget keeps exactly its no-letter result.
		await expect(untouched.getByText('Boston', { exact: false })).toBeVisible();
		await expect(untouched.getByText('Austin', { exact: false })).toBeVisible();
		await expect(untouched.getByText('Boise', { exact: false })).toHaveCount(0);
	});

	test('the same View rendered twice returns the same rows in both embeds', async ({ page }) => {
		const azView = await createAZView({ title: 'AZ Twice Embedded' });
		const pageUrl = await createViewsPage([azView.view_id, azView.view_id]);

		await page.goto(`${pageUrl}?letter=b&e2e_collation=bin`);

		const embeds = page.locator(`.gv-container-${azView.view_id}`);
		await expect(embeds).toHaveCount(2);

		// A rewrite that stacked across queries would leave the second embed with
		// different rows than the first.
		for (const index of [0, 1]) {
			const embed = embeds.nth(index);

			for (const city of B_CITIES) {
				await expect(embed.getByText(city, { exact: false })).toBeVisible();
			}

			await expect(embed.getByText('Austin', { exact: false })).toHaveCount(0);
		}
	});
});

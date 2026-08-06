import { test, expect } from '@playwright/test';
import { createAZView } from '../helpers/test-helpers';

/**
 * A letter filter and a Search Bar search narrow the same View at once. The letter
 * rewrite lowercases (and optionally collates) only its own comparison, so the search
 * on another field keeps matching exactly as it does with no letter applied.
 *
 * `?e2e_collation=bin` forces the binary collation through the harness mu-plugin. It is
 * the condition that makes a leaked rewrite visible: under a binary collation, a search
 * comparison that was lowercased along with the letter stops matching its own term.
 *
 * @see https://linear.app/gravitykit/issue/GVAZ-10
 */
const SEARCH_ENTRIES = [
	{ 1: 'Smith', 2: 'Boston' },
	{ 1: 'Smith', 2: 'Austin' },
	{ 1: 'Jones', 2: 'Boise' }
];

test.describe('A-Z letter combined with a Search Bar search', () => {
	test('the search keeps matching its own field when a letter is applied', async ({ page }) => {
		const { view_url } = await createAZView({
			title: 'AZ Search Bar Combo',
			entries: SEARCH_ENTRIES,
			searchBar: true
		});

		// Baseline: the search alone matches both Smith entries. This also proves the
		// search parameter is the one the seeded Search Bar actually reads.
		await page.goto(`${view_url}?filter_1=Smith`);
		await expect(page.getByText('Boston', { exact: false })).toBeVisible();
		await expect(page.getByText('Austin', { exact: false })).toBeVisible();
		await expect(page.getByText('Boise', { exact: false })).toHaveCount(0);

		// Adding the letter narrows to the Smith entry whose city starts with B. The
		// search must still match "Smith" under the forced binary collation.
		await page.goto(`${view_url}?filter_1=Smith&letter=b&e2e_collation=bin`);
		await expect(page.getByText('Boston', { exact: false })).toBeVisible();
		await expect(page.getByText('Austin', { exact: false })).toHaveCount(0);
		await expect(page.getByText('Boise', { exact: false })).toHaveCount(0);
	});
});

import { test, expect } from '@playwright/test';
import { createAZView, createAuthorFor } from '../helpers/test-helpers';

/**
 * Created By filtering buckets entries by their author's display name. The 0-9 bucket
 * must reach the authors whose display name starts with a digit, not the authors whose
 * numeric user ID happens to start with one.
 *
 * @see https://linear.app/gravitykit/issue/GVAZ-9
 */
const AUTHOR_ENTRIES = [
	{ 1: 'One', 2: 'Boston' },
	{ 1: 'Two', 2: 'Austin' },
	{ 1: 'Three', 2: 'Chicago' }
];

test.describe('A-Z Created By filtering', () => {
	test('the 0-9 bucket matches the author whose display name starts with a digit', async ({ page }) => {
		const data = await createAZView({
			title: 'AZ Created By Digits',
			entries: AUTHOR_ENTRIES,
			filterField: 'created_by'
		});

		const entryIds = data.entries.map((entry) => entry.id);

		await createAuthorFor('3M Company', [entryIds[0]]);
		await createAuthorFor('Alice Smith', [entryIds[1]]);
		await createAuthorFor('Bob Jones', [entryIds[2]]);

		// Every author has a numeric user ID, so a bucket that matched IDs instead of
		// display names would return all three rows here.
		await page.goto(`${data.view_url}?letter=0-9`);
		await expect(page.getByText('Boston', { exact: false })).toBeVisible();
		await expect(page.getByText('Austin', { exact: false })).toHaveCount(0);
		await expect(page.getByText('Chicago', { exact: false })).toHaveCount(0);

		// A letter bucket still resolves through the display name.
		await page.goto(`${data.view_url}?letter=a`);
		await expect(page.getByText('Austin', { exact: false })).toBeVisible();
		await expect(page.getByText('Boston', { exact: false })).toHaveCount(0);
	});
});

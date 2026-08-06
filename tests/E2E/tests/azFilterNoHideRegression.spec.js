import { test, expect } from '@playwright/test';
import { createAZCitiesView, B_CITIES } from '../helpers/test-helpers';

/**
 * Registering `letter` as a search request must not change plain A-Z filtering
 * on a View that does not hide entries until search: each letter still returns
 * exactly its matching entries.
 */
test.describe('A-Z filtering on a View without hide-until-searched', () => {
	test('each letter returns its matching entries', async ({ page }) => {
		const { view_url } = await createAZCitiesView({ hideUntilSearched: false });

		// No filter: every entry shows.
		await page.goto(view_url);
		for (const city of ['Boston', 'Austin', 'Nashville']) {
			await expect(page.getByText(city, { exact: false })).toBeVisible();
		}

		// ?letter=B: only the three B-cities.
		await page.goto(`${view_url}?letter=B`);
		for (const city of B_CITIES) {
			await expect(page.getByText(city, { exact: false })).toBeVisible();
		}
		await expect(page.getByText('Austin', { exact: false })).toHaveCount(0);

		// ?letter=C: only Chicago.
		await page.goto(`${view_url}?letter=C`);
		await expect(page.getByText('Chicago', { exact: false })).toBeVisible();
		await expect(page.getByText('Boston', { exact: false })).toHaveCount(0);
	});
});

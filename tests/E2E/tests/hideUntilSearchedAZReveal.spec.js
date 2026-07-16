import { test, expect } from '@playwright/test';
import { createAZCitiesView, B_CITIES } from '../helpers/test-helpers';

/**
 * An active A-Z filter (?letter=) must count as a search so a "Hide entries
 * until search" View reveals the matching entries. The widget registers
 * `letter` with core's search-request detection; without it, core never sees
 * the letter as a search and the View stays empty.
 */
test.describe('A-Z filter reveals hide-until-searched Views', () => {
	test('clicking a letter reveals only the matching entries', async ({ page }) => {
		const { view_url } = await createAZCitiesView({ hideUntilSearched: true });

		// 1. Nothing searched yet: entries are withheld, but the A-Z bar (a
		//    header widget) still renders so the visitor can search. Letters are
		//    uppercased with CSS (gv-uppercase), so the link's text is lowercase.
		await page.goto(view_url);
		const letterB = page.getByRole('link', { name: 'b', exact: true });
		await expect(letterB).toBeVisible();
		await expect(page.getByText('Boston', { exact: false })).toHaveCount(0);
		await expect(page.getByText('Austin', { exact: false })).toHaveCount(0);

		// 2. Click "B": the View reveals exactly the three B-cities and nothing
		//    else (no empty result, and no "everything" leak from a bogus filter).
		await letterB.click();
		await expect(page).toHaveURL(/[?&]letter=b/i);
		for (const city of B_CITIES) {
			await expect(page.getByText(city, { exact: false })).toBeVisible();
		}
		await expect(page.getByText('Austin', { exact: false })).toHaveCount(0);

		// 3. A different letter updates the results.
		await page.goto(`${view_url}?letter=A`);
		await expect(page.getByText('Austin', { exact: false })).toBeVisible();
		await expect(page.getByText('Boston', { exact: false })).toHaveCount(0);

		// 4. A letter with no matches reveals nothing.
		await page.goto(`${view_url}?letter=Z`);
		for (const city of ['Boston', 'Austin', 'Miami']) {
			await expect(page.getByText(city, { exact: false })).toHaveCount(0);
		}
	});
});

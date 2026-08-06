import { test, expect } from '@playwright/test';
import {
	createAZView,
	PAGING_ENTRIES,
	PAGING_B_CITIES,
	letterLink,
	showAllLink
} from '../helpers/test-helpers';

/**
 * Navigating with an active letter: clearing it restores the full list, a letter that
 * matches nothing shows the empty state rather than everything, and paging keeps the
 * letter applied.
 *
 * @see https://linear.app/gravitykit/issue/GVAZ-1
 */
test.describe('A-Z filter navigation', () => {
	test('"Show All" clears the letter and restores every entry', async ({ page }) => {
		const { view_url } = await createAZView({ title: 'AZ Reset Link' });

		await page.goto(`${view_url}?letter=b`);
		await expect(page.getByText('Boston', { exact: false })).toBeVisible();
		await expect(page.getByText('Austin', { exact: false })).toHaveCount(0);

		await showAllLink(page).first().click();

		await expect(page).not.toHaveURL(/[?&]letter=/i);

		for (const city of ['Boston', 'Austin', 'Chicago', 'Nashville']) {
			await expect(page.getByText(city, { exact: false })).toBeVisible();
		}
	});

	test('"Show All" is offered only while a letter is active', async ({ page }) => {
		const { view_url } = await createAZView({ title: 'AZ Reset Link Absent' });

		await page.goto(view_url);
		await expect(showAllLink(page)).toHaveCount(0);

		await page.goto(`${view_url}?letter=b`);
		await expect(showAllLink(page).first()).toBeVisible();
	});

	test('a letter with no matches shows the empty state, not every entry', async ({ page }) => {
		const { view_url } = await createAZView({ title: 'AZ Empty State' });

		await page.goto(`${view_url}?letter=z`);

		// The bar itself still renders so the visitor can pick another letter.
		await expect(letterLink(page.locator('ul.gravityview-az-filter').first(), 'b')).toBeVisible();

		for (const city of ['Boston', 'Austin', 'Chicago', 'Nashville', 'Miami']) {
			await expect(page.getByText(city, { exact: false })).toHaveCount(0);
		}

		// An active letter counts as a search, so the View reports an empty search
		// rather than an unfiltered empty directory.
		await expect(page.getByText(/returned no results/i).first()).toBeVisible();
	});

	test('the active letter survives paging to the second page', async ({ page }) => {
		const { view_url } = await createAZView({
			title: 'AZ Paging',
			entries: PAGING_ENTRIES,
			settings: { page_size: 2 },
			pageLinks: true
		});

		await page.goto(`${view_url}?letter=b`);

		// Five B-cities across pages of two, so page one holds a strict subset.
		const firstPage = [];

		for (const city of PAGING_B_CITIES) {
			if (await page.getByText(city, { exact: false }).count()) {
				firstPage.push(city);
			}
		}

		expect(firstPage).toHaveLength(2);

		await page.getByRole('link', { name: '2', exact: true }).first().click();

		await expect(page).toHaveURL(/[?&]letter=b/i);

		const secondPage = [];

		for (const city of PAGING_B_CITIES) {
			if (await page.getByText(city, { exact: false }).count()) {
				secondPage.push(city);
			}
		}

		expect(secondPage).toHaveLength(2);
		expect(secondPage.some((city) => firstPage.includes(city))).toBe(false);

		// Paging must not drop the filter and let a non-matching city through.
		for (const city of ['Austin', 'Chicago']) {
			await expect(page.getByText(city, { exact: false })).toHaveCount(0);
		}
	});
});

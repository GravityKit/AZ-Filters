import { test, expect } from '@playwright/test';
import { createAZView, EN_ALPHABET, LOCALIZED_ALPHABETS, letterLink } from '../helpers/test-helpers';

/**
 * The letter bar is the whole interface for the widget: it must render the configured
 * alphabet in full, honor the localization setting for non-English alphabets, and pick
 * up an alphabet added through the `gravityview_alphabets` filter.
 *
 * @see https://linear.app/gravitykit/issue/GVAZ-1
 */
test.describe('A-Z letter bar rendering', () => {
	test('renders every letter of the configured alphabet', async ({ page }) => {
		const { view_url } = await createAZView({ title: 'AZ English Alphabet' });

		await page.goto(view_url);

		// The list itself collapses to zero height around its floated items, so assert
		// on the letters it contains rather than on the list box.
		const bar = page.locator('ul.gravityview-az-filter').first();
		await expect(bar).toBeAttached();

		// The numbers bucket leads the bar, then all 26 English letters.
		await expect(letterLink(bar, '#')).toBeVisible();

		for (const letter of EN_ALPHABET) {
			await expect(letterLink(bar, letter)).toBeVisible();
		}

		await expect(bar.getByRole('listitem')).toHaveCount(EN_ALPHABET.length + 1);
	});

	for (const alphabet of LOCALIZED_ALPHABETS) {
		test(`renders and filters the ${alphabet.label} alphabet`, async ({ page }) => {
			const entries = alphabet.cities.map((city, index) => ({ 1: `Row ${index}`, 2: city }));

			const { view_url } = await createAZView({
				title: `AZ ${alphabet.label} Alphabet`,
				localization: alphabet.locale,
				entries
			});

			await page.goto(view_url);

			const bar = page.locator('ul.gravityview-az-filter').first();

			for (const letter of alphabet.sampleLetters) {
				await expect(letterLink(bar, letter)).toBeVisible();
			}

			// The English alphabet must not leak into a non-English bar.
			if ('sv_SE' !== alphabet.locale) {
				await expect(letterLink(bar, 'w')).toHaveCount(0);
			}

			await page.goto(`${view_url}?letter=${encodeURIComponent(alphabet.letter)}`);

			for (const city of alphabet.matching) {
				await expect(page.getByText(city, { exact: false })).toBeVisible();
			}

			await expect(page.getByText(alphabet.excluded, { exact: false })).toHaveCount(0);
		});
	}

	test('an alphabet added through the localization filter replaces the letters', async ({ page }) => {
		const { view_url } = await createAZView({
			title: 'AZ Custom Alphabet',
			localization: 'e2e_custom',
			entries: [
				{ 1: 'Row 0', 2: 'Xenia' },
				{ 1: 'Row 1', 2: 'York' },
				{ 1: 'Row 2', 2: 'Zurich' }
			]
		});

		await page.goto(view_url);

		const bar = page.locator('ul.gravityview-az-filter').first();

		for (const letter of ['x', 'y', 'z']) {
			await expect(letterLink(bar, letter)).toBeVisible();
		}

		// The filtered alphabet is only x, y and z, plus the numbers bucket.
		await expect(bar.getByRole('listitem')).toHaveCount(4);
		await expect(letterLink(bar, 'a')).toHaveCount(0);

		await page.goto(`${view_url}?letter=y`);
		await expect(page.getByText('York', { exact: false })).toBeVisible();
		await expect(page.getByText('Xenia', { exact: false })).toHaveCount(0);
	});
});

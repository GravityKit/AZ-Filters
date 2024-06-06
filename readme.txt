=== GravityView - A-Z Filters Extension ===
Tags: gravityview
Requires at least: 4.3
Tested up to: 6.0.2
Stable tag: trunk
Contributors: The GravityKit Team
License: GPL 3 or higher

Alphabetically filter your entries by letters of the alphabet.

== Installation ==

1. Upload plugin files to your plugins folder, or install using WordPress' built-in Add New Plugin installer
2. Activate the plugin
3. Follow the instructions

== Changelog ==

= 1.4.1 on June 6, 2024 =

This release addresses a minor issue with the A-Z Filters widget.

#### 🐛 Fixed
* Typo in a localization string used inside the A-Z Filters widget.

= 1.4 on June 5, 2024 =

This release adds the ability to filter entries by the "Created By" field.

#### 🚀 Added
* Support for filtering entries using the "Created By" field.

= 1.3.5 on December 22, 2023 =

This update brings a small yet important addition—a missing icon for the GravityView widget!

#### 🚀 Added
* Added the missing GravityView widget icon.

= 1.3.4 on March 15, 2022 =

* Added: Support for filtering by the Greek alphabet 🇬🇷

= 1.3.3 on September 27, 2022 =

* Fixed: In Divi and Kadence themes, clicking a filter would not filter results (it would scroll the page instead)

= 1.3.2 on July 31, 2022 =

* Fixed: Issues with plugin auto-updates that was introduced in version 1.2

= 1.3.1 on March 24, 2022 =

__Developer Updates:__

* Fixed: Using the deprecated `gravityview_blacklist_field_types` filter results in an incorrect list of fields that are available for filtering as well as a PHP error notice. (Note: please use the `gravityview_blocklist_field_types` filter instead!)

= 1.3 on March 15, 2022 =

* Added: Support for filtering by the Polish alphabet 🇵🇱
* Added: Support for filtering by the Ukrainian alphabet 🇺🇦
* Added: When filtering by a letter, the webpage will scroll back to the clicked link
* Modified: Localized numbers will be used for links instead of always 0-9. For example, in Bengali, the URL will now show `?letter=০-৯`
* Improved: Multiple A-Z Entry Filter widgets may be added to the same View using different languages
* Fixed: Support for custom collation overrides in situations where accented letters are shown in a filter for the other (for example, L and Ł in Polish).
* Updated translations

__Developer Updates:__

* Removed legacy query overrides in favor of exclusively relying on `GF_Query`. This may, in theory, affect some custom code. If it does, it will stop filtering values. If that happens, please let support@gravitykit.com know.
* Added: `gravityview/az_filter/collation` filter to override collation for the letter comparison query. This is helpful when the database interprets multiple letters as the same due to collation. For example, in Polish, L and Ł. This functionality requires Gravity Forms 2.4.3 or newer.
* Added: `gravityview/az_filter/anchor` filter to modify the anchor ID added to the end of the letter filter links. Return an empty string to remove the functionality.
* Deprecated: `gravityview_blacklist_field_types` filter. Use `gravityview_blocklist_field_types` instead.

= 1.2.1 on November 13, 2018 =

* Fixed: Bugs when using A-Z Filters in combination with Advanced Filters
* Added Polish translation by [@dariusz.zielonka](https://www.transifex.com/user/profile/dariusz.zielonka/)
* Updated translations - thank you, translators!
    - Chinese translated by [@michaeledi](https://www.transifex.com/user/profile/michaeledi/)
    - Russian translated by [@awsswa59](https://www.transifex.com/user/profile/awsswa59/)

= 1.2 on May 8, 2018 =

* Updated to work with GravityView 2.0
* Requires GravityView 2.0
* Requires PHP 5.3 or newer

__Developer Updates:__

* Added `$context` second parameter to the `gravityview_az_entry_args` filter

= 1.1.1.1 on April 28, 2018 =
* Added: Compatibility notice for GravityView 2.0

= 1.1.1 on April 23, 2018 =
* Fixed: Translation resource updated
* Fixed: Some strings not properly configured for translation
* If you want to help translate this extension, [join us on Transifex](https://www.transifex.com/katzwebservices/gravityview-az-filters/)

= 1.1 on April 18, 2018 =
* Added: Icelandic and Swedish alphabet support
* Fixed: Support for Gravity Forms 2.3
* Fixed: Clicking letter links would include the page number from the prior letter
* Fixed: Add custom CSS classes to the A-Z Widget wrapper HTML

= 1.0.8 on November 2, 2017 =
* Fixed: WordPress 4.8.3 introduced breaking change
* Updated translations: Turkish, Russian, Romanian, Portuguese (PT & BR), Dutch, German
* Updated extension framework for improved auto-updates

= 1.0.7 on September 28 =
* Fixed: Fatal error when GravityView is disabled

= 1.0.6 on August 18 =
* Fixed: Conflict with Gravity Forms 1.9.12+ preventing the A-Z filter from retrieving values

= 1.0.5 on July 20 =
* Fixed: Sanitize links to improve security
* Fixed: Link to "[Use this field to filter entries](https://docs.gravitykit.com/article/198-the-use-this-field-to-filter-entries-setting)" documentation
* Updated: Translations
    - Added Danish (thanks, [@jaegerbo](https://www.transifex.com/accounts/profile/jaegerbo/))

= 1.0.4 on April 10 =
* Fixed: Compatibility with GravityView 1.7.5
* Updated Hungarian translation (thanks, [@dbalage](https://www.transifex.com/accounts/profile/dbalage/)!)

= 1.0.3 on December 10 =
* Fixed: PHP warnings on Edit View page
* Modified: Use GravityView extension manager by default
* Modified: Use minified script in admin
* Updated translations:
    - Added Spanish (thanks, [@jorgepelaez](https://www.transifex.com/accounts/profile/jorgepelaez/))
    - Added Dutch (thanks, [@erikvanbeek](https://www.transifex.com/accounts/profile/erikvanbeek/))
    - Updated Bengali (thanks [@tareqhi](https://www.transifex.com/accounts/profile/tareqhi/))

= 1.0.2 on October 3 =
* Fixed: Added Bengali to language dropdown
* Updated translation files

= 1.0.1 on October 2 =
* Support non-Latin number sets
* Add Bengali alphabet and number set support
* Add additional debugging
* Add Bengali translation (thanks [@tareqhi](https://www.transifex.com/accounts/profile/tareqhi/))
* Add Finnish translation (thanks [@harjuja](https://www.transifex.com/accounts/profile/harjuja/))
* Add Turkish translation (thanks [@suhakaralar](https://www.transifex.com/accounts/profile/suhakaralar/))

= 1.0.0 on September 25 =
* Liftoff!

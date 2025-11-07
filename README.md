# Mosparo for Powermail

This extension integrates the [Mosparo](https://mosparo.org/) spam protection into the Powermail extension for TYPO3.

## Installation

1. Create a new mosparo project inside your Mosparo instance
2. Install the extension via the Extension Manager in TYPO3.
3. Configure the required typoscript constants in your TypoScript setup. You can find the necessary constants in the extension documentation or the example TypoScript provided in the extension.
4. Add the Mosparo captcha field to your Powermail forms. This can typically be done in the Powermail form editor by adding a new field and selecting the Mosparo captcha type.
5. Make sure to test the integration by submitting a form and verifying that the Mosparo captcha is working correctly.
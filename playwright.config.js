const baseConfig = require('@wordpress/e2e-test-utils-playwright/build/config');

module.exports = {
  ...baseConfig,
  testDir: './tests/e2e',
};

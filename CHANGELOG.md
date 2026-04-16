# Changelog

## 1.4.1

### Security

- Restricted the `violet` payment method from being available for internal use. It was discovered that the core `can_use_checkout` flag may not work as expected in some Magento instances. When this occurred, the payment method could appear in the Guest Carts API payment method listings (e.g. the `shipping-information` endpoint response) and be used to place orders outside of Violet's API flow. The method is now only usable programmatically through Violet's custom API endpoints.

### Changed

- Added `isAvailable()` override to `Violet` payment model to return `false`, preventing the method from appearing in any payment method listing.
- Added `can_use_internal=0` to payment configuration to explicitly block usage in the Magento admin panel.

## 1.4.0

- Direct order submission support.

## 1.0.0

- Initial release.

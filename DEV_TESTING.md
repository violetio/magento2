# Local Dev Testing Guide

## ngrok Setup (for Apple Pay testing)

Apple Pay requires HTTPS with a registered domain. ngrok exposes the local Docker Magento store.

### Start ngrok

```bash
ngrok http https://localhost
```

**Important:** Use `https://localhost` (not `http://localhost:80`) to avoid redirect loops. The TLS nginx container on port 80 redirects HTTP to HTTPS, which causes an infinite loop with ngrok.

### When you get a new ngrok URL

Every time ngrok restarts (free tier), you get a new subdomain. The following must be updated:

#### 1. Update Magento base URLs

```bash
docker exec magento2-fpm-1 php /app/bin/magento config:set web/unsecure/base_url https://{ngrok-domain}/
docker exec magento2-fpm-1 php /app/bin/magento config:set web/secure/base_url https://{ngrok-domain}/
docker exec magento2-fpm-1 php /app/bin/magento cache:flush
```

#### 2. Purge Varnish cache

```bash
docker exec magento2-varnish-1 varnishadm "ban req.url ~ ."
```

#### 3. Register domain with Stripe for Apple Pay

1. Go to [Stripe Dashboard > Settings > Payment method domains](https://dashboard.stripe.com/test/settings/payment_method_domains)
2. Add the new ngrok domain (e.g. `abc123.ngrok-free.app`)
3. Domain should show as **Enabled** automatically

**Tip:** Use a paid ngrok plan to pin a stable subdomain and avoid repeating these steps on every restart.

### Restore localhost after testing

```bash
docker exec magento2-fpm-1 php /app/bin/magento config:set web/unsecure/base_url http://localhost/
docker exec magento2-fpm-1 php /app/bin/magento config:set web/secure/base_url https://localhost/
docker exec magento2-fpm-1 php /app/bin/magento cache:flush
docker exec magento2-varnish-1 varnishadm "ban req.url ~ ."
```

## Stripe Config (already enabled)

These config values have been set in the local Magento store:

| Config Path | Value | Description |
|-------------|-------|-------------|
| `payment/stripe_payments/active` | `1` | Stripe payments enabled |
| `payment/stripe_payments_basic/stripe_mode` | `test` | Using Stripe test mode |
| `payment/stripe_payments_express/global_enabled` | `1` | Express Checkout enabled |
| `payment/stripe_payments_express/enabled` | `checkout_page` | Express buttons on checkout page |
| `payment/stripe_payments_express/apple_pay_enabled` | `1` | Apple Pay enabled |

## Apple Pay Requirements

- **Safari only** (not Chrome/Firefox)
- **Card in Apple Wallet** on your Mac (System Settings > Wallet & Apple Pay)
- Stripe test mode uses your real card but does not charge it

## Plugin Dev Workflow

### Sync plugin changes to local Magento

```bash
./sync-to-magento.sh          # one-time sync
./sync-to-magento.sh --watch  # continuous sync with fswatch
```

### After syncing code changes

```bash
# Clear generated interceptors (required after constructor changes)
docker exec magento2-fpm-1 bash -c "rm -rf /app/generated/code/Violet/"

# Flush all caches
docker exec magento2-fpm-1 php /app/bin/magento cache:flush

# Purge Varnish
docker exec magento2-varnish-1 varnishadm "ban req.url ~ ."
```

## Testing the Checkout Redirect (Phase 3)

### Create a test cart and get redirect URL

```bash
docker exec magento2-fpm-1 php -r "
require '/app/app/bootstrap.php';
\$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, \$_SERVER);
\$om = \$bootstrap->getObjectManager();
\$state = \$om->get(\Magento\Framework\App\State::class);
try { \$state->setAreaCode('frontend'); } catch (\Exception \$e) {}

\$quoteManagement = \$om->get(\Magento\Quote\Api\CartManagementInterface::class);
\$quoteId = \$quoteManagement->createEmptyCart();
\$quoteRepo = \$om->get(\Magento\Quote\Api\CartRepositoryInterface::class);
\$quote = \$quoteRepo->get(\$quoteId);
\$productRepo = \$om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
\$searchCriteria = \$om->get(\Magento\Framework\Api\SearchCriteriaBuilder::class)
    ->addFilter('type_id', 'simple')->addFilter('status', 1)->setPageSize(1)->create();
\$product = reset(\$productRepo->getList(\$searchCriteria)->getItems());
\$quote->setCustomerIsGuest(true)->setStoreId(1);
\$quote->addProduct(\$product, 1);
\$quote->collectTotals()->save();
\$mask = \$om->get(\Magento\Quote\Model\QuoteIdMaskFactory::class)->create();
\$mask->setQuoteId(\$quoteId)->save();
echo 'Redirect URL: https://localhost/ultraviolet/checkout?cart_token=' . \$mask->getMaskedId() . PHP_EOL;
"
```

### Test error cases

```bash
# Missing token -> redirects to /
curl -sk -D - https://localhost/ultraviolet/checkout | grep -E "^(HTTP|Location)"

# Invalid token -> redirects to /checkout/cart
curl -sk -D - "https://localhost/ultraviolet/checkout?cart_token=bogus" | grep -E "^(HTTP|Location)"
```

## Docker Container Reference

| Container | Purpose |
|-----------|---------|
| `magento2-tls-1` | TLS termination nginx (ports 80, 443) |
| `magento2-varnish-1` | Varnish cache |
| `magento2-web-1` | Web nginx |
| `magento2-fpm-1` | PHP-FPM (run `php` commands here) |
| `magento2-db-1` | MySQL |
| `magento2-opensearch-1` | OpenSearch |
| `magento2-redis-1` | Redis |

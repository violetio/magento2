<?php
/**
 * Convert the T-Shirt from a simple product with a required "Size" custom option
 * into a configurable product with child simple products for each size (S, M, L, XL).
 *
 * Run inside the Magento container:
 *   php /app/setup-configurable-tshirt.php
 */

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get(\Magento\Framework\App\State::class);

try {
    $state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
} catch (\Exception $e) {
    // already set
}

$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$eavSetup = $objectManager->get(\Magento\Eav\Setup\EavSetup::class);
$eavConfig = $objectManager->get(\Magento\Eav\Model\Config::class);
$attributeSetId = $objectManager->get(\Magento\Catalog\Model\Product::class)->getDefaultAttributeSetId();

// ──────────────────────────────────────────────────
// Step 1: Create "tshirt_size" attribute if missing
// ──────────────────────────────────────────────────

$attrCode = 'tshirt_size';
// Check via DB connection since EAV config cache can be unreliable
$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();
$attrRow = $connection->fetchRow(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = '$attrCode' AND entity_type_id = 4"
);

if (!$attrRow) {
    echo "Creating '$attrCode' attribute...\n";
    $eavSetup->addAttribute(
        \Magento\Catalog\Model\Product::ENTITY,
        $attrCode,
        [
            'type'                    => 'int',
            'label'                   => 'Size',
            'input'                   => 'select',
            'required'                => false,
            'user_defined'            => true,
            'searchable'              => true,
            'filterable'              => true,
            'comparable'              => false,
            'visible_in_advanced_search' => true,
            'used_in_product_listing' => true,
            'is_used_in_grid'         => true,
            'is_filterable_in_grid'   => true,
            'option'                  => [
                'values' => ['S', 'M', 'L', 'XL'],
            ],
            'global'                  => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'apply_to'                => 'simple',
        ]
    );
    echo "Attribute created.\n";
} else {
    echo "Attribute '$attrCode' already exists (id={$attrRow['attribute_id']}).\n";
}

$attribute = $eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $attrCode);
echo "Attribute id={$attribute->getId()}\n";

// Reload attribute to get fresh option IDs
$attribute = $eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $attrCode);
$options = $attribute->getSource()->getAllOptions(false);
$optionMap = [];
foreach ($options as $opt) {
    $optionMap[$opt['label']] = $opt['value'];
}
echo "Size options: " . json_encode($optionMap) . "\n";

// Assign attribute to the default attribute set/group
$attributeSetFactory = $objectManager->get(\Magento\Eav\Model\Entity\Attribute\SetFactory::class);
$attributeGroupFactory = $objectManager->get(\Magento\Eav\Model\Entity\Attribute\GroupFactory::class);

$eavSetup->addAttributeToGroup(
    \Magento\Catalog\Model\Product::ENTITY,
    $attributeSetId,
    'General',
    $attrCode,
    100
);
echo "Attribute assigned to default attribute set.\n";

// ──────────────────────────────────────────────────
// Step 2: Remove the old custom option from product 1
// ──────────────────────────────────────────────────

echo "\nRemoving custom 'Size' option from T-Shirt...\n";
$tshirt = $productRepository->get('UCP-TSHIRT-001');
$customOptions = $tshirt->getOptions();
if ($customOptions) {
    foreach ($customOptions as $option) {
        echo "  Deleting custom option: {$option->getTitle()} (id={$option->getOptionId()})\n";
        $option->delete();
    }
}
$tshirt->setOptions([]);
$tshirt->setHasOptions(false);
$tshirt->setRequiredOptions(false);
$productRepository->save($tshirt);
echo "Custom options removed.\n";

// ──────────────────────────────────────────────────
// Step 3: Create child simple products for each size
// ──────────────────────────────────────────────────

echo "\nCreating child simple products...\n";

$sizes = ['S', 'M', 'L', 'XL'];
$baseSku = 'UCP-TSHIRT-001';
$basePrice = 19.99;
$childIds = [];

$stockRegistry = $objectManager->get(\Magento\CatalogInventory\Api\StockRegistryInterface::class);
$productFactory = $objectManager->get(\Magento\Catalog\Api\Data\ProductInterfaceFactory::class);

foreach ($sizes as $size) {
    $childSku = $baseSku . '-' . $size;

    try {
        $existing = $productRepository->get($childSku);
        echo "  $childSku already exists (id={$existing->getId()}), skipping.\n";
        $childIds[] = $existing->getId();
        continue;
    } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
        // Good — doesn't exist yet
    }

    $child = $productFactory->create();
    $child->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
        ->setAttributeSetId($attributeSetId)
        ->setSku($childSku)
        ->setName("Basic Cotton T-Shirt - $size")
        ->setPrice($basePrice)
        ->setWeight(0.5)
        ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE)
        ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
        ->setData($attrCode, $optionMap[$size])
        ->setStockData([
            'use_config_manage_stock' => 0,
            'manage_stock'            => 1,
            'is_in_stock'             => 1,
            'qty'                     => 100,
        ]);

    $saved = $productRepository->save($child);
    $childIds[] = $saved->getId();
    echo "  Created $childSku (id={$saved->getId()}, size=$size)\n";
}

echo "Child product IDs: " . implode(', ', $childIds) . "\n";

// ──────────────────────────────────────────────────
// Step 4: Convert parent to configurable + link children
// ──────────────────────────────────────────────────

echo "\nConverting UCP-TSHIRT-001 to configurable...\n";

// We need to update the type_id directly because Magento won't let you
// change type via the repository easily. Use resource model.
$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();
$connection->update(
    $resource->getTableName('catalog_product_entity'),
    ['type_id' => 'configurable'],
    ['sku = ?' => $baseSku]
);

// Reload the product as configurable
$eavConfig->clear();
$parent = $productRepository->get($baseSku, true, null, true);
echo "Product type is now: {$parent->getTypeId()}\n";

// Build option values array from the size options
$optionValues = [];
foreach ($optionMap as $label => $valueIndex) {
    $optionValues[] = [
        'label'         => $label,
        'attribute_id'  => $attribute->getId(),
        'value_index'   => $valueIndex,
        'is_percent'    => 0,
        'pricing_value' => 0,
    ];
}

$configurableAttributeData = [
    [
        'attribute_id' => $attribute->getId(),
        'code'         => $attribute->getAttributeCode(),
        'label'        => $attribute->getStoreLabel(),
        'position'     => 0,
        'values'       => $optionValues,
    ],
];

$configurableOptionsFactory = $objectManager->get(
    \Magento\ConfigurableProduct\Helper\Product\Options\Factory::class
);
$configurableOptions = $configurableOptionsFactory->create($configurableAttributeData);
$extensionAttributes = $parent->getExtensionAttributes();
$extensionAttributes->setConfigurableProductOptions($configurableOptions);
$extensionAttributes->setConfigurableProductLinks($childIds);
$parent->setExtensionAttributes($extensionAttributes);

$productRepository->save($parent);
echo "Configurable product saved with " . count($childIds) . " children.\n";

// ──────────────────────────────────────────────────
// Step 5: Verify
// ──────────────────────────────────────────────────

echo "\n=== Verification ===\n";
$final = $productRepository->get($baseSku, true, null, true);
echo "SKU: {$final->getSku()}\n";
echo "Type: {$final->getTypeId()}\n";
echo "Name: {$final->getName()}\n";

$ext = $final->getExtensionAttributes();
$links = $ext->getConfigurableProductLinks();
echo "Child links: " . ($links ? implode(', ', $links) : 'none') . "\n";

$opts = $ext->getConfigurableProductOptions();
if ($opts) {
    foreach ($opts as $opt) {
        echo "Config attribute: {$opt->getLabel()} (attr_id={$opt->getAttributeId()})\n";
        foreach ($opt->getValues() as $val) {
            echo "  option_id={$val->getValueIndex()}\n";
        }
    }
}

echo "\nDone! T-Shirt is now a configurable product with size variants.\n";
echo "Violet needs to re-sync products so each size gets its own SKU.\n";

<?php

/**
 * AddSimpleProduct Data Patch
 *
 * @category     Scandiweb
 * @package      Scandiweb_ScandiwebTest
 * @author       Valdemars Udris <info@scandiweb.com>
 * @copyright    Copyright (c) 2024 Scandiweb, Inc (https://scandiweb.com)
 */

declare(strict_types=1);

namespace Scandiweb\ScandiwebTest\Setup\Patch\Data;

use Exception;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\State;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\StoreManagerInterface;

class CreateAndAssignSimpleProduct implements DataPatchInterface
{
    /**
     * Product attributes used for creating and configuring a simple product.
     */
    private const PRODUCT_ATTRIBUTES = [
        'sku' => '2002',
        'name' => 'Simple Product Example',
        'url_key' => 'refactored-product',
        'price' => 99.99,
        'quantity' => 150,
        'source_code' => 'default',
        'category_name' => 'Men',
        'attribute_set' => 'Default',
    ];

    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $setup;

    /**
     * @var ProductInterfaceFactory
     */
    private ProductInterfaceFactory $productInterfaceFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var State
     */
    private State $appState;

    /**
     * @var EavSetup
     */
    private EavSetup $eavSetup;

    /**
     * @var CategoryLinkManagementInterface
     */
    private CategoryLinkManagementInterface $categoryLink;

    /**
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var SourceItemInterfaceFactory
     */
    private SourceItemInterfaceFactory $sourceItemFactory;

    /**
     * @var SourceItemsSaveInterface
     */
    private SourceItemsSaveInterface $sourceItemsSaveInterface;

    /**
     * @var array
     */
    private array $sourceItems = [];

    public function __construct(
        ModuleDataSetupInterface $setup,
        ProductInterfaceFactory $productInterfaceFactory,
        ProductRepositoryInterface $productRepository,
        State $appState,
        EavSetup $eavSetup,
        CategoryLinkManagementInterface $categoryLink,
        CategoryCollectionFactory $categoryCollectionFactory,
        StoreManagerInterface $storeManager,
        SourceItemInterfaceFactory $sourceItemFactory,
        SourceItemsSaveInterface $sourceItemsSaveInterface
    )
    {
        $this->setup = $setup;
        $this->productInterfaceFactory = $productInterfaceFactory;
        $this->productRepository = $productRepository;
        $this->appState = $appState;
        $this->eavSetup = $eavSetup;
        $this->categoryLink = $categoryLink;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->storeManager = $storeManager;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->sourceItemsSaveInterface = $sourceItemsSaveInterface;
    }

    /**
     * Apply the patch.
     *
     * @throws Exception
     */
    public function apply(): void
    {
        $this->appState->emulateAreaCode('adminhtml', function () {
            $this->createSimpleProduct();
        });
    }

    /**
     * Create the simple product and assign categories.
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws StateException
     */
    private function createSimpleProduct(): void
    {
        if ($this->productExists(self::SKU)) {
            return;
        }

        $product = $this->initializeProduct();
        $this->productRepository->save($product);

        $this->assignInventory($product);
        $this->assignCategories($product);
    }

    /**
     * Check if the product already exists.
     *
     * @param string $sku
     * @return bool
     */
    private function productExists(string $sku): bool
    {
        return (bool)$this->productRepository->get($sku, false)->getId();
    }

    /**
     * Initialize the product object.
     *
     * @return ProductInterface
     * @throws LocalizedException
     */
    private function initializeProduct(): ProductInterface
    {
        $product = $this->productInterfaceFactory->create();
        $attributeSetId = $this->eavSetup->getAttributeSetId(
            ProductInterface::ENTITY,
            self::PRODUCT_ATTRIBUTES['attribute_set']
        );

        return $product->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId($attributeSetId)
            ->setSku(self::PRODUCT_ATTRIBUTES['sku'])
            ->setName(self::PRODUCT_ATTRIBUTES['name'])
            ->setUrlKey(self::PRODUCT_ATTRIBUTES['url_key'])
            ->setPrice(self::PRODUCT_ATTRIBUTES['price'])
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED);
    }

    private function assignInventory(ProductInterface $product): void
    {
        $sourceItem = $this->sourceItemFactory->create();
        $sourceItem->setSourceCode(self::PRODUCT_ATTRIBUTES['source_code'])
            ->setQuantity(self::PRODUCT_ATTRIBUTES['quantity'])
            ->setSku($product->getSku())
            ->setStatus(SourceItemInterface::STATUS_IN_STOCK);

        $this->sourceItems[] = $sourceItem;
        $this->sourceItemsSaveInterface->execute($this->sourceItems);
    }

    private function assignCategories(ProductInterface $product): void
    {
        $categoryIds = $this->categoryCollectionFactory->create()
            ->addAttributeToFilter('name', ['in' => self::PRODUCT_ATTRIBUTES['category_name']])
            ->getAllIds();

        $this->categoryLink->assignProductToCategories($product->getSku(), $categoryIds);
    }


    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }
}

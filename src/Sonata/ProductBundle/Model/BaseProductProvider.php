<?php
/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\ProductBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Component\Currency\CurrencyPriceCalculatorInterface;
use Sonata\Component\Delivery\ServiceDeliveryInterface;
use Sonata\Component\Product\ProductCategoryManagerInterface;
use Sonata\Component\Product\ProductInterface;
use Sonata\Component\Order\OrderInterface;
use Sonata\Component\Order\OrderElementInterface;
use Sonata\Component\Product\ProductProviderInterface;
use Sonata\Component\Basket\BasketElementInterface;
use Sonata\Component\Basket\BasketInterface;
use Sonata\Component\Basket\BasketElement;
use Sonata\AdminBundle\Validator\ErrorElement;
use Symfony\Component\Form\FormBuilder;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Component\Product\ProductCollectionManagerInterface;

use Sonata\Component\Basket\BasketElementManagerInterface;

use JMS\Serializer\SerializerInterface;

abstract class BaseProductProvider implements ProductProviderInterface
{
    /**
     * @var array
     */
    protected $options           = array();

    /**
     * @var array
     */
    protected $variationFields   = array();

    /**
     * @var string
     */
    protected $code;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var ProductCategoryManagerInterface
     */
    protected $productCategoryManager;

    /**
     * @var ProductCollectionManagerInterface
     */
    protected $productCollectionManager;

    /**
     * @var BasketElementManagerInterface
     */
    protected $basketElementManager;

    /**
     * @var string
     */
    protected $orderElementClassName;

    /**
     * @var CurrencyPriceCalculatorInterface
     */
    protected $currencyPriceCalculator;

    /**
     * @param \JMS\Serializer\SerializerInterface $serializer
     */
    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @param \Sonata\Component\Currency\CurrencyPriceCalculatorInterface $currencyPriceCalculator
     */
    public function setCurrencyPriceCalculator(CurrencyPriceCalculatorInterface $currencyPriceCalculator)
    {
        $this->currencyPriceCalculator = $currencyPriceCalculator;
    }

    /**
     * @return \Sonata\Component\Currency\CurrencyPriceCalculatorInterface
     */
    public function getCurrencyPriceCalculator()
    {
        return $this->currencyPriceCalculator;
    }

    /**
     * @param  \Sonata\Component\Basket\BasketElementManagerInterface $basketElementManager
     * @return void
     */
    public function setBasketElementManager(BasketElementManagerInterface $basketElementManager)
    {
        $this->basketElementManager = $basketElementManager;
    }

    /**
     * @param string $orderElementClassName
     */
    public function setOrderElementClassName($orderElementClassName)
    {
        $this->orderElementClassName = $orderElementClassName;
    }

    /**
     * @return \Sonata\Component\Basket\BasketElementManagerInterface
     */
    public function getBasketElementManager()
    {
        return $this->basketElementManager;
    }

    /**
     * {@inheritdoc}
     */
    public function setProductCategoryManager(ProductCategoryManagerInterface $productCategoryManager)
    {
        $this->productCategoryManager = $productCategoryManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getProductCategoryManager()
    {
        return $this->productCategoryManager;
    }

    /**
     * {@inheritdoc}
     */
    public function setProductCollectionManager(ProductCollectionManagerInterface $productCollectionManager)
    {
        $this->productCollectionManager = $productCollectionManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getProductCollectionManager()
    {
        return $this->productCollectionManager;
    }

    /**
     * @param  array $options
     * @return void
     */
    public function setOptions(array $options = array())
    {
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param  string     $name
     * @param  mixed      $default
     * @return array|null
     */
    public function getOption($name, $default = null)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    ////////////////////////////////////////////////
    //   ORDER RELATED FUNCTIONS

    /**
     * @param \Sonata\Component\Basket\BasketElementInterface $basketElement
     * @param string                                          $format
     *
     * @return \Sonata\Component\Order\OrderElementInterface
     */
    public function createOrderElement(BasketElementInterface $basketElement, $format = 'json')
    {
        $orderElement = new $this->orderElementClassName;
        $orderElement->setQuantity($basketElement->getQuantity());
        $orderElement->setPrice($basketElement->getTotal(false));
        $orderElement->setVat($basketElement->getVat());
        $orderElement->setDesignation($basketElement->getName());
        $orderElement->setProductType($this->getCode());
        $orderElement->setStatus(OrderInterface::STATUS_PENDING);
        $orderElement->setDeliveryStatus(ServiceDeliveryInterface::STATUS_OPEN);
        $orderElement->setCreatedAt(new \DateTime);
        $orderElement->setOptions($basketElement->getOptions());

        $product = $basketElement->getProduct();
        $orderElement->setDescription($product->getDescription());
        $orderElement->setProductId($product->getId());
        $orderElement->setRawProduct($this->getRawProduct($product, $format));

        return $orderElement;
    }

    /**
     * @param  \Sonata\Component\Product\ProductInterface $product
     * @param  string                                     $format
     * @return array
     */
    public function getRawProduct(ProductInterface $product, $format = 'json')
    {
        return json_decode($this->serializer->serialize($product, $format), true);
    }

    /**
     * @param OrderElementInterface $orderElement
     * @param string                $type
     * @param string                $format
     *
     * @return \Sonata\Component\Product\ProductInterface
     */
    public function getProductFromRaw(OrderElementInterface $orderElement, $type, $format = 'json')
    {
        return $this->serializer->deserialize(json_encode($orderElement->getRawProduct()), $type, $format);
    }

    ////////////////////////////////////////////////
    //   VARIATION RELATED FUNCTIONS

    /**
     * @param  $name
     * @return bool return true if the field $name is a variation
     */
    public function isVariateBy($name)
    {
        return in_array($name, $this->variationFields);
    }

    /**
     * @return bool return true if the product haas some variation fields
     */
    public function hasVariationFields()
    {
        return count($this->getVariationFields()) > 0;
    }

    /**
     * @param  array $fields
     * @return void
     */
    public function setVariationFields(array $fields = array())
    {
        $this->variationFields = $fields;
    }

    /**
     * @return array
     */
    public function getVariationFields()
    {
        return $this->variationFields;
    }

    /**
     * {@inheritdoc}
     */
    public function buildEditForm(FormMapper $formMapper, $isVariation = false)
    {
        $formMapper->with('Product');

        $formMapper->add('name');
        $formMapper->add('sku');

        if (!$isVariation || in_array('description', $this->variationFields)) {
            $formMapper->add('description', 'sonata_formatter_type', array(
                'source_field'         => 'rawDescription',
                'source_field_options' => array('attr' => array('class' => 'span10', 'rows' => 20)),
                'format_field'         => 'descriptionFormatter',
                'target_field'         => 'description',
                'event_dispatcher'     => $formMapper->getFormBuilder()->getEventDispatcher()
            ));
        }

        $formMapper
            ->add('price', 'number')
            ->add('vat',   'number')
            ->add('stock', 'integer')
        ;

        if (!$isVariation || in_array('image', $this->variationFields)) {
            $formMapper->add('image', 'sonata_type_model_list', array(
                'required' => false
            ), array(
                'link_parameters' => array(
                    'context'  => 'sonata_product',
                    'filter'   => array('context' => array('value' => 'sonata_product')),
                    'provider' => ''
                )
            ));
        }

        $formMapper->add('enabled');

        $formMapper->end();

        $formMapper
            ->with('Categories')
                ->add('productCategories', 'sonata_type_collection', array(
                    'required' => false,
                    'by_reference' => false,
                ), array(
                    'edit' => 'inline',
                    'inline' => 'table',
                    'link_parameters' => array('provider' => $this->getCode())
                ))
            ->end()
            ->with('Collections')
                ->add('productCollections', 'sonata_type_collection', array(
                    'required' => false,
                    'by_reference' => false,
                ), array(
                    'edit' => 'inline',
                    'inline' => 'table',
                    'link_parameters' => array('provider' => $this->getCode())
                ))
            ->end()
            ->with('Deliveries')
                ->add('deliveries', 'sonata_type_collection', array(
                    'required' => false,
                    'by_reference' => false,
                ), array(
                    'edit' => 'inline',
                    'inline' => 'table',
                    'link_parameters' => array('provider' => $this->getCode())
                ))
            ->end()
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function buildCreateForm(FormMapper $formMapper)
    {
        $this->buildEditForm($formMapper);
    }

    /**
     * {@inheritdoc}
     */
    public function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('name')
            ->add('sku')
            ->add('description')
            ->add('price')
            ->add('number')
            ->add('vat')
            ->add('stock')
            ->add('enabled')
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function createVariation(ProductInterface $product, $copyDependencies = true)
    {
        if ($product->isVariation()) {
            throw new \RuntimeException('Cannot create a variation from a variation product');
        }

        $variation = clone $product;
        $variation->setParent($product);
        $variation->setId(null);
        $variation->setVariations(new ArrayCollection());
        $variation->setDeliveries(new ArrayCollection());
        $variation->setProductCategories(new ArrayCollection());
        $variation->setProductCollections(new ArrayCollection());
        $variation->setPackages(new ArrayCollection());

        $product->addVariation($variation);

        $variationCollection = new ArrayCollection(array($variation));

        $this->synchronizeVariationsProduct($product, $variationCollection);

        if ($copyDependencies) {
            $this->synchronizeVariationsDeliveries($product, $variationCollection);
            $this->synchronizeVariationsPackages($product, $variationCollection);
            $this->synchronizeVariationsCategories($product, $variationCollection);
            $this->synchronizeVariationsCollections($product, $variationCollection);
        }

        $variation->setEnabled(false);
        $variation->setName(sprintf('%s (duplicated)', $product->getName()));

        return $variation;
    }

    /**
     * {@inheritdoc}
     */
    public function synchronizeVariations(ProductInterface $product, ArrayCollection $variations = null)
    {
        $this->synchronizeVariationsProduct($product, $variations);
        $this->synchronizeVariationsDeliveries($product, $variations);
        $this->synchronizeVariationsCategories($product, $variations);
        $this->synchronizeVariationsPackages($product, $variations);
        $this->synchronizeVariationsCollections($product, $variations);
    }

    /**
     * {@inheritdoc}
     */
    public function synchronizeVariationsProduct(ProductInterface $product, ArrayCollection $variations = null)
    {
        $variationFields = array_merge(array('id', 'parent'), $this->getVariationFields());

        $values = $product->toArray();

        foreach ($variationFields as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }

            unset($values[$field]);
        }

        if (!$variations) {
            $variations = $product->getVariations();
        }

        foreach ($variations as $variation) {
            foreach ($values as $name => $value) {
                $callable = array($variation, sprintf('set%s', $name));

                if (is_callable($callable)) {
                    call_user_func($callable, $value);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function synchronizeVariationsDeliveries(ProductInterface $product, ArrayCollection $variations = null)
    {
        if (!$variations) {
            $variations = $product->getVariations();
        }

        $productDeliveries = $product->getDeliveries();

        /** @var ProductInterface $variation */
        foreach ($variations as $variation) {
            $variationDeliveries = $variation->getDeliveries();

            // browsing variation deliveries and remove excessing deliveries
            foreach ($variationDeliveries as $productDelivery) {
                if ($productDelivery && !$productDeliveries->contains($productDelivery)) {
                    $variation->removeDelivery($productDelivery);
                }
            }

            // browsing Product deliveries and add missing deliveries
            foreach ($productDeliveries as $productDelivery) {
                if ($productDelivery && !$variationDeliveries->contains($productDelivery)) {
                    $delivery = clone $productDelivery;
                    $variation->addDelivery($delivery);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function synchronizeVariationsCategories(ProductInterface $product, ArrayCollection $variations = null)
    {
        if (!$variations) {
            $variations = $product->getVariations();
        }

        $productCategories = $product->getCategories();

        /** @var ProductInterface $variation */
        foreach ($variations as $variation) {
            $variationCategories = $variation->getCategories();

            // browsing variation categories and remove excessing categories
            foreach ($variationCategories as $variationCategory) {
                if ($variationCategory && !$productCategories->contains($variationCategory)) {
                    $this->productCategoryManager->removeCategoryFromProduct($variation, $variationCategory);
                }
            }

            // browsing Product categories and add missing categories
            foreach ($productCategories as $productCategory) {
                if ($productCategory && !$variationCategories->contains($productCategory)) {
                    $this->productCategoryManager->addCategoryToProduct($variation, $productCategory);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function synchronizeVariationsCollections(ProductInterface $product, ArrayCollection $variations = null)
    {
        if (!$variations) {
            $variations = $product->getVariations();
        }

        $productCollections = $product->getCollections();

        /** @var ProductInterface $variation */
        foreach ($variations as $variation) {
            $variationCollections = $variation->getCollections();

            // browsing variation collections and remove excessing collections
            foreach ($variationCollections as $variationCollection) {
                if ($variationCollection && !$productCollections->contains($variationCollection)) {
                    $this->productCollectionManager->removeCollectionFromProduct($variation, $variationCollection);
                }
            }

            // browsing Product collections and add missing collections
            foreach ($productCollections as $productCollection) {
                if ($productCollection && !$variationCollections->contains($productCollection)) {
                    $this->productCollectionManager->addCollectionToProduct($variation, $productCollection);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function synchronizeVariationsPackages(ProductInterface $product, ArrayCollection $variations = null)
    {
        if (!$variations) {
            $variations = $product->getVariations();
        }

        $productPackages = $product->getPackages();

        /** @var ProductInterface $variation */
        foreach ($variations as $variation) {
            $variationPackages = $variation->getPackages();

            // browsing variation packages and remove excessing packages
            foreach ($variationPackages as $variationPackage) {
                if ($variationPackage && !$productPackages->contains($variationPackage)) {
                    $variation->removePackage($variationPackage);
                }
            }

            // browsing Product packages and add missing packages
            foreach ($productPackages as $productPackage) {
                if ($productPackage && !$variationPackages->contains($productPackage)) {
                    $package = clone $productPackage;
                    $variation->addPackage($package);
                }
            }
        }
    }

    /////////////////////////////////////////////////////
    // BASKET RELATED FUNCTIONS

    /**
     * (non-PHPdoc)
     * @see \Sonata\Component\Product\ProductProviderInterface::createBasketElement()
     */
    public function createBasketElement(ProductInterface $product = null, array $options = array())
    {
        $basketElement = $this->getBasketElementManager()->create();
        $this->buildBasketElement($basketElement, $product, $options);

        return $basketElement;
    }

    /**
     * {@inheritdoc}
     */
    public function buildBasketElement(BasketElementInterface $basketElement, ProductInterface $product = null, array $options = array())
    {
        if ($product) {
            $basketElement->setProduct($this->code, $product);

            if (!$basketElement->getQuantity()) {
                $basketElement->setQuantity(1);
            }
        }

        $basketElement->setOptions($options);
    }

    /**
     * This function return the form used in the product view page
     *
     * @param  \Sonata\Component\Product\ProductInterface $product
     * @param  \Symfony\Component\Form\FormBuilder        $formBuilder
     * @param  array                                      $options
     * @return void
     */
    public function defineAddBasketForm(ProductInterface $product, FormBuilder $formBuilder, array $options = array())
    {
        $basketElement = $this->createBasketElement($product);

        // create the product form
        $formBuilder
            ->setData($basketElement)
            ->add('quantity', 'text')
            ->add('productId', 'hidden');
    }

    /**
     * @param  \Sonata\Component\Basket\BasketElementInterface $basketElement
     * @param  \Symfony\Component\Form\FormBuilder             $formBuilder
     * @param  array                                           $options
     * @return void
     */
    public function defineBasketElementForm(BasketElementInterface $basketElement, FormBuilder $formBuilder, array $options = array())
    {
        $formBuilder
            ->add('delete', 'checkbox')
            ->add('quantity', 'text')
            ->add('productId', 'hidden');
    }

    /**
     * {@inheritdoc}
     */
    public function validateFormBasketElement(ErrorElement $errorElement, BasketElementInterface $basketElement, BasketInterface $basket)
    {
        // the item is flagged as deleted, no need to validate the item
        if ($basketElement->getDelete()) {
            return;
        }

        // refresh the product from the database
        $product = $basketElement->getProduct();

        // check if the product is still in database
        if (!$product) {
            $errorElement->addViolation('product_not_available');

            return;
        }

        // check if the product is still enabled
        if (!$basketElement->getProduct()->getEnabled()) {
            $errorElement->addViolation('product_not_enabled');

            return;
        }

        // check if the quantity is numeric
        if (!is_numeric($basketElement->getQuantity())) {
            $errorElement
                ->with('quantity')
                    ->addViolation('product_quantity_not_numeric')
                ->end();

            return;
        }

        $errorElement
            ->with('quantity')
                ->assertRange(
                    array(
                        'min' => 1,
                        'max' => $this->getStockAvailable($basketElement->getProduct()),
                        'minMessage' => 'basket_quantity_limit_min',
                        'maxMessage' => 'basket_quantity_limit_max'
                    )
                )
            ->end();
    }

    /**
     * Returns true if the basket element is still valid
     *
     * @param  \Sonata\Component\Basket\BasketInterface             $basket
     * @param  \Sonata\Component\Product\ProductInterface           $product
     * @param  \Sonata\Component\Basket\BasketElementInterface      $basketElement
     * @return bool|\Sonata\Component\Basket\BasketElementInterface
     */
    public function basketAddProduct(BasketInterface $basket, ProductInterface $product, BasketElementInterface $basketElement)
    {
        if ($basket->hasProduct($product)) {
            return false;
        }

        $basketElementOptions = $product->getOptions();
        // add the default product options to the basket element
        if (is_array($basketElementOptions) && !empty($basketElementOptions)) {
            foreach ($basketElementOptions as $option => $value) {
                $basketElement->setOption($option, $value);
            }
        }

        $basket->addBasketElement($basketElement);

        return $basketElement;
    }

    /**
     * Merge a product with another when the product is already present into the basket
     *
     *
     * @param \Sonata\Component\Basket\BasketInterface        $basket
     * @param \Sonata\Component\Product\ProductInterface      $product
     * @param \Sonata\Component\Basket\BasketElementInterface $newBasketElement
     *
     * @throws \RuntimeException
     *
     * @return bool|\Sonata\Component\Basket\BasketElementInterface
     */
    public function basketMergeProduct(BasketInterface $basket, ProductInterface $product, BasketElementInterface $newBasketElement)
    {
        if (!$basket->hasProduct($product)) {
            return false;
        }

        $basketElement = $basket->getElement($product);
        if (!$basketElement) {
            throw new \RuntimeException('no basket element related to product.id : %s', $product->getId());
        }

        $basketElement->setQuantity($basketElement->getQuantity() + $newBasketElement->getQuantity());

        return $basketElement;
    }

    /**
     * @param \Sonata\Component\Basket\BasketElementInterface $basketElement
     *
     * @return boolean true if the basket element is still valid
     */
    public function isValidBasketElement(BasketElementInterface $basketElement)
    {
        $product = $basketElement->getProduct();

        if (!$product instanceof ProductInterface) {
            return false;
        }

        return true;
    }

    /**
     * @param \Sonata\Component\Basket\BasketInterface        $basket
     * @param \Sonata\Component\Basket\BasketElementInterface $basketElement
     *
     * @return float price of the basket element
     */
    public function basketCalculatePrice(BasketInterface $basket, BasketElementInterface $basketElement)
    {
        return $this->currencyPriceCalculator->getPrice($basketElement->getProduct(), $basket->getCurrency());
    }

    /**
     * Return true if the product can be added to the provided basket
     *
     * @param \Sonata\Component\Basket\BasketInterface   $basket
     * @param \Sonata\Component\Product\ProductInterface $product
     * @param array                                      $options
     *
     * @return boolean
     */
    public function isAddableToBasket(BasketInterface $basket, ProductInterface $product, array $options = array())
    {
        return true;
    }

    /**
     * return a fresh product instance (so information are reloaded: enabled and stock ...)
     *
     * @param  \Sonata\Component\Product\ProductInterface $product
     * @return \Sonata\Component\Product\ProductInterface
     */
    public function reloadProduct(ProductInterface $product)
    {
        return $this->findOneById($product->getId());
    }

    /**
     * @param  integer $id
     * @return bool
     */
    public function findOneById($id)
    {
        $results = $this->createQueryBuilder('p')
            ->addSelect('i')
            ->leftJoin('p.image', 'i')
            ->andWhere('p.id = :id')
            ->getQuery()
            ->setParameters(array('id' => $id))
            ->setMaxResults(1)
            ->execute();

        return count($results) > 0 ? $results[0] : false;
    }

    /**
     * return the stock available for the current product
     *
     * @param  \Sonata\Component\Product\ProductInterface $product
     * @return int                                        the stock available
     */
    public function getStockAvailable(ProductInterface $product)
    {
        return $product->getStock();
    }

    /**
     * @param string $code
     * @return void
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }
}

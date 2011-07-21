<?php

/*
 * This file is part of the Sonata project.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\BasketBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Sonata\Component\Basket\BasketInterface;
use Sonata\Component\Customer\AddressManagerInterface;
use Sonata\Component\Customer\AddressInterface;
use Sonata\Component\Delivery\Pool as DeliveryPool;
use Sonata\Component\Form\Transformer\DeliveryMethodTransformer;
use Sonata\Component\Basket\InvalidBasketStateException;
use Sonata\Component\Delivery\DeliverySelectorInterface;
use Symfony\Component\Form\Extension\Core\ChoiceList\ArrayChoiceList;
use Sonata\AdminBundle\Model\ModelManagerInterface;

class ShippingType extends AbstractType
{
    protected $addressManager;

    protected $deliveryPool;

    protected $deliverySelector;

    protected $modelManager;

    public function __construct(AddressManagerInterface $addressManager, ModelManagerInterface $modelManager, DeliveryPool $deliveryPool, DeliverySelectorInterface $deliverySelector)
    {
        $this->addressManager   = $addressManager;
        $this->modelManager     = $modelManager;
        $this->deliverySelector = $deliverySelector;
        $this->deliveryPool     = $deliveryPool;
    }

    public function buildForm(FormBuilder $builder, array $options)
    {
        $basket = $builder->getData();

        if (!$basket instanceof BasketInterface) {
            throw new \RunTimeException('Please provide a BasketInterface instance');
        }

        $addresses = $this->addressManager->findBy(array(
            'customer' => $basket->getCustomer()->getId(),
            'type'        => AddressInterface::TYPE_DELIVERY
        ));

        $builder->add('deliveryAddress', 'sonata_type_model', array(
            'model_manager' => $this->modelManager,
            'class'         => $this->addressManager->getClass(),
            'choices'       => $addresses,
            'expanded'      => true
        ));

        $address = $basket->getDeliveryAddress() ?: current($addresses);
        $basket->setDeliveryAddress($address ?: null);

        $methods = $this->deliverySelector->getAvailableMethods($basket, $basket->getDeliveryAddress());

        $choices = array();
        foreach ($methods as $method) {
            $choices[$method->getCode()] = $method->getName();
        }

        reset($methods);

        $method = $basket->getDeliveryMethod() ?: current($methods);
        $basket->setDeliveryMethod($method ?: null);

        $sub = $builder->create('deliveryMethod', 'choice', array(
            'expanded'  => true,
            'choice_list'   => new ArrayChoiceList($choices),
        ));

        $sub->prependClientTransformer(new DeliveryMethodTransformer($this->deliveryPool));

        $builder->add($sub);
    }
}

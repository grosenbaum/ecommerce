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

use Sonata\DoctrineORMAdminBundle\Model\ModelManager;
use Sonata\Component\Product\Pool;

/**
 * this method overwrite the default AdminModelManager to call
 * the custom methods from the dedicated media manager
 */
class DoctrineModelManager extends ModelManager
{
    /**
     * @var Pool
     */
    protected $pool;

    /**
     * @param $entityManager
     * @param \Sonata\Component\Product\Pool $pool
     */
    public function __construct($entityManager, Pool $pool)
    {
        parent::__construct($entityManager);

        $this->pool = $pool;
    }

    /**
     * {@inheritdoc}
     */
    public function create($object)
    {
        $this->pool->getManager($object)->save($object);
    }

    /**
     * {@inheritdoc}
     */
    public function update($object)
    {
        $this->pool->getManager($object)->save($object);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($object)
    {
        $this->pool->getManager($object)->delete($object);
    }
}

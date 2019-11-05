<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository\Query;

use App\Entity\Customer;

/**
 * Can be used for advanced queries with the: ProjectRepository
 */
class ProjectQuery extends CustomerQuery
{
    public const PROJECT_ORDER_ALLOWED = ['id', 'name', 'comment', 'customer', 'orderNumber'];

    /**
     * @var Customer|int|null
     */
    private $customer;

    public function __construct()
    {
        parent::__construct();
        $this->setDefaults([
            'orderBy' => 'name',
        ]);
    }

    /**
     * @return Customer|int|null
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    /**
     * @param Customer|int|null $customer
     * @return $this
     */
    public function setCustomer($customer = null)
    {
        $this->customer = $customer;

        return $this;
    }
}

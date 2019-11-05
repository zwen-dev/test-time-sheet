<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository\Query;

class TeamQuery extends BaseQuery
{
    public const TEAM_ORDER_ALLOWED = ['id', 'name', 'teamlead'];

    public function __construct()
    {
        $this->setDefaults([
            'orderBy' => 'name',
        ]);
    }
}

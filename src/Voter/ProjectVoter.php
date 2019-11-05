<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Voter;

use App\Entity\Project;
use App\Entity\Team;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * A voter to check permissions on Projects.
 */
class ProjectVoter extends AbstractVoter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const BUDGET = 'budget';
    public const DELETE = 'delete';
    public const PERMISSIONS = 'permissions';

    /**
     * support rules based on the given $subject (here: Project)
     */
    public const ALLOWED_ATTRIBUTES = [
        self::VIEW,
        self::EDIT,
        self::BUDGET,
        self::DELETE,
        self::PERMISSIONS,
    ];

    /**
     * @param string $attribute
     * @param Project $subject
     * @return bool
     */
    protected function supports($attribute, $subject)
    {
        if (!($subject instanceof Project)) {
            return false;
        }

        if (!in_array($attribute, self::ALLOWED_ATTRIBUTES)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $attribute
     * @param Project $subject
     * @param TokenInterface $token
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->hasRolePermission($user, $attribute . '_project')) {
            return true;
        }

        $hasTeamleadPermission = $this->hasRolePermission($user, $attribute . '_teamlead_project');
        $hasTeamPermission = $this->hasRolePermission($user, $attribute . '_team_project');

        if (!$hasTeamleadPermission && !$hasTeamPermission) {
            return false;
        }

        /** @var Team $team */
        foreach ($subject->getTeams() as $team) {
            if ($hasTeamleadPermission && $user->isTeamleadOf($team)) {
                return true;
            }

            if ($hasTeamPermission && $user->isInTeam($team)) {
                return true;
            }
        }

        /** @var Team $team */
        foreach ($subject->getCustomer()->getTeams() as $team) {
            if ($hasTeamleadPermission && $user->isTeamleadOf($team)) {
                return true;
            }

            if ($hasTeamPermission && $user->isInTeam($team)) {
                return true;
            }
        }

        return false;
    }
}

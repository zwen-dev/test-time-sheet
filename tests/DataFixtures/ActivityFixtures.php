<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\DataFixtures;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Faker\Factory;

/**
 * Defines the sample data to load in during controller tests.
 */
final class ActivityFixtures extends Fixture
{
    /**
     * @var int
     */
    private $amount = 0;
    /**
     * @var bool
     */
    private $isGlobal = false;
    /**
     * @var bool
     */
    private $isVisible = null;
    /**
     * @var callable
     */
    private $callback;

    /**
     * Will be called prior to persisting the object.
     *
     * @param callable $callback
     * @return ActivityFixtures
     */
    public function setCallback(callable $callback): ActivityFixtures
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setIsGlobal(bool $global): ActivityFixtures
    {
        $this->isGlobal = $global;

        return $this;
    }

    public function setIsVisible(bool $visible): ActivityFixtures
    {
        $this->isVisible = $visible;

        return $this;
    }

    public function setAmount(int $amount): ActivityFixtures
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $projects = $this->getAllProjects($manager);
        $faker = Factory::create();

        // random amount of timesheet entries for every user
        for ($i = 0; $i < $this->amount; $i++) {
            $project = null;
            if (false === $this->isGlobal) {
                $project = $projects[array_rand($projects)];
            }
            $visible = 0 != $i % 3;
            if (null !== $this->isVisible) {
                $visible = $this->isVisible;
            }
            $activity = new Activity();
            $activity
                ->setProject($project)
                ->setName($faker->bs . ($visible ? '' : ' (x)'))
                ->setComment($faker->text)
                ->setVisible($visible)
            ;

            if (null !== $this->callback) {
                call_user_func($this->callback, $activity);
            }
            $manager->persist($activity);
        }

        $manager->flush();
    }

    /**
     * @param ObjectManager $manager
     * @return Project[]
     */
    protected function getAllProjects(ObjectManager $manager)
    {
        $all = [];
        /* @var User[] $entries */
        $entries = $manager->getRepository(Project::class)->findAll();
        foreach ($entries as $temp) {
            $all[$temp->getId()] = $temp;
        }

        return $all;
    }
}

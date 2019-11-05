<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber;

use App\Configuration\FormConfiguration;
use App\Entity\User;
use App\Entity\UserPreference;
use App\Event\PrepareUserEvent;
use App\Event\UserPreferenceEvent;
use App\Form\Type\CalendarViewType;
use App\Form\Type\InitialViewType;
use App\Form\Type\LanguageType;
use App\Form\Type\SkinType;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraints\Range;

class UserPreferenceSubscriber implements EventSubscriberInterface
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;
    /**
     * @var AuthorizationCheckerInterface
     */
    protected $voter;
    /**
     * @var TokenStorageInterface
     */
    protected $storage;
    /**
     * @var FormConfiguration
     */
    protected $formConfig;

    public function __construct(EventDispatcherInterface $dispatcher, TokenStorageInterface $storage, AuthorizationCheckerInterface $voter, FormConfiguration $formConfig)
    {
        $this->eventDispatcher = $dispatcher;
        $this->storage = $storage;
        $this->voter = $voter;
        $this->formConfig = $formConfig;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PrepareUserEvent::class => ['loadUserPreferences', 200]
        ];
    }

    private function getDefaultTheme(): ?string
    {
        return $this->formConfig->getUserDefaultTheme();
    }

    private function getDefaultCurrency(): ?string
    {
        return $this->formConfig->getUserDefaultCurrency();
    }

    private function getDefaultLanguage(): string
    {
        return $this->formConfig->getUserDefaultLanguage();
    }

    private function getDefaultTimezone(): string
    {
        $timezone = $this->formConfig->getUserDefaultTimezone();
        if (null === $timezone) {
            $timezone = date_default_timezone_get();
        }

        return $timezone;
    }

    /**
     * @param User $user
     * @return UserPreference[]
     */
    public function getDefaultPreferences(User $user)
    {
        $enableHourlyRate = false;
        $hourlyRateOptions = [];

        if ($this->voter->isGranted('hourly-rate', $user)) {
            $enableHourlyRate = true;
            $hourlyRateOptions = ['currency' => $this->getDefaultCurrency()];
        }

        return [
            (new UserPreference())
                ->setName(UserPreference::HOURLY_RATE)
                ->setValue(0)
                ->setType(MoneyType::class)
                ->setEnabled($enableHourlyRate)
                ->setOptions($hourlyRateOptions)
                ->addConstraint(new Range(['min' => 0])),

            (new UserPreference())
                ->setName(UserPreference::TIMEZONE)
                ->setValue($this->getDefaultTimezone())
                ->setType(TimezoneType::class),

            (new UserPreference())
                ->setName(UserPreference::LOCALE)
                ->setValue($this->getDefaultLanguage())
                ->setType(LanguageType::class),

            (new UserPreference())
                ->setName(UserPreference::SKIN)
                ->setValue($this->getDefaultTheme())
                ->setType(SkinType::class),

            (new UserPreference())
                ->setName('theme.collapsed_sidebar')
                ->setValue(false)
                ->setType(CheckboxType::class),

            (new UserPreference())
                ->setName('calendar.initial_view')
                ->setValue(CalendarViewType::DEFAULT_VIEW)
                ->setType(CalendarViewType::class),

            (new UserPreference())
                ->setName('login.initial_view')
                ->setValue(InitialViewType::DEFAULT_VIEW)
                ->setType(InitialViewType::class),

            (new UserPreference())
                ->setName('timesheet.daily_stats')
                ->setValue(false)
                ->setType(CheckboxType::class),
        ];
    }

    /**
     * @param PrepareUserEvent $event
     */
    public function loadUserPreferences(PrepareUserEvent $event)
    {
        $user = $event->getUser();

        $prefs = [];
        foreach ($user->getPreferences() as $preference) {
            $prefs[$preference->getName()] = $preference;
        }

        $event = new UserPreferenceEvent($user, $this->getDefaultPreferences($user));
        $this->eventDispatcher->dispatch($event);

        foreach ($event->getPreferences() as $preference) {
            /* @var UserPreference[] $prefs */
            if (isset($prefs[$preference->getName()])) {
                /* @var UserPreference $pref */
                $prefs[$preference->getName()]
                    ->setType($preference->getType())
                    ->setConstraints($preference->getConstraints())
                    ->setEnabled($preference->isEnabled())
                    ->setOptions($preference->getOptions())
                ;
            } else {
                $prefs[$preference->getName()] = $preference;
            }
        }

        $user->setPreferences(array_values($prefs));
    }
}

<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use App\Doctrine\TimesheetSubscriber;
use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Entity\UserPreference;
use App\Timesheet\Util;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\Type;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Command used to import data from a Kimai v1 installation.
 * Getting help in improving this script would be fantastic, it currently only handles the most basic use-cases.
 *
 * This command is way to messy and complex to be tested ... so we use something, which I actually don't like:
 * @codeCoverageIgnore
 */
class KimaiImporterCommand extends Command
{
    // minimum required Kimai and database version, lower versions are not supported by this command
    public const MIN_VERSION = '1.0.1';
    public const MIN_REVISION = '1388';

    /**
     * Create the user default passwords
     * @var UserPasswordEncoderInterface
     */
    protected $encoder;
    /**
     * Validates the entities before they will be created
     * @var ValidatorInterface
     */
    protected $validator;
    /**
     * Connection to the Kimai v2 database to write imported data to
     * @var RegistryInterface
     */
    protected $doctrine;
    /**
     * Connection to the old database to import data from
     * @var Connection
     */
    protected $connection;
    /**
     * Prefix for the v1 database tables.
     * @var string
     */
    protected $dbPrefix = '';
    /**
     * @var User[]
     */
    protected $users = [];
    /**
     * @var Customer[]
     */
    protected $customers = [];
    /**
     * @var Project[]
     */
    protected $projects = [];
    /**
     * id => [projectId => Activity]
     * @var Activity[]
     */
    protected $activities = [];
    /**
     * @var bool
     */
    protected $debug = false;
    /**
     * @var array
     */
    protected $oldActivities = [];

    /**
     * @param UserPasswordEncoderInterface $encoder
     * @param RegistryInterface $registry
     * @param ValidatorInterface $validator
     */
    public function __construct(
        UserPasswordEncoderInterface $encoder,
        RegistryInterface $registry,
        ValidatorInterface $validator
    ) {
        $this->encoder = $encoder;
        $this->doctrine = $registry;
        $this->validator = $validator;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('kimai:import-v1')
            ->setDescription('Import data from a Kimai v1 installation')
            ->setHelp('This command allows you to import the most important data from a Kimi v1 installation.')
            ->addArgument(
                'connection',
                InputArgument::REQUIRED,
                'The database connection as URL, e.g.: mysql://user:password@127.0.0.1:3306/kimai?charset=latin1'
            )
            ->addArgument('prefix', InputArgument::REQUIRED, 'The database prefix for the old Kimai v1 tables')
            ->addArgument('password', InputArgument::REQUIRED, 'The new password for all imported user')
            ->addArgument('country', InputArgument::OPTIONAL, 'The default country for customer (2-character uppercase)', 'DE')
            ->addArgument('currency', InputArgument::OPTIONAL, 'The default currency for customer (code like EUR, CHF, GBP or USD)', 'EUR')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // do not convert the times, Kimai 1 stored them already in UTC
        Type::overrideType(Type::DATETIME, DateTimeType::class);

        // don't calculate rates ... this was done in Kimai 1
        $this->deactivateLifecycleCallbacks($this->getDoctrine()->getConnection());

        $io = new SymfonyStyle($input, $output);

        $config = new Configuration();
        $connectionParams = ['url' => $input->getArgument('connection')];
        $this->connection = DriverManager::getConnection($connectionParams, $config);

        $this->dbPrefix = $input->getArgument('prefix');

        $password = $input->getArgument('password');
        if (trim(strlen($password)) < 6) {
            $io->error('Password length is not sufficient, at least 6 character are required');

            return;
        }

        $country = $input->getArgument('country');
        if (2 != trim(strlen($country))) {
            $io->error('Country code needs to be exactly 2 character');

            return;
        }

        $currency = $input->getArgument('currency');
        if (3 != trim(strlen($currency))) {
            $io->error('Currency code needs to be exactly 3 character');

            return;
        }

        if (!$this->checkDatabaseVersion($io, self::MIN_VERSION, self::MIN_REVISION)) {
            return;
        }

        $bytesStart = memory_get_usage(true);

        // pre-load all data to make sure we can fully import everything
        try {
            $users = $this->fetchAllFromImport('users');
        } catch (\Exception $ex) {
            $io->error('Failed to load users: ' . $ex->getMessage());

            return;
        }

        try {
            $customer = $this->fetchAllFromImport('customers');
        } catch (\Exception $ex) {
            $io->error('Failed to load customers: ' . $ex->getMessage());

            return;
        }

        try {
            $projects = $this->fetchAllFromImport('projects');
        } catch (\Exception $ex) {
            $io->error('Failed to load projects: ' . $ex->getMessage());

            return;
        }

        try {
            $activities = $this->fetchAllFromImport('activities');
        } catch (\Exception $ex) {
            $io->error('Failed to load activities: ' . $ex->getMessage());

            return;
        }

        try {
            $activityToProject = $this->fetchAllFromImport('projects_activities');
        } catch (\Exception $ex) {
            $io->error('Failed to load activities-project mapping: ' . $ex->getMessage());

            return;
        }

        try {
            $records = $this->fetchAllFromImport('timeSheet');
        } catch (\Exception $ex) {
            $io->error('Failed to load timeSheet: ' . $ex->getMessage());

            return;
        }

        try {
            $fixedRates = $this->fetchAllFromImport('fixedRates');
        } catch (\Exception $ex) {
            $io->error('Failed to load fixedRates: ' . $ex->getMessage());

            return;
        }

        try {
            $rates = $this->fetchAllFromImport('rates');
        } catch (\Exception $ex) {
            $io->error('Failed to load rates: ' . $ex->getMessage());

            return;
        }

        $bytesCached = memory_get_usage(true);

        $io->success('Fetched Kimai v1 data, trying to import now ...');

        $allImports = 0;

        try {
            $counter = $this->importUsers($io, $password, $users, $rates);
            $allImports += $counter;
            $io->success('Imported users: ' . $counter);
        } catch (\Exception $ex) {
            $io->error('Failed to import users: ' . $ex->getMessage() . PHP_EOL . $ex->getTraceAsString());

            return;
        }

        try {
            $counter = $this->importCustomers($io, $customer, $country, $currency);
            $allImports += $counter;
            $io->success('Imported customers: ' . $counter);
        } catch (\Exception $ex) {
            $io->error('Failed to import customers: ' . $ex->getMessage() . PHP_EOL . $ex->getTraceAsString());

            return;
        }

        try {
            $counter = $this->importProjects($io, $projects, $fixedRates, $rates);
            $allImports += $counter;
            $io->success('Imported projects: ' . $counter);
        } catch (\Exception $ex) {
            $io->error('Failed to import projects: ' . $ex->getMessage() . PHP_EOL . $ex->getTraceAsString());

            return;
        }

        try {
            $counter = $this->importActivities($io, $activities, $activityToProject, $fixedRates, $rates);
            $allImports += $counter;
            $io->success('Imported activities: ' . $counter);
        } catch (\Exception $ex) {
            $io->error('Failed to import activities: ' . $ex->getMessage() . PHP_EOL . $ex->getTraceAsString());

            return;
        }

        try {
            $counter = $this->importTimesheetRecords($io, $records, $fixedRates, $rates);
            $allImports += $counter;
            $io->success('Imported timesheet records: ' . $counter);
        } catch (\Exception $ex) {
            $io->error('Failed to import timesheet records: ' . $ex->getMessage() . PHP_EOL . $ex->getTraceAsString());

            return;
        }

        // TODO support expenses - new database required

        $bytesImported = memory_get_usage(true);

        $io->success(
            'Memory usage: ' . PHP_EOL .
            'Start: ' . $this->bytesHumanReadable($bytesStart) . PHP_EOL .
            'After caching: ' . $this->bytesHumanReadable($bytesCached) . PHP_EOL .
            'After import: ' . $this->bytesHumanReadable($bytesImported) . PHP_EOL .
            'Total consumption for importing ' . $allImports . ' new database entries: ' .
            $this->bytesHumanReadable($bytesImported - $bytesStart)
        );
    }

    /**
     * Checks if the given database connection for import has an underlying database with a compatible structure.
     * This is checked against the Kimai version and database revision.
     *
     * @param SymfonyStyle $io
     * @param string $requiredVersion
     * @param string $requiredRevision
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function checkDatabaseVersion(SymfonyStyle $io, $requiredVersion, $requiredRevision)
    {
        $optionColumn = $this->connection->quoteIdentifier('option');
        $qb = $this->connection->createQueryBuilder();

        $version = $this->connection->createQueryBuilder()
            ->select('value')
            ->from($this->connection->quoteIdentifier($this->dbPrefix . 'configuration'))
            ->where($qb->expr()->eq($optionColumn, ':option'))
            ->setParameter('option', 'version')
            ->execute()
            ->fetchColumn();

        $revision = $this->connection->createQueryBuilder()
            ->select('value')
            ->from($this->connection->quoteIdentifier($this->dbPrefix . 'configuration'))
            ->where($qb->expr()->eq($optionColumn, ':option'))
            ->setParameter('option', 'revision')
            ->execute()
            ->fetchColumn();

        if (1 == version_compare($requiredVersion, $version)) {
            $io->error(
                'Import can only performed from an up-to-date Kimai version:' . PHP_EOL .
                'Needs at least ' . $requiredVersion . ' but found ' . $version
            );

            return false;
        }

        if (1 == version_compare($requiredRevision, $revision)) {
            $io->error(
                'Import can only performed from an up-to-date Kimai version:' . PHP_EOL .
                'Database revision needs to be ' . $requiredRevision . ' but found ' . $revision
            );

            return false;
        }

        return true;
    }

    /**
     * Remove the timesheet lifecycle events subscriber, which would overwrite values for imported timesheet records.
     *
     * @param Connection $connection
     */
    protected function deactivateLifecycleCallbacks(Connection $connection)
    {
        $allListener = $connection->getEventManager()->getListeners();
        foreach ($allListener as $name => $listener) {
            if (in_array($name, ['prePersist', 'preUpdate'])) {
                foreach ($listener as $service => $class) {
                    if (TimesheetSubscriber::class === $class) {
                        $connection->getEventManager()->removeEventListener(['prePersist', 'preUpdate'], $class);
                    }
                }
            }
        }
    }

    /**
     * Thanks to "xelozz -at- gmail.com", see http://php.net/manual/en/function.memory-get-usage.php#96280
     * @param int $size
     * @return string
     */
    protected function bytesHumanReadable($size)
    {
        $unit = ['b', 'kB', 'MB', 'GB'];
        $i = floor(log($size, 1024));
        $a = (int) $i;

        return @round($size / pow(1024, $i), 2) . ' ' . $unit[$a];
    }

    /**
     * @param string $table
     * @param array $where
     * @return array
     */
    protected function fetchAllFromImport($table, array $where = [])
    {
        $query = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->connection->quoteIdentifier($this->dbPrefix . $table));

        foreach ($where as $column => $value) {
            $query->andWhere($query->expr()->eq($column, $value));
        }

        return $query->execute()->fetchAll();
    }

    /**
     * @return RegistryInterface
     */
    protected function getDoctrine()
    {
        return $this->doctrine;
    }

    /**
     * @param SymfonyStyle $io
     * @param object $object
     * @return bool
     */
    protected function validateImport(SymfonyStyle $io, $object)
    {
        $errors = $this->validator->validate($object);

        if ($errors->count() > 0) {
            /** @var \Symfony\Component\Validator\ConstraintViolation $error */
            foreach ($errors as $error) {
                $io->error(
                    (string) $error
                );
            }

            return false;
        }

        return true;
    }

    /**
     * -- are currently unsupported fields that can't be mapped
     *
     * ["userID"]=> string(9) "833336177"
     * ["name"]=> string(5) "admin"
     * ["alias"]=> NULL
     * --- ["status"]=> string(1) "0"
     * ["trash"]=> string(1) "0"
     * ["active"]=> string(1) "1"
     * ["mail"]=> string(21) "foo@bar.com"
     * ["password"]=> string(32) ""
     * ["passwordResetHash"]=> NULL
     * ["ban"]=> string(1) "0"
     * ["banTime"]=> string(1) "0"
     * --- ["secure"]=> string(30) ""
     * ["lastProject"]=> string(1) "2"
     * ["lastActivity"]=> string(1) "2"
     * ["lastRecord"]=> string(1) "2"
     * ["timeframeBegin"]=> string(10) "1304200800"
     * ["timeframeEnd"]=> string(1) "0"
     * ["apikey"]=> NULL
     * ["globalRoleID"]=> string(1) "1"
     *
     * @param SymfonyStyle $io
     * @param string $password
     * @param array $users
     * @param array $rates
     * @return int
     * @throws \Exception
     */
    protected function importUsers(SymfonyStyle $io, $password, $users, $rates)
    {
        $counter = 0;
        $entityManager = $this->getDoctrine()->getManager();

        foreach ($users as $oldUser) {
            $isActive = (bool) $oldUser['active'] && !(bool) $oldUser['trash'] && !(bool) $oldUser['ban'];
            $role = (1 == $oldUser['globalRoleID']) ? User::ROLE_SUPER_ADMIN : User::DEFAULT_ROLE;

            $user = new User();
            $user->setUsername($oldUser['name'])
                ->setAlias($oldUser['alias'])
                ->setEmail($oldUser['mail'])
                ->setPlainPassword($password)
                ->setEnabled($isActive)
                ->setRoles([$role])
            ;

            $pwd = $this->encoder->encodePassword($user, $user->getPlainPassword());
            $user->setPassword($pwd);

            if (!$this->validateImport($io, $user)) {
                throw new \Exception('Failed to validate user: ' . $user->getUsername());
            }

            // find and migrate user preferences
            $prefsToImport = ['ui.lang' => 'language', 'timezone' => 'timezone'];
            $preferences = $this->fetchAllFromImport('preferences', ['userID' => $oldUser['userID']]);
            foreach ($preferences as $pref) {
                $key = $pref['option'];

                if (!array_key_exists($key, $prefsToImport)) {
                    continue;
                }

                if (empty($pref['value'])) {
                    continue;
                }

                $newPref = new UserPreference();
                $newPref
                    ->setName($prefsToImport[$key])
                    ->setValue($pref['value']);
                $user->addPreference($newPref);
            }

            // find hourly rate
            foreach ($rates as $ratesRow) {
                if ($ratesRow['userID'] === $oldUser['userID'] && $ratesRow['activityID'] === null && $ratesRow['projectID'] === null) {
                    $newPref = new UserPreference();
                    $newPref
                        ->setName(UserPreference::HOURLY_RATE)
                        ->setValue($ratesRow['rate']);
                    $user->addPreference($newPref);
                }
            }

            try {
                $entityManager->persist($user);
                $entityManager->flush();
                if ($this->debug) {
                    $io->success('Created user: ' . $user->getUsername());
                }
                ++$counter;
            } catch (\Exception $ex) {
                $io->error('Failed to create user: ' . $user->getUsername());
                $io->error('Reason: ' . $ex->getMessage());
            }

            $this->users[$oldUser['userID']] = $user;
        }

        return $counter;
    }

    /**
     * -- are currently unsupported fields that can't be mapped
     *
     * ["customerID"]=> string(2) "11"
     * ["name"]=> string(9) "Customer"
     * ["password"]=> NULL
     * ["passwordResetHash"]=> NULL
     * ["secure"]=> NULL
     * ["comment"]=> NULL
     * ["visible"]=> string(1) "1"
     * ["filter"]=> string(1) "0"
     * ["company"]=> string(14) "Customer Ltd."
     * --- ["vat"]=> string(2) "19"
     * ["contact"]=> string(2) "Someone"
     * ["street"]=> string(22) "Street name"
     * ["zipcode"]=> string(5) "12345"
     * ["city"]=> string(6) "Berlin"
     * ["phone"]=> NULL
     * ["fax"]=> NULL
     * ["mobile"]=> NULL
     * ["mail"]=> NULL
     * ["homepage"]=> NULL
     * ["trash"]=> string(1) "0"
     * ["timezone"]=> string(13) "Europe/Berlin"
     *
     * @param SymfonyStyle $io
     * @param array $customers
     * @param string $country
     * @param string $currency
     * @return int
     * @throws \Exception
     */
    protected function importCustomers(SymfonyStyle $io, $customers, $country, $currency)
    {
        $counter = 0;
        $entityManager = $this->getDoctrine()->getManager();

        foreach ($customers as $oldCustomer) {
            $isActive = (bool) $oldCustomer['visible'] && !(bool) $oldCustomer['trash'];
            $name = $oldCustomer['name'];
            if (empty($name)) {
                $name = uniqid();
                $io->warning('Found empty customer name, setting it to: ' . $name);
            }

            $customer = new Customer();
            $customer
                ->setName($name)
                ->setComment($oldCustomer['comment'])
                ->setCompany($oldCustomer['company'])
                ->setFax($oldCustomer['fax'])
                ->setHomepage($oldCustomer['homepage'])
                ->setMobile($oldCustomer['mobile'])
                ->setEmail($oldCustomer['mail'])
                ->setPhone($oldCustomer['phone'])
                ->setContact($oldCustomer['contact'])
                ->setAddress($oldCustomer['street'] . PHP_EOL . $oldCustomer['zipcode'] . ' ' . $oldCustomer['city'])
                ->setTimezone($oldCustomer['timezone'])
                ->setVisible($isActive)
                ->setCountry(strtoupper($country))
                ->setCurrency(strtoupper($currency))
            ;

            if (!$this->validateImport($io, $customer)) {
                throw new \Exception('Failed to validate customer: ' . $customer->getName());
            }

            try {
                $entityManager->persist($customer);
                $entityManager->flush();
                if ($this->debug) {
                    $io->success('Created customer: ' . $customer->getName());
                }
                ++$counter;
            } catch (\Exception $ex) {
                $io->error('Reason: ' . $ex->getMessage());
                $io->error('Failed to create customer: ' . $customer->getName());
            }

            $this->customers[$oldCustomer['customerID']] = $customer;
        }

        return $counter;
    }

    /**
     * -- are currently unsupported fields that can't be mapped
     *
     * ["projectID"]=> string(1) "1"
     * ["customerID"]=> string(1) "1"
     * ["name"]=> string(11) "Test"
     * ["comment"]=> string(0) ""
     * ["visible"]=> string(1) "1"
     * --- ["filter"]=> string(1) "0"
     * ["trash"]=> string(1) "1"
     * ["budget"]=> string(4) "0.00"
     * --- ["effort"]=> NULL
     * --- ["approved"]=> NULL
     * --- ["internal"]=> string(1) "0"
     *
     * @param SymfonyStyle $io
     * @param array $projects
     * @param array $fixedRates
     * @param array $rates
     * @return int
     * @throws \Exception
     */
    protected function importProjects(SymfonyStyle $io, $projects, array $fixedRates, array $rates)
    {
        $counter = 0;
        $entityManager = $this->getDoctrine()->getManager();

        foreach ($projects as $oldProject) {
            $isActive = (bool) $oldProject['visible'] && !(bool) $oldProject['trash'];
            $customer = $this->customers[$oldProject['customerID']];
            $name = $oldProject['name'];
            if (empty($name)) {
                $name = uniqid();
                $io->warning('Found empty project name, setting it to: ' . $name);
            }

            $project = new Project();
            $project
                ->setCustomer($customer)
                ->setName($name)
                ->setComment($oldProject['comment'] ?: null)
                ->setVisible($isActive)
                ->setBudget($oldProject['budget'] ?: 0)
            ;

            foreach ($fixedRates as $fixedRow) {
                if ($fixedRow['activityID'] !== null || $fixedRow['projectID'] === null) {
                    continue;
                }
                if ($fixedRow['projectID'] == $oldProject['projectID']) {
                    $project->setFixedRate($fixedRow['rate']);
                }
            }

            foreach ($rates as $ratesRow) {
                if ($ratesRow['userID'] !== null || $ratesRow['activityID'] !== null || $ratesRow['projectID'] === null) {
                    continue;
                }
                if ($ratesRow['projectID'] == $oldProject['projectID']) {
                    $project->setHourlyRate($ratesRow['rate']);
                }
            }

            if (!$this->validateImport($io, $project)) {
                throw new \Exception('Failed to validate project: ' . $project->getName());
            }

            try {
                $entityManager->persist($project);
                $entityManager->flush();
                if ($this->debug) {
                    $io->success('Created project: ' . $project->getName() . ' for customer: ' . $customer->getName());
                }
                ++$counter;
            } catch (\Exception $ex) {
                $io->error('Failed to create project: ' . $project->getName());
                $io->error('Reason: ' . $ex->getMessage());
            }

            $this->projects[$oldProject['projectID']] = $project;
        }

        return $counter;
    }

    /**
     * -- are currently unsupported fields that can't be mapped
     *
     * $activities:
     * -- ["activityID"]=> string(1) "1"
     * ["name"]=> string(6) "Test"
     * ["comment"]=> string(0) ""
     * ["visible"]=> string(1) "1"
     * --- ["filter"]=> string(1) "0"
     * ["trash"]=> string(1) "1"
     *
     * $activityToProject
     * ["projectID"]=> string(1) "1"
     * ["activityID"]=> string(1) "1"
     * ["budget"]=> string(4) "0.00"
     * -- ["effort"]=> string(4) "0.00"
     * -- ["approved"]=> string(4) "0.00"
     *
     * @param SymfonyStyle $io
     * @param array $activities
     * @param array $activityToProject
     * @param array $fixedRates
     * @param array $rates
     * @return int
     * @throws \Exception
     */
    protected function importActivities(SymfonyStyle $io, array $activities, array $activityToProject, array $fixedRates, array $rates)
    {
        $counter = 0;
        $entityManager = $this->getDoctrine()->getManager();

        // remember which activity has at least one assigned project
        $oldActivityMapping = [];
        foreach ($activityToProject as $mapping) {
            $oldActivityMapping[$mapping['activityID']][] = $mapping['projectID'];
        }

        // create global activities
        foreach ($activities as $oldActivity) {
            $this->oldActivities[$oldActivity['activityID']] = $oldActivity;
            if (isset($oldActivityMapping[$oldActivity['activityID']])) {
                continue;
            }

            $this->createActivity($io, $entityManager, $oldActivity, $fixedRates, $rates, null);
            ++$counter;
        }

        $io->success('Created global activities: ' . $counter);

        // create project specific activities
        foreach ($activities as $oldActivity) {
            if (!isset($oldActivityMapping[$oldActivity['activityID']])) {
                continue;
            }
            foreach ($oldActivityMapping[$oldActivity['activityID']] as $projectId) {
                if (!isset($this->projects[$projectId])) {
                    throw new \Exception(
                        'Invalid project linked to activity ' . $oldActivity['name'] . ': ' . $projectId
                    );
                }

                $this->createActivity($io, $entityManager, $oldActivity, $fixedRates, $rates, $projectId);
                ++$counter;
            }
        }

        return $counter;
    }

    /**
     * @param SymfonyStyle $io
     * @param ObjectManager $entityManager
     * @param array $oldActivity
     * @param array $fixedRates
     * @param array $rates
     * @param int $projectId
     * @return Activity
     * @throws \Exception
     */
    protected function createActivity(
        SymfonyStyle $io,
        ObjectManager $entityManager,
        array $oldActivity,
        array $fixedRates,
        array $rates,
        $projectId
    ) {
        $activityId = $oldActivity['activityID'];

        if (isset($this->activities[$activityId][$projectId])) {
            return $this->activities[$activityId][$projectId];
        }

        $isActive = (bool) $oldActivity['visible'] && !(bool) $oldActivity['trash'];
        $name = $oldActivity['name'];
        if (empty($name)) {
            $name = uniqid();
            $io->warning('Found empty activity name, setting it to: ' . $name);
        }

        if (null !== $projectId && !isset($this->projects[$projectId])) {
            throw new \Exception(
                sprintf('Did not find project [%s], skipping activity creation [%s] %s', $projectId, $activityId, $name)
            );
        }

        $activity = new Activity();
        $activity
            ->setName($name)
            ->setComment($oldActivity['comment'] ?? null)
            ->setVisible($isActive)
            ->setBudget($oldActivity['budget'] ?? 0)
        ;

        if (null !== $projectId) {
            $project = $this->projects[$projectId];
            $activity->setProject($project);
        }

        foreach ($fixedRates as $fixedRow) {
            if ($fixedRow['activityID'] === null) {
                continue;
            }
            if ($fixedRow['projectID'] !== null && $fixedRow['projectID'] !== $projectId) {
                continue;
            }

            if ($fixedRow['activityID'] == $oldActivity['activityID']) {
                $activity->setFixedRate($fixedRow['rate']);
            }
        }

        foreach ($rates as $ratesRow) {
            if ($ratesRow['userID'] !== null || $ratesRow['activityID'] === null) {
                continue;
            }
            if ($ratesRow['projectID'] !== null && $ratesRow['projectID'] !== $projectId) {
                continue;
            }

            if ($ratesRow['activityID'] == $oldActivity['activityID']) {
                $activity->setHourlyRate($ratesRow['rate']);
            }
        }

        if (!$this->validateImport($io, $activity)) {
            throw new \Exception('Failed to validate activity: ' . $activity->getName());
        }

        try {
            $entityManager->persist($activity);
            $entityManager->flush();
            if ($this->debug) {
                $io->success('Created activity: ' . $activity->getName());
            }
        } catch (\Exception $ex) {
            $io->error('Failed to create activity: ' . $activity->getName());
            $io->error('Reason: ' . $ex->getMessage());
        }

        if (!isset($this->activities[$activityId])) {
            $this->activities[$activityId] = [];
        }
        $this->activities[$activityId][$projectId] = $activity;

        return $activity;
    }

    /**
     * -- are currently unsupported fields that can't be mapped
     *
     * -- ["timeEntryID"]=> string(1) "1"
     * ["start"]=> string(10) "1306747800"
     * ["end"]=> string(10) "1306752300"
     * ["duration"]=> string(4) "4500"
     * ["userID"]=> string(9) "228899434"
     * ["projectID"]=> string(1) "1"
     * ["activityID"]=> string(1) "1"
     * ["description"]=> NULL
     * ["comment"]=> string(36) "a work description"
     * -- ["commentType"]=> string(1) "0"
     * ["cleared"]=> string(1) "0"
     * -- ["location"]=> string(0) ""
     * -- ["trackingNumber"]=> NULL
     * ["rate"]=> string(5) "50.00"
     * ["fixedRate"]=> string(4) "0.00"
     * -- ["budget"]=> NULL
     * -- ["approved"]=> NULL
     * -- ["statusID"]=> string(1) "1"
     * -- ["billable"]=> NULL
     *
     * @param SymfonyStyle $io
     * @param array $records
     * @param array $fixedRates
     * @param array $rates
     * @return int
     * @throws \Exception
     */
    protected function importTimesheetRecords(SymfonyStyle $io, array $records, array $fixedRates, array $rates)
    {
        $errors = [
            'projectActivityMismatch' => [],
        ];
        $counter = 0;
        $failed = 0;
        $activityCounter = 0;
        $userCounter = 0;
        $entityManager = $this->getDoctrine()->getManager();
        $total = count($records);

        $io->writeln('Importing timesheets, please wait');

        foreach ($records as $oldRecord) {
            $activity = null;
            $project = null;
            $activityId = $oldRecord['activityID'];
            $projectId = $oldRecord['projectID'];

            if (isset($this->projects[$projectId])) {
                $project = $this->projects[$projectId];
            } else {
                $io->error('Could not create timesheet record, missing project with ID: ' . $projectId);
                $failed++;
                continue;
            }

            $customerId = $project->getCustomer()->getId();

            if (isset($this->activities[$activityId][$projectId])) {
                $activity = $this->activities[$activityId][$projectId];
            } elseif (isset($this->activities[$activityId][null])) {
                $activity = $this->activities[$activityId][null];
            }

            if (null === $activity && isset($this->oldActivities[$activityId])) {
                $oldActivity = $this->oldActivities[$activityId];
                $activity = $this->createActivity($io, $entityManager, $oldActivity, $fixedRates, $rates, $projectId);
                ++$activityCounter;
            }

            // this should not happen at all
            if (null === $activity) {
                $io->error('Could not import timesheet record, missing activity with ID: ' . $activityId . '/' . $projectId . '/' . $customerId);
                $failed++;
                continue;
            }

            if (empty($oldRecord['end']) || $oldRecord['end'] === 0) {
                $io->error('Cannot import running timesheet record, skipping: ' . $oldRecord['timeEntryID']);
                $failed++;
                continue;
            }

            $duration = (int) ($oldRecord['end'] - $oldRecord['start']);

            // ----------------------- unknown user, damned missing data integrity in Kimai v1 -----------------------
            if (!isset($this->users[$oldRecord['userID']])) {
                $tempUserName = uniqid();
                $tempPassword = uniqid() . uniqid();

                $user = new User();
                $user->setUsername($tempUserName)
                    ->setAlias('Import: ' . $tempUserName)
                    ->setEmail($tempUserName . '@example.com')
                    ->setPlainPassword($tempPassword)
                    ->setEnabled(false)
                    ->setRoles([USER::ROLE_USER])
                ;

                $pwd = $this->encoder->encodePassword($user, $user->getPlainPassword());
                $user->setPassword($pwd);

                if (!$this->validateImport($io, $user)) {
                    $io->error('Found timesheet record for unknown user and failed to create user, skipping timesheet: ' . $oldRecord['timeEntryID']);
                    $failed++;
                    continue;
                }

                try {
                    $entityManager->persist($user);
                    $entityManager->flush();
                    if ($this->debug) {
                        $io->success('Created deactivated user: ' . $user->getUsername());
                    }
                    $userCounter++;
                } catch (\Exception $ex) {
                    $io->error('Failed to create user: ' . $user->getUsername());
                    $io->error('Reason: ' . $ex->getMessage());
                    $failed++;
                    continue;
                }

                $this->users[$oldRecord['userID']] = $user;
            }
            // ----------------------- unknown user end -----------------------

            $timesheet = new Timesheet();

            $fixedRate = $oldRecord['fixedRate'];
            if (!empty($fixedRate) && 0.00 != $fixedRate) {
                $timesheet->setFixedRate($fixedRate);
            }

            $hourlyRate = $oldRecord['rate'];
            if (!empty($hourlyRate) && 0.00 != $hourlyRate) {
                $timesheet->setHourlyRate($hourlyRate);
            }

            if ($timesheet->getFixedRate() !== null) {
                $timesheet->setRate($timesheet->getFixedRate());
            } elseif ($timesheet->getHourlyRate() !== null) {
                $hourlyRate = (float) $timesheet->getHourlyRate();
                $rate = Util::calculateRate($hourlyRate, $duration);
                $timesheet->setRate($rate);
            }

            $user = $this->users[$oldRecord['userID']];
            $timezone = $user->getTimezone();
            $dateTimezone = new \DateTimeZone('UTC');

            $begin = new \DateTime('@' . $oldRecord['start']);
            $begin->setTimezone($dateTimezone);
            $end = new \DateTime('@' . $oldRecord['end']);
            $end->setTimezone($dateTimezone);

            // ---------- workaround for localizeDates ----------
            // if getBegin() is not executed first, then the dates will we re-written in validateImport() below
            $timesheet->setBegin($begin)->setEnd($end)->getBegin();
            // --------------------------------------------------

            // ---------- this was a bug in the past, should not happen anymore ----------
            if ($activity->getProject() !== null && $project->getId() !== $activity->getProject()->getId()) {
                $errors['projectActivityMismatch'][] = $oldRecord['timeEntryID'];
                continue;
            }
            // ---------------------------------------------------------------------

            $timesheet
                ->setDescription($oldRecord['description'] ?? ($oldRecord['comment'] ?? null))
                ->setUser($this->users[$oldRecord['userID']])
                ->setBegin($begin)
                ->setEnd($end)
                ->setDuration($duration)
                ->setActivity($activity)
                ->setProject($project)
                ->setExported(intval($oldRecord['cleared']) !== 0)
                ->setTimezone($timezone)
            ;

            if (!$this->validateImport($io, $timesheet)) {
                $io->error('Failed to validate timesheet record: ' . $oldRecord['timeEntryID'] . ' - skipping!');
                $failed++;
                continue;
            }

            try {
                $entityManager->persist($timesheet);
                $entityManager->flush();
                if ($this->debug) {
                    $io->success('Created timesheet record: ' . $timesheet->getId());
                }
                ++$counter;
            } catch (\Exception $ex) {
                $io->error('Failed to create timesheet record: ' . $ex->getMessage());
                $failed++;
            }

            $io->write('.');
            if (0 == $counter % 80) {
                $io->writeln(' (' . $counter . '/' . $total . ')');
                $entityManager->clear(Timesheet::class);
            }
        }

        for ($i = 0; $i < 80 - ($counter % 80); $i++) {
            $io->write(' ');
        }
        $io->writeln(' (' . $counter . '/' . $total . ')');

        if ($userCounter > 0) {
            $io->success('Created new users during timesheet import: ' . $userCounter);
        }
        if ($activityCounter > 0) {
            $io->success('Created new activities during timesheet import: ' . $activityCounter);
        }
        if (count($errors['projectActivityMismatch']) > 0) {
            $io->error('Found invalid mapped project - activity combinations in these old timesheet recors: ' . implode(',', $errors['projectActivityMismatch']));
        }
        if ($failed > 0) {
            $io->error(sprintf('Failed importing %s timesheet records', $failed));
        }

        return $counter;
    }
}

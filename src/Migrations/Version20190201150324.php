<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DoctrineMigrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Adds the timezone column to the timesheet table
 * See https://github.com/kevinpapst/kimai2/pull/372 for further information.
 *
 * @version 0.8
 */
final class Version20190201150324 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $platform = $this->getPlatform();

        if (!in_array($platform, ['sqlite', 'mysql'])) {
            $this->abortIf(true, 'Unsupported database platform: ' . $platform);
        }

        $timesheet = $this->getTableName('timesheet');
        $timezone = date_default_timezone_get();

        if ($platform === 'sqlite') {
            $this->addSql('ALTER TABLE ' . $timesheet . ' ADD COLUMN timezone VARCHAR(64) DEFAULT NULL');
        } else {
            $this->addSql('ALTER TABLE ' . $timesheet . ' ADD timezone VARCHAR(64) NOT NULL');
        }

        $this->addSql('UPDATE ' . $timesheet . ' SET timezone = "' . $timezone . '"');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->getPlatform();

        if (!in_array($platform, ['sqlite', 'mysql'])) {
            $this->abortIf(true, 'Unsupported database platform: ' . $platform);
        }

        $timesheet = $this->getTableName('timesheet');

        if ($platform === 'sqlite') {
            $this->addSql('DROP INDEX IDX_4F60C6B1166D1F9C');
            $this->addSql('DROP INDEX IDX_4F60C6B18D93D649');
            $this->addSql('DROP INDEX IDX_4F60C6B181C06096');
            $this->addSql('CREATE TEMPORARY TABLE __temp__' . $timesheet . ' AS SELECT id, user, activity_id, project_id, start_time, end_time, duration, description, rate, fixed_rate, hourly_rate, exported FROM ' . $timesheet);
            $this->addSql('DROP TABLE ' . $timesheet);
            $this->addSql('CREATE TABLE ' . $timesheet . ' (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user INTEGER NOT NULL, activity_id INTEGER NOT NULL, project_id INTEGER NOT NULL, start_time DATETIME NOT NULL --(DC2Type:datetime)
        , end_time DATETIME DEFAULT NULL --(DC2Type:datetime)
        , duration INTEGER DEFAULT NULL, description CLOB DEFAULT NULL, rate NUMERIC(10, 2) NOT NULL, fixed_rate NUMERIC(10, 2) DEFAULT NULL, hourly_rate NUMERIC(10, 2) DEFAULT NULL, exported BOOLEAN NOT NULL DEFAULT false)');
            $this->addSql('INSERT INTO ' . $timesheet . ' (id, user, activity_id, project_id, start_time, end_time, duration, description, rate, fixed_rate, hourly_rate, exported) SELECT id, user, activity_id, project_id, start_time, end_time, duration, description, rate, fixed_rate, hourly_rate, exported FROM __temp__' . $timesheet);
            $this->addSql('DROP TABLE __temp__' . $timesheet);
            $this->addSql('CREATE INDEX IDX_4F60C6B1166D1F9C ON ' . $timesheet . ' (project_id)');
            $this->addSql('CREATE INDEX IDX_4F60C6B18D93D649 ON ' . $timesheet . ' (user)');
            $this->addSql('CREATE INDEX IDX_4F60C6B181C06096 ON ' . $timesheet . ' (activity_id)');
        } else {
            $this->addSql('ALTER TABLE ' . $timesheet . ' DROP timezone');
        }
    }
}

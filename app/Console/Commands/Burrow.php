<?php

namespace App\Console\Commands;

use ChaoticWave\BlueVelvet\Utility\Disk;
use Doctrine\DBAL\Schema\Table;
use Illuminate\Database\Connection;
use Illuminate\Database\Console\Migrations\BaseCommand;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**r
 * Burrows into a database to discover all the goodies within
 */
class Burrow extends BaseCommand
{
    //******************************************************************************
    //* Members
    //******************************************************************************

    /** @inheritdoc */
    protected $name = 'grubworm:burrow';
    /** @inheritdoc */
    protected $description = 'Burrow into a database and discover all its goodies';
    /**
     * @type string Destination path of output. Defaults to app/database/grubworm/
     */
    protected $destination;
    /**
     * @type string The database name to target
     */
    protected $database;
    /**
     * @inheritdoc
     */
    protected $signature = 'grubworm:burrow {database=default} {--output_path=database} {--tables=} {--namespace=App\\Models}';

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Handle the command
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $_db = DB::connection($this->database);
        } catch (\Exception $_ex) {
            throw new \InvalidArgumentException('The database "' . $this->database . '" is invalid.');
        }

        $_database = $this->database;
        $this->_v('* connected to database <info>' . $_database . '</info>.');

        $_tablesWanted = $this->option('tables');

        if (!empty($_tablesWanted)) {
            $_list = explode(',', $_tablesWanted);
            $_tablesWanted = empty($_list) ? false : $_tablesWanted = $_list;
            $this->_vv('* ' . count($_tablesWanted) . ' table(s) will be scanned.');
        } else {
            $this->_vv('* all tables will be scanned.');
        }

        $_sm = $_db->getDoctrineSchemaManager();
        $_tableNames = $_sm->listTableNames();

        $this->_writeln('* examining ' . count($_tableNames) . ' table(s)...');

        foreach ($_tableNames as $_tableName) {
            if ($_tablesWanted && !in_array($_tableName, $_tablesWanted)) {
                $this->_vvv('  * SKIP table <comment>' . $_tableName . '</comment>.');
                continue;
            }

            $this->_v('  * SCAN table <info>' . $_tableName . '</info>.');

            if ($this->_examineTable($_sm->listTableDetails($_tableName))) {
                $this->_writeln('  * <info>' . $_tableName . '</info> complete.');
            }
        }

        return true;
    }

    /**
     * @param \Doctrine\DBAL\Schema\Table $table
     *
     * @return bool|int
     * @todo convert to Blade stub
     */
    protected function _generateModel(Table $table)
    {
        $_props = [];
        $_name = $table->getName();
        $_modelName = $this->_getModelName($_name);

        try {
            foreach ($table->getColumns() as $_column) {
                $_type = $_column->getType()->getName();
                ($_type == 'datetime') && $_type = 'Carbon';
                $_props[] = ' * @property ' . $_type . ' $' . $_column->getName();
            }

            $_payload = [
                'tableName' => $_name,
                'modelName' => $_modelName,
                'namespace' => $this->option('namespace') ?: 'App\Models',
                'props'     => $_props,
            ];

            $_filename = $this->destination . DIRECTORY_SEPARATOR . $_modelName . '.php';
            $_props = implode(PHP_EOL, $_props);

            $_php = <<<TEXT
<?php namespace {$_payload['namespace']};

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
{$_props}
*/
class {$_payload['modelName']} extends Model
{
    //******************************************************************************
    //* Members
    //******************************************************************************

    protected \$table = '{$_payload['tableName']}';
}
TEXT;

            return file_put_contents($_filename, $_php);
        } catch (\Exception $_ex) {
            $this->_writeln('  * error examining table "' . $_name . '": ' . $_ex->getMessage());

            return false;
        }
    }

    /**
     * @param mixed $table
     *
     * @return bool|int
     */
    protected function _examineTable(Table $table)
    {
        try {
            return $this->_generateModel($table);
        } catch (\Exception $_ex) {
            $this->_writeln('  * error examining table "' . $table->getName() . '": ' . $_ex->getMessage());

            return false;
        }
    }

    /**
     * @param string $tableName
     *
     * @return string
     */
    protected function _getModelName($tableName)
    {
        static $_abbreviations = ['_arch_' => '_archive_', '_asgn_' => '_assign_', '_t' => null, '_v' => null,];

        /**
         * Check each table name for abbreviation replacements
         */
        foreach ($_abbreviations as $_suffix => $_replacement) {
            $_check =
                2 == strlen($_suffix) && '_' == $_suffix[0]
                    ? $_suffix == substr($tableName, -2)
                    : false !==
                    strpos($tableName, $_suffix);

            if ($_check) {
                $tableName = str_replace($_suffix, $_replacement, $tableName);
            }
        }

        return str_replace(' ', null, ucwords(str_replace('_', ' ', $tableName)));
    }

    /**
     * @param string|array $messages The message as an array of lines of a single string
     * @param int          $type     The type of output (one of the OUTPUT constants)
     *
     * @throws \InvalidArgumentException When unknown output type is given
     */
    protected function _vvv($messages, $type = OutputInterface::OUTPUT_NORMAL)
    {
        $this->_writeln($messages, OutputInterface::VERBOSITY_DEBUG, $type);
    }

    /**
     * @param string|array $messages The message as an array of lines of a single string
     * @param int          $type     The type of output (one of the OUTPUT constants)
     *
     * @throws \InvalidArgumentException When unknown output type is given
     */
    protected function _vv($messages, $type = OutputInterface::OUTPUT_NORMAL)
    {
        $this->_writeln($messages, OutputInterface::VERBOSITY_VERY_VERBOSE, $type);
    }

    /**
     * @param string|array $messages The message as an array of lines of a single string
     * @param int          $type     The type of output (one of the OUTPUT constants)
     *
     * @throws \InvalidArgumentException When unknown output type is given
     */
    protected function _v($messages, $type = OutputInterface::OUTPUT_NORMAL)
    {
        $this->_writeln($messages, OutputInterface::VERBOSITY_VERBOSE, $type);
    }

    /**
     * @param string|array $messages
     * @param int          $level
     * @param int          $type
     */
    protected function _writeln($messages, $level = OutputInterface::VERBOSITY_NORMAL, $type = OutputInterface::OUTPUT_NORMAL)
    {
        if ($level <= $this->output->getVerbosity()) {
            $this->output->writeln($messages, $type);
        }
    }

    /** @inheritdoc */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->intro();

        if (empty($_path = $this->option('output_path'))) {
            $_path = 'database';
        }

        try {
            $_path = Disk::path([base_path(), $_path], true);
        } catch (\Exception $_ex) {
            $this->error('Unable to write to output path "' . $_path . '"');

            return false;
        }

        $this->destination = $_path;

        if ('default' == ($this->database = $this->argument('database'))) {
            $this->database = config('database.default');
        }

        return true;
    }

    /**
     * Echo's the intro header
     */
    protected function intro()
    {
        $_intro =
            'Grubworm: Data Burrower ' . config('grubworm.version') . ' (' . config('grubworm.version-date') . ')';

        (($_year = date('Y')) > 2015) && $_year = '2015-' . $_year;

        $this->_writeln($_intro . PHP_EOL);
    }
}

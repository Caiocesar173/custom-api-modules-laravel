<?php

namespace Caiocesar173\Modules\Commands;

use Illuminate\Support\Str;
use Caiocesar173\Modules\Support\Config\GenerateConfigReader;
use Caiocesar173\Modules\Support\Stub;
use Caiocesar173\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ModelMakeCommand extends GeneratorCommand
{
    use ModuleCommandTrait;

    /**
     * The name of argument name.
     *
     * @var string
     */
    protected $argumentName = 'model';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:make-model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new model for the specified module.';

    public function handle(): int
    {
        if (parent::handle() === E_ERROR) {
            return E_ERROR;
        }

        $this->handleOptionalMigrationOption();
        $this->handleOptionalControllerOption();

        return 0;
    }

    /**
     * Create a proper migration name:
     * ProductDetail: product_details
     * Product: products
     * @return string
     */
    private function createMigrationName()
    {
        $pieces = preg_split('/(?=[A-Z])/', $this->argument('model'), -1, PREG_SPLIT_NO_EMPTY);

        $string = '';
        foreach ($pieces as $i => $piece) {
            if ($i + 1 < count($pieces)) {
                $string .= strtolower($piece) . '_';
            } else {
                $string .= Str::plural(strtolower($piece));
            }
        }

        return $string;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['model', InputArgument::REQUIRED, 'The name of model will be created.'],
            ['module', InputArgument::OPTIONAL, 'The name of module will be used.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['table', null, InputOption::VALUE_OPTIONAL, 'The table associated migration.', null],
            ['fillable', null, InputOption::VALUE_OPTIONAL, 'The fillable attributes.', null],
            ['migration', 'm', InputOption::VALUE_NONE, 'Flag to create associated migrations', null],
            ['controller', 'c', InputOption::VALUE_NONE, 'Flag to create associated controllers', null],
        ];
    }

    /**
     * Create the migration file with the given model if migration flag was used
     */
    private function handleOptionalMigrationOption()
    {
        if ($this->option('migration') === true) {
            $migrationName = 'create_' . $this->createMigrationName() . '_table';
            $this->call('module:make-migration', ['name' => $migrationName, 'module' => $this->argument('module')]);
        }
    }

    /**
     * Create the controller file for the given model if controller flag was used
     */
    private function handleOptionalControllerOption()
    {
        if ($this->option('controller') === true) {
            $controllerName = "{$this->getModelName()}Controller";

            $this->call('module:make-controller', array_filter([
                'controller' => $controllerName,
                'module' => $this->argument('module')
            ]));
        }
    }

    /**
     * @return mixed
     */
    protected function getTemplateContents()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());

        return (new Stub('/model.stub', [
            'NAME'              => $this->getModelName(),
            'FILLABLE'          => $this->getFillable(),
            'NAMESPACE'         => $this->getClassNamespace($module),
            'CLASS'             => $this->getClass(),
            'TABLE'             => $this->getTable(),
            'LOWER_NAME'        => $module->getLowerName(),
            'MODULE'            => $this->getModuleName(),
            'STUDLY_NAME'       => $module->getStudlyName(),
            'MODULE_NAMESPACE'  => $this->laravel['modules']->config('namespace'),
            'FACTORY_CLASS'     => $this->getFactoryClass(),
            'FACTORY_NAMESPACE' => $this->getFactoryNamespace()
        ]))->render();
    }

    protected function getFactoryClass()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());
        $modelClass = $this->getClass($module);
        return "{$modelClass}Factory";
    }

    protected function getFactoryNamespace()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());
        $moduleNamespace = $this->getModuleName();
        $modelClass = $this->getFactoryClass();

        $moduleNamespace = str_replace('/', '\\', $moduleNamespace);
        return "Modules\\{$moduleNamespace}\\Database\\Factories\\{$modelClass}";
    }

    /**
     * @return mixed
     */
    protected function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        $modelPath = GenerateConfigReader::read('model');

        return $path . $modelPath->getPath() . '/' . $this->getModelName() . '.php';
    }

    /**
     * @return mixed|string
     */
    private function getModelName()
    {
        return Str::studly($this->argument('model'));
    }

    /**
     * @return string
     */
    private function getFillable()
    {
        $fillable = $this->option('fillable');

        if (!is_null($fillable)) {
            if (is_array($fillable))
                return $fillable;

            $arrays = explode(',', $fillable);

            array_push($arrays,  'status');
            return json_encode($arrays);
        }

        return "[
            'status',
        ]";
    }

    /**
     * @return string
     */
    private function getTable()
    {
        $table = $this->option('table');

        if (is_null($table))
            return strtolower($this->argument('model'));

        return $table;
    }

    /**
     * Get default namespace.
     *
     * @return string
     */
    public function getDefaultNamespace(): string
    {
        $module = $this->laravel['modules'];

        return $module->config('paths.generator.model.namespace') ?: $module->config('paths.generator.model.path', 'Entities');
    }
}

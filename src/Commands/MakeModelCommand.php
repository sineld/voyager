<?php

namespace TCG\Voyager\Commands;

use Illuminate\Foundation\Console\ModelMakeCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeModelCommand extends ModelMakeCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'voyager:make:model';

    /**
     * Laravel tags ModelMakeCommand with #[AsCommand(name: 'make:model')], and Symfony
     * reads that attribute from the parent when a subclass does not declare its own
     * name, which registered this command as `make:model` as well. Overriding the
     * accessor keeps the attribute out of it entirely.
     */
    public static function getDefaultName(): ?string
    {
        return 'voyager:make:model';
    }

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Voyager model class';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/../../stubs/model.stub';
    }

    /**
     * Build the class with the given name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = $this->files->get($this->getStub());

        return $this->addSoftDelete($stub)->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    /**
     * Add SoftDelete to the given stub.
     *
     * @param string $stub
     *
     * @return $this
     */
    protected function addSoftDelete(&$stub)
    {
        $traitIncl = $trait = '';

        if ($this->getDefinition()->hasOption('softdelete') && $this->option('softdelete')) {
            $traitIncl = 'use Illuminate\Database\Eloquent\SoftDeletes;';
            $trait = 'use SoftDeletes;';
        }

        $stub = str_replace('//DummySDTraitInclude', $traitIncl, $stub);
        $stub = str_replace('//DummySDTrait', $trait, $stub);

        return $this;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        $options = [
            ['softdelete', 'd', InputOption::VALUE_NONE, 'Add soft-delete field to Model'],
        ];

        return array_merge($options, parent::getOptions());
    }
}

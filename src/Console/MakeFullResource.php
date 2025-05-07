<?php

/**
 * Author: @JJRuizDeveloper
 * Web: https://64software.com
 * Youtube: @Gogodev
 */

namespace SixtyFourSoftware\MakeFullResource\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Filesystem\Filesystem;

class MakeFullResource extends Command
{
    protected $signature = 'make:full-resource-64 {--log} {--json=}';
    protected $description = 'A Laravel package to generate full resources with migrations, models, and Filament resources. | Crea modelo, migración, relaciones y recurso Filament completo';

    public function handle()
    {
        // Selección de idioma
        $language = $this->choice('Select language / Seleccione idioma', ['English', 'Español'], 0);
        $useEnglish = $language === 'English';

        // Entradas interactivas o vía JSON
        if ($jsonInput = $this->option('json')) {
            $data = json_decode($jsonInput, true);
            if (!is_array($data)) {
                $this->error($useEnglish ? 'Invalid JSON input' : 'Entrada JSON inválida');
                return;
            }
            $modelName = $data['model'] ?? null;
            $fields = $data['fields'] ?? [];
            $relations = $data['relations'] ?? [];
        } else {
            $modelName = $this->ask($useEnglish ? 'Model name (e.g., Product)' : 'Nombre del modelo (ej. Producto)');
            $fields = [];
            while ($this->confirm($useEnglish ? 'Add a field?' : '¿Deseas agregar un campo?')) {
                $type = $this->choice($useEnglish ? 'Field type' : 'Tipo de campo', [
                    'string' => $useEnglish ? 'string' : 'cadena',
                    'text' => $useEnglish ? 'text' : 'texto',
                    'integer' => $useEnglish ? 'integer' : 'entero',
                    'bigInteger' => $useEnglish ? 'bigInteger' : 'gran entero',
                    'unsignedBigInteger' => $useEnglish ? 'unsignedBigInteger' : 'entero grande sin signo',
                    'boolean' => $useEnglish ? 'boolean' : 'booleano',
                    'date' => $useEnglish ? 'date' : 'fecha',
                    'dateTime' => $useEnglish ? 'dateTime' : 'fecha y hora',
                    'decimal' => $useEnglish ? 'decimal' : 'decimal',
                    'float' => $useEnglish ? 'float' : 'flotante',
                    'foreignId' => $useEnglish ? 'foreignId' : 'ID externo',
                    'timestamps' => $useEnglish ? 'timestamps' : 'marca de tiempo',
                    'time' => $useEnglish ? 'time' : 'hora',
                    'json' => $useEnglish ? 'json' : 'json',
                    'jsonb' => $useEnglish ? 'jsonb' : 'json binario',
                    'enum' => $useEnglish ? 'enum' : 'enum',
                    'unsignedInteger' => $useEnglish ? 'unsignedInteger' : 'entero sin signo',
                    'unsignedTinyInteger' => $useEnglish ? 'unsignedTinyInteger' : 'entero pequeño sin signo',
                    'unsignedSmallInteger' => $useEnglish ? 'unsignedSmallInteger' : 'entero pequeño sin signo',
                    'unsignedMediumInteger' => $useEnglish ? 'unsignedMediumInteger' : 'entero mediano sin signo'
                ], 'string');
                $name = $this->ask($useEnglish ? 'Field name' : 'Nombre del campo');
                $fields[] = "$type:$name";
            }
            $relations = [];
            while ($this->confirm($useEnglish ? 'Add a relationship?' : '¿Deseas agregar una relación?')) {
                $type = $this->choice($useEnglish ? 'Relation type' : 'Tipo de relación', [
                    'belongsTo' => $useEnglish ? 'belongsTo' : 'pertenece a',
                    'hasOne' => $useEnglish ? 'hasOne' : 'tiene uno',
                    'hasMany' => $useEnglish ? 'hasMany' : 'tiene muchos',
                    'belongsToMany' => $useEnglish ? 'belongsToMany' : 'pertenece a muchos',
                    'morphTo' => $useEnglish ? 'morphTo' : 'morphTo',
                    'morphMany' => $useEnglish ? 'morphMany' : 'morphMany',
                    'morphOne' => $useEnglish ? 'morphOne' : 'morphOne',
                    'morphedByMany' => $useEnglish ? 'morphedByMany' : 'morphedByMany'
                ], 'belongsTo');
                $modelPath = app_path('Models');
                $availableModels = collect((new Filesystem)->files($modelPath))
                    ->filter(fn($file) => $file->getExtension() === 'php')
                    ->map(fn($file) => $file->getFilenameWithoutExtension())
                    ->values()
                    ->all();

                $otherOption = $useEnglish ? 'Other (write manually)' : 'Otro (escribir manualmente)';
                $availableModels[] = $otherOption;

                $selected = $this->choice(
                    $useEnglish ? 'Select related model' : 'Seleccione el modelo relacionado',
                    $availableModels
                );

                if ($selected === $otherOption) {
                    $related = $this->ask($useEnglish ? 'Enter related model name manually' : 'Ingrese el nombre del modelo relacionado manualmente');
                } else {
                    $related = $selected;
                }

                $relations[] = "$type:$related";
            }
        }

        // Previsualización
        $tableName = Str::snake(Str::pluralStudly($modelName));
        $this->info("\n📄 " . ($useEnglish ? 'Summary:' : 'Resumen:'));
        $this->line("Model: $modelName");
        $this->line("Table: $tableName");
        $this->line(($useEnglish ? 'Fields:' : 'Campos:'));
        foreach ($fields as $field) $this->line("  - $field");
        if (count($relations)) {
            $this->line(($useEnglish ? 'Relations:' : 'Relaciones:'));
            foreach ($relations as $r) $this->line("  - $r");
        }
        if (!$this->confirm($useEnglish ? 'Continue?' : '¿Continuar?')) return;

        $fs = new Filesystem();

        // Crear modelo
        Artisan::call('make:model', ['name' => $modelName, '--migration' => true]);
        $this->info(Artisan::output());

        // Editar migración
        $migrationFile = collect($fs->files(database_path('migrations')))
            ->last(fn($file) => str_contains($file->getFilename(), "create_{$tableName}_table"));

        $migrationContent = $fs->get($migrationFile->getPathname());
        $schema = '';
        foreach ($fields as $field) {
            if (!str_contains($field, ':')) continue;
            [$type, $name] = explode(':', $field);
            $schema .= "\$table->$type('$name');\n            ";
        }
        $migrationContent = preg_replace(
            '/Schema::create\(.*function \(Blueprint \$table\) \{(.*?)\$table->timestamps\(\);/s',
            "Schema::create('$tableName', function (Blueprint \$table) {\n            \$table->id();\n            $schema\$table->timestamps();",
            $migrationContent
        );
        $fs->put($migrationFile->getPathname(), $migrationContent);

        // Modelo: guarded + relaciones
        $modelPath = app_path("Models/{$modelName}.php");
        $modelContent = $fs->get($modelPath);
        $modelContent = preg_replace('/\{/', "{\n    protected \$guarded = ['id'];\n", $modelContent, 1);
        $methods = '';
        foreach ($relations as $relation) {
            if (!str_contains($relation, ':')) continue;
            [$type, $related] = explode(':', $relation);
            $methodName = Str::camel($type === 'hasMany' ? Str::plural($related) : $related);
            $methods .= "\n    public function $methodName()\n    {\n        return \$this->$type($related::class);\n    }\n";
        }
        $modelContent = preg_replace('/\}\s*$/', $methods . "\n}", $modelContent);
        $fs->put($modelPath, $modelContent);

        // Filament resource
        Artisan::call('make:filament-resource', ['name' => $modelName]);
        $this->info(Artisan::output());

        $resourcePath = base_path("app/Filament/Resources/{$modelName}Resource.php");
        if (!$fs->exists($resourcePath)) {
            $this->error(($useEnglish ? 'Resource not found:' : 'No se encontró el recurso:') . " $resourcePath");
            return;
        }

        $resourceContent = $fs->get($resourcePath);
        $resourceContent = preg_replace('/^[\s\S]*?(<\?php)/', '$1', $resourceContent);

        if (preg_match('/(<\?php\s*\nnamespace [^;]+;\R)/', $resourceContent, $m)) {
            $block = $m[1];
            $uses = [];
            $imports = [
                'Filament\Forms\Components\TextInput',
                'Filament\Forms\Components\Select',
                'Filament\Tables\Columns\TextColumn',
                'Filament\Tables\Filters\SelectFilter',
            ];
            foreach ($imports as $i) {
                if (!str_contains($resourceContent, "use $i;")) {
                    $uses[] = "use $i;";
                }
            }
            $resourceContent = str_replace($block, $block . implode("\n", $uses) . "\n\n", $resourceContent);
        }

        // Crear form(), table() y filters()
        $formFields = '';
        $tableColumns = '';
        $filters = '';
        foreach ($fields as $field) {
            if (!str_contains($field, ':')) continue;
            [$type, $name] = explode(':', $field);
            if (str_starts_with($type, 'foreignId')) {
                $rel = Str::studly(str_replace('_id', '', $name));
                $formFields   .= "Select::make('$name')->relationship('" . Str::camel($rel) . "','id')->required(),\n                ";
                $tableColumns .= "TextColumn::make('$name'),\n                ";
                $filters      .= "SelectFilter::make('$name')->relationship('" . Str::camel($rel) . "','id'),\n                ";
            } elseif (in_array($type, ['string', 'text'])) {
                $formFields   .= "TextInput::make('$name')->required(),\n                ";
                $tableColumns .= "TextColumn::make('$name'),\n                ";
            } else {
                $formFields   .= "TextInput::make('$name'),\n                ";
                $tableColumns .= "TextColumn::make('$name'),\n                ";
            }
        }

        $resourceContent = preg_replace('/->schema\(\[.*?\]\)/s', "->schema([\n                $formFields\n            ])", $resourceContent);
        $resourceContent = preg_replace('/->columns\(\[.*?\]\)/s', "->columns([\n                $tableColumns\n            ])", $resourceContent);
        $resourceContent = preg_replace('/->filters\(\[.*?\]\)/s', "->filters([\n                $filters\n            ])", $resourceContent);

        $fs->put($resourcePath, $resourceContent);
        $this->info(($useEnglish ? '✅ Full resource created for ' : '✅ Recurso completo creado para ') . $modelName);

        // Log opcional
        if ($this->option('log')) {
            $logPath = storage_path("logs/{$modelName}_resource_log.txt");
            file_put_contents($logPath, "Model: $modelName\nFields: " . implode(',', $fields) . "\nRelations: " . implode(',', $relations));
            $this->info(($useEnglish ? '📄 Log saved to: ' : '📄 Log guardado en: ') . $logPath);
        }
    }
  
}

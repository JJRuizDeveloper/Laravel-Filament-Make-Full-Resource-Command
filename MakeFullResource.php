<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Filesystem\Filesystem;

class MakeFullResource extends Command
{
    protected $signature = 'make:full-resource-64';
    protected $description = 'Crea modelo, migración, relaciones y recurso Filament completo';

    public function handle()
    {
        $language = $this->choice('Select language / Seleccione idioma', ['English', 'Español'], 0);
        $useEnglish = $language === 'English';

        $modelName = $this->ask(
            $useEnglish ? 'Model name (e.g., Product)' : 'Nombre del modelo (ej. Producto)'
        );

        $tableName = Str::snake(Str::pluralStudly($modelName));

        $fieldsInput = $this->ask(
            $useEnglish
                ? 'Fields (format: type:name, e.g., string:name, integer:stock, foreignId:category_id)'
                : 'Campos (formato: tipo:nombre, ej. string:nombre, integer:stock, foreignId:categoria_id)'
        );

        $relationsInput = $this->ask(
            $useEnglish
                ? 'Relationships (format: type:model, e.g., belongsTo:Category, hasMany:Comment)'
                : 'Relaciones (formato: tipo:modelo, ej. belongsTo:Categoria, hasMany:Comentario)',
            ''
        );

        $fields = array_map('trim', explode(',', $fieldsInput));
        $relations = $relationsInput ? array_map('trim', explode(',', $relationsInput)) : [];

        Artisan::call('make:model', ['name' => $modelName, '--migration' => true]);
        $this->info(Artisan::output());

        $fs = new Filesystem();

        $migrationFile = collect($fs->files(database_path('migrations')))
            ->last(fn($file) => str_contains($file->getFilename(), "create_{$tableName}_table"));

        $migrationContent = $fs->get($migrationFile->getPathname());
        $schema = '';
        foreach ($fields as $field) {
            [$type, $name] = explode(':', $field);
            $schema .= "\$table->$type('$name');\n            ";
        }

        $migrationContent = preg_replace(
            '/Schema::create\(.*function \(Blueprint \$table\) \{(.*?)\$table->timestamps\(\);/s',
            "Schema::create('$tableName', function (Blueprint \$table) {\n            \$table->id();\n            $schema\$table->timestamps();",
            $migrationContent
        );

        $fs->put($migrationFile->getPathname(), $migrationContent);

        $modelPath = app_path("Models/{$modelName}.php");
        $modelContent = $fs->get($modelPath);
        $modelContent = preg_replace('/\{/', "{\n    protected \$guarded = ['id'];\n", $modelContent, 1);

        $methods = '';
        foreach ($relations as $relation) {
            [$type, $related] = explode(':', $relation);
            $methodName = Str::camel($type === 'hasMany' ? Str::plural($related) : $related);
            $methods .= "\n    public function $methodName()\n    {\n        return \$this->$type($related::class);\n    }\n";
        }

        $modelContent = preg_replace('/\}\s*$/', $methods . "\n}", $modelContent);
        $fs->put($modelPath, $modelContent);

        Artisan::call('make:filament-resource', ['name' => $modelName]);
        $this->info(Artisan::output());

        $resourcePath = base_path("app/Filament/Resources/{$modelName}Resource.php");

        if (! $fs->exists($resourcePath)) {
            $this->error($useEnglish
                ? "Resource file not found at: $resourcePath"
                : "No se encontró el archivo de recurso en: $resourcePath");
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
                if (! str_contains($resourceContent, "use $i;")) {
                    $uses[] = "use $i;";
                }
            }

            $resourceContent = str_replace($block, $block . implode("\n", $uses) . "\n\n", $resourceContent);
        }

        $formFields = '';
        $tableColumns = '';
        $filters = '';
        foreach ($fields as $field) {
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

        $this->info($useEnglish
            ? "✅ Full resource for {$modelName} created."
            : "✅ Recurso completo para {$modelName} creado.");
    }
}
